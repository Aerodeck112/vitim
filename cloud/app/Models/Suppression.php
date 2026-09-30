<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Channel;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Adresă / număr care nu mai primește mesaje pe un canal (dezabonare, bounce, reclamație, manual).
 * value_hash = HMAC al valorii normalizate: lista rămâne valabilă și după ștergerea contactului, fără a păstra adresa.
 */
#[Fillable(['channel', 'value_hash', 'reason', 'source'])]
class Suppression extends Model
{
    use BelongsToOrganization;

    /** Motive care blochează orice mesaj, nu doar marketingul. */
    public const HARD_REASONS = ['bounced', 'complaint'];

    protected function casts(): array
    {
        return ['channel' => Channel::class];
    }

    public static function hash(string $normalizedValue): string
    {
        return hash_hmac('sha256', $normalizedValue, (string) config('app.key'));
    }

    /**
     * Pune (sau păstrează) adresa pe listă. Motivul poate doar să se agraveze:
     * bounce / reclamație înlocuiesc o dezabonare, niciodată invers.
     */
    public static function suppress(Channel $channel, string $normalizedValue, string $reason, ?string $source = null): self
    {
        $row = static::query()->firstOrNew(['channel' => $channel->value, 'value_hash' => self::hash($normalizedValue)]);
        if (! $row->exists || (in_array($reason, self::HARD_REASONS, true) && ! in_array($row->reason, self::HARD_REASONS, true))) {
            $row->fill(['reason' => $reason, 'source' => $source])->save();
        }

        return $row;
    }
}
