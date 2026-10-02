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

    /** @return array<string, int> numărul de destinatari pe status */
    public function stats(): array
    {
        return CampaignRecipient::query()->where('campaign_id', $this->id)
            ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n)->all();
    }
}
