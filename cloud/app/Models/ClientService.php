<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Un serviciu VITIM contractat de client (mentenanță, SEO, Google Ads, Google Business Profile). */
#[Fillable(['service', 'status', 'started_at', 'notes'])]
class ClientService extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['started_at' => 'date'];
    }
}
