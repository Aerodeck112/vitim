<?php

declare(strict_types=1);

namespace App\Enums;

enum SitePlatform: string
{
    case WordPress = 'wordpress';
    case WooCommerce = 'woocommerce';
    case Custom = 'custom';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::WordPress => 'WordPress',
            self::WooCommerce => 'WooCommerce',
            self::Custom => 'Site custom',
            self::Other => 'Altă platformă',
        };
    }
}
