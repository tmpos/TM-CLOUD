<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Support;
use PDO;
use RuntimeException;

final class StorefrontService
{
    private const PRODUCT_TABLES = ['productos', 'accesorios', 'electrodomesticos', 'inventario', 'articulos', 'items', 'platos', 'menu_items'];
    private const INVOICE_TABLES = ['facturas', 'invoices', 'ventas'];
    private const CUSTOMER_TABLES = ['clientes', 'customers'];

    public function __construct(
        private PDO $db,
        private array $config,
        private ProjectService $projects,
        private SchemaService $schema,
        private RecordService $records,
    ) {
    }

    public function findBySlug(string $slug, bool $requireEnabled = true): array
    {
        $slug = Support::slug($slug);
        $sql = 'SELECT s.*,p.name AS project_name,p.status AS project_status
                FROM storefronts s
                JOIN projects p ON p.uid=s.project_uid
                WHERE s.slug=? LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$slug]);
        $store = $stmt->fetch();
        if (!$store || $store['project_status'] !== 'active' || ($requireEnabled && !(int) $store['enabled'])) {
            throw new RuntimeException('Tienda no disponible.', 404);
        }
        $store = $this->withCompanyIdentity($store);
        $store = $this->withPresentation($store);
        $store['url'] = rtrim((string) $this->config['url'], '/') . '/store/' . $store['slug'];
        return $store;
    }

    public function findForProject(string $projectUid): array
    {
        $project = $this->projects->find($projectUid);
        $stmt = $this->db->prepare('SELECT * FROM storefronts WHERE project_uid=? LIMIT 1');
        $stmt->execute([$projectUid]);
        $store = $stmt->fetch();
        if (!$store) {
            $now = Support::now();
            $this->db->prepare(
                'INSERT INTO storefronts
                 (uid,project_uid,slug,enabled,store_name,created_at,updated_at)
                 VALUES (?,?,?,?,?,?,?)'
            )->execute([Support::uid('sto_'), $projectUid, $project['slug'], 1, $project['name'], $now, $now]);
            $stmt->execute([$projectUid]);
            $store = $stmt->fetch();
        }
        $store = $this->withCompanyIdentity($store);
        $store = $this->withPresentation($store);
        $store['url'] = rtrim((string) $this->config['url'], '/') . '/store/' . $store['slug'];
        return $store;
    }

    public function update(string $projectUid, array $input): array
    {
        $store = $this->findForProject($projectUid);
        $slug = Support::slug((string) ($input['slug'] ?? $store['slug']));
        $storeName = trim((string) ($input['store_name'] ?? $store['store_name']));
        if ($storeName === '' || mb_strlen($storeName) > 100) {
            throw new \InvalidArgumentException('El nombre de la tienda es obligatorio.');
        }
        $color = static function (mixed $value, string $fallback): string {
            $value = trim((string) $value);
            return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) ? strtolower($value) : $fallback;
        };
        $catalogTable = trim((string) ($input['catalog_table'] ?? ''));
        if ($catalogTable !== '') {
            Support::identifier($catalogTable, 'catalog table');
            $this->schema->columns($this->projects->find($projectUid), $catalogTable);
        }
        $fields = [
            'slug' => $slug,
            'enabled' => isset($input['enabled']) ? 1 : 0,
            'store_name' => $storeName,
            'business_type' => in_array((string) ($input['business_type'] ?? 'auto'), ['auto', 'electronics', 'restaurant', 'general'], true)
                ? (string) ($input['business_type'] ?? 'auto')
                : 'auto',
            'tagline' => $this->limited($input['tagline'] ?? '', 160),
            'hero_title' => $this->limited($input['hero_title'] ?? '', 160),
            'hero_text' => $this->limited($input['hero_text'] ?? '', 500),
            'primary_color' => $color($input['primary_color'] ?? '', '#0f766e'),
            'accent_color' => $color($input['accent_color'] ?? '', '#f59e0b'),
            'background_color' => $color($input['background_color'] ?? '', '#ffffff'),
            'surface_color' => $color($input['surface_color'] ?? '', '#f5f7fb'),
            'text_color' => $color($input['text_color'] ?? '', '#14213d'),
            'muted_color' => $color($input['muted_color'] ?? '', '#65758b'),
            'header_color' => $color($input['header_color'] ?? '', '#ffffff'),
            'footer_color' => $color($input['footer_color'] ?? '', '#0b1324'),
            'border_radius' => max(8, min(32, (int) ($input['border_radius'] ?? 18))),
            'font_family' => in_array((string) ($input['font_family'] ?? 'inter'), ['inter', 'system', 'poppins', 'serif'], true)
                ? (string) ($input['font_family'] ?? 'inter')
                : 'inter',
            'hero_style' => in_array((string) ($input['hero_style'] ?? 'gradient'), ['gradient', 'minimal', 'split'], true)
                ? (string) ($input['hero_style'] ?? 'gradient')
                : 'gradient',
            'hero_background_mode' => in_array((string) ($input['hero_background_mode'] ?? 'gradient'), ['gradient', 'color', 'image', 'carousel'], true)
                ? (string) $input['hero_background_mode']
                : 'gradient',
            'hero_background_color' => $color($input['hero_background_color'] ?? '', '#0b1324'),
            'hero_images' => json_encode($this->heroImagesInput($input['hero_images'] ?? ''), JSON_UNESCAPED_SLASHES),
            'hero_overlay_opacity' => max(0, min(85, (int) ($input['hero_overlay_opacity'] ?? 55))),
            'hero_carousel_interval' => max(3000, min(15000, (int) ($input['hero_carousel_interval'] ?? 6000))),
            'promo_enabled' => isset($input['promo_enabled']) ? 1 : 0,
            'promo_title' => $this->limited($input['promo_title'] ?? '', 120),
            'promo_text' => $this->limited($input['promo_text'] ?? '', 280),
            'promo_image' => $this->imageReferenceOrEmpty($input['promo_image'] ?? ''),
            'promo_link' => $this->storeLinkOrEmpty($input['promo_link'] ?? ''),
            'show_featured' => isset($input['show_featured']) ? 1 : 0,
            'show_new_arrivals' => isset($input['show_new_arrivals']) ? 1 : 0,
            'show_brands' => isset($input['show_brands']) ? 1 : 0,
            'card_style' => in_array((string) ($input['card_style'] ?? 'elevated'), ['elevated', 'outlined', 'minimal'], true)
                ? (string) ($input['card_style'] ?? 'elevated')
                : 'elevated',
            'announcement_enabled' => isset($input['announcement_enabled']) ? 1 : 0,
            'announcement_text' => $this->limited($input['announcement_text'] ?? '', 200),
            'show_stock' => isset($input['show_stock']) ? 1 : 0,
            'show_sku' => isset($input['show_sku']) ? 1 : 0,
            'footer_text' => $this->limited($input['footer_text'] ?? '', 240),
            'instagram_url' => $this->urlOrEmpty($input['instagram_url'] ?? ''),
            'facebook_url' => $this->urlOrEmpty($input['facebook_url'] ?? ''),
            'pickup_enabled' => isset($input['pickup_enabled']) ? 1 : 0,
            'delivery_enabled' => isset($input['delivery_enabled']) ? 1 : 0,
            'shipping_enabled' => isset($input['shipping_enabled']) ? 1 : 0,
            'flat_shipping_cost' => max(0, round((float) ($input['flat_shipping_cost'] ?? 0), 2)),
            'free_shipping_threshold' => max(0, round((float) ($input['free_shipping_threshold'] ?? 0), 2)),
            'checkout_terms_url' => $this->urlOrEmpty($input['checkout_terms_url'] ?? ''),
            'logo_url' => $this->urlOrEmpty($input['logo_url'] ?? ''),
            'phone' => $this->limited($input['phone'] ?? '', 40),
            'whatsapp' => preg_replace('/\D+/', '', (string) ($input['whatsapp'] ?? '')),
            'email' => $this->emailOrEmpty($input['email'] ?? ''),
            'address' => $this->limited($input['address'] ?? '', 240),
            'currency' => in_array(strtoupper((string) ($input['currency'] ?? 'DOP')), ['DOP', 'USD', 'EUR'], true)
                ? strtoupper((string) ($input['currency'] ?? 'DOP'))
                : 'DOP',
            'catalog_table' => $catalogTable !== '' ? $catalogTable : null,
            'updated_at' => Support::now(),
        ];
        $assignments = implode(',', array_map(static fn (string $field): string => "$field=:$field", array_keys($fields)));
        $fields['project_uid'] = $projectUid;
        try {
            $this->db->prepare("UPDATE storefronts SET $assignments WHERE project_uid=:project_uid")->execute($fields);
        } catch (\PDOException $e) {
            if (str_contains(strtolower($e->getMessage()), 'unique')) {
                throw new \InvalidArgumentException('Ese enlace de tienda ya está siendo utilizado.');
            }
            throw $e;
        }
        return $this->findForProject($projectUid);
    }

    public function catalog(array $store, string $search = '', string $category = '', array $filters = []): array
    {
        $sources = $this->catalogSources($store);
        if ($sources === []) {
            return ['table' => null, 'products' => [], 'categories' => [], 'brands' => []];
        }
        $table = $sources[0]['table'];
        $products = [];
        $categories = [];
        $brands = [];
        $needle = mb_strtolower(trim($search));
        $categoryNeedle = mb_strtolower(trim($category));
        $minPrice = is_numeric($filters['min_price'] ?? null) ? max(0, (float) $filters['min_price']) : null;
        $maxPrice = is_numeric($filters['max_price'] ?? null) ? max(0, (float) $filters['max_price']) : null;
        $availability = in_array((string) ($filters['availability'] ?? ''), ['in_stock', 'out_of_stock'], true)
            ? (string) $filters['availability']
            : '';
        $brandNeedle = mb_strtolower(trim((string) ($filters['brand'] ?? '')));
        foreach ($sources as $source) {
            ['project' => $project] = $source;
            ['rows' => $rows, 'map' => $map] = $this->catalogRows($source);
            foreach ($rows as $row) {
                if (!$this->isPublicProduct($row, $map['active'])) {
                    continue;
                }
                $product = $this->mapProduct($project['uid'], $row, $map);
                if ($product === null) {
                    continue;
                }
                if ($product['category'] !== '') {
                    $categories[mb_strtolower($product['category'])] = $product['category'];
                }
                if ($product['brand'] !== '') {
                    $brands[mb_strtolower($product['brand'])] = $product['brand'];
                }
                $haystack = mb_strtolower($product['name'] . ' ' . $product['description'] . ' ' . $product['category'] . ' ' . $product['brand']);
                if ($needle !== '' && !str_contains($haystack, $needle)) {
                    continue;
                }
                if ($categoryNeedle !== '' && mb_strtolower($product['category']) !== $categoryNeedle) {
                    continue;
                }
                if ($brandNeedle !== '' && mb_strtolower($product['brand']) !== $brandNeedle) {
                    continue;
                }
                if ($minPrice !== null && ($product['price'] === null || (float) $product['price'] < $minPrice)) {
                    continue;
                }
                if ($maxPrice !== null && ($product['price'] === null || (float) $product['price'] > $maxPrice)) {
                    continue;
                }
                if ($availability === 'in_stock' && !$product['available']) {
                    continue;
                }
                if ($availability === 'out_of_stock' && $product['available']) {
                    continue;
                }
                $products[] = $product;
            }
        }
        natcasesort($categories);
        natcasesort($brands);
        $sort = (string) ($filters['sort'] ?? 'newest');
        if ($sort !== 'newest') {
            usort($products, static function (array $left, array $right) use ($sort): int {
                return match ($sort) {
                    'price_asc' => ($left['price'] ?? PHP_FLOAT_MAX) <=> ($right['price'] ?? PHP_FLOAT_MAX),
                    'price_desc' => ($right['price'] ?? -1) <=> ($left['price'] ?? -1),
                    'name_asc' => strcasecmp((string) $left['name'], (string) $right['name']),
                    'name_desc' => strcasecmp((string) $right['name'], (string) $left['name']),
                    default => 0,
                };
            });
        }
        return [
            'table' => $table,
            'products' => array_slice($products, 0, 240),
            'categories' => array_values($categories),
            'brands' => array_values($brands),
        ];
    }

    public function productDetail(array $store, string $uid): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{3,100}$/', $uid)) {
            throw new RuntimeException('Producto no disponible.', 404);
        }
        $product = null;
        $table = null;
        foreach ($this->catalogSources($store) as $source) {
            ['project' => $project] = $source;
            ['rows' => $rows, 'map' => $map] = $this->catalogRows($source);
            foreach ($rows as $row) {
                if ((string) ($row['uid'] ?? '') !== $uid || !$this->isPublicProduct($row, $map['active'])) {
                    continue;
                }
                $product = $this->mapProduct($project['uid'], $row, $map, true);
                $table = $source['table'];
                break 2;
            }
        }
        if ($product === null || $table === null) {
            throw new RuntimeException('Producto no disponible.', 404);
        }
        $related = array_values(array_filter(
            $this->catalog($store)['products'],
            static fn (array $item): bool => $item['uid'] !== $uid
        ));
        usort($related, static function (array $left, array $right) use ($product): int {
            $leftMatch = $product['category'] !== '' && mb_strtolower($left['category']) === mb_strtolower($product['category']);
            $rightMatch = $product['category'] !== '' && mb_strtolower($right['category']) === mb_strtolower($product['category']);
            return (int) $rightMatch <=> (int) $leftMatch;
        });
        return ['table' => $table, 'product' => $product, 'related' => array_slice($related, 0, 4)];
    }

    public function locateInvoice(array $store, string $number, string $verification): array
    {
        $number = trim($number);
        $verification = trim($verification);
        if ($number === '' || mb_strlen($number) > 80 || mb_strlen($verification) < 4 || mb_strlen($verification) > 120) {
            throw new RuntimeException('No pudimos validar la factura con los datos proporcionados.');
        }
        $project = $this->projects->findActive($store['project_uid']);
        $table = $this->resolveTable($project, null, self::INVOICE_TABLES);
        if ($table === null) {
            throw new RuntimeException('La consulta de facturas aún no está disponible.');
        }
        $columns = array_column($this->schema->columns($project, $table), 'name');
        $numberColumns = array_values(array_filter([
            $this->firstColumn($columns, ['numero', 'numero_factura', 'factura', 'invoice_number', 'ncf', 'codigo']),
            in_array('uid', $columns, true) ? 'uid' : null,
        ]));
        if (!$numberColumns) {
            throw new RuntimeException('La consulta de facturas aún no está disponible.');
        }
        $where = implode(' OR ', array_map(static fn (string $column): string => Support::quoteIdentifier($column) . ' = ?', $numberColumns));
        $stmt = $this->schema->connection($project)->prepare(
            'SELECT * FROM ' . Support::quoteIdentifier($table) . " WHERE $where ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute(array_fill(0, count($numberColumns), $number));
        $invoice = $stmt->fetch();
        if (!$invoice || !$this->verificationMatches($project, $invoice, $verification)) {
            throw new RuntimeException('No pudimos validar la factura con los datos proporcionados.');
        }
        return ['project' => $project, 'table' => $table, 'invoice' => $invoice];
    }

    public function locateInvoiceByNumber(array $store, string $number): array
    {
        $number = trim($number);
        if ($number === '' || mb_strlen($number) > 80) {
            throw new RuntimeException('No encontramos una factura con ese número.');
        }
        $project = $this->projects->findActive($store['project_uid']);
        $table = $this->resolveTable($project, null, self::INVOICE_TABLES);
        if ($table === null) {
            throw new RuntimeException('La consulta de facturas aún no está disponible.');
        }
        $columns = array_column($this->schema->columns($project, $table), 'name');
        $numberColumns = array_values(array_unique(array_filter([
            $this->firstColumn($columns, ['no_factura', 'numero', 'numero_factura', 'factura', 'invoice_number', 'ncf', 'codigo']),
            in_array('uid', $columns, true) ? 'uid' : null,
        ])));
        if (!$numberColumns) {
            throw new RuntimeException('La consulta de facturas aún no está disponible.');
        }
        $where = implode(' OR ', array_map(
            static fn (string $column): string => Support::quoteIdentifier($column) . ' = ?',
            $numberColumns
        ));
        $stmt = $this->schema->connection($project)->prepare(
            'SELECT * FROM ' . Support::quoteIdentifier($table) . " WHERE $where ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute(array_fill(0, count($numberColumns), $number));
        $invoice = $stmt->fetch();
        if (!$invoice) {
            throw new RuntimeException('No encontramos una factura con ese número.');
        }
        return ['project' => $project, 'table' => $table, 'invoice' => $invoice];
    }

    public function customerPortal(array $store, array $anchorInvoice): array
    {
        $project = $this->projects->findActive($store['project_uid']);
        $invoiceTable = $this->resolveTable($project, null, self::INVOICE_TABLES);
        if ($invoiceTable === null) {
            throw new RuntimeException('El portal de clientes no está disponible.');
        }
        $customer = $this->portalCustomer($project, $anchorInvoice);
        $invoices = $this->portalInvoices($project, $invoiceTable, $anchorInvoice, $customer);
        $orders = $this->portalWorkshopOrders($project, $anchorInvoice, $customer);
        $portalCustomer = $customer ?: $this->customerFromInvoice($anchorInvoice);
        return $this->portalPayload(
            $project,
            $invoiceTable,
            $portalCustomer,
            $invoices,
            $orders,
            $this->portalWebOrders($store, $portalCustomer)
        );
    }

    public function locateCustomerByDocument(array $store, string $document): array
    {
        $wanted = $this->normalizedIdentity($document);
        if ($wanted === '' || mb_strlen($wanted) < 5 || mb_strlen($wanted) > 30) {
            throw new RuntimeException('No encontramos un cliente con esa cédula o RNC.');
        }
        $project = $this->projects->findActive($store['project_uid']);
        $table = $this->resolveTable($project, null, self::CUSTOMER_TABLES);
        if ($table === null) {
            throw new RuntimeException('El portal de clientes aún no está disponible.');
        }
        try {
            $columns = array_column($this->schema->columns($project, $table), 'name');
            $columnLookup = array_change_key_case(array_combine($columns, $columns) ?: [], CASE_LOWER);
            $documentColumns = [];
            foreach (['cedula', 'cedula_rnc', 'rnc', 'documento', 'identificacion', 'tax_id'] as $candidate) {
                if (isset($columnLookup[$candidate])) {
                    $documentColumns[] = $columnLookup[$candidate];
                }
            }
            if (!$documentColumns) {
                throw new RuntimeException('El portal de clientes aún no está disponible.');
            }
            $rows = $this->schema->connection($project)->query(
                'SELECT * FROM ' . Support::quoteIdentifier($table) . ' ORDER BY id DESC LIMIT 10000'
            )->fetchAll();
            foreach ($rows as $customer) {
                foreach ($documentColumns as $column) {
                    if ($this->normalizedIdentity($customer[$column] ?? '') === $wanted) {
                        return ['project' => $project, 'table' => $table, 'customer' => $customer];
                    }
                }
            }
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable) {
        }
        throw new RuntimeException('No encontramos un cliente con esa cédula o RNC.');
    }

    public function registerCustomer(array $store, array $input): array
    {
        $name = mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', (string) ($input['nombre'] ?? ''))));
        $document = trim((string) ($input['cedula'] ?? ''));
        $phone = trim((string) ($input['telefono'] ?? ''));
        $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
        $address = trim((string) ($input['direccion'] ?? ''));

        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            throw new \InvalidArgumentException('Escribe el nombre completo del cliente.');
        }
        $normalizedDocument = $this->normalizedIdentity($document);
        if ($normalizedDocument === '' || mb_strlen($normalizedDocument) < 5 || mb_strlen($normalizedDocument) > 30) {
            throw new \InvalidArgumentException('La cédula es obligatoria y no tiene un formato válido.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('El correo electrónico es obligatorio y debe tener un formato válido.');
        }
        if (mb_strlen($phone) > 40 || mb_strlen($email) > 160 || mb_strlen($address) > 250) {
            throw new \InvalidArgumentException('Uno de los datos supera la longitud permitida.');
        }

        $project = $this->projects->findActive($store['project_uid']);
        $table = $this->resolveTable($project, null, self::CUSTOMER_TABLES);
        if ($table === null) {
            throw new RuntimeException('El registro de clientes aún no está disponible.');
        }
        $columns = array_column($this->schema->columns($project, $table), 'name');
        $fieldMap = [
            'nombre' => $this->firstColumn($columns, ['nombre', 'name']),
            'cedula' => $this->firstColumn($columns, ['cedula', 'cedula_rnc', 'documento', 'identificacion', 'rnc', 'tax_id']),
            'telefono' => $this->firstColumn($columns, ['telefono', 'phone', 'celular', 'whatsapp']),
            'email' => $this->firstColumn($columns, ['email', 'correo']),
            'direccion' => $this->firstColumn($columns, ['direccion', 'address', 'domicilio']),
        ];
        if ($fieldMap['nombre'] === null || $fieldMap['cedula'] === null) {
            throw new RuntimeException('La tabla de clientes debe tener columnas para nombre y cédula.');
        }

        try {
            $this->locateCustomerByDocument($store, $document);
            throw new \InvalidArgumentException('Ya existe un cliente registrado con esa cédula. Puedes iniciar sesión.');
        } catch (RuntimeException) {
            // No matching customer means the document is available.
        }

        $values = [
            'nombre' => $name,
            'cedula' => $document,
            'telefono' => $phone,
            'email' => $email,
            'direccion' => $address,
        ];
        $data = [];
        foreach ($fieldMap as $field => $column) {
            if ($column !== null) {
                $data[$column] = $values[$field];
            }
        }
        return $this->records->create($project, $table, $data);
    }

    public function customerPortalForCustomer(array $store, array $customer): array
    {
        $project = $this->projects->findActive($store['project_uid']);
        $invoiceTable = $this->resolveTable($project, null, self::INVOICE_TABLES);
        $invoices = $invoiceTable === null ? [] : $this->portalInvoices($project, $invoiceTable, [], $customer);
        $orders = $this->portalWorkshopOrders($project, [], $customer);
        return $this->portalPayload(
            $project,
            $invoiceTable ?? '',
            $customer,
            $invoices,
            $orders,
            $this->portalWebOrders($store, $customer)
        );
    }

    public function customerByUid(array $store, string $uid): array
    {
        $project = $this->projects->findActive($store['project_uid']);
        $table = $this->resolveTable($project, null, self::CUSTOMER_TABLES);
        if ($table === null || !preg_match('/^[A-Za-z0-9_-]{3,100}$/', $uid)) {
            throw new RuntimeException('Cliente no disponible.');
        }
        return $this->records->find($project, $table, $uid);
    }

    public function customerEmail(array $customer): string
    {
        foreach (['email', 'correo', 'correo_electronico'] as $column) {
            $value = mb_strtolower(trim((string) ($customer[$column] ?? '')));
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return $value;
            }
        }
        throw new \InvalidArgumentException('La cuenta no tiene un correo válido para recibir el código de activación.');
    }

    public function beginCustomerVerification(array $store, array $customer): array
    {
        $customerUid = trim((string) ($customer['uid'] ?? ''));
        if ($customerUid === '') {
            throw new RuntimeException('Cliente no disponible.');
        }
        $email = $this->customerEmail($customer);
        $otp = (string) random_int(100000, 999999);
        $hash = password_hash($otp, PASSWORD_DEFAULT);
        if (!is_string($hash) || $hash === '') {
            throw new RuntimeException('No fue posible generar el código de activación.');
        }
        $now = Support::now();
        $expires = gmdate('Y-m-d H:i:s', time() + 600);
        $this->db->prepare(
            'INSERT INTO storefront_customer_verifications
             (storefront_uid,customer_uid,email,otp_hash,attempts,expires_at,verified_at,created_at,updated_at)
             VALUES (?,?,?,?,0,?,NULL,?,?)
             ON CONFLICT(storefront_uid,customer_uid) DO UPDATE SET
                email=excluded.email,otp_hash=excluded.otp_hash,attempts=0,expires_at=excluded.expires_at,
                verified_at=NULL,updated_at=excluded.updated_at'
        )->execute([$store['uid'], $customerUid, $email, $hash, $expires, $now, $now]);
        return ['otp' => $otp, 'email' => $email, 'expires_minutes' => 10];
    }

    public function customerIsVerified(array $store, array $customer): bool
    {
        $stmt = $this->db->prepare(
            'SELECT verified_at FROM storefront_customer_verifications WHERE storefront_uid=? AND customer_uid=? LIMIT 1'
        );
        $stmt->execute([$store['uid'], $customer['uid'] ?? '']);
        $row = $stmt->fetch();
        // Customers created before OTP activation was introduced remain valid.
        return !$row || trim((string) ($row['verified_at'] ?? '')) !== '';
    }

    public function verifyCustomerOtp(array $store, array $customer, string $otp): void
    {
        $otp = preg_replace('/\D+/', '', $otp) ?? '';
        if (!preg_match('/^\d{6}$/', $otp)) {
            throw new \InvalidArgumentException('Escribe el código de 6 dígitos enviado a tu correo.');
        }
        $stmt = $this->db->prepare(
            'SELECT * FROM storefront_customer_verifications WHERE storefront_uid=? AND customer_uid=? LIMIT 1'
        );
        $stmt->execute([$store['uid'], $customer['uid'] ?? '']);
        $verification = $stmt->fetch();
        if (!$verification || trim((string) ($verification['verified_at'] ?? '')) !== '') {
            throw new RuntimeException('La activación ya no está pendiente.');
        }
        if ((int) ($verification['attempts'] ?? 0) >= 5) {
            throw new \InvalidArgumentException('Superaste el límite de intentos. Solicita un código nuevo.');
        }
        if ((string) ($verification['expires_at'] ?? '') < Support::now()) {
            throw new \InvalidArgumentException('El código expiró. Solicita uno nuevo.');
        }
        $this->db->prepare(
            'UPDATE storefront_customer_verifications SET attempts=attempts+1,updated_at=? WHERE id=?'
        )->execute([Support::now(), $verification['id']]);
        if (!password_verify($otp, (string) $verification['otp_hash'])) {
            throw new \InvalidArgumentException('El código de activación no es correcto.');
        }
        $now = Support::now();
        $this->db->prepare(
            'UPDATE storefront_customer_verifications SET verified_at=?,updated_at=? WHERE id=?'
        )->execute([$now, $now, $verification['id']]);
    }

    private function portalPayload(
        array $project,
        string $invoiceTable,
        array $customer,
        array $invoices,
        array $orders,
        array $webOrders
    ): array {
        $totalSpent = 0.0;
        foreach ($invoices as $invoice) {
            $totalSpent += (float) ($invoice['total'] ?? 0);
        }
        return [
            'project' => $project,
            'invoice_table' => $invoiceTable,
            'customer' => $customer,
            'invoices' => $invoices,
            'orders' => $orders,
            'web_orders' => $webOrders,
            'stats' => [
                'purchases' => count($invoices),
                'total_spent' => $totalSpent,
                'workshop_orders' => count($orders),
                'active_orders' => count(array_filter($orders, static function (array $order): bool {
                    return !in_array(mb_strtoupper(trim((string) ($order['estado'] ?? ''))), [
                        'ENTREGADO', 'ENTREGADA', 'FINALIZADO', 'FINALIZADA', 'CANCELADO', 'CANCELADA',
                    ], true);
                })),
                'web_orders' => count($webOrders),
            ],
        ];
    }

    private function portalWebOrders(array $store, array $customer): array
    {
        $documents = array_values(array_unique(array_filter(array_map(
            fn (mixed $value): string => $this->normalizedIdentity($value),
            [
                $customer['cedula'] ?? '',
                $customer['cedula_rnc'] ?? '',
                $customer['rnc'] ?? '',
                $customer['documento'] ?? '',
                $customer['identificacion'] ?? '',
            ]
        ))));
        if (!$documents) {
            return [];
        }
        try {
            $stmt = $this->db->prepare(
                'SELECT * FROM storefront_orders WHERE storefront_uid=? ORDER BY id DESC LIMIT 200'
            );
            $stmt->execute([(string) $store['uid']]);
            $orders = [];
            $itemStmt = $this->db->prepare(
                'SELECT * FROM storefront_order_items WHERE order_uid=? ORDER BY id ASC'
            );
            foreach ($stmt->fetchAll() as $order) {
                if (!in_array($this->normalizedIdentity($order['customer_document'] ?? ''), $documents, true)) {
                    continue;
                }
                $itemStmt->execute([(string) $order['uid']]);
                $order['items'] = $itemStmt->fetchAll();
                $orders[] = $order;
            }
            return $orders;
        } catch (\Throwable) {
            return [];
        }
    }

    public function portalInvoice(array $portal, string $uid): array
    {
        foreach ($portal['invoices'] as $invoice) {
            if (hash_equals((string) ($invoice['uid'] ?? ''), $uid)) {
                return [
                    'project' => $portal['project'],
                    'table' => $portal['invoice_table'],
                    'invoice' => $invoice,
                ];
            }
        }
        throw new RuntimeException('Factura no disponible.');
    }

    public function portalWorkshopOrder(array $portal, string $uid): array
    {
        foreach ($portal['orders'] as $order) {
            if (hash_equals((string) ($order['uid'] ?? ''), $uid)) {
                return $order;
            }
        }
        throw new RuntimeException('Orden de taller no disponible.');
    }

    public function invoiceByUid(array $store, string $uid): array
    {
        $project = $this->projects->findActive($store['project_uid']);
        $table = $this->resolveTable($project, null, self::INVOICE_TABLES);
        if ($table === null) {
            throw new RuntimeException('Factura no disponible.');
        }
        return ['project' => $project, 'table' => $table, 'invoice' => $this->records->find($project, $table, $uid)];
    }

    public function systemOverview(array $store): array
    {
        try {
            $project = $this->projects->findActive((string) $store['project_uid']);
            $db = $this->schema->connection($project);
            $tableRows = $this->schema->tables($project);
            $tables = [];
            foreach ($tableRows as $row) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name !== '') $tables[mb_strtolower($name)] = $name;
            }
            $countFirst = static function (array $candidates) use ($tables, $db): int {
                foreach ($candidates as $candidate) {
                    $table = $tables[mb_strtolower($candidate)] ?? null;
                    if ($table === null) continue;
                    try {
                        return (int) $db->query(
                            'SELECT COUNT(*) FROM ' . Support::quoteIdentifier($table)
                        )->fetchColumn();
                    } catch (\Throwable) {
                    }
                }
                return 0;
            };
            return [
                'system_tables' => count($tables),
                'native_sales' => $countFirst(['facturas', 'factura', 'invoices', 'ventas']),
                'native_customers' => $countFirst(['clientes', 'customers', 'cliente']),
                'native_workshop_orders' => $countFirst(['ordenes_taller', 'orden_taller', 'reparaciones', 'taller']),
                'native_imeis' => $countFirst(['imei', 'imeis']),
                'native_restaurant_orders' => $countFirst(['comandas', 'ordenes_restaurante', 'pedidos_restaurante']),
                'native_tables_count' => $countFirst(['mesas']),
            ];
        } catch (\Throwable) {
            return [
                'system_tables' => 0,
                'native_sales' => 0,
                'native_customers' => 0,
                'native_workshop_orders' => 0,
                'native_imeis' => 0,
                'native_restaurant_orders' => 0,
                'native_tables_count' => 0,
            ];
        }
    }

    public function tablesForProject(string $projectUid): array
    {
        $project = $this->projects->find($projectUid);
        return array_column($this->schema->tables($project), 'name');
    }

    private function verificationMatches(array $project, array $invoice, string $verification): bool
    {
        $wanted = $this->normalizeVerification($verification);
        $fields = ['email', 'correo', 'telefono', 'phone', 'celular', 'cedula', 'rnc', 'documento', 'identificacion'];
        foreach ($fields as $field) {
            if (array_key_exists($field, $invoice) && $this->normalizeVerification((string) $invoice[$field]) === $wanted) {
                return true;
            }
        }
        $customerTable = $this->resolveTable($project, null, self::CUSTOMER_TABLES);
        if ($customerTable === null) {
            return false;
        }
        $customerColumns = array_column($this->schema->columns($project, $customerTable), 'name');
        $relationPairs = [
            ['cliente_uid', 'uid'],
            ['customer_uid', 'uid'],
            ['cliente_id', 'id'],
            ['id_cliente', 'id'],
            ['customer_id', 'id'],
        ];
        foreach ($relationPairs as [$invoiceColumn, $customerColumn]) {
            if (!array_key_exists($invoiceColumn, $invoice) || !in_array($customerColumn, $customerColumns, true)) {
                continue;
            }
            $stmt = $this->schema->connection($project)->prepare(
                'SELECT * FROM ' . Support::quoteIdentifier($customerTable)
                . ' WHERE ' . Support::quoteIdentifier($customerColumn) . ' = ? LIMIT 1'
            );
            $stmt->execute([$invoice[$invoiceColumn]]);
            $customer = $stmt->fetch();
            if (!$customer) {
                continue;
            }
            foreach ($fields as $field) {
                if (array_key_exists($field, $customer) && $this->normalizeVerification((string) $customer[$field]) === $wanted) {
                    return true;
                }
            }
        }
        return false;
    }

    private function portalCustomer(array $project, array $invoice): array
    {
        $table = $this->resolveTable($project, null, self::CUSTOMER_TABLES);
        if ($table === null) {
            return [];
        }
        try {
            $customers = $this->schema->connection($project)->query(
                'SELECT * FROM ' . Support::quoteIdentifier($table) . ' ORDER BY id DESC LIMIT 5000'
            )->fetchAll();
        } catch (\Throwable) {
            return [];
        }
        $reference = $this->normalizedIdentity($invoice['cod_cliente'] ?? $invoice['cliente_uid'] ?? $invoice['cliente_id'] ?? '');
        $invoicePhone = $this->normalizedIdentity($invoice['telefono_cliente'] ?? $invoice['telefono'] ?? '');
        $invoiceName = $this->normalizedIdentity($invoice['nombre_cliente'] ?? $invoice['cliente'] ?? '');
        foreach ($customers as $customer) {
            $identifiers = array_filter(array_map(
                fn (mixed $value): string => $this->normalizedIdentity($value),
                [$customer['id'] ?? '', $customer['uid'] ?? '', $customer['codigo'] ?? '']
            ));
            if ($reference !== '' && in_array($reference, $identifiers, true)) {
                return $customer;
            }
        }
        foreach ($customers as $customer) {
            $phone = $this->normalizedIdentity($customer['telefono'] ?? $customer['whatsapp'] ?? '');
            $name = $this->normalizedIdentity($customer['nombre'] ?? '');
            if (($invoicePhone !== '' && $phone === $invoicePhone) || ($invoiceName !== '' && $name === $invoiceName)) {
                return $customer;
            }
        }
        return [];
    }

    private function portalInvoices(array $project, string $table, array $anchor, array $customer): array
    {
        try {
            $rows = $this->schema->connection($project)->query(
                'SELECT * FROM ' . Support::quoteIdentifier($table) . ' ORDER BY id DESC LIMIT 1000'
            )->fetchAll();
        } catch (\Throwable) {
            return [$anchor];
        }
        $customerIdentifiers = array_values(array_filter(array_unique(array_map(
            fn (mixed $value): string => $this->normalizedIdentity($value),
            [
                $customer['id'] ?? '',
                $customer['uid'] ?? '',
                $customer['codigo'] ?? '',
                $anchor['cod_cliente'] ?? '',
                $anchor['cliente_uid'] ?? '',
                $anchor['cliente_id'] ?? '',
            ]
        ))));
        $phones = array_values(array_filter(array_unique(array_map(
            fn (mixed $value): string => $this->normalizedIdentity($value),
            [$customer['telefono'] ?? '', $customer['whatsapp'] ?? '', $anchor['telefono_cliente'] ?? '', $anchor['telefono'] ?? '']
        ))));
        $names = array_values(array_filter(array_unique(array_map(
            fn (mixed $value): string => $this->normalizedIdentity($value),
            [$customer['nombre'] ?? '', $anchor['nombre_cliente'] ?? '', $anchor['cliente'] ?? '']
        ))));
        $invoices = [];
        foreach ($rows as $invoice) {
            $isAnchor = (string) ($invoice['uid'] ?? '') === (string) ($anchor['uid'] ?? '');
            $reference = $this->normalizedIdentity($invoice['cod_cliente'] ?? $invoice['cliente_uid'] ?? $invoice['cliente_id'] ?? '');
            $phone = $this->normalizedIdentity($invoice['telefono_cliente'] ?? $invoice['telefono'] ?? '');
            $name = $this->normalizedIdentity($invoice['nombre_cliente'] ?? $invoice['cliente'] ?? '');
            if (
                $isAnchor
                || ($reference !== '' && in_array($reference, $customerIdentifiers, true))
                || ($phone !== '' && in_array($phone, $phones, true))
                || ($name !== '' && in_array($name, $names, true))
            ) {
                $invoice['_portal_product_count'] = $this->inlineItemCount($invoice['productos'] ?? $invoice['items'] ?? '');
                $invoices[] = $invoice;
            }
        }
        return $invoices ?: ($anchor ? [$anchor] : []);
    }

    private function portalWorkshopOrders(array $project, array $invoice, array $customer): array
    {
        $table = $this->resolveTable($project, null, ['ordenes_taller', 'taller', 'orden_taller', 'work_orders']);
        if ($table === null) {
            return [];
        }
        try {
            $orders = $this->schema->connection($project)->query(
                'SELECT * FROM ' . Support::quoteIdentifier($table) . ' ORDER BY id DESC LIMIT 1000'
            )->fetchAll();
        } catch (\Throwable) {
            return [];
        }
        $identities = [
            'cedula' => array_values(array_filter(array_unique(array_map(
                fn (mixed $value): string => $this->normalizedIdentity($value),
                [$customer['cedula'] ?? '', $customer['rnc'] ?? '']
            )))),
            'phone' => array_values(array_filter(array_unique(array_map(
                fn (mixed $value): string => $this->normalizedIdentity($value),
                [$customer['telefono'] ?? '', $customer['whatsapp'] ?? '', $invoice['telefono_cliente'] ?? '', $invoice['telefono'] ?? '']
            )))),
            'email' => array_values(array_filter(array_unique(array_map(
                fn (mixed $value): string => $this->normalizedIdentity($value),
                [$customer['email'] ?? '', $invoice['email'] ?? '']
            )))),
            'name' => array_values(array_filter(array_unique(array_map(
                fn (mixed $value): string => $this->normalizedIdentity($value),
                [$customer['nombre'] ?? '', $invoice['nombre_cliente'] ?? '', $invoice['cliente'] ?? '']
            )))),
        ];
        return array_values(array_filter($orders, function (array $order) use ($identities): bool {
            $values = [
                'cedula' => $this->normalizedIdentity($order['cedula'] ?? $order['documento'] ?? ''),
                'phone' => $this->normalizedIdentity($order['telefono'] ?? $order['whatsapp'] ?? ''),
                'email' => $this->normalizedIdentity($order['email'] ?? $order['correo'] ?? ''),
                'name' => $this->normalizedIdentity($order['nombre'] ?? $order['cliente'] ?? ''),
            ];
            foreach ($values as $type => $value) {
                if ($value !== '' && in_array($value, $identities[$type], true)) {
                    return true;
                }
            }
            return false;
        }));
    }

    private function customerFromInvoice(array $invoice): array
    {
        return [
            'nombre' => trim((string) ($invoice['nombre_cliente'] ?? $invoice['cliente'] ?? 'Cliente')),
            'telefono' => trim((string) ($invoice['telefono_cliente'] ?? $invoice['telefono'] ?? '')),
            'email' => trim((string) ($invoice['email'] ?? $invoice['correo'] ?? '')),
            'direccion' => trim((string) ($invoice['direccion'] ?? $invoice['direccion_cliente'] ?? '')),
            'cedula' => trim((string) ($invoice['cedula'] ?? $invoice['rnc'] ?? '')),
        ];
    }

    private function normalizedIdentity(mixed $value): string
    {
        return mb_strtolower((string) preg_replace('/[^\pL\pN@.+]/u', '', trim((string) $value)));
    }

    private function inlineItemCount(mixed $value): int
    {
        if (is_string($value) && trim($value) !== '') {
            $value = json_decode($value, true);
        }
        if (is_array($value) && !array_is_list($value)) {
            $value = $value['items'] ?? $value['productos'] ?? [];
        }
        return is_array($value) ? count($value) : 0;
    }

    private function catalogSource(array $store): ?array
    {
        $project = $this->projects->findActive($store['project_uid']);
        $table = $this->resolveTable($project, $store['catalog_table'] ?: null, self::PRODUCT_TABLES);
        if ($table === null) {
            return null;
        }
        return $this->catalogSourceForTable($project, $table);
    }

    private function catalogSourceForTable(array $project, string $table): ?array
    {
        $columns = array_column($this->schema->columns($project, $table), 'name');
        $imageColumns = [];
        foreach ($columns as $column) {
            if (
                preg_match('/^(imagen|image|foto)(?:_?(?:url|\d+))?$/i', $column)
                || in_array(strtolower($column), ['imagenes', 'images', 'galeria', 'gallery', 'fotos'], true)
            ) {
                $imageColumns[] = $column;
            }
        }
        $map = [
            'name' => $this->firstColumn($columns, ['nombre', 'name', 'producto', 'titulo', 'modelo', 'descripcion']),
            'description' => $this->firstColumn($columns, ['descripcion', 'description', 'detalle', 'notas', 'caracteristicas']),
            'price' => $this->firstColumn($columns, ['precio_venta', 'precio', 'price', 'venta', 'precio1', 'precio_publico']),
            'compare_price' => $this->firstColumn($columns, ['precio_normal', 'precio_regular', 'precio_lista', 'compare_price', 'old_price']),
            'category' => $this->firstColumn($columns, ['categoria', 'category', 'tipo', 'marca', 'departamento']),
            'brand' => $this->firstColumn($columns, ['marca', 'brand', 'fabricante']),
            'stock' => $this->firstColumn($columns, ['existencia', 'existencias', 'stock', 'cantidad', 'disponible']),
            'active' => $this->firstColumn($columns, ['activo', 'active', 'publicado', 'visible', 'estado', 'estatus']),
            'sku' => $this->firstColumn($columns, ['codigo', 'sku', 'referencia', 'barcode', 'codigo_barra']),
            'image_columns' => $imageColumns,
            'columns' => $columns,
        ];
        if ($map['name'] === null) {
            return null;
        }
        return ['project' => $project, 'table' => $table, 'map' => $map];
    }

    private function catalogSources(array $store): array
    {
        $primary = $this->catalogSource($store);
        if ($primary === null) {
            return [];
        }
        $sources = [$primary['table'] => $primary];
        $project = $primary['project'];
        $tableNames = array_column($this->schema->tables($project), 'name');
        $tables = array_change_key_case(array_combine($tableNames, $tableNames) ?: [], CASE_LOWER);
        $isDeviceStore = isset($tables['telefonos'], $tables['imei'])
            || isset($tables['electrodomesticos'], $tables['serial']);
        if ($isDeviceStore) {
            foreach (['accesorios', 'telefonos', 'electrodomesticos'] as $candidate) {
                if (!isset($tables[$candidate]) || isset($sources[$tables[$candidate]])) {
                    continue;
                }
                $source = $this->catalogSourceForTable($project, $tables[$candidate]);
                if ($source !== null) {
                    $sources[$source['table']] = $source;
                }
            }
        }
        return array_values($sources);
    }

    private function catalogRows(array $source): array
    {
        ['project' => $project, 'table' => $table, 'map' => $map] = $source;
        $db = $this->schema->connection($project);
        $rows = $db->query('SELECT * FROM ' . Support::quoteIdentifier($table) . ' ORDER BY id DESC LIMIT 500')->fetchAll();
        [$rows, $hasOnlineImages] = $this->withOnlineProductImages($db, $table, $rows);
        if ($hasOnlineImages) {
            array_unshift($map['image_columns'], '_tmpos_images');
            $map['image_columns'] = array_values(array_unique($map['image_columns']));
        }
        $tableKey = mb_strtolower($table);
        if ($tableKey !== 'telefonos' && $map['category'] !== null) {
            $categoryNames = $this->lookupNames($db, 'categorias');
            foreach ($rows as &$row) {
                $rawCategory = trim((string) ($row[$map['category']] ?? ''));
                if (isset($categoryNames[$rawCategory])) {
                    $row[$map['category']] = $categoryNames[$rawCategory];
                } elseif ($rawCategory !== '' && ctype_digit($rawCategory)) {
                    $row[$map['category']] = $tableKey === 'accesorios' ? 'Accesorios' : 'Productos';
                }
            }
            unset($row);
        }
        if ($tableKey === 'accesorios') {
            $categoryNames = $this->lookupNames($db, 'categorias');
            $brandNames = $this->lookupNames($db, 'marcas');
            foreach ($rows as &$row) {
                $categoryId = (string) ($row['categoria'] ?? '');
                $brandId = (string) ($row['marca'] ?? '');
                if (isset($categoryNames[$categoryId])) {
                    $row['categoria'] = $categoryNames[$categoryId];
                }
                if (isset($brandNames[$brandId])) {
                    $row['marca'] = $brandNames[$brandId];
                }
            }
            unset($row);
        }
        if ($tableKey === 'telefonos') {
            $inventory = $this->phoneInventory($db);
            $brandNames = $this->lookupNames($db, 'marcas');
            $phoneImageColumn = $this->firstColumn(
                $map['columns'],
                ['imagen', 'imagen_url', 'image', 'image_url', 'foto', 'foto_url']
            );
            // The public selling price belongs to the phone model. IMEI rows only
            // determine availability and must not replace it with their lowest value.
            $map['price'] = $this->firstColumn($map['columns'], ['precio_venta']);
            $map['compare_price'] = null;
            $map['stock'] = 'cantidad';
            $map['category'] = 'categoria';
            $map['brand'] = $this->firstColumn($map['columns'], ['marca', 'brand', 'fabricante']);
            $map['description'] = 'descripcion';
            $map['sku'] = null;
            $map['active'] = null;
            $map['image_columns'] = array_values(array_filter([
                $hasOnlineImages ? '_tmpos_images' : null,
                $phoneImageColumn,
            ]));
            $map['columns'] = array_values(array_unique(array_merge(
                $map['columns'], ['precio_venta', 'cantidad', 'categoria', 'descripcion', 'color', 'capacidad']
            )));
            foreach ($rows as &$row) {
                $brandId = (string) ($row['marca'] ?? '');
                if (isset($brandNames[$brandId])) {
                    $row['marca'] = $brandNames[$brandId];
                }
                $key = (string) ($row['uid'] ?? '');
                $fallback = 'id:' . (string) ($row['id'] ?? '');
                $byName = 'name:' . mb_strtolower(trim((string) ($row['nombre'] ?? '')));
                $stock = $inventory[$key] ?? $inventory[$fallback] ?? $inventory[$byName] ?? null;
                $row['cantidad'] = (int) ($stock['quantity'] ?? 0);
                $row['categoria'] = 'Teléfonos';
                $row['_store_kind'] = 'phone';
                $row['descripcion'] = 'Equipo disponible por IMEI. ' . ($row['cantidad'] === 1
                    ? '1 unidad disponible.'
                    : $row['cantidad'] . ' unidades disponibles.');
                $row['color'] = implode(', ', $stock['colors'] ?? []);
                $row['capacidad'] = implode(', ', $stock['capacities'] ?? []);
            }
            unset($row);
        }
        if ($tableKey === 'electrodomesticos') {
            $inventory = $this->applianceInventory($db);
            $applianceImageColumn = $this->firstColumn(
                $map['columns'],
                ['imagen', 'imagen_url', 'image', 'image_url', 'foto', 'foto_url']
            );
            $map['price'] = 'precio_venta';
            $map['compare_price'] = null;
            $map['stock'] = 'cantidad';
            $map['category'] = 'categoria';
            $map['brand'] = null;
            $map['description'] = 'descripcion';
            $map['sku'] = null;
            $map['active'] = null;
            $map['image_columns'] = array_values(array_filter([
                $hasOnlineImages ? '_tmpos_images' : null,
                $applianceImageColumn,
            ]));
            $map['columns'] = array_values(array_unique(array_merge(
                $map['columns'], ['precio_venta', 'cantidad', 'categoria', 'descripcion', 'color', 'capacidad']
            )));
            foreach ($rows as &$row) {
                $key = (string) ($row['uid'] ?? '');
                $fallback = 'id:' . (string) ($row['id'] ?? '');
                $byName = 'name:' . mb_strtolower(trim((string) ($row['nombre'] ?? '')));
                $stock = $inventory[$key] ?? $inventory[$fallback] ?? $inventory[$byName] ?? null;
                $row['precio_venta'] = $stock['price'] ?? null;
                $row['cantidad'] = (int) ($stock['quantity'] ?? 0);
                $row['categoria'] = 'Electrónicos';
                $row['_store_kind'] = 'electronic';
                $row['descripcion'] = 'Electrónico disponible por serial. ' . ($row['cantidad'] === 1
                    ? '1 unidad disponible.'
                    : $row['cantidad'] . ' unidades disponibles.');
                $row['color'] = implode(', ', $stock['colors'] ?? []);
                $row['capacidad'] = implode(', ', $stock['capacities'] ?? []);
            }
            unset($row);
        }
        return ['rows' => $rows, 'map' => $map];
    }

    private function withOnlineProductImages(PDO $db, string $table, array $rows): array
    {
        $exists = $db->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND lower(name)='datos_config' LIMIT 1");
        $exists->execute();
        if (!$exists->fetchColumn()) {
            return [$rows, false];
        }
        $metadataColumns = array_map(
            'mb_strtolower',
            array_column($db->query('PRAGMA table_info("datos_config")')->fetchAll(), 'name')
        );
        if (!in_array('nombre', $metadataColumns, true) || !in_array('valor', $metadataColumns, true)) {
            return [$rows, false];
        }

        $prefix = '__tmpos_imagen__:' . mb_strtolower($table) . ':';
        $metadata = [];
        foreach ($db->query('SELECT nombre, valor FROM "datos_config"')->fetchAll() as $item) {
            $name = trim((string) ($item['nombre'] ?? ''));
            if (str_starts_with(mb_strtolower($name), $prefix)) {
                $metadata[mb_strtolower($name)] = $item['valor'] ?? '';
            }
        }
        if ($metadata === []) {
            return [$rows, false];
        }

        $matched = false;
        foreach ($rows as &$row) {
            $keys = array_values(array_filter([
                trim((string) ($row['uid'] ?? '')),
                isset($row['id']) ? (string) $row['id'] : '',
            ], static fn (string $value): bool => $value !== ''));
            foreach ($keys as $key) {
                $name = $prefix . mb_strtolower($key);
                if (array_key_exists($name, $metadata)) {
                    $row['_tmpos_images'] = $metadata[$name];
                    $matched = true;
                    break;
                }
            }
        }
        unset($row);
        return [$rows, $matched];
    }

    private function lookupNames(PDO $db, string $table): array
    {
        $exists = $db->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND lower(name)=lower(?) LIMIT 1");
        $exists->execute([$table]);
        if (!$exists->fetchColumn()) {
            return [];
        }
        $lookup = [];
        foreach ($db->query('SELECT id,nombre FROM ' . Support::quoteIdentifier($table))->fetchAll() as $row) {
            $lookup[(string) $row['id']] = trim((string) $row['nombre']);
        }
        return $lookup;
    }

    private function phoneInventory(PDO $db): array
    {
        $columns = array_column($db->query('PRAGMA table_info("imei")')->fetchAll(), 'name');
        if ($columns === []) {
            return [];
        }
        $rows = $db->query('SELECT * FROM "imei" ORDER BY id DESC LIMIT 5000')->fetchAll();
        $inventory = [];
        foreach ($rows as $row) {
            $state = mb_strtoupper(trim((string) ($row['estado'] ?? 'DISPONIBLE')));
            if (!in_array($state, ['', 'DISPONIBLE', 'AVAILABLE', 'EN INVENTARIO'], true)) {
                continue;
            }
            $keys = array_values(array_filter([
                trim((string) ($row['telefono_uid'] ?? '')),
                isset($row['id_equi']) ? 'id:' . (string) $row['id_equi'] : '',
                trim((string) ($row['equipo'] ?? '')) !== ''
                    ? 'name:' . mb_strtolower(trim((string) $row['equipo']))
                    : '',
            ]));
            foreach ($keys as $key) {
                $inventory[$key] ??= ['quantity' => 0, 'colors' => [], 'capacities' => []];
                $inventory[$key]['quantity']++;
                $color = trim((string) ($row['color'] ?? ''));
                if ($color !== '') $inventory[$key]['colors'][$color] = $color;
                $capacity = trim((string) ($row['capacidad'] ?? ''));
                if ($capacity !== '') $inventory[$key]['capacities'][$capacity] = $capacity;
            }
        }
        foreach ($inventory as &$item) {
            $item['colors'] = array_values($item['colors']);
            $item['capacities'] = array_values($item['capacities']);
        }
        unset($item);
        return $inventory;
    }

    private function applianceInventory(PDO $db): array
    {
        $columns = array_column($db->query('PRAGMA table_info("serial")')->fetchAll(), 'name');
        if ($columns === []) {
            return [];
        }
        $rows = $db->query('SELECT * FROM "serial" ORDER BY id DESC LIMIT 5000')->fetchAll();
        $inventory = [];
        foreach ($rows as $row) {
            $state = mb_strtoupper(trim((string) ($row['estado'] ?? 'DISPONIBLE')));
            if (!in_array($state, ['', 'DISPONIBLE', 'AVAILABLE', 'EN INVENTARIO'], true)) {
                continue;
            }
            $keys = array_values(array_filter([
                trim((string) ($row['equipo_uid'] ?? '')),
                isset($row['id_equi']) ? 'id:' . (string) $row['id_equi'] : '',
                trim((string) ($row['equipo'] ?? '')) !== ''
                    ? 'name:' . mb_strtolower(trim((string) $row['equipo']))
                    : '',
            ]));
            foreach ($keys as $key) {
                $inventory[$key] ??= ['quantity' => 0, 'colors' => [], 'capacities' => [], 'prices' => []];
                $inventory[$key]['quantity']++;
                $color = trim((string) ($row['color'] ?? ''));
                if ($color !== '') $inventory[$key]['colors'][$color] = $color;
                $capacity = trim((string) ($row['capacidad'] ?? ''));
                if ($capacity !== '') $inventory[$key]['capacities'][$capacity] = $capacity;
                $price = (float) ($row['precio_venta'] ?? 0);
                if ($price > 0) $inventory[$key]['prices'][] = $price;
            }
        }
        foreach ($inventory as &$item) {
            $item['colors'] = array_values($item['colors']);
            $item['capacities'] = array_values($item['capacities']);
            $item['price'] = $item['prices'] === [] ? null : min($item['prices']);
            unset($item['prices']);
        }
        unset($item);
        return $inventory;
    }

    private function withCompanyIdentity(array $store): array
    {
        try {
            $project = $this->projects->findActive((string) $store['project_uid']);
            $db = $this->schema->connection($project);
            $exists = $db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND lower(name)='empresa' LIMIT 1")->fetchColumn();
            if (!$exists) {
                return $store;
            }
            $company = $db->query('SELECT * FROM "empresa" ORDER BY id ASC LIMIT 1')->fetch();
            if (!$company) {
                return $store;
            }
            $logo = $this->publicImage((string) $store['project_uid'], $company['logo'] ?? '');
            if ($logo !== '') {
                $store['logo_url'] = $logo;
            }
            $store['company_name'] = trim((string) ($company['nombre'] ?? ''));
            $companyEmail = mb_strtolower(trim((string) ($company['email'] ?? $company['correo'] ?? '')));
            if (!filter_var((string) ($store['email'] ?? ''), FILTER_VALIDATE_EMAIL)
                && filter_var($companyEmail, FILTER_VALIDATE_EMAIL)) {
                $store['email'] = $companyEmail;
            }
        } catch (\Throwable) {
            // Company identity is an enhancement; the configured storefront remains a safe fallback.
        }
        return $store;
    }

    private function mapProduct(string $projectUid, array $row, array $map, bool $withSpecifications = false): ?array
    {
        $name = trim((string) ($row[$map['name']] ?? ''));
        $uid = trim((string) ($row['uid'] ?? ''));
        if ($name === '' || $uid === '') {
            return null;
        }
        $images = $this->productImages($projectUid, $row, $map['image_columns']);
        $stock = $map['stock'] ? (float) ($row[$map['stock']] ?? 0) : null;
        $rawPrice = $map['price'] ? ($row[$map['price']] ?? null) : null;
        $rawComparePrice = $map['compare_price'] ? ($row[$map['compare_price']] ?? null) : null;
        $price = $rawPrice === null || $rawPrice === '' ? null : (float) $rawPrice;
        $comparePrice = $rawComparePrice === null || $rawComparePrice === '' ? null : (float) $rawComparePrice;
        if ($price === null || $comparePrice === null || $comparePrice <= $price) {
            $comparePrice = null;
        }
        $product = [
            'uid' => $uid,
            'name' => $name,
            'description' => $map['description'] ? trim((string) ($row[$map['description']] ?? '')) : '',
            'price' => $price,
            'compare_price' => $comparePrice,
            'image' => $images[0] ?? '',
            'images' => $images,
            'category' => $map['category'] ? trim((string) ($row[$map['category']] ?? '')) : '',
            'brand' => $map['brand'] ? trim((string) ($row[$map['brand']] ?? '')) : '',
            'stock' => $stock,
            'available' => $stock === null || $stock > 0,
            'sku' => $map['sku'] ? trim((string) ($row[$map['sku']] ?? '')) : '',
            'kind' => trim((string) ($row['_store_kind'] ?? 'product')),
        ];
        if ($withSpecifications) {
            $product['specifications'] = $this->productSpecifications($row, $map['columns']);
        }
        return $product;
    }

    private function productImages(string $projectUid, array $row, array $columns): array
    {
        $images = [];
        foreach ($columns as $column) {
            $raw = $row[$column] ?? null;
            $values = [];
            if (is_string($raw) && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $values = $decoded;
                } elseif (str_contains($raw, '|')) {
                    $values = explode('|', $raw);
                } else {
                    $values = [$raw];
                }
            } elseif (is_array($raw)) {
                $values = $raw;
            }
            foreach ($values as $value) {
                if (is_array($value)) {
                    $value = $value['url'] ?? $value['uid'] ?? '';
                }
                $image = $this->publicImage($projectUid, $value);
                if ($image !== '') {
                    $images[$image] = $image;
                }
            }
        }
        return array_values($images);
    }

    private function productSpecifications(array $row, array $columns): array
    {
        $definitions = [
            'Marca' => ['marca', 'brand'],
            'Modelo' => ['modelo', 'model'],
            'Color' => ['color'],
            'Capacidad' => ['capacidad', 'capacity'],
            'Almacenamiento' => ['almacenamiento', 'storage', 'rom'],
            'Memoria RAM' => ['ram', 'memoria_ram'],
            'Pantalla' => ['pantalla', 'screen'],
            'Procesador' => ['procesador', 'processor'],
            'Condición' => ['condicion', 'condition'],
            'Garantía' => ['garantia', 'warranty'],
            'Talla' => ['talla', 'size'],
            'Presentación' => ['presentacion', 'presentation'],
            'Sabor' => ['sabor', 'flavor'],
            'Peso' => ['peso', 'weight'],
            'Dimensiones' => ['dimensiones', 'dimensions'],
        ];
        $specifications = [];
        foreach ($definitions as $label => $candidates) {
            $column = $this->firstColumn($columns, $candidates);
            if ($column === null || !is_scalar($row[$column] ?? null)) {
                continue;
            }
            $value = trim((string) $row[$column]);
            if ($value !== '') {
                $specifications[] = ['label' => $label, 'value' => mb_substr($value, 0, 240)];
            }
        }
        return $specifications;
    }

    private function resolveTable(array $project, ?string $preferred, array $candidates): ?string
    {
        $tables = array_column($this->schema->tables($project), 'name');
        $lookup = array_change_key_case(array_combine($tables, $tables) ?: [], CASE_LOWER);
        if ($preferred && isset($lookup[strtolower($preferred)])) {
            return $lookup[strtolower($preferred)];
        }
        foreach ($candidates as $candidate) {
            if (isset($lookup[strtolower($candidate)])) {
                return $lookup[strtolower($candidate)];
            }
        }
        return null;
    }

    private function firstColumn(array $columns, array $candidates): ?string
    {
        $lookup = array_change_key_case(array_combine($columns, $columns) ?: [], CASE_LOWER);
        foreach ($candidates as $candidate) {
            if (isset($lookup[strtolower($candidate)])) {
                return $lookup[strtolower($candidate)];
            }
        }
        return null;
    }

    private function isPublicProduct(array $row, ?string $activeColumn): bool
    {
        if ($activeColumn === null) {
            return true;
        }
        $value = mb_strtolower(trim((string) ($row[$activeColumn] ?? '')));
        return !in_array($value, ['0', 'false', 'inactivo', 'inactive', 'eliminado', 'deleted', 'oculto', 'hidden'], true);
    }

    private function publicImage(string $projectUid, mixed $value): string
    {
        $value = trim((string) $value);
        if (preg_match('/^fil_[A-Za-z0-9_-]+$/', $value)) {
            return rtrim((string) $this->config['url'], '/') . '/api/' . rawurlencode($projectUid) . '/storage/' . rawurlencode($value);
        }
        if (preg_match('#^/(?:api|storage)/[A-Za-z0-9_./-]+$#', $value)) {
            return $value;
        }
        return filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
    }

    private function heroImagesInput(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : preg_split('/[\r\n,]+/', $value);
        }
        if (!is_array($value)) {
            return [];
        }
        $images = [];
        foreach ($value as $item) {
            $item = trim((string) $item);
            if (
                preg_match('/^fil_[A-Za-z0-9_-]+$/', $item)
                || preg_match('#^/(?:api|storage)/[A-Za-z0-9_./-]+$#', $item)
                || filter_var($item, FILTER_VALIDATE_URL)
            ) {
                $images[$item] = $item;
            }
            if (count($images) >= 8) break;
        }
        return array_values($images);
    }

    private function withPresentation(array $store): array
    {
        $raw = $store['hero_images'] ?? [];
        if (is_string($raw)) {
            $raw = json_decode($raw, true) ?: [];
        }
        $images = [];
        foreach (is_array($raw) ? $raw : [] as $value) {
            $url = $this->publicImage((string) $store['project_uid'], $value);
            if ($url !== '') $images[$url] = $url;
        }
        $store['hero_images'] = array_values($images);
        $store['promo_image'] = $this->publicImage((string) $store['project_uid'], $store['promo_image'] ?? '');
        $store['resolved_business_type'] = $this->resolveBusinessType($store);
        return $store;
    }

    private function resolveBusinessType(array $store): string
    {
        $configured = (string) ($store['business_type'] ?? 'auto');
        if (in_array($configured, ['electronics', 'restaurant', 'general'], true)) {
            return $configured;
        }
        try {
            $project = $this->projects->findActive((string) $store['project_uid']);
            $tables = array_map(
                static fn (string $table): string => mb_strtolower($table),
                array_column($this->schema->tables($project), 'name')
            );
            $tableSet = array_fill_keys($tables, true);
            foreach (['mesas', 'comandas', 'ordenes_restaurante', 'pedidos_restaurante', 'platos', 'menu_items', 'cocina'] as $signal) {
                if (isset($tableSet[$signal])) {
                    return 'restaurant';
                }
            }
            foreach (['imei', 'telefonos', 'seriales', 'equipos'] as $signal) {
                if (isset($tableSet[$signal])) {
                    return 'electronics';
                }
            }
            $identity = mb_strtolower(trim(
                (string) ($store['store_name'] ?? '') . ' '
                . (string) ($store['project_name'] ?? '') . ' '
                . (string) ($project['description'] ?? '')
            ));
            if (preg_match('/\b(restaurante|restaurant|cafeteria|cafetería|pizzeria|pizzería|comedor|food|burger|pizza)\b/u', $identity)) {
                return 'restaurant';
            }
            if (preg_match('/\b(celular|celulares|electronica|electrónica|tecnologia|tecnología|mobile|phone)\b/u', $identity)) {
                return 'electronics';
            }
        } catch (\Throwable) {
        }
        return 'general';
    }

    private function normalizeVerification(string $value): string
    {
        return mb_strtolower((string) preg_replace('/[^A-Za-z0-9@.+]/u', '', trim($value)));
    }

    private function limited(mixed $value, int $max): string
    {
        return mb_substr(trim((string) $value), 0, $max);
    }

    private function urlOrEmpty(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' && filter_var($value, FILTER_VALIDATE_URL) ? $value : null;
    }

    private function emailOrEmpty(mixed $value): ?string
    {
        $value = strtolower(trim((string) $value));
        return $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    }

    private function imageReferenceOrEmpty(mixed $value): ?string
    {
        $value = trim((string) $value);
        if (
            preg_match('/^fil_[A-Za-z0-9_-]+$/', $value)
            || preg_match('#^/(?:api|storage)/[A-Za-z0-9_./-]+$#', $value)
            || filter_var($value, FILTER_VALIDATE_URL)
        ) {
            return $value;
        }
        return null;
    }

    private function storeLinkOrEmpty(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (str_starts_with($value, '/') || filter_var($value, FILTER_VALIDATE_URL)) {
            return mb_substr($value, 0, 500);
        }
        return null;
    }
}
