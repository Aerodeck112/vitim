<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Monitoring\MonitorAgent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Agentul de monitorizare: rezumatul de dimineață și alertele pentru problemele critice noi. */
#[Signature('vitim:monitor {--acum : trimite rezumatul imediat}')]
#[Description('Trimite echipei VITIM rezumatul de monitorizare și alertele critice')]
final class Monitor extends Command
{
    public function handle(MonitorAgent $agent): int
    {
        $sent = $this->option('acum') ? ($agent->digest(true) ? 'digest' : null) : $agent->tick();
        $this->info($sent === null ? 'Nimic de trimis.' : "Trimis: {$sent}.");

        return self::SUCCESS;
    }
}
