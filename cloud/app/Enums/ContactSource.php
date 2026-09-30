<?php

declare(strict_types=1);

namespace App\Enums;

/** De unde a intrat contactul în platformă (prima sursă; interacțiunile ulterioare sunt evenimente). */
enum ContactSource: string
{
    case WebsiteAi = 'website_ai';
    case Form = 'form';
    case WooCommerce = 'woocommerce';
    case Manual = 'manual';
    case Import = 'import';
    case Email = 'email';
    case WhatsApp = 'whatsapp';
    case Sms = 'sms';
    case Crm = 'crm';
    case Api = 'api';
    case Ads = 'ads';
}
