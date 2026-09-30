<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SiteKey;

/** Cheie nou emisă. Secretul în clar există doar aici, o singură dată, pentru afișare/transfer. */
final readonly class IssuedSiteKey
{
    public function __construct(
        public SiteKey $key,
        public string $publicKey,
        public string $secret,
    ) {}
}
