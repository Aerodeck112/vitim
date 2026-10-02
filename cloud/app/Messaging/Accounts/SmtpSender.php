<?php

declare(strict_types=1);

namespace App\Messaging\Accounts;

use App\Enums\Channel;
use App\Enums\MessageStatus;
use App\Messaging\OutboundMessage;
use App\Messaging\ProviderResult;
use App\Models\ChannelAccount;
use Closure;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/** Email prin serverul SMTP al firmei (de obicei contul de email din cPanel). */
final class SmtpSender implements AccountSender
{
    /** @param (Closure(ChannelAccount): TransportInterface)|null $transports în teste: un transport care doar reține mesajele */
    public function __construct(private readonly ?Closure $transports = null) {}

    public function send(ChannelAccount $account, OutboundMessage $message): ProviderResult
    {
        $email = (new Email)
            ->from(new Address((string) $account->setting('from_email'), (string) $account->setting('from_name', '')))
            ->to($message->to)
            ->subject((string) $message->subject)
            ->text($message->body)
            ->html((string) ($message->metadata['html'] ?? nl2br(e($message->body))));
        if ($reply = $account->setting('reply_to')) {
            $email->replyTo((string) $reply);
        }
        if ($unsubscribe = $message->metadata['unsubscribe_url'] ?? null) {
            // dezabonare dintr-un click din Gmail / Outlook (RFC 8058)
            $email->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$unsubscribe.'>');
            $email->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        }
        try {
            $sent = $this->transport($account)->send($email);

            return new ProviderResult(MessageStatus::Sent, $sent?->getMessageId());
        } catch (TransportExceptionInterface $e) {
            return new ProviderResult(MessageStatus::Failed, null, Str::limit($e->getMessage(), 280));
        }
    }

    public function test(ChannelAccount $account, string $to): ProviderResult
    {
        return $this->send($account, new OutboundMessage(0, Channel::Email, $to,
            "Acesta este un email de test din panoul VITIM.\n\nDacă l-ai primit, contul de trimitere funcționează.", 'Test trimitere email · VITIM'));
    }

    private function transport(ChannelAccount $account): TransportInterface
    {
        if ($this->transports) {
            return ($this->transports)($account);
        }
        $encryption = (string) $account->setting('encryption', 'tls');
        $transport = new EsmtpTransport((string) $account->setting('host'), (int) $account->setting('port', 587), $encryption === 'ssl');
        $transport->setUsername((string) $account->setting('username'));
        $transport->setPassword((string) $account->setting('password'));
        $transport->getStream()->setTimeout(20);

        return $transport;
    }
}
