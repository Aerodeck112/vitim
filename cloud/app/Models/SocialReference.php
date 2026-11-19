<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** O postare deja publicată de firmă pe Facebook sau Instagram, citită prin interfața oficială Meta. */
#[Fillable(['network', 'external_id', 'message', 'permalink', 'media_type', 'engagement', 'posted_at'])]
class SocialReference extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['posted_at' => 'datetime'];
    }
}
