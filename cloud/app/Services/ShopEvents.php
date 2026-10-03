<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Channel;
use App\Enums\ConsentPurpose;
use App\Enums\ConsentStatus;
use App\Enums\ContactSource;
use App\Enums\IdentityType;
use App\Models\Contact;
use App\Models\ContactEvent;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Site;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Magazinul WooCommerce: evenimentele trimise semnat de plugin (produs văzut, adăugat în coș, comandă începută,
 * comandă plasată), catalogul de produse, identificarea vizitatorilor veniți din emailuri și atribuirea veniturilor.
 * Atribuire (ca în Klaviyo): comanda se atribuie ultimului email cu click în ultimele 5 zile, altfel ultimului deschis în 5 zile.
 */
final class ShopEvents
{
    public const TYPES = ['viewed_product', 'added_to_cart', 'started_checkout', 'placed_order'];

    public const ATTRIBUTION_DAYS = 5;

    public function __construct(
        private readonly ContactService $contacts,
        private readonly ContactActivity $activity,
        private readonly ConsentService $consents,
    ) {}

    /** Token-ul contactului pus în linkurile din emailuri către site-ul firmei (cookie „vitim_ct” pe site). */
    public static function token(Contact $contact): string
    {
        $base = $contact->organization_id.'.'.$contact->getKey();

        return $base.'.'.substr(hash_hmac('sha256', 'ct.'.$base, (string) config('app.key')), 0, 16);
    }

    /** Contactul din token, doar dacă aparține firmei site-ului (se apelează în contextul firmei). */
    public static function fromToken(string $token, Organization $organization): ?Contact
    {
        if (! preg_match('/^(\d+)\.(\d+)\.([a-f0-9]{16})$/', $token, $m) || (int) $m[1] !== $organization->getKey()) {
            return null;
        }
        if (! hash_equals(substr(hash_hmac('sha256', 'ct.'.$m[1].'.'.$m[2], (string) config('app.key')), 0, 16), $m[3])) {
            return null;
        }

        return Contact::query()->find((int) $m[2]);
    }

