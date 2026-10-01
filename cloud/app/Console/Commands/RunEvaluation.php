<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ai\Evaluation;
use App\Models\Organization;
use App\Tenancy\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Setul de evaluare al agentului, rulat la cerere (consumă API real; costul se afișează și intră în consumul firmei).
 * Rezultatul se salvează în storage/app/evals/ ca să poată fi comparat după schimbări de prompt sau de model.
 */
#[Signature('vitim:eval {organization : slug-ul firmei în care rulează (costul intră la ea)} {set=all : generic, auto sau all}')]
#[Description('Rulează setul de evaluare al agentului AI și salvează rezultatul')]
final class RunEvaluation extends Command
{
    public function handle(TenantContext $context, Evaluation $evaluation): int
    {
        $organization = Organization::query()->where('slug', $this->argument('organization'))->first();
        if (! $organization) {
            $this->error('Firmă inexistentă.');

            return self::FAILURE;
        }
        $sets = $this->argument('set') === 'all' ? Evaluation::sets() : [(string) $this->argument('set')];
        if (array_diff($sets, Evaluation::sets())) {
            $this->error('Seturi disponibile: '.implode(', ', Evaluation::sets()));

            return self::FAILURE;
        }

        foreach ($sets as $set) {
            $this->line("<info>{$set}</info>");
            $report = $context->runAs($organization, fn () => $evaluation->run($set, function (array $r): void {
                $this->line(sprintf('  %s %s %s', $r['passed'] ? '✓' : '✗', $r['id'], $r['passed'] ? '' : implode('; ', $r['failures'])));
            }));
            $path = 'evals/'.$set.'-'.now()->format('Ymd-His').'.json';
            Storage::disk('local')->put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->line(sprintf('  %d/%d (%.0f%%) · cost $%.4f · %s', $report['passed'], $report['total'], $report['score'] * 100, $report['cost_usd'], Storage::disk('local')->path($path)));
        }

        return self::SUCCESS;
    }
}
