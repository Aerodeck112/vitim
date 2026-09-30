<?php
declare(strict_types=1);

namespace App\Core;

/**
 * BT iPay (Banca Transilvania) – plăți online cu cardul.
 *
 * Fluxul:
 *  1. register.do (sau registerPreAuth.do în modul „două etape”) → primim orderId + formUrl
 *  2. clientul plătește în pagina băncii (3D Secure) și revine la returnUrl?orderId=…
 *  3. getOrderStatusExtended.do → aflăm rezultatul real (nu ne bazăm niciodată pe parametrii din URL)
 *  4. din panou: deposit.do (încasare preautorizare), reverse.do (anulare), refund.do (rambursare)
 *
 * Banca poate apela și un URL de notificare (callback), setat de BT la cererea comerciantului:
 * {site}/plata/bt/callback?t=<token> – vezi Setări → Plăți.
 */
final class BtIpay
{
    public const CURRENCY_RON = '946';
    public const COUNTRY_RO = 642;

    public const STATUS_LABELS = [
        0 => 'Înregistrată, neplătită',
        1 => 'Preautorizată (sumă blocată pe card)',
        2 => 'Plătită (încasată)',
        3 => 'Autorizare anulată',
        4 => 'Rambursată integral',
        5 => 'Autentificare 3D Secure în curs',
        6 => 'Respinsă',
        7 => 'Rambursată parțial',
    ];

    /** Mesaje prietenoase pentru cele mai frecvente coduri de refuz (actionCode). */
    private const ACTION_CODES = [
        0 => 'Plata a fost aprobată.',
        104 => 'Card restricționat. Te rugăm să folosești alt card sau să contactezi banca emitentă.',
        320 => 'Cardul nu este activ. Contactează banca emitentă.',
        341 => 'Autentificarea 3D Secure nu a reușit. Încearcă din nou.',
        357 => 'Autentificarea 3D Secure nu a reușit. Încearcă din nou.',
        801 => 'Banca emitentă nu răspunde. Încearcă din nou peste câteva minute.',
        803 => 'Cardul este blocat. Contactează banca emitentă.',
        804 => 'Tranzacția nu este permisă de banca emitentă. Contactează banca sau folosește alt card.',
        805 => 'Tranzacția a fost respinsă de banca emitentă.',
        861 => 'Data de expirare a cardului este greșită.',
        871 => 'Codul CVV este greșit.',
        905 => 'Card invalid.',
        906 => 'Cardul este expirat.',
        913 => 'Tranzacție invalidă. Contactează banca emitentă.',
        914 => 'Cont invalid. Contactează banca emitentă.',
        915 => 'Fonduri insuficiente pe card.',
        917 => 'A fost depășită limita de tranzacționare a cardului.',
        952 => 'Tranzacția a fost respinsă de banca emitentă.',
        998 => 'Plata nu a putut fi procesată. Încearcă din nou.',
        -2007 => 'Timpul pentru plată a expirat. Încearcă din nou.',
        -2006 => 'Tranzacția a fost respinsă de banca emitentă.',
        -2011 => 'Autentificarea 3D Secure nu a reușit.',
        -2015 => 'Tranzacția a fost respinsă.',
        -2019 => 'Tranzacția a fost respinsă de sistemul antifraudă al băncii emitente.',
    ];

    public static function mode(): string
    {
        return Settings::get('bt_mode', 'test') === 'live' ? 'live' : 'test';
    }

    /** @return array{url: string, user: string, pass: string} */
    public static function credentials(): array
    {
        $live = self::mode() === 'live';
        return [
            'url' => rtrim((string)Settings::get($live ? 'bt_url_live' : 'bt_url_test', $live ? 'https://ecclients.btrl.ro' : 'https://ecclients-sandbox.btrl.ro'), '/'),
            'user' => (string)Settings::get($live ? 'bt_user' : 'bt_user_test'),
            'pass' => (string)Settings::get($live ? 'bt_pass' : 'bt_pass_test'),
        ];
    }

    public static function configured(): bool
    {
        $c = self::credentials();
        return $c['user'] !== '' && $c['pass'] !== '';
    }

    public static function twoPhase(): bool
    {
        return Settings::get('bt_two_phase') === '1';
    }

