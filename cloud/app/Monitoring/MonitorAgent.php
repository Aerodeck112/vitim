<?php

declare(strict_types=1);

namespace App\Monitoring;

use App\Mail\MonitorReport;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Trimite echipei VITIM rezumatul de dimineață și, între rezumate, alerta pentru problemele critice noi.
 * Fiecare problemă critică anunță o singură dată; dacă se rezolvă și reapare, anunță din nou.
 */
final class MonitorAgent
{
    public function __construct(private readonly FleetMonitor $monitor) {}

    /** Rulat de cron la 15 minute. Întoarce ce a trimis: digest, alert sau null. */
    public function tick(): ?string
    {
        $settings = FleetMonitor::settings();
        if (! $settings['enabled']) {
            return null;
        }
        $local = now()->setTimezone(FleetMonitor::TIMEZONE);
        $state = (array) PlatformSetting::get('monitor.state', []);
        if (($state['digest_on'] ?? null) !== $local->toDateString() && $local->hour >= $settings['hour']) {
            return $this->digest() ? 'digest' : null;
        }
        if (! $settings['urgent']) {
            return null;
        }
        $report = $this->monitor->report();
        $critical = array_values(array_filter($report['items'], fn (array $i) => $i['level'] === 'critical'));
        $known = (array) ($state['alerted'] ?? []);
        $new = array_values(array_filter($critical, fn (array $i) => ! in_array($i['key'], $known, true)));
        $this->remember($report, ['alerted' => array_column($critical, 'key')]);
        if ($new === []) {
            return null;
        }
        $this->send(new MonitorReport(['items' => $new, 'critical' => count($new), 'warning' => 0] + $report, urgent: true));

        return 'alert';
    }

    /** Rezumatul zilei, cu scăderile de scor față de rezumatul anterior. Întoarce false dacă nu a avut ce trimite. */
    public function digest(bool $force = false): bool
    {
        $settings = FleetMonitor::settings();
        $state = (array) PlatformSetting::get('monitor.state', []);
        $report = $this->monitor->report((array) ($state['scores'] ?? []));
        $this->remember($report, [
            'digest_on' => now()->setTimezone(FleetMonitor::TIMEZONE)->toDateString(),
            'scores' => $this->monitor->scores(),
            'alerted' => array_column(array_filter($report['items'], fn (array $i) => $i['level'] === 'critical'), 'key'),
        ]);
        if (! $force && $settings['quiet'] && $report['items'] === [] && $report['platform'] === []) {
            return false;
        }
        $this->send(new MonitorReport($report));

        return true;
    }

    /** @return list<string> */
    public function recipients(): array
    {
        return User::query()->whereNotNull('platform_role')->pluck('email')
            ->merge(FleetMonitor::settings()['extra'])->map(fn ($e) => strtolower(trim((string) $e)))->filter()->unique()->values()->all();
    }

    /** @param array<string, mixed> $state */
    private function remember(array $report, array $state): void
    {
        PlatformSetting::put('monitor.state', $state + (array) PlatformSetting::get('monitor.state', []));
        PlatformSetting::put('monitor.last', ['at' => $report['at'], 'critical' => $report['critical'], 'warning' => $report['warning']]);
    }

    private function send(MonitorReport $mail): void
    {
        foreach ($this->recipients() as $email) {
            Mail::to($email)->send(clone $mail);
        }
    }
}
