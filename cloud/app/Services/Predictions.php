<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contact;
use App\Models\ContactEvent;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Predicții pe fiecare client (calculate zilnic, din comenzile din magazin):
 * - valoarea estimată pe viață (CLV) = ce a cheltuit + comenzile așteptate în următoarele 12 luni × valoarea medie a comenzii;
 * - data probabilă a următoarei comenzi = ultima comandă + intervalul lui obișnuit (sau al clienților firmei care revin);
 * - riscul de pierdere: mic / mediu / mare, după cât a trecut de la ultima comandă față de intervalul obișnuit.
 * Sunt estimări statistice simple, explicate în profil, nu promisiuni.
 */
final class Predictions
{
    public const RISKS = ['low' => 'mic', 'medium' => 'mediu', 'high' => 'mare'];

    public const DEFAULT_INTERVAL_DAYS = 120;

    /** Se apelează în contextul firmei. Întoarce câte contacte au fost actualizate. */
    public function refresh(): int
    {
        $orders = ContactEvent::query()->where('type', 'placed_order')->orderBy('occurred_at')->get(['contact_id', 'value', 'occurred_at'])->groupBy('contact_id');
        if ($orders->isEmpty()) {
            return 0;
        }
        // intervalul tipic între comenzi și cât de des revin clienții, la nivelul firmei
        $intervals = [];
        foreach ($orders as $list) {
            if ($list->count() >= 2) {
                $intervals[] = max(1, $list->first()->occurred_at->diffInDays($list->last()->occurred_at)) / ($list->count() - 1);
            }
        }
        sort($intervals);
        $firmInterval = $intervals ? max(7, $intervals[intdiv(count($intervals), 2)]) : self::DEFAULT_INTERVAL_DAYS;
        $repeatRate = count($intervals) / max(1, $orders->count());
        $updated = 0;
        foreach ($orders as $contactId => $list) {
            $prediction = self::predict($list->map(fn ($e) => ['value' => (float) $e->value, 'at' => $e->occurred_at])->all(), $firmInterval, $repeatRate);
            $updated += Contact::query()->whereKey($contactId)->update($prediction + ['predicted_at' => now()]);
        }
        // cine nu mai are comenzi (ex. comandă ștearsă) nu păstrează predicții vechi
        DB::table('contacts')->where('organization_id', app(TenantContext::class)->organization()->id)
            ->whereNotNull('predicted_at')->where('predicted_at', '<', now()->subMinute())
            ->update(['predicted_clv' => null, 'predicted_next_order_at' => null, 'churn_risk' => null, 'predicted_at' => null]);

        return $updated;
    }

    /**
     * @param  list<array{value: float, at: Carbon}>  $orders  în ordine cronologică
     * @return array{predicted_clv: float, predicted_next_order_at: ?Carbon, churn_risk: string}
     */
    public static function predict(array $orders, float $firmInterval, float $repeatRate): array
    {
        $n = count($orders);
        $spent = array_sum(array_column($orders, 'value'));
        $aov = $n ? $spent / $n : 0;
        $last = end($orders)['at'];
        if ($n >= 2) {
            $interval = max(1, $orders[0]['at']->diffInDays($last)) / ($n - 1);
            $expected = min(12, 365 / max(7, $interval));
        } else {
            $interval = $firmInterval;
            $expected = min(12, $repeatRate * 365 / max(7, $firmInterval)); // un singur client din câți revin
        }
        $since = $last->diffInDays(now());
        $ratio = $since / max(1, $interval);
        $risk = $ratio < 1 ? 'low' : ($ratio < 2 ? 'medium' : 'high');
        // cu cât riscul e mai mare, cu atât mai puține comenzi așteptate
        $expected *= ['low' => 1, 'medium' => 0.5, 'high' => 0.1][$risk];

        return [
            'predicted_clv' => round($spent + $expected * $aov, 2),
            'predicted_next_order_at' => $risk === 'high' ? null : $last->copy()->addDays((int) round($interval)),
            'churn_risk' => $risk,
        ];
    }
}
