<?php

declare(strict_types=1);

namespace App\Monitoring;

use App\Audit\Guidance;
use App\Enums\ConversationStatus;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Organization;
use App\Models\PlatformSetting;
use App\Models\Site;
use App\Models\SiteCommand;
use App\Models\SiteIssue;
use App\Models\Subscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Agentul de monitorizare al echipei VITIM: strânge într-un singur loc ce cere atenție la toți clienții
 * (site-uri, backup-uri, certificate, abonamente, agenți, trimiteri). Cod de platformă: citește peste toate
 * firmele (withoutTenancy) și întoarce doar stări și numere, niciodată date ale clienților finali.
 */
final class FleetMonitor
{
    public const TIMEZONE = 'Europe/Bucharest';

    /** Scăderea de scor (puncte, de la un rezumat la altul) care merită semnalată. */
    public const SCORE_DROP = 10;

    private const DEFAULTS = ['enabled' => true, 'hour' => 7, 'urgent' => true, 'quiet' => true, 'extra' => []];

    /** Avertismentele din audit care apar pe nume în rezumat (restul se numără). */
    private const NAMED_WARNINGS = ['sec.ssl_expiring', 'backup.none', 'backup.stale', 'seo.noindex_home', 'seo.robots_block_all', 'seo.broken_pages'];

    /** @return array{enabled: bool, hour: int, urgent: bool, quiet: bool, extra: list<string>} */
    public static function settings(): array
    {
        return array_merge(self::DEFAULTS, (array) PlatformSetting::get('monitor', []));
    }

    /**
     * @return array{at: string, items: list<array<string, mixed>>, critical: int, warning: int, clients: int, sites: int, other_warnings: int, platform: list<string>}
     */
    public function report(?array $previousScores = null): array
    {
        $orgs = Organization::query()->where('status', 'active')->get()->keyBy('id');
        $items = [];
        $add = function (string $level, string $kind, int $orgId, string $title, ?string $detail, string $url, string $key) use (&$items, $orgs): void {
            $items[] = ['level' => $level, 'kind' => $kind, 'org' => $orgs[$orgId]->name, 'org_slug' => $orgs[$orgId]->slug,
                'title' => $title, 'detail' => $detail, 'url' => $url, 'key' => $key];
        };

        // --- abonamente
        foreach (Subscription::withoutTenancy()->whereIn('organization_id', $orgs->keys())->get() as $sub) {
            $url = route('admin.organizations.show', $orgs[$sub->organization_id]->slug);
            if ($sub->status->value === 'trial' && $sub->trial_ends_at !== null) {
                if ($sub->trial_ends_at->isPast()) {
                    $add('critical', 'abonament', $sub->organization_id, 'Perioada de probă a expirat: agentul și chatul sunt oprite', 'Expirată la '.$sub->trial_ends_at->setTimezone(self::TIMEZONE)->format('d.m.Y'), $url, "sub.expired.{$sub->organization_id}");
                } elseif ($sub->trial_ends_at->lt(now()->addDays(3))) {
                    $add('warning', 'abonament', $sub->organization_id, 'Perioada de probă expiră la '.$sub->trial_ends_at->setTimezone(self::TIMEZONE)->format('d.m.Y'), 'După expirare, agentul și chatul se opresc.', $url, "sub.ending.{$sub->organization_id}");
                }
            } elseif (! $sub->isServiceable()) {
                $add('warning', 'abonament', $sub->organization_id, 'Abonament '.$sub->status->value.': agentul și chatul sunt oprite', null, $url, "sub.off.{$sub->organization_id}");
            } elseif ($sub->status->value === 'past_due') {
                $add('warning', 'abonament', $sub->organization_id, 'Abonament cu plata întârziată', null, $url, "sub.past_due.{$sub->organization_id}");
            }
        }

        // --- site-uri
        $sites = Site::withoutTenancy()->whereIn('organization_id', $orgs->keys())->where('status', 'active')->orderBy('domain')->get();
        $siteUrl = fn (Site $s) => route('admin.sites.health', [$orgs[$s->organization_id]->slug, $s->id]);
        $issues = SiteIssue::withoutTenancy()->whereIn('site_id', $sites->pluck('id'))->where('status', 'open')
            ->where(fn ($q) => $q->where('severity', 'critical')->orWhereIn('code', self::NAMED_WARNINGS))->orderBy('id')->get()->groupBy('site_id');
        $failedCommands = SiteCommand::withoutTenancy()->whereIn('site_id', $sites->pluck('id'))->where('status', 'failed')->where('created_at', '>=', now()->subDay())
            ->select('site_id', DB::raw('count(*) as n'))->groupBy('site_id')->pluck('n', 'site_id');
        $latestPlugin = self::latestPlugin();

        foreach ($sites as $site) {
            $url = $siteUrl($site);
            if ($site->last_seen_at !== null && ! $site->isOnline()) {
                $hours = (int) $site->last_seen_at->diffInHours(now());
                $add($hours >= 24 ? 'critical' : 'warning', 'site', $site->organization_id, "{$site->domain}: conectorul nu mai raportează",
                    'Ultimul semnal: '.($hours >= 48 ? 'acum '.intdiv($hours, 24).' zile' : "acum {$hours} ore").'. Site-ul poate fi căzut sau pluginul dezactivat.', $url, "site.silent.{$site->id}");
            }
            foreach ($issues[$site->id] ?? [] as $issue) {
                $add($issue->severity === 'critical' ? 'critical' : 'warning', self::kind($issue->code), $site->organization_id,
                    "{$site->domain}: {$issue->title}", $issue->first_seen_at ? 'Din '.$issue->first_seen_at->setTimezone(self::TIMEZONE)->format('d.m.Y') : null, $url, "issue.{$site->id}.{$issue->code}");
            }
            if ($site->learn_status === 'failed') {
                $add('warning', 'agent', $site->organization_id, "{$site->domain}: agentul nu a putut citi site-ul", $site->learn_error ? (string) $site->learn_error : null, $url, "learn.{$site->id}");
            }
            if ((int) ($failedCommands[$site->id] ?? 0) > 0) {
                $add('warning', 'site', $site->organization_id, "{$site->domain}: {$failedCommands[$site->id]} remedieri eșuate în ultimele 24 de ore", null, $url, "commands.{$site->id}");
            }
            if ($latestPlugin !== null && $site->connector_version !== null && in_array($site->platform?->value, ['wordpress', 'woocommerce'], true)
                && $site->isOnline() && version_compare($site->connector_version, $latestPlugin, '<')) {
                $add('warning', 'site', $site->organization_id, "{$site->domain}: plugin vechi ({$site->connector_version}, disponibil {$latestPlugin})", null, $url, "plugin.{$site->id}.{$latestPlugin}");
            }
            foreach ($previousScores[$site->id] ?? [] as $category => $before) {
                $now = $site->scores[$category] ?? null;
                $label = Guidance::CATEGORIES[$category] ?? $category;
                if (is_numeric($now) && is_numeric($before) && $before - $now >= self::SCORE_DROP) {
                    $add('warning', 'scor', $site->organization_id, "{$site->domain}: scorul „{$label}” a scăzut de la {$before} la {$now}", null, $url, "score.{$site->id}.{$category}.{$now}");
                }
            }
        }

        // --- conversații care așteaptă un om și conturi de trimitere cu erori (doar numere)
        $waiting = Conversation::withoutTenancy()->whereIn('organization_id', $orgs->keys())->where('is_test', false)
            ->where('status', ConversationStatus::Pending->value)->where('last_message_at', '<', now()->subHours(2))
            ->select('organization_id', DB::raw('count(*) as n'))->groupBy('organization_id')->pluck('n', 'organization_id');
        foreach ($waiting as $orgId => $n) {
            $n = (int) $n;
            $add('warning', 'agent', (int) $orgId, $n === 1 ? 'O conversație așteaptă un om de peste 2 ore' : "{$n} conversații așteaptă un om de peste 2 ore",
                'Vizitatorul a cerut un om și nimeni din firmă nu a răspuns.', route('admin.organizations.show', $orgs[$orgId]->slug), "waiting.{$orgId}");
        }
        foreach (ChannelAccount::withoutTenancy()->whereIn('organization_id', $orgs->keys())->where('status', 'error')->get() as $account) {
            $add('warning', 'trimiteri', $account->organization_id, "Contul de {$account->channel} al firmei dă eroare la trimitere", null,
                route('admin.organizations.show', $orgs[$account->organization_id]->slug), "channel.{$account->id}");
        }

        usort($items, fn (array $a, array $b) => [$a['level'] === 'critical' ? 0 : 1, $a['org']] <=> [$b['level'] === 'critical' ? 0 : 1, $b['org']]);
        $levels = array_count_values(array_column($items, 'level'));

        return [
            'at' => now()->toIso8601String(),
            'items' => $items,
            'critical' => $levels['critical'] ?? 0,
            'warning' => $levels['warning'] ?? 0,
            'clients' => $orgs->count(),
            'sites' => $sites->count(),
            'other_warnings' => SiteIssue::withoutTenancy()->whereIn('site_id', $sites->pluck('id'))->where('status', 'open')
                ->where('severity', 'warning')->whereNotIn('code', self::NAMED_WARNINGS)->count(),
            'platform' => $this->platform($sites),
        ];
    }

