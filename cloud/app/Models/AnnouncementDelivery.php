<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Un email de noutăți VITIM trimis unui utilizator al unei firme. */
#[Fillable(['announcement_id', 'user_id', 'email', 'status', 'error', 'code', 'sent_at', 'opened_at'])]
class AnnouncementDelivery extends Model
{
    use BelongsToOrganization;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'opened_at' => 'datetime'];
    }
}
