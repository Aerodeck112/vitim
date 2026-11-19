<?php

declare(strict_types=1);

namespace App\Office;

use App\Enums\ConversationStatus;
use App\Models\Agent;
use App\Models\Campaign;
use App\Models\Conversation;
use App\Models\Flow;
use App\Models\Site;
use App\Models\SiteBackup;
use App\Models\SiteIssue;
use App\Models\SitePage;
use App\Models\SocialAsset;
use App\Models\SocialPost;
use App\Social\SocialPlanner;
use App\Social\VoiceStudio;
use App\Tenancy\TenantContext;
use Illuminate\Support\Collection;

/**
 * Biroul firmei: fiecare agent al platformei are un birou și răspunde de un singur lucru. Starea fiecăruia se
 * calculează din datele reale ale firmei curente (nimic inventat): lucrează, așteaptă un om, are o problemă sau e oprit.
 */
final class Office
{
    /** starea => [etichetă, clasă] în ordinea în care cere atenție */
    public const STATES = ['problem' => ['are o problemă', 'err'], 'needs' => ['te așteaptă', 'warn'], 'working' => ['lucrează', 'ok'], 'idle' => ['în așteptare', ''], 'off' => ['oprit', 'off']];

    public const ZONES = ['clienti' => 'Relația cu clienții', 'site' => 'Site-ul', 'marketing' => 'Marketing'];

    public function __construct(private readonly TenantContext $context) {}

