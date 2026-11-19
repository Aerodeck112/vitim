<?php

declare(strict_types=1);

namespace App\Mail;

use App\Monitoring\FleetMonitor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Rezumatul agentului de monitorizare (sau alerta pentru probleme critice noi), către echipa VITIM. */
final class MonitorReport extends Mailable
{
    use Queueable;

    /** @param array<string, mixed> $report */
    public function __construct(public readonly array $report, public readonly bool $urgent = false) {}

    public function envelope(): Envelope
    {
        $c = $this->report['critical'];
        $w = $this->report['warning'];
        if ($this->urgent) {
            return new Envelope(subject: 'Alertă VITIM AI: '.($c === 1 ? $this->report['items'][0]['title'] : "{$c} probleme critice noi"));
        }
        $day = now()->setTimezone(FleetMonitor::TIMEZONE)->format('d.m');

        return new Envelope(subject: "Monitorizare VITIM AI {$day}: ".($c + $w === 0 ? 'totul e în regulă' : "{$c} critice, {$w} de urmărit"));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.monitor', text: 'emails.monitor-text', with: [
            'groups' => collect($this->report['items'])->groupBy('org'),
            'url' => route('admin.monitor'),
        ]);
    }
}
