<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Listă statică de contacte (abonați newsletter, participanți la un eveniment...). */
#[Fillable(['name', 'description'])]
class ContactList extends Model
{
    use BelongsToOrganization;

    /** @return HasMany<ContactListMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(ContactListMember::class);
    }
}
