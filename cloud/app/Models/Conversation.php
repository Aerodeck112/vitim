<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Channel;
use App\Enums\ConversationStatus;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Fundația VITIM Inbox: aceeași structură pentru web chat, email, WhatsApp și SMS. */
#[Fillable(['contact_id', 'site_id', 'agent_id', 'channel', 'status', 'mode', 'is_test', 'visitor_token_hash', 'visitor_page', 'visitor_seen_at', 'staff_read_at', 'bot_state', 'assigned_to', 'subject', 'last_message_at', 'closed_at'])]
class Conversation extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
            'is_test' => 'boolean',
            'status' => ConversationStatus::class,
            'last_message_at' => 'datetime',
            'closed_at' => 'datetime',
            'visitor_seen_at' => 'datetime',
            'staff_read_at' => 'datetime',
            'bot_state' => 'array',
        ];
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return BelongsTo<Agent, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /** @return HasMany<AiTurn, $this> */
    public function aiTurns(): HasMany
    {
        return $this->hasMany(AiTurn::class)->orderBy('id');
    }

    /** @return HasMany<ToolExecution, $this> */
    public function toolExecutions(): HasMany
    {
        return $this->hasMany(ToolExecution::class)->orderBy('id');
    }

    /** Vizitatorul are widgetul deschis pe site acum (widgetul verifică mesajele noi la câteva secunde). */
    public function visitorOnline(): bool
    {
        return $this->visitor_seen_at !== null && $this->visitor_seen_at->gt(now()->subSeconds(40));
    }

    public function isLive(): bool
    {
        return $this->mode === 'human';
    }

    /** @return HasOne<Lead, $this> */
    public function lead(): HasOne
    {
        return $this->hasOne(Lead::class);
    }
}