    /** Scorurile curente ale site-urilor, ținute de la un rezumat la altul ca să vedem scăderile. */
    public function scores(): array
    {
        // cod de platformă: doar scoruri (numere), peste toate firmele
        return Site::withoutTenancy()->where('status', 'active')->whereNotNull('scores')->get(['id', 'scores'])
            ->mapWithKeys(fn (Site $s) => [$s->id => array_filter((array) $s->scores, 'is_numeric')])->all();
    }

    /**
     * Semne că platforma însăși nu lucrează (cronul oprit, joburi eșuate).
     *
     * @param  Collection<int, Site>  $sites
     * @return list<string>
     */
    private function platform(Collection $sites): array
    {
        $notes = [];
        $failed = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();
        if ($failed > 0) {
            $notes[] = "{$failed} joburi eșuate în ultimele 24 de ore (emailuri sau procesări care nu au plecat).";
        }
        $audited = $sites->max('last_audit_at');
        if ($sites->isNotEmpty() && $sites->min('created_at')?->lt(now()->subDays(2)) && ($audited === null || $audited->lt(now()->subDays(2)))) {
            $notes[] = 'Niciun site nu a mai fost auditat de 2 zile: verifică dacă cronul platformei rulează.';
        }

        return $notes;
    }

    private static function kind(string $code): string
    {
        return match (true) {
            str_starts_with($code, 'backup.') => 'backup',
            str_starts_with($code, 'sec.ssl') => 'certificat',
            str_starts_with($code, 'sec.') => 'securitate',
            str_starts_with($code, 'seo.') => 'seo',
            str_starts_with($code, 'legal.') => 'legal',
            default => 'site',
        };
    }

    private static function latestPlugin(): ?string
    {
        $info = json_decode((string) @file_get_contents(public_path('downloads/vitim-connector.json')), true);

        return is_array($info) && ! empty($info['version']) ? (string) $info['version'] : null;
    }
}
