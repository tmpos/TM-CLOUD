<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Http;
use App\Services\ProjectService;
use App\Services\PdfService;
use App\Services\MailService;
use App\Services\StorefrontAdminService;
use App\Services\StorefrontService;
use Flight;

final class StorefrontAdminController
{
    public function __construct(
        private StorefrontAdminService $admins,
        private StorefrontService $storefronts,
        private ProjectService $projects,
        private PdfService $pdf,
        private MailService $mail,
    ) {
    }

    public function register(): void
    {
        Flight::route('GET /@store/admin', fn ($store) => $this->entry((string) $store));
        Flight::route('GET /@store/admin/', fn ($store) => $this->entry((string) $store));
        Flight::route('POST /@store/admin/login', fn ($store) => $this->login((string) $store));
        Flight::route('POST /@store/admin/logout', fn ($store) => $this->logout((string) $store));
        Flight::route('GET /@store/admin/dashboard', fn ($store) => $this->page((string) $store, 'dashboard'));
        Flight::route('GET /@store/admin/pos', fn ($store) => $this->page((string) $store, 'pos'));
        Flight::route('POST /@store/admin/pos/sales', fn ($store) => $this->posSale((string) $store));
        Flight::route('GET /@store/admin/orders', fn ($store) => $this->page((string) $store, 'orders'));
        Flight::route('GET /@store/admin/dispatched', fn ($store) => $this->page((string) $store, 'dispatched'));
        Flight::route('GET /@store/admin/delivered', fn ($store) => $this->page((string) $store, 'delivered'));
        Flight::route('GET /@store/admin/orders/@uid/pdf', fn ($store, $uid) => $this->orderPdf((string) $store, (string) $uid));
        Flight::route('POST /@store/admin/orders/@uid/email', fn ($store, $uid) => $this->orderEmail((string) $store, (string) $uid));
        Flight::route('POST /@store/admin/orders/@uid/status', fn ($store, $uid) => $this->orderStatus((string) $store, (string) $uid));
        Flight::route('GET /@store/admin/products', fn ($store) => $this->page((string) $store, 'products'));
        Flight::route('GET /@store/admin/customers', fn ($store) => $this->page((string) $store, 'customers'));
        Flight::route('GET /@store/admin/operations', fn ($store) => $this->page((string) $store, 'operations'));
        Flight::route('GET /@store/admin/web', fn ($store) => $this->page((string) $store, 'web'));

        // Rutas anteriores: solo redirigen al proyecto activo para no romper marcadores.
        Flight::route('GET /admin', fn () => $this->entry());
        Flight::route('GET /admin/', fn () => $this->entry());
        Flight::route('POST /admin/login', fn () => $this->login());
        foreach (['dashboard', 'pos', 'orders', 'dispatched', 'delivered', 'products', 'customers', 'operations', 'web'] as $section) {
            Flight::route('GET /admin/' . $section, fn () => $this->legacyRedirect($section));
        }

        Flight::route('POST /projects/@uid/storefront/admins', fn ($uid) => $this->provision((string) $uid));
    }

    private function entry(?string $storeSlug = null): void
    {
        if ($this->admins->check()) {
            try {
                $store = $this->admins->currentStore($storeSlug);
                Flight::redirect($this->base($store) . '/dashboard');
                return;
            } catch (\Throwable $e) {
                if ($storeSlug !== null) {
                    $this->renderLogin($e->getMessage(), '', $storeSlug);
                    return;
                }
            }
        }
        $this->renderLogin(null, '', $storeSlug);
    }

    private function login(?string $storeSlug = null): void
    {
        $input = Http::input();
        try {
            Csrf::verify($input['_csrf'] ?? null);
            if (!$this->admins->attempt(
                (string) ($input['email'] ?? ''),
                (string) ($input['password'] ?? ''),
                $storeSlug
            )) {
                throw new \RuntimeException('Correo o contraseña incorrectos para este proyecto.');
            }
            $store = $this->admins->currentStore($storeSlug);
            Flight::redirect($this->base($store) . '/dashboard');
        } catch (\Throwable $e) {
            $this->renderLogin($e->getMessage(), (string) ($input['email'] ?? ''), $storeSlug);
        }
    }

    private function logout(string $storeSlug): void
    {
        $input = Http::input();
        try {
            Csrf::verify($input['_csrf'] ?? null);
        } catch (\Throwable) {
        }
        $this->admins->logout();
        Flight::redirect('/' . rawurlencode($storeSlug) . '/admin/');
    }

