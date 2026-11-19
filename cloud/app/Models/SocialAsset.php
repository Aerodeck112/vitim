<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Un material al firmei pentru postări (poză din site, din pagina de Meta sau încărcată). */
#[Fillable(['source', 'origin', 'origin_hash', 'path', 'title', 'width', 'height', 'bytes'])]
class SocialAsset extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [];
    }
}
