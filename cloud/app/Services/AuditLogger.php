<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
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
            'actor_type' => match (true) {
                $actorType !== 'user' => $actorType,
                $user === null => 'system',
                $user instanceof User && $user->isPlatformStaff() => 'platform',
                default => 'user',
            },
            'action' => $action,
            'entity_type' => $target ? class_basename($target) : null,
            'entity_id' => $target?->getKey(),
            'ip' => app()->runningInConsole() ? null : request()->ip(),
            'meta' => $meta ?: null,
        ]);
    }
}
