<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrgRole;
use App\Mail\AnnouncementMail;
use App\Models\Announcement;
use App\Models\AnnouncementDelivery;
use App\Models\ClientService;
use App\Models\ContactEvent;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Membership;
use App\Models\MonthlyReport;
use App\Models\Organization;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\WorkLog;
use App\Reports\ServiceCatalog;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

/**
 * Noutățile VITIM către clienți: cine le primește, cum arată emailul (cu blocul personal „ce am făcut pentru firma ta”),
 * trimiterea în tranșe, ciorna automată la fiecare versiune nouă și rezumatul lunar automat.
 * Se rulează din cod de platformă; fiecare firmă se citește în contextul ei (TenantContext::runAs).
 */
final class Announcements
{
    /** emailuri pe rulare (la 5 minute): sub limitele obișnuite ale serverelor de email din cPanel */
    public const PER_RUN = 40;

    public const SETTINGS = ['auto_updates' => false, 'digest' => false, 'digest_day' => 3, 'roles' => 'owners'];

    public function __construct(private readonly TenantContext $context, private readonly AuditLogger $audit) {}

    /** @return array{auto_updates: bool, digest: bool, digest_day: int, roles: string} */
    public static function settings(): array
    {
        return array_replace(self::SETTINGS, array_intersect_key((array) PlatformSetting::get('announcements', []), self::SETTINGS));
    }

    // ---------- destinatari ----------

    /** Firmele vizate (active, cu serviciile cerute). @return Collection<int, Organization> */
    public function organizations(Announcement $a): Collection
    {
        $services = array_values(array_intersect((array) ($a->audience['services'] ?? []), array_keys(ServiceCatalog::SERVICES)));

        return Organization::query()->orderBy('name')->get()->filter(fn (Organization $o) => $o->isActive())
            ->filter(fn (Organization $o) => $services === [] || $this->context->runAs($o, fn () => ClientService::query()->where('status', 'active')->whereIn('service', $services)->exists()))
            ->values();
    }

    /** Utilizatorii unei firme care primesc noutățile (proprietari și administratori, sau toți). @return Collection<int, User> */
    public function users(Announcement $a, Organization $organization): Collection
    {
        $roles = ($a->audience['roles'] ?? 'owners') === 'all' ? null : [OrgRole::Owner->value, OrgRole::Admin->value];

        return $this->context->runAs($organization, fn () => Membership::query()->with('user')
            ->when($roles, fn ($q) => $q->whereIn('role', $roles))->get()
            ->map(fn (Membership $m) => $m->user)->filter(fn (?User $u) => $u && $u->email && $u->product_updates !== false)->unique('id')->values());
    }

    public function count(Announcement $a): int
    {
        return $this->organizations($a)->sum(fn (Organization $o) => $this->users($a, $o)->count());
    }

    // ---------- conținut ----------

    /** @return array{subject: string, html: string, text: string} */
    public function render(Announcement $a, Organization $organization, ?User $user, ?string $code = null): array
    {
        $personal = $this->context->runAs($organization, fn () => $a->kind === 'digest' ? $this->digestBlock($a, $organization) : ($a->include_work ? $this->workBlock($a, $organization) : null));
        $company = $organization->company_name ?: $organization->name;
        $vars = ['{{firma}}' => $company, '{{prenume}}' => $user ? Str::before(trim((string) $user->name), ' ') : ''];
        $fill = fn (?string $t) => trim(strtr((string) $t, $vars));
        $unsubscribe = $user ? URL::signedRoute('updates.unsubscribe', ['user' => $user->id]) : route('login');
        $portal = route('portal.news', $organization->slug);
        $data = [
            'a' => $a, 'company' => $company, 'greeting' => $user ? 'Bună ziua, '.$vars['{{prenume}}'].',' : 'Bună ziua,',
            'intro' => $fill($a->intro), 'body' => self::markdown($fill($a->body)), 'personal' => $personal,
            'cta' => $a->cta_label && $a->cta_url ? ['label' => $a->cta_label, 'url' => $a->cta_url] : null,
            'portal' => $portal, 'unsubscribe' => $unsubscribe, 'pixel' => $code ? route('updates.open', $code) : null,
        ];

        return [
            'subject' => $fill($a->subject),
            'html' => view('emails.announcement', $data)->render(),
            'text' => view('emails.announcement-text', $data + ['bodyText' => $fill($a->body)])->render(),
        ];
    }

