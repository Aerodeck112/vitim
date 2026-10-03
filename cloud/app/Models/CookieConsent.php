<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** O alegere a unui vizitator în bannerul de cookie-uri (registrul de dovezi al site-ului). */
#[Fillable(['site_id', 'consent_id', 'action', 'preferences', 'statistics', 'marketing', 'policy_version', 'ip_hash', 'user_agent', 'page', 'created_at'])]
class CookieConsent extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    public const ACTIONS = ['accept_all' => 'a acceptat tot', 'reject_all' => 'a refuzat tot', 'custom' => 'a ales din setări'];

    protected function casts(): array
    {
        return ['preferences' => 'boolean', 'statistics' => 'boolean', 'marketing' => 'boolean'];
    }
}
