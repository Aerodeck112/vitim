<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Organization;

/**
 * Organizația (tenantul) pentru care rulează cererea sau jobul curent.
 *
 * Se setează într-un singur loc per cerere: middleware-ul portalului (membership),
 * rezolvarea cheii de site (widget / plugin) sau explicit în joburi prin runAs().
 * Nu se setează niciodată din date trimise de client sau de modelul AI.
 */
final class TenantContext
{
    private ?Organization $organization = null;

    public function set(Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function clear(): void
    {
        $this->organization = null;
    }

    public function has(): bool
    {
        return $this->organization !== null;
    }

    public function id(): int
    {
        return $this->organization()->getKey();
    }

    public function organization(): Organization
    {
        return $this->organization ?? throw TenancyViolation::missingContext();
    }

    /**
     * Rulează codul în contextul unei organizații și restaurează contextul anterior.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function runAs(Organization $organization, callable $callback): mixed
    {
        $previous = $this->organization;
        $this->organization = $organization;
        try {
            return $callback();
        } finally {
            $this->organization = $previous;
        }
    }
}