    /** Lucrările vizibile clientului de la ultimul email de noutăți (sau din ultimele 30 de zile). @return ?array<string, mixed> */
    private function workBlock(Announcement $a, Organization $organization): ?array
    {
        $since = AnnouncementDelivery::query()->where('announcement_id', '!=', $a->id)->where('status', 'sent')->max('sent_at');
        $since = $since ? Carbon::parse($since) : now()->subDays(30);
        $logs = WorkLog::query()->where('visible_to_client', true)->where('performed_at', '>=', $since)->latest('performed_at')->limit(6)->get();
        if ($logs->isEmpty()) {
            return null;
        }

        return ['title' => 'Ce am făcut pentru '.($organization->company_name ?: $organization->name).' de la ultimul nostru mesaj',
            'items' => $logs->map(fn (WorkLog $l) => ['label' => $l->category->label(), 'text' => $l->title, 'date' => $l->performed_at->format('d.m')])->all(), 'stats' => []];
    }

    /** Rezumatul lunar: lucrările lunii, cifrele platformei pentru firmă și raportul lunar, dacă e publicat. @return ?array<string, mixed> */
    private function digestBlock(Announcement $a, Organization $organization): ?array
    {
        $start = Carbon::createFromFormat('Y-m-d', ($a->period ?: now()->subMonth()->format('Y-m')).'-01', 'Europe/Bucharest')->startOfMonth();
        $range = [$start->copy()->utc(), $start->copy()->endOfMonth()->utc()];
        $logs = WorkLog::query()->where('visible_to_client', true)->whereBetween('performed_at', $range)->get();
        $stats = array_filter([
            'Conversații cu clienții pe site' => Conversation::query()->where('is_test', false)->whereBetween('created_at', $range)->count(),
            'Cereri noi' => Lead::query()->whereBetween('created_at', $range)->count(),
            'Abonați noi' => ContactEvent::query()->where('type', 'subscribed')->whereBetween('occurred_at', $range)->count(),
            'Emailuri și SMS-uri trimise clienților' => ContactEvent::query()->whereIn('type', ['email_sent', 'sms_sent', 'whatsapp_sent'])->whereBetween('occurred_at', $range)->count(),
            'Lucrări VITIM pe site-urile tale' => $logs->count(),
        ]);
        if ($stats === []) {
            return null; // nimic de spus luna aceasta: firma nu primește rezumatul
        }
        $report = MonthlyReport::query()->where('period', $start->format('Y-m'))->whereNotNull('published_at')->exists();

        return [
            'title' => 'Luna '.$start->locale('ro')->translatedFormat('F Y').' pe scurt',
            'stats' => $stats,
            'items' => $logs->sortByDesc('performed_at')->take(10)->map(fn (WorkLog $l) => ['label' => $l->category->label(), 'text' => $l->title, 'date' => $l->performed_at->format('d.m')])->values()->all(),
            'report' => $report ? route('portal.reports.show', [$organization->slug, $start->format('Y-m')]) : null,
            'updates' => Announcement::query()->where('kind', 'update')->where('status', 'sent')->whereBetween('sent_at', $range)->pluck('title')->all(),
        ];
    }

