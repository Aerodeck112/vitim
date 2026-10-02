<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Un design de email salvat de firmă, refolosibil în campanii și automatizări. */
#[Fillable(['name', 'blocks'])]
class EmailTemplate extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['blocks' => 'array'];
    }
}
