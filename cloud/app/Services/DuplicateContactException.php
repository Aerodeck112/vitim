<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/** Un identificator (email, telefon, ID extern) aparține deja altui contact din aceeași firmă. */
final class DuplicateContactException extends RuntimeException
{
    public function __construct(public readonly int $existingContactId, public readonly string $identityType)
    {
        parent::__construct("Există deja un contact cu acest {$identityType}.");
    }
}
