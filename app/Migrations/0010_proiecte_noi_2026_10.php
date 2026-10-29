<?php
declare(strict_types=1);

use App\Core\Cache;
use App\Core\ProjectSync;

/** v1.11: studii de caz noi — Another World Cluj, Sevalia, Autentik Market (cu capturile site-urilor). */
return function (): void {
    ProjectSync::run();
    Cache::clear();
};
