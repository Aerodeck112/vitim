<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * O tură din conversația cu modelul AI, în formatul API (user: text sau rezultate de tool-uri;
 * assistant: răspunsul complet, cu blocurile de gândire). Append-only: istoricul se retrimite neschimbat.
 */
#[Fillable(['conversation_id', 'role', 'payload'])]
class AiTurn extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
