<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Anunță echipa firmei: cerere nouă de la agentul AI sau vizitator care vrea un om. */
final class TeamAlert extends Mailable
{
    use Queueable;

    /** @param list<string> $lines */
    public function __construct(public readonly string $title, public readonly array $lines, public readonly string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->title);
    }

    public function content(): Content
    {
        return new Content(text: 'emails.team-alert');
    }
}
