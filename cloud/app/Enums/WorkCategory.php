<?php

declare(strict_types=1);

namespace App\Enums;

enum WorkCategory: string
{
    case Updates = 'updates';
    case Backup = 'backup';
    case Security = 'security';
    case Repair = 'repair';
    case Content = 'content';
    case Seo = 'seo';
    case Ads = 'ads';
    case Gbp = 'gbp';
    case Development = 'development';
    case Support = 'support';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Updates => 'Actualizări',
            self::Backup => 'Backup',
            self::Security => 'Securitate',
            self::Repair => 'Reparație / depanare',
            self::Content => 'Conținut',
            self::Seo => 'SEO',
            self::Ads => 'Google Ads',
            self::Gbp => 'Google Business Profile',
            self::Development => 'Dezvoltare',
            self::Support => 'Suport',
            self::Other => 'Altele',
        };
    }
}
