<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Channel;
use App\Enums\ConsentPurpose;
use App\Enums\MessageStatus;
use App\Enums\SenderType;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['conversation_id', 'direction', 'sender_type', 'sender_user_id', 'channel', 'purpose', 'content', 'status', 'provider', 'external_message_id', 'error', 'metadata', 'sent_at', 'delivered_at', 'read_at', 'failed_at'])]
class Message extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'sender_type' => SenderType::class,
            'channel' => Channel::class,
            'purpose' => ConsentPurpose::class,
            'status' => MessageStatus::class,
            'metadata' => 'array',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
