<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Raportul lunar al unui client: cifrele pe servicii completate de VITIM + rezumat. Vizibil clientului după publicare. */
#[Fillable(['period', 'data', 'summary'])]
class MonthlyReport extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['data' => 'array', 'published_at' => 'datetime'];
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
