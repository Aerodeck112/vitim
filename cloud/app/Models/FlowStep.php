<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Un pas al fluxului; pașii din ramurile unei condiții au parent_id = condiția și branch = yes / no. */
#[Fillable(['flow_id', 'parent_id', 'branch', 'position', 'type', 'config'])]
class FlowStep extends Model
{
    use BelongsToOrganization;

    public const TYPES = ['wait' => 'Așteaptă', 'email' => 'Email', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'condition' => 'Condiție (da / nu)', 'list_add' => 'Adaugă în listă'];

    protected function casts(): array
    {
        return ['config' => 'array'];
    }

    public function conf(string $key, mixed $default = null): mixed
    {
        return ($this->config ?? [])[$key] ?? $default;
    }
}
