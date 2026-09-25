<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Http;
use App\Core\View;
use App\Services\ApiKeyService;
use App\Services\PdfService;
use App\Services\ProjectService;
use App\Services\RecordService;
use App\Services\MailService;
use App\Services\StorefrontService;
use App\Services\StorefrontCommerceService;
use Flight;

final class StorefrontController
{
    public function __construct(
        private array $config,
        private StorefrontService $storefronts,
        private ProjectService $projects,
        private RecordService $records,
        private PdfService $pdf,
        private ApiKeyService $keys,
        private StorefrontCommerceService $commerce,
        private MailService $mail,
        private \App\Services\SpaAppointmentService $spaAppointments,
    ) {
    }

    public function register(): void
    {
        Flight::route('GET /store/@slug', fn ($slug) => $this->home((string) $slug));
        Flight::route('GET /store/@slug/pages/@page', fn ($slug, $page) => $this->informationPage((string) $slug, (string) $page));
        Flight::route('POST /store/@slug/pages/contacto', fn ($slug) => $this->contactSubmit((string) $slug));
        Flight::route('GET /store/@slug/products/@uid', fn ($slug, $uid) => $this->productPage((string) $slug, (string) $uid));
        Flight::route('GET /store/@slug/categories', fn ($slug) => $this->categoriesPage((string) $slug));
        Flight::route('GET /store/@slug/cart', fn ($slug) => $this->cartPage((string) $slug));
        Flight::route('GET /store/@slug/checkout', fn ($slug) => $this->checkoutPage((string) $slug));
        Flight::route('POST /api/storefront/@slug/checkout', fn ($slug) => $this->createCheckout((string) $slug));
        Flight::route('GET /store/@slug/orders/@uid', fn ($slug, $uid) => $this->orderPage((string) $slug, (string) $uid));
        Flight::route('GET /store/@slug/invoices', fn ($slug) => $this->invoiceSearch((string) $slug));
        Flight::route('POST /store/@slug/invoices', fn ($slug) => $this->verifyInvoice((string) $slug));
        Flight::route('GET /store/@slug/register', fn ($slug) => $this->customerRegistration((string) $slug));
        Flight::route('POST /store/@slug/register', fn ($slug) => $this->registerCustomer((string) $slug));
        Flight::route('GET /store/@slug/verify', fn ($slug) => $this->customerVerification((string) $slug));
        Flight::route('POST /store/@slug/verify', fn ($slug) => $this->verifyCustomer((string) $slug));
        Flight::route('POST /store/@slug/verify/resend', fn ($slug) => $this->resendCustomerOtp((string) $slug));
        Flight::route('GET /store/@slug/invoices/@uid', fn ($slug, $uid) => $this->showInvoice((string) $slug, (string) $uid, false));
        Flight::route('GET /store/@slug/invoices/@uid/pdf', fn ($slug, $uid) => $this->showInvoice((string) $slug, (string) $uid, true));
        Flight::route('GET /store/@slug/client', fn ($slug) => $this->clientDashboard((string) $slug));
        Flight::route('POST /store/@slug/client/logout', fn ($slug) => $this->clientLogout((string) $slug));
        Flight::route('GET /store/@slug/client/invoices/@uid', fn ($slug, $uid) => $this->showPortalInvoice((string) $slug, (string) $uid, false));
        Flight::route('GET /store/@slug/client/invoices/@uid/pdf', fn ($slug, $uid) => $this->showPortalInvoice((string) $slug, (string) $uid, true));
        Flight::route('GET /store/@slug/client/workshop/@uid', fn ($slug, $uid) => $this->showPortalWorkshop((string) $slug, (string) $uid));

        Flight::route('GET /api/storefront/@slug', fn ($slug) => $this->storeInfo((string) $slug));
        Flight::route('GET /api/storefront/@slug/products', fn ($slug) => $this->products((string) $slug));
        Flight::route('GET /api/storefront/@slug/products/@uid', fn ($slug, $uid) => $this->product((string) $slug, (string) $uid));

        Flight::route('GET /projects/@uid/storefront', fn ($uid) => $this->settings((string) $uid));
        Flight::route('POST /projects/@uid/storefront', fn ($uid) => $this->saveSettings((string) $uid));
        Flight::route('POST /projects/@uid/storefront/payments', fn ($uid) => $this->savePayments((string) $uid));
    }

    private function home(string $slug): void
    {
        try {
            $store = $this->storefronts->findBySlug($slug);
            $search = trim((string) ($_GET['q'] ?? ''));
            $category = trim((string) ($_GET['category'] ?? ''));
            $catalog = $this->storefronts->catalog($store, $search, $category);
            $allCatalog = ($search !== '' || $category !== '')
                ? $this->storefronts->catalog($store)
                : $catalog;
            header('Cache-Control: public, max-age=60, stale-while-revalidate=300');
            $this->renderPublic('storefront', compact('store', 'catalog', 'allCatalog', 'search', 'category'));
        } catch (\Throwable $e) {
            $this->notFound($e->getMessage());
        }
    }

