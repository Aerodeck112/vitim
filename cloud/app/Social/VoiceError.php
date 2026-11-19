<?php

declare(strict_types=1);

namespace App\Social;

use RuntimeException;

/** Generarea vocii a eșuat; mesajul e pentru utilizator (fără cheia serviciului). */
final class VoiceError extends RuntimeException {}
