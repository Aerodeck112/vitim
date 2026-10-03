<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Noutate VITIM trimisă clienților (date de platformă, nu ale unei firme; doar echipa VITIM le creează).
 * kind: update = ce e nou în platformă, news = anunț liber, digest = rezumatul lunar personalizat.
 */
#[Fillable(['kind', 'title', 'subject', 'intro', 'body', 'cta_label', 'cta_url', 'include_work', 'audience', 'status', 'version', 'period', 'scheduled_at', 'sent_at', 'created_by'])]
class Announcement extends Model
{
    public const KINDS = ['update' => 'Noutăți în platformă', 'news' => 'Anunț', 'digest' => 'Rezumat lunar'];

    public const STATUSES = ['draft' => 'Ciornă', 'scheduled' => 'Programat', 'sending' => 'Se trimite', 'sent' => 'Trimis', 'cancelled' => 'Anulat'];

    protected function casts(): array
    {
        return ['include_work' => 'boolean', 'audience' => 'array', 'scheduled_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    /** @return HasMany<AnnouncementDelivery, $this> */
    public function deliveries(): HasMany
    {
        // livrările aparțin firmelor; aici le citește doar codul de platformă
        return $this->hasMany(AnnouncementDelivery::class)->withoutGlobalScopes();
    }

    public function editable(): bool
    {
        return in_array($this->status, ['draft', 'scheduled'], true);
    }
}
