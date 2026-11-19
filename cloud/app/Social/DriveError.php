<?php

declare(strict_types=1);

namespace App\Social;

use RuntimeException;

/** Citirea folderului de Google Drive a eșuat; mesajul e pentru utilizator (fără cheia contului de serviciu). */
final class DriveError extends RuntimeException {}