    /** Text simplu → HTML sigur: paragrafe, liste cu „- ”, **îngroșat**, `cod`, [text](https://link). */
    public static function markdown(string $text): string
    {
        $html = '';
        foreach (preg_split('/\n\s*\n/', trim(str_replace("\r", '', $text))) ?: [] as $block) {
            $lines = array_values(array_filter(explode("\n", trim($block)), fn ($l) => trim($l) !== ''));
            if ($lines === []) {
                continue;
            }
            $inline = function (string $s): string {
                $s = e($s);
                $s = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $s);
                $s = preg_replace('/`([^`]+)`/', '<code style="background:#f1f4fa;padding:1px 5px;border-radius:4px;font-size:90%">$1</code>', (string) $s);

                return (string) preg_replace('/\[([^\]]+)\]\((https:\/\/[^\s)]+)\)/', '<a href="$2" style="color:#2f6bff">$1</a>', (string) $s);
            };
            if (str_starts_with(ltrim($lines[0]), '- ')) {
                $html .= '<ul style="margin:0 0 16px;padding-left:20px">'.implode('', array_map(fn ($l) => '<li style="margin:0 0 8px">'.$inline(ltrim(preg_replace('/^\s*-\s+/', '', $l))).'</li>', $lines)).'</ul>';
            } elseif (preg_match('/^#{1,3}\s+(.*)$/', $lines[0], $m)) {
                $html .= '<h3 style="font-size:17px;margin:20px 0 8px">'.$inline($m[1]).'</h3>'.(count($lines) > 1 ? '<p style="margin:0 0 16px">'.implode('<br>', array_map($inline, array_slice($lines, 1))).'</p>' : '');
            } else {
                $html .= '<p style="margin:0 0 16px">'.implode('<br>', array_map($inline, $lines)).'</p>';
            }
        }

