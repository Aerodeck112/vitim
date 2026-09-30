<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IdentityType;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Email, telefon, WhatsApp sau ID extern al unui contact. Unic per firmă după valoarea normalizată. */
#[Fillable(['contact_id', 'type', 'provider', 'value', 'normalized_value', 'verified', 'is_primary'])]
class ContactIdentity extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['type' => IdentityType::class, 'verified' => 'boolean', 'is_primary' => 'boolean'];
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