    /**
     * Apel către API-ul BT (POST application/x-www-form-urlencoded, răspuns JSON).
     * @return array{0: ?array, 1: string} [răspuns, eroare]
     */
    public static function call(string $endpoint, array $params): array
    {
        $c = self::credentials();
        if ($c['user'] === '' || $c['pass'] === '') {
            return [null, 'BT iPay nu este configurat (lipsesc utilizatorul și parola API pentru modul ' . (self::mode() === 'live' ? 'producție' : 'test') . ').'];
        }
        $params = ['userName' => $c['user'], 'password' => $c['pass']] + $params;
        $url = $c['url'] . '/payment/rest/' . $endpoint;
        $body = http_build_query($params, '', '&', PHP_QUERY_RFC1738);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 40,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
                CURLOPT_USERAGENT => 'BunatatiDeLaMichele/' . APP_VERSION,
            ]);
            $raw = curl_exec($ch);
            $err = curl_error($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } else {
            $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n", 'content' => $body, 'timeout' => 40, 'ignore_errors' => true]]);
            $raw = @file_get_contents($url, false, $ctx);
            $err = $raw === false ? 'conexiune eșuată' : '';
            $code = 200;
            foreach ($http_response_header ?? [] as $h) {
                if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) {
                    $code = (int)$m[1];
                }
            }
        }
        if ($raw === false || $raw === '' || $raw === null) {
            log_error("BT iPay $endpoint: fără răspuns ($err)");
            return [null, 'Nu am putut contacta serverul BT iPay' . ($err ? " ($err)" : '') . '.'];
        }
        $json = json_decode((string)$raw, true);
        if (!is_array($json)) {
            log_error("BT iPay $endpoint: răspuns invalid HTTP $code: " . mb_substr((string)$raw, 0, 300));
            return [null, "Răspuns neașteptat de la BT iPay (HTTP $code)."];
        }
        return [$json, ''];
    }

    private static function errorOf(array $r): string
    {
        $code = (string)($r['errorCode'] ?? '0');
        if ($code !== '0' && $code !== '') {
            return trim((string)($r['errorMessage'] ?? 'Eroare BT iPay')) . " (cod $code)";
        }
        return '';
    }

    /** Număr de telefon în formatul cerut de BT: doar cifre, cu prefixul de țară (ex. 40740123456). */
    public static function phone(string $phone): string
    {
        $p = (string)preg_replace('/\D+/', '', $phone);
        if (str_starts_with($p, '00')) {
            $p = substr($p, 2);
        }
        if (str_starts_with($p, '0')) {
            $p = '4' . $p;
        }
        return substr($p, 0, 15);
    }

    private static function ascii(string $s, int $max): string
    {
        $s = strtr($s, ['ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't', 'Ă' => 'A', 'Â' => 'A', 'Î' => 'I', 'Ș' => 'S', 'Ş' => 'S', 'Ț' => 'T', 'Ţ' => 'T']);
        if (function_exists('transliterator_transliterate')) {
            $s = (string)transliterator_transliterate('Any-Latin; Latin-ASCII', $s);
        }
        $s = trim((string)preg_replace('/[^\x20-\x7E]/', '', $s));
        return mb_substr($s, 0, $max);
    }

    /**
     * Înregistrează plata la BT și întoarce adresa paginii de plată.
     * @return array{0: ?string, 1: string} [formUrl, eroare]
     */
    public static function register(array $order): array
    {
        $attempt = (int)$order['bt_attempts'] + 1;
        // BT cere un număr de comandă unic la fiecare înregistrare: la reîncercări adăugăm un sufix
        $orderNumber = $order['number'] . ($attempt > 1 ? '-' . $attempt : '');
        $shipSame = (int)$order['ship_same'] === 1;
        $billing = [
            'country' => self::COUNTRY_RO,
            'city' => self::ascii((string)$order['billing_city'], 40),
            'postAddress' => self::ascii((string)$order['billing_address'], 50),
        ];
        if ($order['billing_postcode']) {
            $billing['postalCode'] = self::ascii((string)$order['billing_postcode'], 9);
        }
        $delivery = [
            'deliveryType' => $order['shipping_method'] === 'ridicare' ? 'pickup' : 'delivery',
            'country' => self::COUNTRY_RO,
            'city' => self::ascii((string)($shipSame ? $order['billing_city'] : $order['shipping_city']), 40),
            'postAddress' => self::ascii((string)($shipSame ? $order['billing_address'] : $order['shipping_address']), 50),
        ];
        $bundle = [
            'orderCreationDate' => date('Y-m-d', (int)strtotime($order['created_at'] . ' UTC')),
            'customerDetails' => [
                'email' => (string)$order['email'],
                'phone' => self::phone((string)$order['phone']),
                'contact' => self::ascii(trim($order['first_name'] . ' ' . $order['last_name']), 40),
                'deliveryInfo' => $delivery,
                'billingInfo' => $billing,
            ],
        ];
        $desc = strtr((string)Settings::get('bt_description', 'Comanda {{numar}}'), ['{{numar}}' => $order['number'], '{{brand}}' => (string)Settings::get('brand_name')]);
        $params = [
            'orderNumber' => $orderNumber,
            'amount' => (string)(int)round((float)$order['total'] * 100),
            'currency' => self::CURRENCY_RON,
            'returnUrl' => abs_url('/plata/bt/retur'),
            'description' => self::ascii($desc, 99),
            'language' => 'ro',
            'email' => (string)$order['email'],
            'orderBundle' => json_encode($bundle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'jsonParams' => json_encode(['FORCE_3DS2' => 'true']),
        ];
        [$r, $err] = self::call(self::twoPhase() ? 'registerPreAuth.do' : 'register.do', $params);
        DB::update('orders', ['bt_attempts' => $attempt, 'updated_at' => DB::now()], 'id = :id', ['id' => $order['id']]);
        if (!$r) {
            Orders::event((int)$order['id'], 'plata', 'Înregistrarea plății la BT iPay a eșuat: ' . $err);
            return [null, $err];
        }
        if ($e = self::errorOf($r)) {
            Orders::event((int)$order['id'], 'plata', 'BT iPay a refuzat înregistrarea plății: ' . $e, ['orderNumber' => $orderNumber]);
            log_error('BT iPay register ' . $orderNumber . ': ' . $e);
            return [null, $e];
        }
        if (empty($r['orderId']) || empty($r['formUrl'])) {
            return [null, 'Răspuns incomplet de la BT iPay.'];
        }
        DB::update('orders', ['bt_order_id' => (string)$r['orderId'], 'bt_status' => 0, 'updated_at' => DB::now()], 'id = :id', ['id' => $order['id']]);
        Orders::event((int)$order['id'], 'plata', 'Clientul a fost trimis la pagina de plată BT iPay (încercarea ' . $attempt . ', ' . (self::mode() === 'live' ? 'producție' : 'TEST') . ').', ['orderId' => $r['orderId'], 'orderNumber' => $orderNumber]);
        return [(string)$r['formUrl'], ''];
    }

    /** @return array{0: ?array, 1: string} */
    public static function status(string $btOrderId): array
    {
        [$r, $err] = self::call('getOrderStatusExtended.do', ['orderId' => $btOrderId, 'language' => 'ro']);
        if (!$r) {
            return [null, $err];
        }
        if (!isset($r['orderStatus']) && ($e = self::errorOf($r))) {
            return [null, $e];
        }
        return [$r, ''];
    }

    public static function actionMessage(?int $code, string $bankText = ''): string
    {
        if ($code !== null && isset(self::ACTION_CODES[$code])) {
            return self::ACTION_CODES[$code];
        }
        return $bankText !== '' ? $bankText : 'Plata nu a fost aprobată.';
    }

    /**
     * Citește starea plății de la BT și actualizează comanda.
     * @return array{0: bool, 1: string} [s-a putut verifica, mesaj]
     */
    public static function sync(array $order, string $source = 'retur'): array
    {
        if (empty($order['bt_order_id'])) {
            return [false, 'Comanda nu are o plată BT iPay înregistrată.'];
        }
        [$r, $err] = self::status((string)$order['bt_order_id']);
        if (!$r) {
            Orders::event((int)$order['id'], 'plata', 'Verificarea plății la BT iPay a eșuat: ' . $err);
            return [false, $err];
        }
        $st = (int)$r['orderStatus'];
        $action = isset($r['actionCode']) ? (int)$r['actionCode'] : null;
        $amt = $r['paymentAmountInfo'] ?? [];
        $upd = [
            'bt_status' => $st,
            'bt_action_code' => $action !== null ? (string)$action : null,
            'bt_message' => mb_substr((string)($r['actionCodeDescription'] ?? ''), 0, 250) ?: null,
            'bt_approved_amount' => round((float)($amt['approvedAmount'] ?? 0) / 100, 2),
            'bt_deposited_amount' => round((float)($amt['depositedAmount'] ?? 0) / 100, 2),
            'bt_refunded_amount' => round((float)($amt['refundedAmount'] ?? 0) / 100, 2),
            'updated_at' => DB::now(),
        ];
        if (!empty($r['cardAuthInfo']['pan'])) {
            $upd['bt_card'] = mb_substr((string)$r['cardAuthInfo']['pan'], 0, 30);
        } elseif (!empty($r['cardAuthInfo']['maskedPan'])) {
            $upd['bt_card'] = mb_substr((string)$r['cardAuthInfo']['maskedPan'], 0, 30);
        }
        $prevStatus = $order['bt_status'] !== null ? (int)$order['bt_status'] : null;
        DB::update('orders', $upd, 'id = :id', ['id' => $order['id']]);
        $order = array_merge($order, $upd);
        if ($prevStatus !== $st) {
            Orders::event((int)$order['id'], 'plata', 'BT iPay (' . $source . '): ' . (self::STATUS_LABELS[$st] ?? "stare $st") . ($action !== null && $action !== 0 ? ' – ' . self::actionMessage($action, (string)($r['actionCodeDescription'] ?? '')) . " (cod $action)" : ''), ['orderStatus' => $st, 'actionCode' => $action, 'amount' => $r['amount'] ?? null]);
        }

        switch ($st) {
            case 1:
                Orders::paymentReceived($order, 'autorizata');
                break;
            case 2:
                Orders::paymentReceived($order, 'platita');
                break;
            case 3:
                Orders::setPaymentStatus($order, 'anulata');
                break;
            case 4:
                Orders::setPaymentStatus($order, 'rambursata');
                break;
            case 7:
                Orders::setPaymentStatus($order, 'rambursata_partial');
                break;
            case 6:
                if (!in_array($order['payment_status'], ['platita', 'autorizata'], true)) {
                    Orders::setPaymentStatus($order, 'esuata');
                }
                break;
        }
        return [true, self::STATUS_LABELS[$st] ?? ''];
    }

    /** Încasează o plată preautorizată (modul în două etape). */
    public static function deposit(array $order, ?float $amount = null): array
    {
        $amount ??= (float)$order['bt_approved_amount'] ?: (float)$order['total'];
        [$r, $err] = self::call('deposit.do', ['orderId' => $order['bt_order_id'], 'amount' => (string)(int)round($amount * 100)]);
        return self::finishOp($order, $r, $err, 'Încasare ' . Shop::money($amount));
    }

    /** Anulează o preautorizare (suma se deblochează pe cardul clientului). */
    public static function reverse(array $order): array
    {
        [$r, $err] = self::call('reverse.do', ['orderId' => $order['bt_order_id']]);
        return self::finishOp($order, $r, $err, 'Anulare autorizare');
    }

    /** Rambursare totală sau parțială a unei plăți încasate. */
    public static function refund(array $order, float $amount): array
    {
        [$r, $err] = self::call('refund.do', ['orderId' => $order['bt_order_id'], 'amount' => (string)(int)round($amount * 100)]);
        return self::finishOp($order, $r, $err, 'Rambursare ' . Shop::money($amount));
    }

    private static function finishOp(array $order, ?array $r, string $err, string $what): array
    {
        if (!$r) {
            Orders::event((int)$order['id'], 'plata', "$what – eșuată: $err");
            return [false, $err];
        }
        if ($e = self::errorOf($r)) {
            Orders::event((int)$order['id'], 'plata', "$what – refuzată de BT: $e");
            return [false, $e];
        }
        Orders::event((int)$order['id'], 'plata', "$what – reușită.", ['user' => Auth::user()['email'] ?? null]);
        $fresh = DB::row('SELECT * FROM orders WHERE id = ?', [$order['id']]);
        self::sync($fresh, 'panou');
        return [true, "$what reușită."];
    }

    /** Verificarea sumei de control (checksum) trimise de BT în callback, dacă banca a configurat o cheie. */
    public static function verifyCallback(array $params): bool
    {
        $key = (string)Settings::get('bt_callback_key');
        if ($key === '') {
            return true; // fără cheie: oricum verificăm starea direct la BT, nu ne bazăm pe parametri
        }
        $checksum = strtoupper((string)($params['checksum'] ?? ''));
        unset($params['checksum'], $params['t']);
        ksort($params);
        $data = '';
        foreach ($params as $k => $v) {
            $data .= $k . ';' . $v . ';';
        }
        return $checksum !== '' && hash_equals(strtoupper(hash_hmac('sha256', $data, $key)), $checksum);
    }
}
