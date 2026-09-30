<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Organization;
use App\Models\User;
use App\Tenancy\BelongsToOrganization;
use Tests\TestCase;

/**
 * Orice model nou trebuie să fie scoped pe organizație, cu excepția celor globale prin definiție.
 * Dacă acest test pică, fie adaugi BelongsToOrganization, fie justifici excepția aici.
 */
final class ArchitectureTest extends TestCase
{
    private const GLOBAL_MODELS = [
        User::class,         // un om poate fi membru în mai multe firme; accesul vine din Membership
        Organization::class, // tenantul însuși
    ];

    public function test_every_model_is_tenant_scoped_or_explicitly_global(): void
    {
        $unscoped = [];
        foreach (glob(app_path('Models/*.php')) ?: [] as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');
            if (in_array($class, self::GLOBAL_MODELS, true)) {
                continue;
            }
            if (! in_array(BelongsToOrganization::class, class_uses_recursive($class), true)) {
                $unscoped[] = $class;
            }
        }

        $this->assertSame([], $unscoped, 'Modele fără BelongsToOrganization: '.implode(', ', $unscoped));
    }
}
