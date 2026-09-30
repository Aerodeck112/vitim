<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Singurul punct de scriere în audit_logs. meta = detalii tehnice, fără date personale sau conținut de conversații.
 */
final class AuditLogger
{
    public function __construct(private readonly TenantContext $context) {}

    /** @param array<string, scalar|null> $meta */
    public function record(string $action, ?Model $target = null, array $meta = [], string $actorType = 'user'): AuditLog
    {
        $user = Auth::user();

        return AuditLog::create([
            'organization_id' => $this->context->has() ? $this->context->id() : null,
            'actor_user_id' => $user?->getAuthIdentifier(),
            'actor_type' => $user === null && $actorType === 'user' ? 'system' : $actorType,
            'action' => $action,
            'target_type' => $target ? class_basename($target) : null,
            'target_id' => $target?->getKey(),
            'ip' => app()->runningInConsole() ? null : request()->ip(),
            'meta' => $meta ?: null,
        ]);
    }
}
