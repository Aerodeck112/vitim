<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Consum agregat pe zi și metrică (scris prin UsageMeter). */
#[Fillable(['metric', 'period_date', 'quantity'])]
class UsageRecord extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['period_date' => 'date', 'quantity' => 'integer'];
    }
}
