<?php

declare(strict_types=1);

namespace App\Enums;

/** Canalele de comunicare. Conversațiile folosesc Web/Email/WhatsApp/Sms/Other; consimțământul Email/Sms/WhatsApp/Phone. */
enum Channel: string
{
    case Web = 'web';
    case Email = 'email';
    case WhatsApp = 'whatsapp';
    case Sms = 'sms';
    case Phone = 'phone';
    case Other = 'other';

    /** @return list<self> */
    public static function consentChannels(): array
    {
        return [self::Email, self::Sms, self::WhatsApp, self::Phone];
    }

    /** @return list<self> */
    public static function messagingChannels(): array
    {
        return [self::Email, self::Sms, self::WhatsApp];
    }
}
