<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Un backup raportat de plugin (baza de date + fișierele site-ului, pe hostingul clientului). */
#[Fillable(['site_id', 'status', 'verified', 'started_at', 'finished_at', 'db_bytes', 'files_bytes', 'files_count', 'location', 'kept', 'error'])]
class SiteBackup extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['verified' => 'boolean', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function totalBytes(): int
    {
        return (int) $this->db_bytes + (int) $this->files_bytes;
    }
}
