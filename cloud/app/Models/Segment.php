<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Segment dinamic: condiții evaluate la fiecare folosire (contactele intră și ies singure). */
#[Fillable(['name', 'definition', 'contacts_count', 'refreshed_at'])]
class Segment extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['definition' => 'array', 'refreshed_at' => 'datetime'];
    }
}