    private function page(string $storeSlug, string $section): void
    {
        if (!$this->admins->check()) {
            Flight::redirect('/' . rawurlencode($storeSlug) . '/admin/');
            return;
        }
        try {
            $store = $this->admins->currentStore($storeSlug);
            $catalog = in_array($section, ['dashboard', 'pos', 'products', 'operations', 'web'], true)
                ? $this->storefronts->catalog($store)
                : ['products' => [], 'categories' => [], 'brands' => []];
            $orders = in_array($section, ['dashboard', 'orders', 'dispatched', 'delivered', 'operations'], true)
                ? $this->admins->orders($store, in_array($section, ['orders', 'dispatched', 'delivered'], true) ? 500 : 12)
                : [];
            if ($section === 'dispatched') {
                $orders = array_values(array_filter($orders, static fn (array $order): bool =>
                    (string) ($order['status'] ?? '') === 'dispatched'
                ));
            } elseif ($section === 'delivered') {
                $orders = array_values(array_filter($orders, static fn (array $order): bool =>
                    (string) ($order['status'] ?? '') === 'delivered'
                ));
            }
            $customers = $section === 'customers' ? $this->admins->customers($store, 250) : [];
            $this->render('storefront-admin', [
                'section' => $section,
                'store' => $store,
                'user' => $this->admins->user(),
                'memberships' => $this->admins->memberships(),
                'catalog' => $catalog,
                'orders' => $orders,
                'customers' => $customers,
                'metrics' => $this->admins->metrics($store),
                'flashes' => Http::flashes(),
                'adminBase' => $this->base($store),
            ]);
        } catch (\Throwable $e) {
            $this->renderLogin($e->getMessage(), '', $storeSlug);
        }
    }

    private function posSale(string $storeSlug): void
    {
        try {
            if (!$this->admins->check()) throw new \RuntimeException('Tu sesión expiró.', 401);
            $input = Http::input();
            Csrf::verify($input['_csrf'] ?? null);
            $store = $this->admins->currentStore($storeSlug);
            if (($store['admin_role'] ?? '') === 'kitchen') {
                throw new \RuntimeException('El rol de cocina no puede registrar ventas.', 403);
            }
            $sale = $this->admins->createPosSale($store, $input, $this->admins->user() ?? []);
            Flight::json(['data' => $sale], 201);
        } catch (\Throwable $e) {
            Http::error($e, in_array($e->getCode(), [401, 403, 422], true) ? $e->getCode() : 400);
        }
    }

    private function orderStatus(string $storeSlug, string $uid): void
    {
        if (!$this->admins->check()) {
            Flight::redirect('/' . rawurlencode($storeSlug) . '/admin/');
            return;
        }
        $input = Http::input();
        try {
            Csrf::verify($input['_csrf'] ?? null);
            $this->admins->updateOrderStatus(
                $this->admins->currentStore($storeSlug),
                $uid,
                (string) ($input['status'] ?? ''),
                $this->admins->user() ?? []
            );
            Http::flash('admin_success', 'Estado actualizado.');
        } catch (\Throwable $e) {
            Http::flash('admin_error', $e->getMessage());
        }
        Flight::redirect('/' . rawurlencode($storeSlug) . '/admin/orders');
    }