    private function informationPage(string $slug, string $page, ?string $error = null, array $old = []): void
    {
        try {
            $store = $this->storefronts->findBySlug($slug);
            $definition = \App\Services\StorefrontPagesService::PAGES[$page] ?? null;
            if (!$definition) throw new \RuntimeException('Página no disponible.', 404);
            if ($page === 'contacto') {
                header('Cache-Control: private, no-store');
                $services = []; $schedule = null;
                if ($store['is_spa']) {
                    $project = $this->projects->findActive($store['project_uid']);
                    $scheduler = new \App\Services\SpaScheduleService();
                    if (isset($_GET['availability'])) {
                        try {
                            $this->keys->rateLimitPublic('store-spa-availability:' . $store['uid'], 120);
                            Flight::json(['data' => $scheduler->availability($project, (string) $_GET['availability'])]);
                        } catch (\Throwable) { Flight::json(['error' => 'No se pudo consultar la disponibilidad.'], 422); }
                        return;
                    }
                    $schedule = $scheduler->get($project);
                    $services = $this->spaAppointments->publicServices($this->spaRequest($store), $project);
                }
                $booked = !empty($_SESSION['store_booking_success'][$store['uid']]);
                unset($_SESSION['store_booking_success'][$store['uid']]);
                $bookingToken = $_SESSION['store_booking_token'][$store['uid']] ??= bin2hex(random_bytes(24));
                $this->renderPublic('storefront-information', compact('store', 'page', 'definition', 'services', 'schedule', 'booked', 'bookingToken', 'error', 'old'));
                return;
            }
            header('Cache-Control: public, max-age=60');
            $this->renderPublic('storefront-information', compact('store', 'page', 'definition'));
        } catch (\Throwable $error) {
            Flight::response()->status(404);
            header('Cache-Control: no-store');
            $this->notFound('Página no disponible.');
        }
    }

    private function spaRequest(array $store): array
    {
        $warehouse = $this->storefronts->warehouseForStore($store);
        if (!$warehouse || !empty($warehouse['missing'])) throw new \RuntimeException('Sucursal no disponible.');
        return ['uid' => 'store:' . $store['uid'], 'reusable' => true, 'status' => 'pending',
            'almacen_uid' => $warehouse['uid'], 'almacen_id' => $warehouse['id']];
    }

    private function contactSubmit(string $slug): void
    {
        $input = Http::input();
        try {
            $store = $this->storefronts->findBySlug($slug);
            if (!$store['is_spa']) { $this->registerCustomer($slug, true); return; }
            Csrf::verify($input['_csrf'] ?? null);
            $this->keys->rateLimitPublic('store-spa-book:' . $store['uid'], 12, 900);
            $token = $_SESSION['store_booking_token'][$store['uid']] ?? '';
            if ($token === '' || !hash_equals($token, (string) ($input['booking_token'] ?? ''))) {
                throw new \RuntimeException('Este formulario ya fue enviado o expiró. Recarga la página para reservar.');
            }
            $project = $this->projects->findActive($store['project_uid']);
            if (!(new \App\Services\SpaScheduleService())->get($project)['enabled']) {
                throw new \RuntimeException('Las reservas no están disponibles por ahora. Contacta al spa.');
            }
            $this->spaAppointments->complete($this->spaRequest($store), $project, $input);
            unset($_SESSION['store_booking_token'][$store['uid']]);
            $_SESSION['store_booking_success'][$store['uid']] = true;
            Flight::redirect('/store/' . rawurlencode($store['slug']) . '/pages/contacto#registro');
        } catch (\Throwable $error) {
            Flight::response()->status(422);
            $this->informationPage($slug, 'contacto', $error->getMessage(), $input);
        }
    }

    private function productPage(string $slug, string $uid): void
    {
        try {
            $store = $this->storefronts->findBySlug($slug);
            $detail = $this->storefronts->productDetail($store, $uid);
            header('Cache-Control: public, max-age=60, stale-while-revalidate=300');
            $this->renderPublic('storefront-product', compact('store', 'detail'));
        } catch (\Throwable $e) {
            $this->notFound($e->getMessage());
        }
    }

