<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ContactEvent;
use Illuminate\Support\Carbon;

/**
 * Ora optimă de trimitere (ca „Smart Send Time”): ora din zi, la ora României, la care abonații firmei
 * deschid cel mai des emailurile, din deschiderile ultimelor 180 de zile. Sub 30 de deschideri, recomandarea implicită e 10:00.
 */
final class SendTime
{
    public const MIN_SAMPLE = 30;

    /** @return array{hour: int, sample: int, reliable: bool, by_hour: array<int, int>, next: string} */
    public static function best(): array
    {
        $byHour = array_fill(0, 24, 0);
        $sample = 0;
        ContactEvent::query()->where('type', 'email_opened')->where('occurred_at', '>=', now()->subDays(180))
            ->orderByDesc('id')->limit(20000)->pluck('occurred_at')
            ->each(function ($at) use (&$byHour, &$sample): void {
                $byHour[(int) Carbon::parse($at)->setTimezone('Europe/Bucharest')->format('G')]++;
                $sample++;
            });
        $reliable = $sample >= self::MIN_SAMPLE;
        $hour = 10;
        if ($reliable) {
            // fereastră de o oră + vecinii (netezire), doar între 7 și 21: nu trimitem campanii noaptea
            $best = -1;
            for ($h = 7; $h <= 21; $h++) {
                $score = 2 * $byHour[$h] + ($byHour[$h - 1] ?? 0) + ($byHour[$h + 1] ?? 0);
                if ($score > $best) {
                    [$best, $hour] = [$score, $h];
                }
            }
        }
        $next = now()->setTimezone('Europe/Bucharest')->setTime($hour, 0);
        if ($next->lte(now()->setTimezone('Europe/Bucharest')->addMinutes(15))) {
            $next = $next->addDay();
        }

        return ['hour' => $hour, 'sample' => $sample, 'reliable' => $reliable, 'by_hour' => $byHour, 'next' => $next->format('Y-m-d\TH:i')];
    }
}
