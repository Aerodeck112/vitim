<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Produs din magazinul WooCommerce al firmei (pentru blocurile de produs din emailuri și segmente). */
#[Fillable(['site_id', 'external_id', 'name', 'price', 'currency', 'url', 'image', 'categories', 'in_stock', 'synced_at'])]
class Product extends Model
{
    use BelongsToOrganization;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'categories' => 'array', 'in_stock' => 'boolean', 'synced_at' => 'datetime'];
    }
}
