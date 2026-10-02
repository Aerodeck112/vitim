<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Channel;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** O campanie de marketing pe un canal: ciornă → (test) → aprobare → programată / în trimitere → încheiată. */
#[Fillable(['name', 'channel', 'status', 'audience', 'subject', 'body', 'template', 'scheduled_at', 'approved_by', 'approved_at', 'started_at', 'completed_at', 'last_error', 'created_by'])]
class Campaign extends Model
{
    use BelongsToOrganization;

    public const STATUSES = ['draft' => 'Ciornă', 'scheduled' => 'Programată', 'sending' => 'Se trimite', 'paused' => 'Oprită', 'completed' => 'Trimisă', 'cancelled' => 'Anulată'];

    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
            'audience' => 'array',
            'template' => 'array',
            'scheduled_at' => 'datetime',
            'approved_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function editable(): bool
    {
        return $this->status === 'draft';
    }

    /** @return HasMany<CampaignRecipient, $this> */
    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Deschideri și click-uri unice, raportate la mesajele trimise. @return array{sent: int, opened: int, clicked: int, open_rate: float, click_rate: float} */
    public function engagement(): array
    {
        $row = CampaignRecipient::query()->where('campaign_id', $this->id)->whereNotNull('sent_at')->whereIn('status', ['sent', 'delivered', 'read', 'unsubscribed'])
            ->selectRaw('count(*) as sent, sum(case when opened_at is not null then 1 else 0 end) as opened, sum(case when clicked_at is not null then 1 else 0 end) as clicked')->first();
        $sent = (int) ($row->sent ?? 0);

        return ['sent' => $sent, 'opened' => (int) ($row->opened ?? 0), 'clicked' => (int) ($row->clicked ?? 0),
            'open_rate' => $sent ? round(100 * (int) $row->opened / $sent, 1) : 0.0, 'click_rate' => $sent ? round(100 * (int) $row->clicked / $sent, 1) : 0.0];
    }

    /** @return array<string, int> numărul de destinatari pe status */
    public function stats(): array
    {
        return CampaignRecipient::query()->where('campaign_id', $this->id)
            ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n)->all();
    }
}
