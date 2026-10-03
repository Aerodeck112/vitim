<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/** Noutățile VITIM către clienți (conținutul e randat de App\Services\Announcements). */
final class AnnouncementMail extends Mailable
{
    public function __construct(public readonly string $subjectLine, public readonly string $htmlBody, public readonly string $textBody, public readonly ?string $unsubscribeUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->htmlBody, text: 'emails.raw-text', with: ['text' => $this->textBody]);
    }

    public function headers(): Headers
    {
        return new Headers(text: $this->unsubscribeUrl ? ['List-Unsubscribe' => '<'.$this->unsubscribeUrl.'>'] : []);
    }
}