    private function orderPdf(string $storeSlug, string $uid): void
    {
        if (!$this->admins->check()) {
            Flight::redirect('/' . rawurlencode($storeSlug) . '/admin/');
            return;
        }

        try {
            $store = $this->admins->currentStore($storeSlug);
            $order = $this->admins->order($store, $uid);
            $project = $this->projects->find((string) $store['project_uid']);
            $document = $this->orderDocument($store, $order);
            $format = strtolower(trim((string) ($_GET['format'] ?? 'letter')));
            $content = $format === '80mm'
                ? $this->pdf->receipt($project, $document)
                : $this->pdf->invoice($project, 'storefront_orders', $document);
            $number = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($order['order_number'] ?? $uid)) ?: 'pedido';
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $number . ($format === '80mm' ? '-80mm' : '-carta') . '.pdf"');
            header('Content-Length: ' . strlen($content));
            header('Cache-Control: private, no-store, max-age=0');
            header('X-Robots-Tag: noindex, nofollow');
            echo $content;
        } catch (\Throwable $e) {
            Http::error($e, in_array((int) $e->getCode(), [401, 403, 404], true) ? (int) $e->getCode() : 500);
        }
    }

    private function orderEmail(string $storeSlug, string $uid): void
    {
        try {
            if (!$this->admins->check()) {
                throw new \RuntimeException('Tu sesión expiró.', 401);
            }
            $input = Http::input();
            Csrf::verify($input['_csrf'] ?? null);
            $store = $this->admins->currentStore($storeSlug);
            $order = $this->admins->order($store, $uid);
            $recipient = trim((string) ($input['email'] ?? $order['customer_email'] ?? ''));
            $project = $this->projects->find((string) $store['project_uid']);
            $document = $this->orderDocument($store, $order);
            $pdf = $this->pdf->invoice($project, 'storefront_orders', $document);
            $number = (string) ($order['order_number'] ?? $uid);
            $currency = (string) ($document['moneda'] ?? 'RD$');
            $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $rows = '';
            foreach ($document['productos'] as $item) {
                $rows .= '<tr><td style="padding:9px;border-bottom:1px solid #e2e8f0">' . $e($item['nombre'])
                    . '</td><td style="padding:9px;border-bottom:1px solid #e2e8f0;text-align:center">' . $e($item['cantidad'])
                    . '</td><td style="padding:9px;border-bottom:1px solid #e2e8f0;text-align:right">' . $e($currency)
                    . ' ' . number_format((float) $item['total'], 2, '.', ',') . '</td></tr>';
            }
            $html = '<!doctype html><html><body style="margin:0;background:#f1f5f9;padding:24px;font-family:Arial,sans-serif;color:#0f172a">'
                . '<div style="max-width:680px;margin:auto;background:#fff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden">'
                . '<div style="padding:26px;background:#0b3c46;color:#fff"><div style="font-size:12px;letter-spacing:2px;color:#99f6e4">COMPROBANTE DE COMPRA</div>'
                . '<h1 style="margin:7px 0 4px">Pedido ' . $e($number) . '</h1><div>' . $e($store['store_name']) . '</div></div>'
                . '<div style="padding:25px"><p>Hola <strong>' . $e($order['customer_name']) . '</strong>, adjuntamos el PDF de tu compra.</p>'
                . '<table style="width:100%;border-collapse:collapse"><thead><tr style="background:#f8fafc"><th style="padding:9px;text-align:left">Producto</th>'
                . '<th style="padding:9px">Cantidad</th><th style="padding:9px;text-align:right">Importe</th></tr></thead><tbody>' . $rows . '</tbody></table>'
                . '<div style="margin-top:18px;padding:16px;background:#ecfdf5;border-radius:10px;text-align:right;font-size:20px"><strong>Total: '
                . $e($currency) . ' ' . number_format((float) $order['total'], 2, '.', ',') . '</strong></div>'
                . '<p style="color:#64748b;font-size:12px">El documento PDF está adjunto a este correo. Conserva este mensaje para futuras referencias.</p></div></div></body></html>';
            $text = 'Pedido ' . $number . "\nCliente: " . (string) $order['customer_name']
                . "\nTotal: " . $currency . ' ' . number_format((float) $order['total'], 2, '.', ',')
                . "\nEl comprobante PDF está adjunto.";
            $filename = (preg_replace('/[^A-Za-z0-9_-]+/', '-', $number) ?: 'pedido') . '.pdf';
            $result = $this->mail->sendDocument(
                $recipient,
                'Pedido ' . $number . ' - ' . (string) $store['store_name'],
                $html,
                $text,
                $pdf,
                $filename
            );
            $this->admins->addEvent(
                $store,
                $uid,
                (string) (($this->admins->user() ?? [])['uid'] ?? ''),
                'email_sent',
                (string) ($order['status'] ?? ''),
                (string) ($order['status'] ?? ''),
                'Comprobante enviado por correo.',
                ['recipient' => $recipient, 'message_id' => $result['message_id'] ?? '']
            );
            Flight::json(['data' => $result, 'message' => 'Correo enviado correctamente.']);
        } catch (\Throwable $e) {
            Http::error($e, in_array((int) $e->getCode(), [400, 401, 403, 404, 422], true) ? (int) $e->getCode() : 500);
        }
    }

    private function orderDocument(array $store, array $order): array
    {
        $items = [];
        foreach ($order['items'] as $item) {
            $metadata = json_decode((string) ($item['metadata'] ?? ''), true);
            $metadata = is_array($metadata) ? $metadata : [];
            $identifiers = array_values(array_filter([
                trim((string) ($metadata['imei'] ?? '')) !== '' ? 'IMEI: ' . trim((string) $metadata['imei']) : '',
                trim((string) ($metadata['note'] ?? '')),
            ]));
            $items[] = [
                'nombre' => (string) ($item['product_name'] ?? 'Producto'),
                'codigo' => (string) ($item['product_sku'] ?? ''),
                'cantidad' => (float) ($item['quantity'] ?? 1),
                'precio_unitario' => (float) ($item['unit_price'] ?? 0),
                'total' => (float) ($item['line_total'] ?? 0),
                'notas' => implode(' · ', $identifiers),
            ];
        }
        $statusLabels = [
            'pending' => 'Pendiente',
            'confirmed' => 'Confirmado',
            'preparing' => 'En preparación',
            'ready' => 'Listo',
            'dispatched' => 'Despachado',
            'delivered' => 'Entregado',
            'completed' => 'Completado',
            'cancelled' => 'Cancelado',
        ];
        $currency = match (strtoupper((string) ($order['currency'] ?? 'DOP'))) {
            'USD' => 'US$',
            'EUR' => '€',
            default => 'RD$',
        };
        $notes = array_values(array_filter([
            trim((string) ($order['customer_notes'] ?? '')),
            trim((string) ($order['table_reference'] ?? '')) !== ''
                ? 'Mesa / referencia: ' . trim((string) $order['table_reference'])
                : '',
        ]));
        return [
            'uid' => (string) $order['uid'],
            'no_factura' => (string) ($order['order_number'] ?? $order['uid']),
            'created_at' => (string) ($order['created_at'] ?? ''),
            'nombre_cliente' => (string) ($order['customer_name'] ?? ''),
            'customer_document' => (string) ($order['customer_document'] ?? ''),
            'customer_phone' => (string) ($order['customer_phone'] ?? ''),
            'customer_email' => (string) ($order['customer_email'] ?? ''),
            'delivery_address' => (string) ($order['delivery_address'] ?? ''),
            'subtotal' => (float) ($order['subtotal'] ?? 0),
            'descuento_monto' => (float) ($order['discount_total'] ?? 0),
            'impuesto_monto' => (float) ($order['tax_total'] ?? 0),
            'envio_monto' => (float) ($order['shipping_total'] ?? 0),
            'total' => (float) ($order['total'] ?? 0),
            'metodo_pago' => (string) ($order['payment_provider'] ?? 'Pendiente'),
            'estado' => $statusLabels[(string) ($order['status'] ?? '')] ?? (string) ($order['status'] ?? 'Pendiente'),
            'notas' => implode("\n", $notes),
            'moneda' => $currency,
            'productos' => $items,
            'empresa_nombre' => (string) ($store['store_name'] ?? ''),
            'empresa_telefono' => (string) ($store['phone'] ?? ''),
            'empresa_email' => (string) ($store['email'] ?? ''),
            'empresa_direccion' => (string) ($store['address'] ?? ''),
            'documento_titulo' => 'Pedido',
            'documento_subtitulo' => 'Comprobante de pedido',
            'documento_numero_label' => 'Pedido No.',
        ];
    }

    private function provision(string $projectUid): void
    {
        try {
            if (!Auth::check()) throw new \RuntimeException('Tu sesión de soporte expiró.');
            $input = Http::input();
            Csrf::verify($input['_csrf'] ?? null);
            $this->projects->find($projectUid);
            $this->admins->provision($projectUid, $input);
            $store = $this->storefronts->findForProject($projectUid);
            Http::flash('success', 'Administrador creado. Acceso: /' . $store['slug'] . '/admin/');
        } catch (\Throwable $e) {
            Http::flash('error', $e->getMessage());
        }
        Flight::redirect('/projects/' . rawurlencode($projectUid) . '/storefront#administradores');
    }

    private function renderLogin(?string $error = null, string $email = '', ?string $storeSlug = null): void
    {
        header('Cache-Control: private, no-store');
        $adminBase = $storeSlug === null || $storeSlug === ''
            ? '/admin'
            : '/' . rawurlencode($storeSlug) . '/admin';
        $this->render('storefront-admin-login', compact('error', 'email', 'adminBase', 'storeSlug'));
    }

    private function legacyRedirect(string $section): void
    {
        if (!$this->admins->check()) {
            Flight::redirect('/admin');
            return;
        }
        $store = $this->admins->currentStore();
        Flight::redirect($this->base($store) . '/' . $section);
    }

    private function base(array $store): string
    {
        return '/' . rawurlencode((string) $store['slug']) . '/admin';
    }

    private function render(string $template, array $data = []): void
    {
        $file = dirname(__DIR__) . '/Views/' . $template . '.php';
        extract($data, EXTR_SKIP);
        require $file;
    }
}