    /** @return list<array{key: string, zone: string, name: string, role: string, state: string, line: string, facts: list<string>, url: ?string}> */
    public function desks(): array
    {
        $organization = $this->context->organization();
        $slug = $organization->slug;
        $serviceable = (bool) $organization->subscription?->isServiceable();
        $sites = Site::query()->where('status', 'active')->get();
        $siteIds = $sites->pluck('id');
        $wp = $sites->filter(fn (Site $s) => in_array($s->platform?->value, ['wordpress', 'woocommerce'], true));
        $issues = fn (string $category, ?string $severity = null) => SiteIssue::query()->whereIn('site_id', $siteIds)->where('status', 'open')->where('category', $category)
            ->when($severity, fn ($q) => $q->where('severity', $severity))->count();
        $date = fn ($at) => $at ? $at->setTimezone(SocialPlanner::TIMEZONE)->format('d.m.Y') : 'încă nu';
        $desks = [];

        // --- asistentul de pe site
        $agents = Agent::query()->get();
        $active = $agents->filter(fn (Agent $a) => $a->isActive())->count();
        $waiting = Conversation::query()->where('is_test', false)->where('status', ConversationStatus::Pending->value)->count();
        $week = Conversation::query()->where('is_test', false)->where('created_at', '>=', now()->subDays(7))->count();
        $desks[] = ['key' => 'chat', 'zone' => 'clienti', 'name' => 'Asistentul de pe site', 'role' => 'Răspunde vizitatorilor și preia cererile',
            'state' => match (true) {
                $active === 0 || ! $serviceable => 'off', $waiting > 0 => 'needs', default => 'working'
            },
            'line' => match (true) {
                $agents->isEmpty() => 'Nu e configurat încă.', ! $serviceable => 'Oprit: abonamentul nu este activ.', $active === 0 => 'Niciun asistent activ.',
                $waiting > 0 => $waiting === 1 ? 'Un vizitator așteaptă un om.' : "{$waiting} vizitatori așteaptă un om.", default => 'Răspunde pe site.'
            },
            'facts' => ["{$week} conversații în ultimele 7 zile"], 'url' => route('portal.conversations.index', $slug)];

        // --- cunoaștere
        $pages = SitePage::query()->whereIn('site_id', $siteIds)->where('status', 'ok')->where('excluded', false)->count();
        $reading = $sites->contains(fn (Site $s) => in_array($s->learn_status, ['queued', 'running'], true));
        $failed = $sites->contains(fn (Site $s) => $s->learn_status === 'failed');
        $desks[] = ['key' => 'knowledge', 'zone' => 'clienti', 'name' => 'Documentaristul', 'role' => 'Citește site-ul ca asistentul să știe ce răspunde',
            'state' => match (true) {
                $sites->isEmpty() => 'off', $reading => 'working', $failed && $pages === 0 => 'problem', default => 'idle'
            },
            'line' => match (true) {
                $sites->isEmpty() => 'Niciun site conectat.', $reading => 'Citește site-ul acum.', $failed && $pages === 0 => 'Nu a putut citi site-ul.', default => 'Recitește site-ul săptămânal.'
            },
            'facts' => ["{$pages} pagini cunoscute", 'ultima citire: '.$date($sites->max('learned_at'))], 'url' => $agents->first() ? route('portal.agents.knowledge', [$slug, $agents->first()->id]) : null];

        // --- SEO
        $seoOpen = $issues('seo') + $issues('ai');
        $seoOn = $sites->contains(fn (Site $s) => $s->seo_autopilot);
        $desks[] = ['key' => 'seo', 'zone' => 'site', 'name' => 'Specialistul SEO', 'role' => 'Verifică zilnic site-ul și repară săptămânal ce e sigur',
            'state' => match (true) {
                $sites->isEmpty() => 'off', $issues('seo', 'critical') > 0 => 'problem', default => $seoOn ? 'working' : 'idle'
            },
            'line' => match (true) {
                $sites->isEmpty() => 'Niciun site conectat.', $seoOpen === 0 => 'Nicio problemă deschisă.', default => "{$seoOpen} probleme de SEO și vizibilitate deschise."
            },
            'facts' => ['ultimul audit: '.$date($sites->max('last_audit_at')), 'ultima reparație automată: '.$date($sites->max('seo_autopilot_at'))], 'url' => self::siteUrl($slug, $sites)];

        // --- securitate și actualizări
        $updates = SiteIssue::query()->whereIn('site_id', $siteIds)->where('status', 'open')->where('category', 'updates')->count();
        $secCritical = $issues('security', 'critical') + $issues('updates', 'critical');
        $auto = $wp->contains(fn (Site $s) => $s->update_autopilot);
        $desks[] = ['key' => 'security', 'zone' => 'site', 'name' => 'Paznicul', 'role' => 'Securitatea și actualizările site-ului',
            'state' => match (true) {
                $sites->isEmpty() => 'off', $secCritical > 0 => 'problem', $updates > 0 && ! $auto => 'needs', default => 'working'
            },
            'line' => match (true) {
                $sites->isEmpty() => 'Niciun site conectat.', $secCritical > 0 => "{$secCritical} probleme critice de securitate.", $updates > 0 => "{$updates} actualizări disponibile.", default => 'Totul este la zi.'
            },
            'facts' => [$wp->isEmpty() ? 'actualizările automate: doar pe WordPress' : 'actualizări automate: '.($auto ? 'pornite' : 'oprite'), 'ultima rulare: '.$date($wp->max('update_autopilot_at'))], 'url' => self::siteUrl($slug, $sites)];

        // --- backup
        $lastOk = SiteBackup::query()->whereIn('site_id', $siteIds)->where('status', 'ok')->where('verified', true)->latest('started_at')->first();
        $backupIssues = $issues('backup', 'critical');
        $scheduled = $sites->contains(fn (Site $s) => ! empty($s->health['backup_schedule']) && $s->health['backup_schedule'] !== 'off');
        $desks[] = ['key' => 'backup', 'zone' => 'site', 'name' => 'Arhivarul', 'role' => 'Copiile de siguranță ale site-ului',
            'state' => match (true) {
                ! $scheduled => 'off', $backupIssues > 0 => 'problem', default => 'working'
            },
            'line' => match (true) {
                ! $scheduled => 'Backup-ul automat nu este pornit.', $backupIssues > 0 => 'Ultimul backup a eșuat sau e prea vechi.', default => 'Face backup automat și îl verifică.'
            },
            'facts' => ['ultimul backup verificat: '.$date($lastOk?->started_at)], 'url' => self::siteUrl($slug, $sites)];

        // --- social media
        $monday = SocialPlanner::monday();
        $posts = SocialPost::query()->betweenDays($monday->toDateString(), $monday->copy()->addDays(6)->toDateString())->get();
        $drafts = $posts->where('status', 'draft')->count();
        $social = SocialPlanner::settings($organization);
        $desks[] = ['key' => 'social', 'zone' => 'marketing', 'name' => 'Agentul de social media', 'role' => 'Planul săptămânii: texte, fotografii, scenarii video',
            'state' => match (true) {
                $drafts > 0 => 'needs', $posts->isNotEmpty() => 'working', default => $social['auto'] ? 'idle' : 'off'
            },
            'line' => match (true) {
                $drafts > 0 => "{$drafts} postări așteaptă aprobarea ta.", $posts->isNotEmpty() => 'Planul săptămânii este aprobat.', $social['auto'] => 'Scrie planul duminică.', default => 'Niciun plan săptămâna aceasta.'
            },
            'facts' => [$posts->where('status', 'published')->count().' publicate săptămâna aceasta', SocialAsset::query()->count().' fotografii în materiale'], 'url' => route('portal.social', $slug)];

        // --- audio
        $voiced = $posts->filter(fn (SocialPost $p) => $p->voice_path !== null)->count();
        $toVoice = $posts->filter(fn (SocialPost $p) => $p->voice_path === null && trim((string) $p->voiceover) !== '' && $p->status !== 'skipped')->count();
        $voiceOn = VoiceStudio::configured();
        $desks[] = ['key' => 'audio', 'zone' => 'marketing', 'name' => 'Seniorul audio', 'role' => 'Vocea în română, muzica și sunetul din video',
            'state' => match (true) {
                ! $voiceOn => 'off', $toVoice > 0 => 'needs', $voiced > 0 => 'working', default => 'idle'
            },
            'line' => match (true) {
                ! $voiceOn => 'Serviciul de voce nu este configurat.', $toVoice > 0 => "{$toVoice} postări au text de voce, fără înregistrare.", $voiced > 0 => 'Vocile săptămânii sunt înregistrate.', default => 'Nimic de înregistrat acum.'
            },
            'facts' => ["{$voiced} voci înregistrate săptămâna aceasta"], 'url' => route('portal.social', $slug)];

        // --- campanii și automatizări
        $sending = Campaign::query()->whereIn('status', ['sending', 'scheduled'])->count();
        $live = Flow::query()->where('status', 'live')->count();
        $desks[] = ['key' => 'marketing', 'zone' => 'marketing', 'name' => 'Curierul', 'role' => 'Trimite campaniile și mesajele automate',
            'state' => $sending + $live > 0 ? 'working' : 'idle',
            'line' => match (true) {
                $sending > 0 => $sending === 1 ? 'O campanie programată sau în trimitere.' : "{$sending} campanii programate sau în trimitere.", $live > 0 => 'Automatizările rulează.', default => 'Nimic de trimis acum.'
            },
            'facts' => ["{$live} automatizări pornite"], 'url' => route('portal.campaigns.index', $slug)];

        return $desks;
    }

    /** Rezumat pe scurt: câți agenți sunt în fiecare stare. @return array<string, int> */
    public static function summary(array $desks): array
    {
        return array_merge(array_fill_keys(array_keys(self::STATES), 0), array_count_values(array_column($desks, 'state')));
    }

    /** @param Collection<int, Site> $sites */
    private static function siteUrl(string $slug, $sites): ?string
    {
        return $sites->isEmpty() ? null : route('portal.sites.report', [$slug, $sites->first()->id]);
    }
}
