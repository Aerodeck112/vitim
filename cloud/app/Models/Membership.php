<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrgRole;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'user_id', 'role'])]
class Membership extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['role' => OrgRole::class];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
