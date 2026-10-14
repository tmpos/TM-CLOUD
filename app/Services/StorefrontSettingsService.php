<?php
declare(strict_types=1);
namespace App\Services;

final class StorefrontSettingsService
{
    public function __construct(private StorefrontService $stores, private ?StorefrontPaymentSettingsService $payments = null) {}

    private function merge(array $store, array $input): array
    {
        $fields = array_intersect_key($input, $store);
        foreach (['uid', 'project_uid', 'created_at', 'updated_at', 'url', 'page_content'] as $key) unset($fields[$key]);
        $fields = array_replace($store, $fields);
        foreach (['enabled', 'promo_enabled', 'show_featured', 'show_new_arrivals', 'show_brands', 'announcement_enabled', 'show_stock', 'show_sku', 'pickup_enabled', 'delivery_enabled', 'shipping_enabled'] as $key) if (empty($fields[$key])) unset($fields[$key]);
        return $fields;
    }
    public function handle(array $project, string $operation, array $input = []): array
    {
        $uid = (string) $project['uid'];
        $store = $this->stores->findForProject($uid);
        $repository = $this->stores->editorStore();
        $html = null; $action = null;
        if (isset($input['_website_payments'])) {
            if ($operation !== 'save' || isset($input['_website_editor'])) throw new \InvalidArgumentException('Guarda pagos y diseño por separado.');
            if (!$this->payments) throw new \RuntimeException('Falta actualizar el servidor de pagos.');
            $this->payments->save($uid,$input['_website_payments']);
        } elseif ($operation === 'save' && isset($input['_website_editor'])) {
            $request = $input['_website_editor'];
            if (!is_array($request) || !in_array($request['action'] ?? '', ['draft', 'publish', 'preview', 'discard'], true)) throw new \InvalidArgumentException('Acción del editor inválida.');
            $action = $request['action'];
            $prepare = fn (array $settings) => $this->stores->update($uid, $this->merge($this->stores->findForProject($uid), $settings), true);
            if ($action === 'preview') {
                $preview = $prepare((array) ($request['settings'] ?? []));
                $html = $this->preview($preview, (string) ($request['preview_page'] ?? 'home'));
            } else {
                $repository->mutate($action, $request, fn () => $this->stores->findForProject($uid), $prepare,
                    fn (array $settings) => $this->stores->update($uid, $this->merge($this->stores->findForProject($uid), $settings)));
                $store = $this->stores->findForProject($uid);
            }
        } elseif ($operation === 'save') {
            $store = $this->stores->update($uid, $this->merge($store, $input));
        } elseif ($operation !== 'get') throw new \InvalidArgumentException('Operación de página web no válida.');
        $editor = $repository->state($store);
        $catalogStore = $store;
        if ($editor['draft'] !== null) $catalogStore = $this->stores->update($uid, $this->merge($store, $editor['draft']), true);
        $catalog = $this->stores->catalog($catalogStore, '', '', [], true);
        $result = ['settings' => $store, 'warehouses' => $this->stores->warehousesForProject($uid), 'tables' => $this->stores->tablesForProject($uid), 'editor' => $editor,
            'products' => array_map(static fn ($p) => array_intersect_key($p, array_flip(['uid', 'name', 'description', 'images', 'image', 'category'])), $catalog['products'])];
        if ($action !== null) $result['editor_action'] = $action;
        $result['capabilities'] = ['designs' => 1, 'payments' => $this->payments ? 1 : 0];
        $result['links'] = $this->stores->publicLinks($store);
        if ($this->payments) $result['payments'] = $this->payments->state($uid);
        if (isset($input['_website_payments'])) $result['payments_saved'] = $input['_website_payments']['provider'];
        if ($html !== null) $result['preview_html'] = $html;
        return $result;
    }

    private function preview(array $store, string $page): string
    {
        $store['_preview'] = true;
        $catalog = $allCatalog = $this->stores->catalog($store); $search = $category = '';
        $template = 'storefront';
        if (str_starts_with($page, 'product:')) {
            $detail = $this->stores->productDetail($store, substr($page, 8)); $template = 'storefront-product';
        } elseif ($page === 'categories') {
            $filters = ['brand' => '', 'min_price' => '', 'max_price' => '', 'availability' => '', 'sort' => 'newest'];
            $categoryCounts = [];
            foreach ($catalog['products'] as $product) {
                $key = mb_strtolower($product['category']);
                $categoryCounts[$key] = ($categoryCounts[$key] ?? 0) + 1;
            }
            $template = 'storefront-categories';
        } elseif ($page !== 'home') {
            $definition = StorefrontPagesService::PAGES[$page] ?? null;
            if (!$definition) throw new \InvalidArgumentException('Página de vista previa inválida.');
            // Contact forms are intentionally excluded from read-only previews.
            $services = []; $schedule = null; $booked = false; $bookingToken = ''; $error = null; $old = [];
            $template = 'storefront-information';
        }
        $previousGet = $_GET; $_GET = [];
        ob_start();
        try { require dirname(__DIR__) . '/Views/' . $template . '.php'; $html = ob_get_clean(); }
        catch (\Throwable $error) { ob_end_clean(); throw $error; }
        finally { $_GET = $previousGet; }
        // The application also uses an empty sandbox: preview cannot buy, submit or track.
        $html = preg_replace('~<script\b[^>]*>.*?</script\s*>~is', '', $html);
        $html = preg_replace('~<form\b.*?</form\s*>~is', '<p>Formulario desactivado en vista previa.</p>', $html);
        $html = preg_replace('~(<a\b[^>]*\bhref=)["\'][^"\']*["\']~i', '$1"#"', $html);
        return str_replace('</head>', '<meta name="robots" content="noindex,nofollow"><style>html{pointer-events:none}</style></head>', $html);
    }
}