    /**
     * @param  list<array<string, mixed>>  $events  validate deja de controller
     * @return array{recorded: int, duplicates: int, skipped: int}
     */
    public function ingest(Site $site, array $events): array
    {
        $out = ['recorded' => 0, 'duplicates' => 0, 'skipped' => 0];
        foreach ($events as $e) {
            $uid = mb_substr((string) $e['id'], 0, 80);
            if (ContactEvent::query()->where('type', $e['type'])->where('data->uid', $uid)->exists()) {
                $out['duplicates']++;

                continue;
            }
            try {
                $contact = $this->contact($site, $e);
            } catch (DuplicateContactException) {
                $contact = null;
            }
            if (! $contact) {
                $out['skipped']++;

                continue;
            }
            $at = isset($e['occurred_at']) ? CarbonImmutable::createFromTimestamp((int) $e['occurred_at']) : null;
            $at = $at && $at->lte(now()->addMinutes(5)) ? $at : null;
            $data = array_filter([
                'uid' => $uid, 'site_id' => $site->id,
                'order_id' => $e['data']['order_id'] ?? null, 'currency' => $e['data']['currency'] ?? null,
                'product' => $e['data']['product'] ?? null, 'product_id' => $e['data']['product_id'] ?? null,
                'url' => $e['data']['url'] ?? null, 'image' => $e['data']['image'] ?? null, 'price' => $e['data']['price'] ?? null,
                'checkout_url' => $e['data']['checkout_url'] ?? null, 'coupon' => $e['data']['coupon'] ?? null,
                'items' => array_values(array_map(fn ($i) => array_filter([
                    'name' => mb_substr((string) ($i['name'] ?? ''), 0, 200), 'product_id' => isset($i['product_id']) ? (string) $i['product_id'] : null,
                    'qty' => (int) ($i['qty'] ?? 1), 'price' => isset($i['price']) ? (float) $i['price'] : null,
                    'url' => self::url($i['url'] ?? null), 'image' => self::url($i['image'] ?? null),
                ], fn ($v) => $v !== null && $v !== ''), array_slice((array) ($e['data']['items'] ?? []), 0, 50))) ?: null,
            ], fn ($v) => $v !== null && $v !== '');
            [$campaignId, $flowId] = $e['type'] === 'placed_order' ? $this->attribute($contact, $at ?? now(), $data) : [null, null];

            DB::transaction(function () use ($contact, $e, $data, $at, $campaignId, $flowId): void {
                $this->activity->record($contact, $e['type'], $data, isset($e['value']) ? round((float) $e['value'], 2) : null, $campaignId, $flowId, null, $at);
                if ($e['type'] === 'placed_order' && ($e['marketing_consent'] ?? false) === true
                    && $this->consents->current($contact, Channel::Email, ConsentPurpose::Marketing) !== ConsentStatus::Granted) {
                    $this->consents->record($contact, Channel::Email, ConsentPurpose::Marketing, ConsentStatus::Granted, 'checkout',
                        ['order_id' => (string) ($e['data']['order_id'] ?? ''), 'text' => mb_substr((string) ($e['consent_text'] ?? ''), 0, 200)]);
                }
            });
            $out['recorded']++;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $products
     * @return array{saved: int, deleted: int}
     */
    public function syncProducts(Site $site, array $products): array
    {
        $saved = $deleted = 0;
        foreach ($products as $p) {
            $id = (string) $p['id'];
            if (! empty($p['deleted'])) {
                $deleted += Product::query()->where('site_id', $site->id)->where('external_id', $id)->delete();

                continue;
            }
            Product::query()->updateOrCreate(['site_id' => $site->id, 'external_id' => $id], [
                'name' => mb_substr((string) $p['name'], 0, 200),
                'price' => isset($p['price']) && $p['price'] !== '' ? round((float) $p['price'], 2) : null,
                'currency' => strtoupper(mb_substr((string) ($p['currency'] ?? 'RON'), 0, 3)) ?: 'RON',
                'url' => self::url($p['url'] ?? null), 'image' => self::url($p['image'] ?? null),
                'categories' => array_values(array_slice(array_map(fn ($c) => mb_substr((string) $c, 0, 80), (array) ($p['categories'] ?? [])), 0, 10)) ?: null,
                'in_stock' => (bool) ($p['in_stock'] ?? true), 'synced_at' => now(),
            ]);
            $saved++;
        }

        return ['saved' => $saved, 'deleted' => $deleted];
    }

    /** Venitul atribuit unei campanii sau unui flux (comenzi plasate). */
    public static function revenue(?int $campaignId = null, ?int $flowId = null, ?CarbonImmutable $since = null): array
    {
        $q = ContactEvent::query()->where('type', 'placed_order')
            ->when($campaignId, fn ($q) => $q->where('campaign_id', $campaignId))
            ->when($flowId, fn ($q) => $q->where('flow_id', $flowId))
            ->when($since, fn ($q) => $q->where('occurred_at', '>=', $since));

        return ['orders' => (int) (clone $q)->count(), 'revenue' => round((float) (clone $q)->sum('value'), 2)];
    }

    /** @param array<string, mixed> $e */
    private function contact(Site $site, array $e): ?Contact
    {
        if (! empty($e['contact_token']) && ($contact = self::fromToken((string) $e['contact_token'], $site->organization))) {
            return $contact;
        }
        $email = IdentityNormalizer::email((string) ($e['email'] ?? ''));
        if ($email === null) {
            return null;
        }
        $phone = isset($e['phone']) ? IdentityNormalizer::phone((string) $e['phone']) : null;
        $contact = $this->contacts->findByIdentity(IdentityType::Email, $email);
        if ($phone && ($owner = $this->contacts->findByIdentity(IdentityType::Phone, $phone)) && $owner->id !== $contact?->id) {
            $phone = null; // numărul e deja al altui contact: nu unim profiluri pe baza telefonului din comandă
        }
        $names = array_filter(['first_name' => mb_substr(trim((string) ($e['first_name'] ?? '')), 0, 80) ?: null, 'last_name' => mb_substr(trim((string) ($e['last_name'] ?? '')), 0, 80) ?: null]);
        if (! $contact) {
            return $this->contacts->create(array_filter(['email' => $email, 'phone' => $phone] + $names), ContactSource::WooCommerce);
        }
        $missing = array_filter([
            'first_name' => ! $contact->first_name ? ($names['first_name'] ?? null) : null,
            'last_name' => ! $contact->last_name ? ($names['last_name'] ?? null) : null,
            'phone' => ! $contact->phone ? $phone : null,
        ]);

        return $missing ? $this->contacts->update($contact, $missing) : $contact;
    }

    /** @param array<string, mixed> $data @return array{0: ?int, 1: ?int} */
    private function attribute(Contact $contact, CarbonImmutable|Carbon $at, array &$data): array
    {
        foreach (['email_clicked' => 'click', 'email_opened' => 'open'] as $type => $how) {
            $event = ContactEvent::query()->where('contact_id', $contact->id)->where('type', $type)
                ->whereBetween('occurred_at', [$at->copy()->subDays(self::ATTRIBUTION_DAYS), $at])
                ->where(fn ($q) => $q->whereNotNull('campaign_id')->orWhereNotNull('flow_id'))
                ->latest('occurred_at')->latest('id')->first();
            if ($event) {
                $data['attributed'] = $how;

                return [$event->campaign_id, $event->flow_id];
            }
        }

        return [null, null];
    }

    private static function url(mixed $url): ?string
    {
        $url = trim((string) $url);

        return $url !== '' && mb_strlen($url) <= 500 && preg_match('#^https?://#i', $url) ? $url : null;
    }
}
