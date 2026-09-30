<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LeadIntent;
use App\Enums\LeadStatus;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** O oportunitate a unui contact (un contact poate avea mai multe lead-uri în timp). Se modifică prin LeadService. */
#[Fillable(['contact_id', 'site_id', 'agent_id', 'conversation_id', 'source', 'status', 'intent', 'score', 'assigned_to', 'summary', 'value_amount', 'currency', 'closed_at'])]
class Lead extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'intent' => LeadIntent::class,
            'score' => 'integer',
            'value_amount' => 'decimal:2',
            'closed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
