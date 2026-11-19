<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/** Legătura firmei cu pagina ei de Facebook / contul de Instagram. Codul de acces e criptat și nu se afișează niciodată. */
#[Fillable(['network', 'config', 'status', 'last_error', 'synced_at'])]
#[Hidden(['config'])]
class SocialConnection extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['config' => 'encrypted:array', 'synced_at' => 'datetime'];
    }
}
