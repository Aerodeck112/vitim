<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContactSource;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Persoana (client sau potențial client) a unei firme. Emailul și telefonul de aici sunt doar copii de afișare
 * ale identităților principale; recunoașterea aceluiași om pe mai multe canale se face prin ContactIdentity.
 * Se creează și se modifică prin ContactService (deduplicare, evenimente, audit).
 */
#[Fillable(['first_name', 'last_name', 'email', 'phone', 'company', 'language', 'source', 'status', 'custom_fields', 'last_activity_at'])]
class Contact extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'source' => ContactSource::class,
            'custom_fields' => 'array',
            'last_activity_at' => 'datetime',
            'predicted_clv' => 'decimal:2',
            'predicted_next_order_at' => 'datetime',
            'predicted_at' => 'datetime',
        ];
    }

    public function displayName(): string
    {
        $name = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

        return $name !== '' ? $name : ($this->email ?? $this->phone ?? 'Contact #'.$this->getKey());
    }

    /** @return HasMany<ContactIdentity, $this> */
    public function identities(): HasMany
    {
        return $this->hasMany(ContactIdentity::class);
    }

    /** @return HasMany<ContactConsent, $this> */
    public function consents(): HasMany
    {
        return $this->hasMany(ContactConsent::class);
    }

    /** @return HasMany<Lead, $this> */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
