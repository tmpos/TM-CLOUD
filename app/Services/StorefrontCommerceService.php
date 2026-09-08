<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Support;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class StorefrontCommerceService
{
    private const PROVIDERS = [
        'cash' => ['name' => 'Efectivo al retirar', 'order' => 10],
        'bank_transfer' => ['name' => 'Transferencia bancaria', 'order' => 20],
        'card_on_delivery' => ['name' => 'Tarjeta al recibir', 'order' => 30],
        'azul' => ['name' => 'Azul', 'order' => 40],
        'stripe' => ['name' => 'Tarjeta con Stripe', 'order' => 50],
        'paypal' => ['name' => 'PayPal', 'order' => 60],
    ];

    public function __construct(
        private PDO $db,
        private array $config,
        private CredentialCipher $cipher,
        private StorefrontService $storefronts,
        private LogService $logs,
    ) {
    }

    public function adminMethods(string $projectUid): array
    {
        $this->ensureMethods($projectUid);
        $stmt = $this->db->prepare('SELECT * FROM storefront_payment_methods WHERE project_uid=? ORDER BY sort_order,id');
        $stmt->execute([$projectUid]);
        return array_map(function (array $row): array {
            $row['public_config'] = $this->decode((string) ($row['public_config'] ?? ''));
            $row['credentials_configured'] = trim((string) ($row['credentials_encrypted'] ?? '')) !== '';
            unset($row['credentials_encrypted']);
            return $row;
        }, $stmt->fetchAll());
    }

    public function publicMethods(array $store): array
    {
        $this->ensureMethods((string) $store['project_uid']);
        $stmt = $this->db->prepare(
            'SELECT provider,display_name,test_mode,public_config,instructions
             FROM storefront_payment_methods WHERE project_uid=? AND enabled=1 ORDER BY sort_order,id'
        );
        $stmt->execute([$store['project_uid']]);
        return array_map(function (array $row): array {
            $row['public_config'] = $this->decode((string) ($row['public_config'] ?? ''));
            return $row;
        }, $stmt->fetchAll());
    }

    public function saveMethods(string $projectUid, array $input): void
    {
        $this->ensureMethods($projectUid);
        $payments = is_array($input['payments'] ?? null) ? $input['payments'] : [];
        $select = $this->db->prepare('SELECT credentials_encrypted FROM storefront_payment_methods WHERE project_uid=? AND provider=?');
        $update = $this->db->prepare(
            'UPDATE storefront_payment_methods
             SET display_name=?,enabled=?,test_mode=?,public_config=?,credentials_encrypted=?,instructions=?,updated_at=?
             WHERE project_uid=? AND provider=?'
        );
        foreach (self::PROVIDERS as $provider => $definition) {
            $values = is_array($payments[$provider] ?? null) ? $payments[$provider] : [];
            $select->execute([$projectUid, $provider]);
            $existing = (string) ($select->fetchColumn() ?: '');
            $credentials = $this->credentialsFromInput($provider, $values);
            $encrypted = $existing;
            if (!empty($values['clear_credentials'])) {
                $encrypted = '';
            } elseif ($credentials !== []) {
                $previous = [];
                if ($existing !== '') {
                    try {
                        $previous = $this->decode($this->cipher->decrypt($existing));
                    } catch (\Throwable) {
                        $previous = [];
                    }
                }
                $encrypted = $this->cipher->encrypt(Support::json(array_merge($previous, $credentials)));
            }
            $public = $this->publicConfigFromInput($provider, $values);
            $displayName = trim((string) ($values['display_name'] ?? $definition['name']));
            if ($displayName === '') {
                $displayName = $definition['name'];
            }
            $update->execute([
                mb_substr($displayName, 0, 80),
                isset($values['enabled']) ? 1 : 0,
                isset($values['test_mode']) ? 1 : 0,
                Support::json($public),
                $encrypted !== '' ? $encrypted : null,
                mb_substr(trim((string) ($values['instructions'] ?? '')), 0, 1000),
                Support::now(),
                $projectUid,
                $provider,
            ]);
        }
        $this->logs->write('storefront.payments.updated', $projectUid, 'storefront_payment_methods');
    }

    public function createOrder(array $store, array $input): array
    {
        $name = mb_substr(trim((string) ($input['customer_name'] ?? '')), 0, 120);
        $phone = mb_substr(trim((string) ($input['customer_phone'] ?? '')), 0, 40);
        $email = mb_strtolower(trim((string) ($input['customer_email'] ?? '')));
        if ($name === '' || $phone === '') {
            throw new InvalidArgumentException('Escribe tu nombre y teléfono.');
        }
        if (mb_strlen($email) > 160 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('El correo electrónico es obligatorio y debe ser válido.');
        }
        $delivery = (string) ($input['delivery_method'] ?? '');
        $deliveryAllowed = [
            'pickup' => (bool) $store['pickup_enabled'],
            'delivery' => (bool) $store['delivery_enabled'],
            'shipping' => (bool) $store['shipping_enabled'],
        ];
        if (!($deliveryAllowed[$delivery] ?? false)) {
            throw new InvalidArgumentException('Selecciona un método de entrega disponible.');
        }
        $address = mb_substr(trim((string) ($input['delivery_address'] ?? '')), 0, 300);
        if ($delivery !== 'pickup' && $address === '') {
            throw new InvalidArgumentException('Escribe la dirección de entrega.');
        }
        $provider = (string) ($input['payment_provider'] ?? '');
        $method = $this->method((string) $store['project_uid'], $provider, true);
        $cart = is_array($input['items'] ?? null) ? array_slice($input['items'], 0, 50) : [];
        if ($cart === []) {
            throw new InvalidArgumentException('El carrito está vacío.');
        }
        $items = [];
        $subtotal = 0.0;
        foreach ($cart as $requested) {
            if (!is_array($requested)) {
                continue;
            }
            $quantity = max(1, min(99, (int) ($requested['quantity'] ?? 0)));
            $detail = $this->storefronts->productDetail($store, (string) ($requested['uid'] ?? ''));
            $product = $detail['product'];
            $sourceTable = (string) $detail['table'];
            if (!$product['available']) {
                throw new InvalidArgumentException($product['name'] . ' no tiene existencia disponible.');
            }
            if ($product['stock'] !== null && $quantity > (float) $product['stock']) {
                throw new InvalidArgumentException('Solo hay ' . (int) $product['stock'] . ' unidades de ' . $product['name'] . '.');
            }
            if ($product['price'] === null || (float) $product['price'] < 0) {
                throw new InvalidArgumentException('El precio de ' . $product['name'] . ' debe confirmarse con la tienda.');
            }
            $unitPrice = round((float) $product['price'], 2);
            $lineTotal = round($unitPrice * $quantity, 2);
            $subtotal += $lineTotal;
            $items[] = compact('product', 'sourceTable', 'quantity', 'unitPrice', 'lineTotal');
        }
        if ($items === []) {
            throw new InvalidArgumentException('El carrito está vacío.');
        }
        $subtotal = round($subtotal, 2);
        $shipping = 0.0;
        if ($delivery === 'shipping') {
            $threshold = (float) $store['free_shipping_threshold'];
            $shipping = $threshold > 0 && $subtotal >= $threshold ? 0.0 : round((float) $store['flat_shipping_cost'], 2);
        }
        $total = round($subtotal + $shipping, 2);
        $uid = Support::uid('ord_');
        $number = 'WEB-' . gmdate('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $now = Support::now();
        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                'INSERT INTO storefront_orders
                 (uid,order_number,project_uid,storefront_uid,customer_name,customer_email,customer_phone,customer_document,
                  delivery_method,delivery_address,delivery_city,customer_notes,payment_provider,currency,subtotal,shipping_total,total,
                  status,payment_status,created_at,updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $uid, $number, $store['project_uid'], $store['uid'], $name, $email ?: null, $phone,
                mb_substr(trim((string) ($input['customer_document'] ?? '')), 0, 50) ?: null,
                $delivery, $address ?: null, mb_substr(trim((string) ($input['delivery_city'] ?? '')), 0, 100) ?: null,
                mb_substr(trim((string) ($input['customer_notes'] ?? '')), 0, 600) ?: null,
                $provider, $store['currency'], $subtotal, $shipping, $total, 'pending', 'pending', $now, $now,
            ]);
            $insert = $this->db->prepare(
                'INSERT INTO storefront_order_items
                 (uid,order_uid,product_uid,product_name,product_sku,product_image,unit_price,quantity,line_total,metadata,created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)'
            );
            foreach ($items as $item) {
                $product = $item['product'];
                $insert->execute([
                    Support::uid('itm_'), $uid, $product['uid'], $product['name'], $product['sku'] ?: null,
                    $product['image'] ?: null, $item['unitPrice'], $item['quantity'], $item['lineTotal'],
                    Support::json([
                        'kind' => $product['kind'] ?? 'product',
                        'source_table' => $item['sourceTable'],
                    ]),
                    $now,
                ]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        $this->logs->write('storefront.order.created', (string) $store['project_uid'], 'storefront_orders', $uid, null, [
            'order_number' => $number, 'payment_provider' => $method['provider'], 'total' => $total,
        ]);
        return $this->order($uid, (string) $store['uid']);
    }

    public function beginPayment(array $store, array $order): array
    {
        $provider = (string) $order['payment_provider'];
        $method = $this->method((string) $store['project_uid'], $provider, true);
        if (in_array($provider, ['cash', 'bank_transfer', 'card_on_delivery'], true)) {
            $this->setPayment($order['uid'], 'awaiting_payment');
            return ['type' => 'confirmation', 'url' => $this->orderUrl($store, $order)];
        }
        if ($provider === 'stripe') {
            return ['type' => 'redirect', 'url' => $this->createStripeSession($store, $order, $method)];
        }
        if ($provider === 'paypal') {
            return ['type' => 'redirect', 'url' => $this->createPayPalOrder($store, $order, $method)];
        }
        if ($provider === 'azul') {
            $config = $this->decode((string) ($method['public_config'] ?? ''));
            $url = trim((string) ($config['payment_url'] ?? ''));
            if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
                throw new RuntimeException('Azul no tiene un enlace de pago válido configurado.');
            }
            $separator = str_contains($url, '?') ? '&' : '?';
            $redirect = $url . $separator . http_build_query([
                'order' => $order['order_number'],
                'amount' => number_format((float) $order['total'], 2, '.', ''),
                'currency' => $order['currency'],
                'return_url' => $this->absoluteOrderUrl($store, $order),
            ]);
            $this->setPayment($order['uid'], 'pending_external', null, ['redirect' => $url]);
            return ['type' => 'redirect', 'url' => $redirect];
        }
        throw new RuntimeException('Método de pago no soportado.');
    }

    public function completeReturn(array $store, array $order, array $query): array
    {
        if ($order['payment_provider'] === 'stripe' && isset($query['session_id'])) {
            $method = $this->method((string) $store['project_uid'], 'stripe', true);
            $session = $this->requestJson(
                'GET',
                'https://api.stripe.com/v1/checkout/sessions/' . rawurlencode((string) $query['session_id']),
                [],
                ['Authorization: Bearer ' . $this->credentials($method)['secret_key']]
            );
            if (($session['payment_status'] ?? '') === 'paid'
                && ($session['metadata']['order_uid'] ?? '') === $order['uid']
                && (int) ($session['amount_total'] ?? -1) === (int) round((float) $order['total'] * 100)) {
                $this->setPayment($order['uid'], 'paid', (string) ($session['id'] ?? ''), $session, true);
            }
        } elseif ($order['payment_provider'] === 'paypal' && isset($query['token'])) {
            $method = $this->method((string) $store['project_uid'], 'paypal', true);
            $credentials = $this->credentials($method);
            $base = (int) $method['test_mode'] ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
            $access = $this->paypalAccessToken($base, $credentials);
            $capture = $this->requestJson(
                'POST',
                $base . '/v2/checkout/orders/' . rawurlencode((string) $query['token']) . '/capture',
                new \stdClass(),
                ['Authorization: Bearer ' . $access, 'Content-Type: application/json']
            );
            $captured = $capture['purchase_units'][0]['payments']['captures'][0]['amount'] ?? [];
            if (($capture['status'] ?? '') === 'COMPLETED'
                && (string) ($capture['id'] ?? '') === (string) $order['provider_reference']
                && strtoupper((string) ($captured['currency_code'] ?? '')) === strtoupper((string) $order['currency'])
                && abs((float) ($captured['value'] ?? -1) - (float) $order['total']) < 0.01) {
                $this->setPayment($order['uid'], 'paid', (string) $capture['id'], $capture, true);
            }
        }
        return $this->order((string) $order['uid'], (string) $store['uid']);
    }

    public function order(string $uid, string $storefrontUid): array
    {
        $stmt = $this->db->prepare('SELECT * FROM storefront_orders WHERE uid=? AND storefront_uid=? LIMIT 1');
        $stmt->execute([$uid, $storefrontUid]);
        $order = $stmt->fetch();
        if (!$order) {
            throw new RuntimeException('Pedido no disponible.', 404);
        }
        $items = $this->db->prepare('SELECT * FROM storefront_order_items WHERE order_uid=? ORDER BY id');
        $items->execute([$uid]);
        $order['items'] = $items->fetchAll();
        return $order;
    }

    public function recentOrders(string $projectUid, int $limit = 20): array
    {
        $stmt = $this->db->prepare('SELECT * FROM storefront_orders WHERE project_uid=? ORDER BY id DESC LIMIT ?');
        $stmt->bindValue(1, $projectUid);
        $stmt->bindValue(2, max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function ensureMethods(string $projectUid): void
    {
        $insert = $this->db->prepare(
            'INSERT OR IGNORE INTO storefront_payment_methods
             (uid,project_uid,provider,display_name,enabled,test_mode,public_config,sort_order,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        );
        $now = Support::now();
        foreach (self::PROVIDERS as $provider => $definition) {
            $insert->execute([
                Support::uid('pay_'), $projectUid, $provider, $definition['name'],
                $provider === 'cash' ? 1 : 0, 1, '{}', $definition['order'], $now, $now,
            ]);
        }
    }

    private function method(string $projectUid, string $provider, bool $enabled): array
    {
        if (!isset(self::PROVIDERS[$provider])) {
            throw new InvalidArgumentException('Selecciona un método de pago válido.');
        }
        $this->ensureMethods($projectUid);
        $sql = 'SELECT * FROM storefront_payment_methods WHERE project_uid=? AND provider=?';
        if ($enabled) {
            $sql .= ' AND enabled=1';
        }
        $stmt = $this->db->prepare($sql . ' LIMIT 1');
        $stmt->execute([$projectUid, $provider]);
        $method = $stmt->fetch();
        if (!$method) {
            throw new InvalidArgumentException('El método de pago seleccionado no está disponible.');
        }
        return $method;
    }

    private function publicConfigFromInput(string $provider, array $input): array
    {
        return match ($provider) {
            'stripe' => ['publishable_key' => mb_substr(trim((string) ($input['publishable_key'] ?? '')), 0, 220)],
            'paypal' => ['client_id' => mb_substr(trim((string) ($input['client_id'] ?? '')), 0, 220)],
            'azul' => [
                'merchant_id' => mb_substr(trim((string) ($input['merchant_id'] ?? '')), 0, 120),
                'payment_url' => $this->url((string) ($input['payment_url'] ?? '')),
            ],
            default => [],
        };
    }

    private function credentialsFromInput(string $provider, array $input): array
    {
        $values = match ($provider) {
            'stripe' => ['secret_key' => trim((string) ($input['secret_key'] ?? ''))],
            'paypal' => [
                'client_id' => trim((string) ($input['client_id'] ?? '')),
                'client_secret' => trim((string) ($input['client_secret'] ?? '')),
            ],
            'azul' => ['auth_key' => trim((string) ($input['auth_key'] ?? ''))],
            default => [],
        };
        return array_filter($values, static fn (string $value): bool => $value !== '');
    }

    private function credentials(array $method): array
    {
        $encrypted = trim((string) ($method['credentials_encrypted'] ?? ''));
        if ($encrypted === '') {
            throw new RuntimeException($method['display_name'] . ' no tiene sus credenciales configuradas.');
        }
        return $this->decode($this->cipher->decrypt($encrypted));
    }

    private function createStripeSession(array $store, array $order, array $method): string
    {
        $credentials = $this->credentials($method);
        $data = [
            'mode' => 'payment',
            'success_url' => $this->absoluteOrderUrl($store, $order) . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => rtrim((string) $this->config['url'], '/') . '/store/' . rawurlencode($store['slug']) . '/checkout?cancelled=1',
            'client_reference_id' => $order['uid'],
            'customer_email' => $order['customer_email'] ?: null,
            'metadata[order_uid]' => $order['uid'],
            'line_items[0][price_data][currency]' => strtolower((string) $order['currency']),
            'line_items[0][price_data][product_data][name]' => 'Pedido ' . $order['order_number'] . ' — ' . $store['store_name'],
            'line_items[0][price_data][unit_amount]' => (int) round((float) $order['total'] * 100),
            'line_items[0][quantity]' => 1,
        ];
        $data = array_filter($data, static fn (mixed $value): bool => $value !== null);
        $session = $this->requestJson(
            'POST',
            'https://api.stripe.com/v1/checkout/sessions',
            $data,
            ['Authorization: Bearer ' . ($credentials['secret_key'] ?? ''), 'Content-Type: application/x-www-form-urlencoded'],
            true
        );
        if (!isset($session['id'], $session['url'])) {
            throw new RuntimeException('Stripe no devolvió una sesión de pago válida.');
        }
        $this->setPayment($order['uid'], 'pending_external', (string) $session['id'], $session);
        return (string) $session['url'];
    }

    private function createPayPalOrder(array $store, array $order, array $method): string
    {
        $credentials = $this->credentials($method);
        $base = (int) $method['test_mode'] ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        $access = $this->paypalAccessToken($base, $credentials);
        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $order['uid'],
                'description' => 'Pedido ' . $order['order_number'],
                'amount' => ['currency_code' => $order['currency'], 'value' => number_format((float) $order['total'], 2, '.', '')],
            ]],
            'payment_source' => ['paypal' => ['experience_context' => [
                'brand_name' => $store['store_name'],
                'return_url' => $this->absoluteOrderUrl($store, $order),
                'cancel_url' => rtrim((string) $this->config['url'], '/') . '/store/' . rawurlencode($store['slug']) . '/checkout?cancelled=1',
                'user_action' => 'PAY_NOW',
            ]]],
        ];
        $created = $this->requestJson(
            'POST',
            $base . '/v2/checkout/orders',
            $payload,
            ['Authorization: Bearer ' . $access, 'Content-Type: application/json', 'PayPal-Request-Id: ' . $order['uid']]
        );
        $approve = '';
        foreach (($created['links'] ?? []) as $link) {
            if (($link['rel'] ?? '') === 'payer-action' || ($link['rel'] ?? '') === 'approve') {
                $approve = (string) ($link['href'] ?? '');
                break;
            }
        }
        if (!isset($created['id']) || $approve === '') {
            throw new RuntimeException('PayPal no devolvió una orden de pago válida.');
        }
        $this->setPayment($order['uid'], 'pending_external', (string) $created['id'], $created);
        return $approve;
    }

    private function paypalAccessToken(string $base, array $credentials): string
    {
        $clientId = (string) ($credentials['client_id'] ?? '');
        $secret = (string) ($credentials['client_secret'] ?? '');
        // Older saved configurations may keep the public client ID outside the encrypted payload.
        if ($clientId === '') {
            throw new RuntimeException('PayPal necesita guardar nuevamente su Client ID y secreto.');
        }
        $result = $this->requestJson(
            'POST',
            $base . '/v1/oauth2/token',
            ['grant_type' => 'client_credentials'],
            ['Authorization: Basic ' . base64_encode($clientId . ':' . $secret), 'Content-Type: application/x-www-form-urlencoded'],
            true
        );
        if (empty($result['access_token'])) {
            throw new RuntimeException('No fue posible autenticar con PayPal.');
        }
        return (string) $result['access_token'];
    }

    private function setPayment(string $orderUid, string $status, ?string $reference = null, ?array $payload = null, bool $paid = false): void
    {
        $stmt = $this->db->prepare(
            'UPDATE storefront_orders SET payment_status=?,provider_reference=COALESCE(?,provider_reference),
             provider_payload=COALESCE(?,provider_payload),paid_at=CASE WHEN ?=1 THEN ? ELSE paid_at END,
             status=CASE WHEN ?=1 THEN ? ELSE status END,updated_at=? WHERE uid=?'
        );
        $stmt->execute([
            $status, $reference, $payload === null ? null : Support::json($payload), $paid ? 1 : 0, Support::now(),
            $paid ? 1 : 0, $paid ? 'confirmed' : 'pending', Support::now(), $orderUid,
        ]);
    }

    private function orderUrl(array $store, array $order): string
    {
        return '/store/' . rawurlencode((string) $store['slug']) . '/orders/' . rawurlencode((string) $order['uid']);
    }

    private function absoluteOrderUrl(array $store, array $order): string
    {
        return rtrim((string) $this->config['url'], '/') . $this->orderUrl($store, $order);
    }

    private function requestJson(string $method, string $url, array|\stdClass $data, array $headers, bool $form = false): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('La extensión cURL de PHP es necesaria para pagos en línea.');
        }
        $curl = curl_init($url);
        $body = $form ? http_build_query($data) : Support::json($data);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
            CURLOPT_POSTFIELDS => $method === 'GET' ? null : $body,
        ]);
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        $decoded = is_string($response) ? json_decode($response, true) : null;
        if ($response === false || $status < 200 || $status >= 300 || !is_array($decoded)) {
            $message = is_array($decoded)
                ? (string) ($decoded['error']['message'] ?? $decoded['message'] ?? 'Respuesta inválida del proveedor.')
                : ($error ?: 'No fue posible conectar con el proveedor de pago.');
            throw new RuntimeException(mb_substr($message, 0, 300));
        }
        return $decoded;
    }

    private function decode(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function url(string $value): string
    {
        $value = trim($value);
        return $value === '' || !filter_var($value, FILTER_VALIDATE_URL) ? '' : mb_substr($value, 0, 500);
    }
}
