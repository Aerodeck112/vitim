<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/** Contul firmei la un furnizor de trimitere (SMTP, SMSLink, WhatsApp Cloud API). Datele de acces sunt criptate. */
#[Fillable(['channel', 'provider', 'config', 'status', 'last_error', 'tested_at', 'hourly_limit', 'webhook_token'])]
#[Hidden(['config', 'webhook_token'])]
class ChannelAccount extends Model
{
    use BelongsToOrganization;

    public const PROVIDERS = ['email' => 'smtp', 'sms' => 'smslink', 'whatsapp' => 'meta'];

    protected function casts(): array
    {
        return ['config' => 'encrypted:array', 'tested_at' => 'datetime'];
    }

    /** O valoare din configurare (fără secrete în loguri). */
    public function setting(string $key, mixed $default = null): mixed
    {
        return ($this->config ?? [])[$key] ?? $default;
    }
}
