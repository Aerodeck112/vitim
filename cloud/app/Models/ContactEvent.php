<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** O activitate din profilul contactului (ca „metricile” din Klaviyo): email deschis, formular trimis, comandă... */
#[Fillable(['contact_id', 'type', 'data', 'value', 'campaign_id', 'flow_id', 'recipient_id', 'occurred_at'])]
class ContactEvent extends Model
{
    use BelongsToOrganization;

    public $timestamps = false;

    /** tip => [etichetă, iconiță] */
    public const TYPES = [
        'email_sent' => ['A primit emailul', '✉️'],
        'email_opened' => ['A deschis emailul', '👁'],
        'email_clicked' => ['A dat click în email', '🔗'],
        'sms_sent' => ['A primit SMS', '💬'],
        'whatsapp_sent' => ['A primit WhatsApp', '🟢'],
        'unsubscribed' => ['S-a dezabonat', '🚫'],
        'subscribed' => ['S-a abonat', '✅'],
        'joined_list' => ['Adăugat în listă', '📋'],
        'form_submitted' => ['A completat un formular', '📝'],
        'lead_created' => ['A trimis o cerere', '📩'],
        'viewed_product' => ['A văzut un produs', '🛍'],
        'added_to_cart' => ['A adăugat în coș', '🛒'],
        'started_checkout' => ['A început comanda', '💳'],
        'placed_order' => ['A plasat o comandă', '✅'],
    ];

    protected function casts(): array
    {
        return ['data' => 'array', 'value' => 'decimal:2', 'occurred_at' => 'datetime'];
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function label(): string
    {
        return self::TYPES[$this->type][0] ?? $this->type;
    }
}
