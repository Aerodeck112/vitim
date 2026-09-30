<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Events\DomainEventListener;
use App\Models\DomainEvent;
use App\Models\Organization;
use App\Tenancy\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/** Livrează evenimentele din outbox către consumatori. Rulează la minut din scheduler. */
#[Signature('vitim:events')]
#[Description('Procesează evenimentele de domeniu (outbox)')]
final class ProcessEvents extends Command
{
    public function handle(TenantContext $context): int
    {
        $max = (int) config('domain_events.max_attempts', 5);
        // cod de platformă: evenimentele tuturor firmelor, fiecare procesat apoi în contextul firmei lui
        $events = DomainEvent::withoutTenancy()->whereNull('processed_at')->where('attempts', '<', $max)
            ->orderBy('id')->limit((int) config('domain_events.batch', 200))->get();
        $organizations = Organization::query()->whereIn('id', $events->pluck('organization_id')->filter()->unique())->get()->keyBy('id');

        $done = 0;
        foreach ($events as $event) {
            try {
                $run = fn () => $this->dispatch($event);
                $organization = $organizations[$event->organization_id] ?? null;
                $organization ? $context->runAs($organization, $run) : $run();
                DomainEvent::withoutTenancy()->whereKey($event->getKey())->update(['processed_at' => now(), 'last_error' => null]);
                $done++;
            } catch (Throwable $e) {
                report($e);
                DomainEvent::withoutTenancy()->whereKey($event->getKey())->update([
                    'attempts' => $event->attempts + 1,
                    'last_error' => mb_substr($e->getMessage(), 0, 1000),
                ]);
            }
        }
        if ($events->isNotEmpty()) {
            $this->info("Evenimente procesate: {$done}/{$events->count()}");
        }

        return self::SUCCESS;
    }

    private function dispatch(DomainEvent $event): void
    {
        $listeners = config('domain_events.listeners', []);
        foreach ([...($listeners[$event->type] ?? []), ...($listeners['*'] ?? [])] as $class) {
            $listener = app($class);
            if (! $listener instanceof DomainEventListener) {
                throw new \LogicException("{$class} nu implementează DomainEventListener");
            }
            $listener->handle($event);
        }
    }
}
