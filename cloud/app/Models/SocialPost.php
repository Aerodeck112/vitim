<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Postarea unei zile din planul de social media: text, hashtaguri, ideea vizuală și scenariul video scurt. */
#[Fillable(['day', 'topic', 'hook', 'caption', 'hashtags', 'visual', 'scenes', 'status', 'source', 'reminded_at', 'published_at'])]
class SocialPost extends Model
{
    use BelongsToOrganization;

    public const STATUSES = ['draft' => 'Ciornă', 'approved' => 'Aprobată', 'published' => 'Publicată', 'skipped' => 'Sărită'];

    protected function casts(): array
    {
        return ['day' => 'date', 'scenes' => 'array', 'reminded_at' => 'datetime', 'published_at' => 'datetime'];
    }

    /**
     * Postările dintr-un interval de zile (coloana e dată calendaristică; comparația merge la fel pe SQLite și MariaDB).
     *
     * @param  Builder<SocialPost>  $query
     */
    public function scopeBetweenDays(Builder $query, string $from, string $to): void
    {
        $query->whereDate('day', '>=', $from)->whereDate('day', '<=', $to);
    }

    /** Textul complet de lipit în Facebook / Instagram / TikTok. */
    public function fullText(): string
    {
        return trim($this->caption."\n\n".$this->hashtags);
    }
}