        return $html;
    }

    // ---------- trimitere ----------

    public function schedule(Announcement $a, ?Carbon $at = null): void
    {
        $a->forceFill(['status' => 'scheduled', 'scheduled_at' => $at ?? now()])->save();
    }

    /** O tranșă: trimite cel mult $limit emailuri; la final marchează anunțul trimis. Întoarce câte au plecat. */
    public function process(Announcement $a, int $limit = self::PER_RUN): int
    {
        if ($a->status === 'scheduled') {
            if ($a->scheduled_at && $a->scheduled_at->isFuture()) {
                return 0;
            }
            $a->forceFill(['status' => 'sending'])->save();
        }
        if ($a->status !== 'sending') {
            return 0;
        }
        $sent = 0;
        foreach ($this->organizations($a) as $organization) {
            foreach ($this->users($a, $organization) as $user) {
                if ($sent >= $limit) {
                    return $sent;
                }
                $done = $this->context->runAs($organization, fn () => AnnouncementDelivery::query()->where('announcement_id', $a->id)->where('user_id', $user->id)->exists());
                if ($done) {
                    continue;
                }
                if ($a->kind === 'digest' && ! $this->context->runAs($organization, fn () => $this->digestBlock($a, $organization))) {
                    continue; // fără activitate în luna respectivă
                }
                $sent += $this->deliver($a, $organization, $user) ? 1 : 0;
            }
        }
        $a->forceFill(['status' => 'sent', 'sent_at' => now()])->save();
        $this->audit->record('announcement.sent', $a, ['deliveries' => AnnouncementDelivery::withoutTenancy()->where('announcement_id', $a->id)->count()]);

        return $sent;
    }

    private function deliver(Announcement $a, Organization $organization, User $user): bool
    {
        $code = Str::random(24);
        $mail = $this->render($a, $organization, $user, $code);
        try {
            Mail::to($user->email)->send(new AnnouncementMail($mail['subject'], $mail['html'], $mail['text'], URL::signedRoute('updates.unsubscribe', ['user' => $user->id])));
            $status = 'sent';
            $error = null;
        } catch (Throwable $e) {
            report($e);
            $status = 'failed';
            $error = mb_substr($e->getMessage(), 0, 300);
        }
        $this->context->runAs($organization, fn () => AnnouncementDelivery::create([
            'announcement_id' => $a->id, 'user_id' => $user->id, 'email' => $user->email, 'status' => $status, 'error' => $error,
            'code' => $code, 'sent_at' => now(),
        ]));

        return $status === 'sent';
    }

    public function sendTest(Announcement $a, User $to, Organization $organization): void
    {
        $mail = $this->render($a, $organization, $to);
        Mail::to($to->email)->send(new AnnouncementMail('[TEST] '.$mail['subject'], $mail['html'], $mail['text'], null));
    }

    // ---------- automat ----------

    /** Ciorna „Ce e nou” din CHANGELOG pentru versiunea instalată (o singură dată pe versiune). */
    public function draftFromChangelog(string $version, string $changelog): ?Announcement
    {
        if ($version === '' || Announcement::query()->where('version', $version)->exists()) {
            return null;
        }
        if (! preg_match('/^## '.preg_quote($version, '/').'(?:[ \t]*[—-][ \t]*([^\n]+))?[ \t]*$(.*?)(?=^## |\z)/msu', $changelog, $m)) {
            return null;
        }
        $title = trim($m[1] ?? '') !== '' ? Str::ucfirst(trim($m[1])) : 'Noutăți în VITIM AI';
        // rândurile pentru echipa VITIM (instrucțiuni de actualizare) nu ajung la clienți
        $body = trim(preg_replace('/^\*\*Actualizare:\*\*.*$/mu', '', trim($m[2])));
        $settings = self::settings();
        $a = Announcement::create([
            'kind' => 'update', 'version' => $version, 'title' => mb_substr($title, 0, 160),
            'subject' => mb_substr('Nou în VITIM AI: '.$title, 0, 200),
            'intro' => 'Am actualizat platforma VITIM AI pentru {{firma}}. Iată ce poți folosi de acum:',
            'body' => $body, 'cta_label' => 'Deschide panoul', 'cta_url' => route('login'), 'include_work' => true,
            'audience' => ['services' => [], 'roles' => $settings['roles']],
            'status' => $settings['auto_updates'] ? 'scheduled' : 'draft',
            // trimis automat a doua zi la 10:00, ca echipa să-l poată citi și ajusta înainte
            'scheduled_at' => $settings['auto_updates'] ? now('Europe/Bucharest')->addDay()->setTime(10, 0)->utc() : null,
        ]);
        $this->audit->record('announcement.drafted', $a, ['version' => $version, 'auto' => $settings['auto_updates']]);

        return $a;
    }

    /** Rezumatul lunar automat pentru luna trecută, din ziua aleasă, de la ora 10 (o singură dată pe lună). */
    public function monthlyDigest(): ?Announcement
    {
        $settings = self::settings();
        $now = now('Europe/Bucharest');
        if (! $settings['digest'] || $now->day < $settings['digest_day'] || $now->hour < 10) {
            return null;
        }
        $period = $now->copy()->subMonthNoOverflow()->format('Y-m');
        if (Announcement::query()->where('period', $period)->exists()) {
            return null;
        }
        $label = Carbon::createFromFormat('Y-m-d', $period.'-01')->locale('ro')->translatedFormat('F Y');
        $a = Announcement::create([
            'kind' => 'digest', 'period' => $period, 'title' => 'Rezumatul lunii '.$label, 'subject' => '{{firma}}: ce am făcut împreună în '.$label,
            'intro' => 'Iată pe scurt cum a lucrat VITIM AI pentru {{firma}} luna trecută și ce am făcut noi pe site-urile tale.',
            'body' => null, 'cta_label' => 'Vezi detaliile în panou', 'cta_url' => route('login'), 'include_work' => true,
            'audience' => ['services' => [], 'roles' => $settings['roles']], 'status' => 'scheduled', 'scheduled_at' => now(),
        ]);
        $this->audit->record('announcement.digest', $a, ['period' => $period]);

        return $a;
    }
}