    private function categoriesPage(string $slug): void
    {
        try {
            $store = $this->storefronts->findBySlug($slug);
            $search = trim((string) ($_GET['q'] ?? ''));
            $category = trim((string) ($_GET['category'] ?? ''));
            $filters = [
                'min_price' => trim((string) ($_GET['min_price'] ?? '')),
                'max_price' => trim((string) ($_GET['max_price'] ?? '')),
                'availability' => trim((string) ($_GET['availability'] ?? '')),
                'brand' => trim((string) ($_GET['brand'] ?? '')),
                'sort' => trim((string) ($_GET['sort'] ?? 'newest')),
            ];
            $allCatalog = $this->storefronts->catalog($store);
            $catalog = $this->storefronts->catalog($store, $search, $category, $filters);
            $categoryCounts = [];
            foreach ($allCatalog['products'] as $product) {
                $name = trim((string) ($product['category'] ?? ''));
                if ($name !== '') {
                    $categoryCounts[mb_strtolower($name)] = ($categoryCounts[mb_strtolower($name)] ?? 0) + 1;
                }
            }
            header('Cache-Control: public, max-age=30, stale-while-revalidate=120');
            $this->renderPublic('storefront-categories', compact(
                'store', 'catalog', 'allCatalog', 'categoryCounts', 'search', 'category', 'filters'
            ));
        } catch (\Throwable $e) {
            $this->notFound($e->getMessage());
        }
    }

    private function cartPage(string $slug): void
    {
        try {
            $store = $this->storefronts->findBySlug($slug);
            if (!(int) ($store['show_prices'] ?? 1)) {
                Flight::redirect('/store/' . rawurlencode($store['slug']) . '#productos');
                return;
            }
            header('Cache-Control: private, no-cache');
            $this->renderPublic('storefront-cart', compact('store'));
        } catch (\Throwable $e) {
            $this->notFound($e->getMessage());
        }
    }

    private function checkoutPage(string $slug): void
    {
        try {
            $store = $this->storefronts->findBySlug($slug);
            if (!(int) ($store['show_prices'] ?? 1)) {
                Flight::redirect('/store/' . rawurlencode($store['slug']) . '#productos');
                return;
            }
            $checkoutCustomer = $this->checkoutCustomerForStore($store);
            if ($checkoutCustomer === null) {
                $_SESSION['storefront_customer_next'][$store['uid']] = '/store/' . rawurlencode($store['slug']) . '/checkout';
                Flight::redirect('/store/' . rawurlencode($store['slug']) . '/invoices');
                return;
            }
            $paymentMethods = $this->commerce->publicMethods($store);
            header('Cache-Control: private, no-store');
            $this->renderPublic('storefront-checkout', compact('store', 'paymentMethods', 'checkoutCustomer'));
        } catch (\Throwable $e) {
            $this->notFound($e->getMessage());
        }
    }

