<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Anunță clientul că raportul lunar e disponibil în panou. */
final class MonthlyReportPublished extends Mailable
{
    use Queueable;

    public function __construct(public readonly string $organization, public readonly string $label, public readonly string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Raportul VITIM pentru {$this->label}");
    }

    public function content(): Content
    {
        return new Content(text: 'emails.report-published');
    }
}
