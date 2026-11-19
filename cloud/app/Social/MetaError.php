<?php

declare(strict_types=1);

namespace App\Social;

use RuntimeException;

/** Citirea paginii de Meta a eșuat; mesajul e pentru utilizator (fără codul de acces). */
final class MetaError extends RuntimeException {}