    private function createCheckout(string $slug): void
    {
        try {
            $input = Http::input();
            Csrf::verify($input['_csrf'] ?? null);
            $store = $this->storefronts->findBySlug($slug);
            $this->keys->rateLimitPublic('storefront-checkout:' . hash('sha256', $store['uid']), 20, 300);
            $customer = $this->checkoutCustomerForStore($store);
            if ($customer === null) {
                throw new \RuntimeException('Debes registrarte o iniciar sesión antes de completar la compra.', 401);
            }
            if (!filter_var((string) ($store['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('La tienda todavía no tiene un correo válido configurado para recibir pedidos.');
            }
            if (!(bool) ($this->mail->settings()['enabled'] ?? false)) {
                throw new \InvalidArgumentException('El servidor de correo todavía no está activado para procesar la compra.');
            }
            $input['customer_name'] = $this->customerField($customer, ['nombre', 'nombre_completo', 'name']);
            $input['customer_document'] = $this->customerField($customer, ['cedula', 'cedula_rnc', 'rnc', 'documento', 'identificacion', 'tax_id']);
            $order = $this->commerce->createOrder($store, $input);
            $_SESSION['storefront_order_access'][$store['uid']][$order['uid']] = time() + 86400 * 30;
            $payment = $this->commerce->beginPayment($store, $order);
            $order = $this->commerce->order((string) $order['uid'], (string) $store['uid']);
            $this->queueOrderEmails($store, $order);
            Flight::json(['data' => [
                'order_number' => $order['order_number'],
                'order_url' => '/store/' . rawurlencode($store['slug']) . '/orders/' . rawurlencode($order['uid']),
                'next_url' => $payment['url'],
                'next_type' => $payment['type'],
            ]], 201);
        } catch (\Throwable $e) {
            $status = (int) $e->getCode() === 401
                ? (int) $e->getCode()
                : ($e instanceof \InvalidArgumentException ? 422 : 400);
            Http::error($e, $status);
        }
    }

    private function orderPage(string $slug, string $uid): void
    {
        try {
            $store = $this->storefronts->findBySlug($slug);
            $expires = (int) ($_SESSION['storefront_order_access'][$store['uid']][$uid] ?? 0);
            if ($expires < time()) {
                throw new \RuntimeException('Este enlace de pedido expiró.', 403);
            }
            $order = $this->commerce->order($uid, (string) $store['uid']);
            if (isset($_GET['session_id']) || isset($_GET['token'])) {
                $order = $this->commerce->completeReturn($store, $order, $_GET);
            }
            header('Cache-Control: private, no-store');
            header('X-Robots-Tag: noindex, nofollow');
            $this->renderPublic('storefront-order', compact('store', 'order'));
        } catch (\Throwable $e) {
            $this->notFound($e->getMessage());
        }
    }

    private function invoiceSearch(string $slug, ?string $error = null, array $old = []): void
    {
        try {
            $store = $this->storefronts->findBySlug($slug);
            if ($this->checkoutCustomerForStore($store) !== null) {
                Flight::redirect('/store/' . rawurlencode($store['slug']) . '/client');
                return;
            }
            header('Cache-Control: no-store');
            $this->renderPublic('storefront-invoices', compact('store', 'error', 'old'));
        } catch (\Throwable $e) {
            $this->notFound($e->getMessage());
        }
    }

    private function verifyInvoice(string $slug): void
    {
        $input = Http::input();
        try {
            Csrf::verify($input['_csrf'] ?? null);
            $store = $this->storefronts->findBySlug($slug);
            $accessType = 'document';
            $value = trim((string) ($input['access_value'] ?? $input['invoice_number'] ?? ''));
            $this->keys->rateLimitPublic(
                'storefront-client-portal:' . hash('sha256', $store['uid']),
                40,
                300
            );
            $this->keys->rateLimitPublic(
                'storefront-client-access:' . hash('sha256', $store['uid'] . '|' . $accessType . '|' . mb_strtolower($value)),
                6,
                900
            );
            session_regenerate_id(true);
            $found = $this->storefronts->locateCustomerByDocument($store, $value);
            if (!$this->storefronts->customerIsVerified($store, $found['customer'])) {
                $this->startPendingCustomer($store, $found['customer']);
                try {
                    $this->sendCustomerOtp($store, $found['customer']);
                    Flight::redirect('/store/' . rawurlencode($store['slug']) . '/verify');
                } catch (\Throwable $mailError) {
                    $this->customerVerification($slug, $mailError->getMessage());
                }
                return;
            }
            $this->startCustomerSession($store, $found['customer']);
            Flight::redirect($this->customerDestination($store));
        } catch (\Throwable $e) {
            $this->invoiceSearch($slug, $e->getMessage(), [
                'access_type' => (string) ($input['access_type'] ?? 'document'),
                'access_value' => trim((string) ($input['access_value'] ?? $input['invoice_number'] ?? '')),
            ]);
        }
    }

    private function customerRegistration(string $slug, ?string $error = null, array $old = []): void
    {
        try {
            $store = $this->storefronts->findBySlug($slug);
            if ($this->checkoutCustomerForStore($store) !== null) {
                Flight::redirect('/store/' . rawurlencode($store['slug']) . '/client');
                return;
            }
            header('Cache-Control: private, no-store');
            $this->renderPublic('storefront-register', compact('store', 'error', 'old'));
        } catch (\Throwable $e) {
            $this->notFound($e->getMessage());
        }
    }

    private function registerCustomer(string $slug, bool $fromContact = false): void
    {
        $input = Http::input();
        try {
            Csrf::verify($input['_csrf'] ?? null);
            $store = $this->storefronts->findBySlug($slug);
            $this->keys->rateLimitPublic(
                'storefront-customer-register:' . hash('sha256', $store['uid']),
                12,
                900
            );
            $customer = $this->storefronts->registerCustomer($store, $input);
            session_regenerate_id(true);
            $this->startPendingCustomer($store, $customer);
            try {
                $this->sendCustomerOtp($store, $customer);
                Flight::redirect('/store/' . rawurlencode($store['slug']) . '/verify');
            } catch (\Throwable $mailError) {
                $this->customerVerification($slug, $mailError->getMessage());
            }
        } catch (\Throwable $e) {
            $old = [
                'nombre' => trim((string) ($input['nombre'] ?? '')),
                'telefono' => trim((string) ($input['telefono'] ?? '')),
                'email' => trim((string) ($input['email'] ?? '')),
                'direccion' => trim((string) ($input['direccion'] ?? '')),
                'cedula' => trim((string) ($input['cedula'] ?? '')),
            ];
            if ($fromContact) {
                Flight::response()->status(422);
                $this->informationPage($slug, 'contacto', $e->getMessage(), $old);
            } else $this->customerRegistration($slug, $e->getMessage(), $old);
        }
    }

    private function customerVerification(string $slug, ?string $error = null): void
    {
        try {
            $store = $this->storefronts->findBySlug($slug);
            $customer = $this->pendingCustomerForStore($store);
            if ($customer === null) {
                Flight::redirect('/store/' . rawurlencode($store['slug']) . '/invoices');
                return;
            }
            $email = $this->storefronts->customerEmail($customer);
            header('Cache-Control: private, no-store');
            $this->renderPublic('storefront-verify', compact('store', 'email', 'error'));
        } catch (\Throwable $e) {
            $this->notFound($e->getMessage());
        }
    }

    private function verifyCustomer(string $slug): void
    {
        $input = Http::input();
        try {
            Csrf::verify($input['_csrf'] ?? null);
            $store = $this->storefronts->findBySlug($slug);
            $customer = $this->pendingCustomerForStore($store);
            if ($customer === null) {
                throw new \RuntimeException('La activación de esta cuenta ya no está disponible.');
            }
            $this->keys->rateLimitPublic(
                'storefront-customer-otp:' . hash('sha256', $store['uid'] . '|' . ($customer['uid'] ?? '')),
                10,
                900
            );
            $this->storefronts->verifyCustomerOtp($store, $customer, (string) ($input['otp'] ?? ''));
            unset($_SESSION['storefront_customer_pending'][$store['uid']]);
            session_regenerate_id(true);
            $this->startCustomerSession($store, $customer);
            Flight::redirect($this->customerDestination($store));
        } catch (\Throwable $e) {
            $this->customerVerification($slug, $e->getMessage());
        }
    }

    private function resendCustomerOtp(string $slug): void
    {
        $input = Http::input();
        try {
            Csrf::verify($input['_csrf'] ?? null);
            $store = $this->storefronts->findBySlug($slug);
            $customer = $this->pendingCustomerForStore($store);
            if ($customer === null) {
                throw new \RuntimeException('La activación de esta cuenta ya no está disponible.');
            }
            $this->keys->rateLimitPublic(
                'storefront-customer-otp-resend:' . hash('sha256', $store['uid'] . '|' . ($customer['uid'] ?? '')),
                3,
                900
            );
            $this->sendCustomerOtp($store, $customer);
            $this->customerVerification($slug, 'Enviamos un código nuevo a tu correo.');
        } catch (\Throwable $e) {
            $this->customerVerification($slug, $e->getMessage());
        }
    }

    private function startCustomerSession(array $store, array $customer): void
    {
        $_SESSION['storefront_customer_portal'][$store['uid']] = [
            'access_type' => 'customer',
            'customer_uid' => (string) $customer['uid'],
            'logged_in_at' => time(),
        ];
    }

    private function startPendingCustomer(array $store, array $customer): void
    {
        unset($_SESSION['storefront_customer_portal'][$store['uid']]);
        $_SESSION['storefront_customer_pending'][$store['uid']] = [
            'customer_uid' => (string) ($customer['uid'] ?? ''),
            'started_at' => time(),
        ];
    }

    private function pendingCustomerForStore(array $store): ?array
    {
        $pending = $_SESSION['storefront_customer_pending'][$store['uid']] ?? null;
        if (!is_array($pending)) {
            return null;
        }
        $customerUid = trim((string) ($pending['customer_uid'] ?? ''));
        if ($customerUid === '') {
            unset($_SESSION['storefront_customer_pending'][$store['uid']]);
            return null;
        }
        try {
            return $this->storefronts->customerByUid($store, $customerUid);
        } catch (\Throwable) {
            unset($_SESSION['storefront_customer_pending'][$store['uid']]);
            return null;
        }
    }

    private function sendCustomerOtp(array $store, array $customer): void
    {
        $verification = $this->storefronts->beginCustomerVerification($store, $customer);
        $this->mail->sendOtp(
            (string) $store['project_uid'],
            (string) $verification['email'],
            (string) $verification['otp'],
            [
                'company_name' => (string) $store['store_name'],
                'purpose' => 'activar tu cuenta de cliente',
                'expires_minutes' => (int) $verification['expires_minutes'],
            ]
        );
    }

    private function customerDestination(array $store): string
    {
        $default = '/store/' . rawurlencode((string) $store['slug']) . '/client';
        $destination = (string) ($_SESSION['storefront_customer_next'][$store['uid']] ?? '');
        unset($_SESSION['storefront_customer_next'][$store['uid']]);
        return $destination === '/store/' . rawurlencode((string) $store['slug']) . '/checkout'
            ? $destination
            : $default;
    }

    private function customerField(array $customer, array $candidates): string
    {
        $lookup = array_change_key_case($customer, CASE_LOWER);
        foreach ($candidates as $candidate) {
            $value = trim((string) ($lookup[mb_strtolower($candidate)] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }

    private function queueOrderEmails(array $store, array $order): void
    {
        $items = array_map(static fn (array $item): array => [
            'name' => (string) ($item['product_name'] ?? 'Producto'),
            'sku' => (string) ($item['product_sku'] ?? ''),
            'quantity' => (int) ($item['quantity'] ?? 1),
            'unit_price' => (float) ($item['unit_price'] ?? 0),
            'line_total' => (float) ($item['line_total'] ?? 0),
        ], array_slice($order['items'] ?? [], 0, 50));
        $baseUrl = rtrim((string) ($this->config['url'] ?? ''), '/');
        $payload = [
            'company_name' => (string) $store['store_name'],
            'primary_color' => (string) ($store['primary_color'] ?? '#0f766e'),
            'order_number' => (string) $order['order_number'],
            'customer_name' => (string) $order['customer_name'],
            'customer_email' => (string) $order['customer_email'],
            'customer_phone' => (string) $order['customer_phone'],
            'customer_document' => (string) ($order['customer_document'] ?? ''),
            'delivery_method' => (string) $order['delivery_method'],
            'delivery_address' => (string) ($order['delivery_address'] ?? ''),
            'delivery_city' => (string) ($order['delivery_city'] ?? ''),
            'customer_notes' => (string) ($order['customer_notes'] ?? ''),
            'payment_provider' => (string) $order['payment_provider'],
            'payment_status' => (string) $order['payment_status'],
            'currency' => (string) $order['currency'],
            'subtotal' => (float) $order['subtotal'],
            'shipping_total' => (float) $order['shipping_total'],
            'total' => (float) $order['total'],
            'items' => $items,
            'customer_url' => $baseUrl . '/store/' . rawurlencode((string) $store['slug']) . '/client#orders',
            'admin_url' => $baseUrl . '/' . rawurlencode((string) $store['slug']) . '/admin/orders',
        ];

        foreach ([
            ['email' => (string) $order['customer_email'], 'role' => 'customer'],
            ['email' => (string) $store['email'], 'role' => 'store'],
        ] as $recipient) {
            try {
                $job = $this->mail->queue(
                    (string) $store['project_uid'],
                    'storefront_order',
                    $recipient['email'],
                    $payload + ['recipient_role' => $recipient['role']]
                );
                $this->mail->deliver((string) $store['project_uid'], (string) $job['uid']);
            } catch (\Throwable) {
                // Never duplicate or invalidate an order already committed because one recipient is unavailable.
            }
        }
    }

    private function showInvoice(string $slug, string $uid, bool $asPdf): void
    {
        try {
            $store = $this->storefronts->findBySlug($slug);
            $expires = (int) ($_SESSION['storefront_invoice_access'][$store['uid']][$uid] ?? 0);
            if ($expires < time()) {
                unset($_SESSION['storefront_invoice_access'][$store['uid']][$uid]);
                Flight::redirect('/store/' . rawurlencode($store['slug']) . '/invoices');
                return;
            }
            $found = $this->storefronts->invoiceByUid($store, $uid);
            $content = $asPdf
                ? $this->pdf->invoice($found['project'], $found['table'], $found['invoice'])
                : $this->pdf->invoiceHtml($found['project'], $found['invoice']);
            header('Cache-Control: private, no-store');
            header('X-Robots-Tag: noindex, nofollow');
            header('Content-Type: ' . ($asPdf && str_starts_with($content, '%PDF')
                ? 'application/pdf'
                : 'text/html; charset=UTF-8'));
            if ($asPdf && str_starts_with($content, '%PDF')) {
                header('Content-Disposition: inline; filename="factura.pdf"');
            }
            echo $content;
        } catch (\Throwable) {
            $this->notFound('Factura no disponible.');
        }
    }

    private function clientDashboard(string $slug): void
    {
        try {
            $store = $this->storefronts->findBySlug($slug);
            $portal = $this->portalForStore($store);
            if ($portal === null) {
                Flight::redirect('/store/' . rawurlencode($store['slug']) . '/invoices');
                return;
            }
            header('Cache-Control: private, no-store');
            header('X-Robots-Tag: noindex, nofollow');
            $this->renderPublic('storefront-client-dashboard', compact('store', 'portal'));
        } catch (\Throwable) {
            $this->notFound('Portal de cliente no disponible.');
        }
    }

    private function clientLogout(string $slug): void
    {
        $input = Http::input();
        try {
            Csrf::verify($input['_csrf'] ?? null);
            $store = $this->storefronts->findBySlug($slug);
            unset(
                $_SESSION['storefront_customer_portal'][$store['uid']],
                $_SESSION['storefront_customer_pending'][$store['uid']],
                $_SESSION['storefront_invoice_access'][$store['uid']],
                $_SESSION['storefront_customer_next'][$store['uid']]
            );
            session_regenerate_id(true);
            Flight::redirect('/store/' . rawurlencode($store['slug']) . '/invoices');
        } catch (\Throwable) {
            Flight::redirect('/store/' . rawurlencode($slug) . '/invoices');
        }
    }

    private function showPortalInvoice(string $slug, string $uid, bool $asPdf): void
    {
        try {
            $store = $this->storefronts->findBySlug($slug);
            $portal = $this->portalForStore($store);
            if ($portal === null) {
                Flight::redirect('/store/' . rawurlencode($store['slug']) . '/invoices');
                return;
            }
            $found = $this->storefronts->portalInvoice($portal, $uid);
            $content = $asPdf
                ? $this->pdf->invoice($found['project'], $found['table'], $found['invoice'])
                : $this->pdf->invoiceHtml($found['project'], $found['invoice']);
            header('Cache-Control: private, no-store');
            header('X-Robots-Tag: noindex, nofollow');
            header('Content-Type: ' . ($asPdf && str_starts_with($content, '%PDF')
                ? 'application/pdf'
                : 'text/html; charset=UTF-8'));
            if ($asPdf && str_starts_with($content, '%PDF')) {
                $number = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) (
                    $found['invoice']['no_factura'] ?? $found['invoice']['uid'] ?? 'factura'
                ));
                header('Content-Disposition: inline; filename="factura-' . $number . '.pdf"');
            }
            echo $content;
        } catch (\Throwable) {
            $this->notFound('Factura no disponible.');
        }
    }

    private function showPortalWorkshop(string $slug, string $uid): void
    {
        try {
            $store = $this->storefronts->findBySlug($slug);
            $portal = $this->portalForStore($store);
            if ($portal === null) {
                Flight::redirect('/store/' . rawurlencode($store['slug']) . '/invoices');
                return;
            }
            $order = $this->storefronts->portalWorkshopOrder($portal, $uid);
            header('Cache-Control: private, no-store');
            header('X-Robots-Tag: noindex, nofollow');
            $this->renderPublic('storefront-client-workshop', compact('store', 'portal', 'order'));
        } catch (\Throwable) {
            $this->notFound('Orden de taller no disponible.');
        }
    }

    private function portalForStore(array $store): ?array
    {
        $access = $_SESSION['storefront_customer_portal'][$store['uid']] ?? null;
        if (!is_array($access)) {
            unset($_SESSION['storefront_customer_portal'][$store['uid']]);
            return null;
        }
        if (($access['access_type'] ?? 'invoice') === 'customer') {
            $customerUid = (string) ($access['customer_uid'] ?? '');
            if ($customerUid === '') {
                return null;
            }
            $customer = $this->storefronts->customerByUid($store, $customerUid);
            if (!$this->storefronts->customerIsVerified($store, $customer)) {
                unset($_SESSION['storefront_customer_portal'][$store['uid']]);
                return null;
            }
            return $this->storefronts->customerPortalForCustomer($store, $customer);
        }
        $anchorUid = (string) ($access['anchor_invoice_uid'] ?? '');
        if ($anchorUid === '') {
            return null;
        }
        $anchor = $this->storefronts->invoiceByUid($store, $anchorUid);
        return $this->storefronts->customerPortal($store, $anchor['invoice']);
    }

    private function checkoutCustomerForStore(array $store): ?array
    {
        $access = $_SESSION['storefront_customer_portal'][$store['uid']] ?? null;
        if (
            !is_array($access)
            || ($access['access_type'] ?? '') !== 'customer'
        ) {
            return null;
        }

        $customerUid = trim((string) ($access['customer_uid'] ?? ''));
        if ($customerUid === '') {
            return null;
        }

        try {
            $customer = $this->storefronts->customerByUid($store, $customerUid);
            if (!$this->storefronts->customerIsVerified($store, $customer)) {
                unset($_SESSION['storefront_customer_portal'][$store['uid']]);
                return null;
            }
            return $customer;
        } catch (\Throwable) {
            unset($_SESSION['storefront_customer_portal'][$store['uid']]);
            return null;
        }
    }

    private function storeInfo(string $slug): void
    {
        try {
            $store = $this->storefronts->findBySlug($slug);
            Flight::json(['data' => ['store' => $this->publicStore($store)]]);
        } catch (\Throwable $e) {
            Http::error($e, 404);
        }
    }

    private function products(string $slug): void
    {
        try {
            $store = $this->storefronts->findBySlug($slug);
            $this->keys->rateLimitPublic('storefront-catalog:' . hash('sha256', $store['uid']), 120);
            $catalog = $this->storefronts->catalog(
                $store,
                (string) ($_GET['q'] ?? ''),
                (string) ($_GET['category'] ?? '')
            );
            header('Cache-Control: public, max-age=60');
            Flight::json([
                'data' => [
                    'store' => $this->publicStore($store),
                    'products' => $catalog['products'],
                    'categories' => $catalog['categories'],
                ],
                'meta' => ['count' => count($catalog['products'])],
            ]);
        } catch (\Throwable $e) {
            Http::error($e, $e->getCode() === 404 ? 404 : 400);
        }
    }

    private function product(string $slug, string $uid): void
    {
        try {
            $store = $this->storefronts->findBySlug($slug);
            $this->keys->rateLimitPublic('storefront-product:' . hash('sha256', $store['uid']), 120);
            $detail = $this->storefronts->productDetail($store, $uid);
            header('Cache-Control: public, max-age=60');
            Flight::json([
                'data' => [
                    'store' => $this->publicStore($store),
                    'product' => $detail['product'],
                    'related' => $detail['related'],
                ],
            ]);
        } catch (\Throwable $e) {
            Http::error($e, $e->getCode() === 404 ? 404 : 400);
        }
    }

    private function settings(string $projectUid): void
    {
        if (!Auth::check()) {
            Flight::redirect('/');
            return;
        }
        try {
            View::render('storefront-settings', [
                'title' => 'Tienda web',
                'project' => $this->projects->find($projectUid),
                'store' => $this->storefronts->findForProject($projectUid),
                'tables' => $this->storefronts->tablesForProject($projectUid),
                'warehouses' => $this->storefronts->warehousesForProject($projectUid),
                'paymentMethods' => $this->commerce->adminMethods($projectUid),
                'orders' => $this->commerce->recentOrders($projectUid),
                'flashes' => Http::flashes(),
            ]);
        } catch (\Throwable $e) {
            Http::flash('error', $e->getMessage());
            Flight::redirect('/dashboard');
        }
    }

    private function savePayments(string $projectUid): void
    {
        try {
            if (!Auth::check()) {
                throw new \RuntimeException('Tu sesión expiró.');
            }
            $input = Http::input();
            Csrf::verify($input['_csrf'] ?? null);
            $this->projects->find($projectUid);
            $this->commerce->saveMethods($projectUid, $input);
            Http::flash('success', 'Los métodos de pago fueron actualizados de forma segura.');
        } catch (\Throwable $e) {
            Http::flash('error', $e->getMessage());
        }
        Flight::redirect('/projects/' . rawurlencode($projectUid) . '/storefront#pagos');
    }

    private function saveSettings(string $projectUid): void
    {
        try {
            if (!Auth::check()) {
                throw new \RuntimeException('Tu sesión expiró.');
            }
            $input = Http::input();
            Csrf::verify($input['_csrf'] ?? null);
            $this->storefronts->update($projectUid, $input);
            Http::flash('success', 'La tienda web fue actualizada.');
        } catch (\Throwable $e) {
            Http::flash('error', $e->getMessage());
        }
        Flight::redirect('/projects/' . rawurlencode($projectUid) . '/storefront');
    }

    private function publicStore(array $store): array
    {
        return array_intersect_key($store, array_flip([
            'uid', 'slug', 'store_name', 'tagline', 'hero_title', 'hero_text',
            'business_type', 'resolved_business_type',
            'primary_color', 'accent_color', 'logo_url', 'phone', 'whatsapp',
            'email', 'address', 'currency', 'url', 'background_color', 'surface_color',
            'text_color', 'muted_color', 'header_color', 'footer_color', 'border_radius',
            'font_family', 'hero_style', 'card_style', 'announcement_enabled',
            'hero_background_mode', 'hero_background_color', 'hero_images',
            'hero_overlay_opacity', 'hero_carousel_interval',
            'promo_enabled', 'promo_title', 'promo_text', 'promo_image', 'promo_link',
            'show_featured', 'show_new_arrivals', 'show_brands',
            'announcement_text', 'show_prices', 'show_stock', 'show_sku', 'footer_text', 'instagram_url',
            'facebook_url', 'pickup_enabled', 'delivery_enabled', 'shipping_enabled',
            'flat_shipping_cost', 'free_shipping_threshold', 'checkout_terms_url',
        ]));
    }

    private function renderPublic(string $template, array $data): void
    {
        $file = dirname(__DIR__) . '/Views/' . $template . '.php';
        extract($data, EXTR_SKIP);
        require $file;
    }

    private function notFound(string $message): void
    {
        http_response_code(404);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>No disponible</title><body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#f8fafc;font:16px system-ui;color:#0f172a">'
            . '<main style="max-width:520px;padding:32px;text-align:center"><h1>Tienda no disponible</h1><p>'
            . e($message) . '</p></main></body></html>';
    }
}
