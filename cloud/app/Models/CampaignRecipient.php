<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un destinatar al campaniei: trimis, eșuat sau exclus (cu motivul: fără acord, dezabonat, fără adresă). */
#[Fillable(['campaign_id', 'flow_id', 'flow_step_id', 'flow_run_id', 'contact_id', 'address', 'status', 'reason', 'external_id', 'unsubscribe_code', 'sent_at', 'opened_at', 'clicked_at', 'open_count', 'click_count'])]
class CampaignRecipient extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'opened_at' => 'datetime', 'clicked_at' => 'datetime'];
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
