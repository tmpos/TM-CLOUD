<?php

declare(strict_types=1);

$root = dirname(__DIR__);
foreach ([
    'app/Core/Support.php',
    'app/Core/Database.php',
    'app/Core/Auth.php',
    'app/Core/Http.php',
    'app/Services/LogService.php',
    'app/Services/ProjectService.php',
    'app/Services/SchemaService.php',
    'app/Services/RecordService.php',
    'app/Services/StorefrontService.php',
    'app/Services/CredentialCipher.php',
    'app/Services/StorefrontCommerceService.php',
] as $file) {
    require_once $root . '/' . $file;
}

use App\Core\Database;
use App\Services\LogService;
use App\Services\ProjectService;
use App\Services\RecordService;
use App\Services\SchemaService;
use App\Services\StorefrontService;
use App\Services\CredentialCipher;
use App\Services\StorefrontCommerceService;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$storage = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tmpbase-storefront-' . bin2hex(random_bytes(6));
foreach ([$storage, "$storage/projects", "$storage/backups", "$storage/uploads"] as $directory) {
    mkdir($directory, 0775, true);
}
$database = $storage . DIRECTORY_SEPARATOR . 'central.sqlite';
$config = ['storage' => $storage, 'database' => $database, 'url' => 'https://api.example.test'];

try {
    $db = Database::connect($database);
    Database::migrate($db);
    $logs = new LogService($db);
    $projects = new ProjectService($db, $config, $logs);
    $schema = new SchemaService($projects, $logs);
    $records = new RecordService($schema, $logs);
    $storefronts = new StorefrontService($db, $config, $projects, $schema, $records);
    $commerce = new StorefrontCommerceService($db, $config, new CredentialCipher($storage), $storefronts, $logs);

    $project = $projects->create(['name' => 'Tienda Demo']);
    $store = $storefronts->findForProject($project['uid']);
    check($store['slug'] === 'tienda-demo', 'The storefront slug was not created automatically.');
    check((int) $store['enabled'] === 1, 'The storefront must be enabled by default.');
    $store = $storefronts->update($project['uid'], [
        'enabled' => '1',
        'slug' => $store['slug'],
        'store_name' => $store['store_name'],
        'hero_background_mode' => 'carousel',
        'hero_background_color' => '#102030',
        'hero_images' => "https://cdn.example.test/hero-1.jpg\nhttps://cdn.example.test/hero-2.jpg",
        'hero_overlay_opacity' => '45',
        'hero_carousel_interval' => '5000',
        'pickup_enabled' => '1',
        'email' => 'pedidos@tienda.test',
    ]);
    check($store['hero_background_mode'] === 'carousel', 'Hero carousel mode was not saved.');
    check(count($store['hero_images']) === 2, 'Hero carousel images were not normalized.');
    check((int) $store['hero_overlay_opacity'] === 45, 'Hero overlay setting was not saved.');

    $schema->createTable($project, 'productos', [
        ['name' => 'nombre', 'type' => 'TEXT', 'required' => true],
        ['name' => 'descripcion', 'type' => 'TEXT'],
        ['name' => 'precio_venta', 'type' => 'REAL'],
        ['name' => 'costo', 'type' => 'REAL'],
        ['name' => 'categoria', 'type' => 'TEXT'],
        ['name' => 'existencia', 'type' => 'REAL'],
        ['name' => 'activo', 'type' => 'BOOLEAN'],
        ['name' => 'imagen', 'type' => 'IMAGE'],
        ['name' => 'imagen2', 'type' => 'IMAGE'],
        ['name' => 'modelo', 'type' => 'TEXT'],
        ['name' => 'color', 'type' => 'TEXT'],
    ]);
    $createdProduct = $records->create($project, 'productos', [
        'nombre' => 'Teléfono de prueba',
        'descripcion' => 'Equipo publicado',
        'precio_venta' => 19990,
        'costo' => 11000,
        'categoria' => 'Celulares',
        'existencia' => 3,
        'activo' => 1,
        'imagen' => 'https://cdn.example.test/producto-frente.jpg',
        'imagen2' => 'https://cdn.example.test/producto-dorso.jpg',
        'modelo' => 'Demo Pro',
        'color' => 'Negro',
    ]);
    $records->create($project, 'productos', [
        'nombre' => 'Teléfono relacionado',
        'descripcion' => 'Otro equipo de la categoría',
        'precio_venta' => 14990,
        'costo' => 9000,
        'categoria' => 'Celulares',
        'existencia' => 2,
        'activo' => 1,
    ]);

    $schema->createTable($project, 'clientes', [
        ['name' => 'nombre', 'type' => 'TEXT'],
        ['name' => 'cedula', 'type' => 'TEXT'],
        ['name' => 'telefono', 'type' => 'TEXT'],
        ['name' => 'email', 'type' => 'EMAIL'],
        ['name' => 'direccion', 'type' => 'TEXT'],
    ]);
    $customer = $records->create($project, 'clientes', [
        'nombre' => 'Cliente Demo',
        'cedula' => '00112345678',
        'email' => 'cliente@example.test',
    ]);
    $registrationWithoutEmailBlocked = false;
    try {
        $storefronts->registerCustomer($store, [
            'nombre' => 'CLIENTE SIN CORREO',
            'cedula' => '40276543210',
        ]);
    } catch (InvalidArgumentException) {
        $registrationWithoutEmailBlocked = true;
    }
    check($registrationWithoutEmailBlocked, 'Customer registration accepted an empty email.');
    $registeredCustomer = $storefronts->registerCustomer($store, [
        'nombre' => '  nuevo cliente  ',
        'cedula' => '402-1234567-8',
        'telefono' => '809-555-0101',
        'email' => 'NUEVO@EXAMPLE.TEST',
        'direccion' => 'Santo Domingo',
    ]);
    check($registeredCustomer['nombre'] === 'NUEVO CLIENTE', 'Customer registration did not uppercase the name.');
    check($registeredCustomer['telefono'] === '809-555-0101', 'Customer registration did not save the phone.');
    check($registeredCustomer['email'] === 'nuevo@example.test', 'Customer registration did not normalize the email.');
    check($registeredCustomer['direccion'] === 'Santo Domingo', 'Customer registration did not save the address.');
    $activation = $storefronts->beginCustomerVerification($store, $registeredCustomer);
    check(!$storefronts->customerIsVerified($store, $registeredCustomer), 'A new customer was active before OTP verification.');
    $storefronts->verifyCustomerOtp($store, $registeredCustomer, $activation['otp']);
    check($storefronts->customerIsVerified($store, $registeredCustomer), 'OTP verification did not activate the customer.');
    check(
        $storefronts->locateCustomerByDocument($store, '40212345678')['customer']['uid'] === $registeredCustomer['uid'],
        'A registered customer could not sign in with the document.'
    );
    $duplicateRegistrationBlocked = false;
    try {
        $storefronts->registerCustomer($store, [
            'nombre' => 'CLIENTE DUPLICADO',
            'cedula' => '40212345678',
            'email' => 'duplicado@example.test',
        ]);
    } catch (InvalidArgumentException) {
        $duplicateRegistrationBlocked = true;
    }
    check($duplicateRegistrationBlocked, 'Customer registration accepted a duplicate document.');

    $schema->createTable($project, 'facturas', [
        ['name' => 'numero', 'type' => 'TEXT', 'required' => true],
        ['name' => 'cliente_uid', 'type' => 'TEXT'],
        ['name' => 'productos', 'type' => 'JSON'],
        ['name' => 'total', 'type' => 'REAL'],
    ]);
    $invoice = $records->create($project, 'facturas', [
        'numero' => 'FAC-1001',
        'cliente_uid' => $customer['uid'],
        'productos' => [['nombre' => 'Teléfono de prueba', 'cantidad' => 1, 'precio' => 19990]],
        'total' => 19990,
    ]);
    $records->create($project, 'facturas', [
        'numero' => 'FAC-1002',
        'cliente_uid' => $customer['uid'],
        'productos' => [['nombre' => 'Accesorio', 'cantidad' => 1, 'precio' => 500]],
        'total' => 500,
    ]);
    $unrelatedInvoice = $records->create($project, 'facturas', [
        'numero' => 'FAC-PRIVATE',
        'cliente_uid' => 'rec_other_customer',
        'productos' => [['nombre' => 'Producto privado', 'cantidad' => 1, 'precio' => 999]],
        'total' => 999,
    ]);
    $schema->createTable($project, 'ordenes_taller', [
        ['name' => 'no_orden', 'type' => 'TEXT'],
        ['name' => 'nombre', 'type' => 'TEXT'],
        ['name' => 'email', 'type' => 'EMAIL'],
        ['name' => 'equipo', 'type' => 'TEXT'],
        ['name' => 'fallas', 'type' => 'TEXT'],
        ['name' => 'estado', 'type' => 'TEXT'],
        ['name' => 'total', 'type' => 'REAL'],
    ]);
    $workshopOrder = $records->create($project, 'ordenes_taller', [
        'no_orden' => 'OT-1001',
        'nombre' => 'Cliente Demo',
        'email' => 'cliente@example.test',
        'equipo' => 'IPHONE 11',
        'fallas' => 'No enciende',
        'estado' => 'EN_PROCESO',
        'total' => 2500,
    ]);
    $records->create($project, 'ordenes_taller', [
        'no_orden' => 'OT-PRIVATE',
        'nombre' => 'Otro Cliente',
        'email' => 'otro@example.test',
        'equipo' => 'Equipo privado',
        'estado' => 'RECIBIDO',
        'total' => 9999,
    ]);

    $catalog = $storefronts->catalog($store);
    check(count($catalog['products']) === 2, 'The storefront catalog did not expose the products.');
    $publicProduct = array_values(array_filter(
        $catalog['products'],
        static fn (array $item): bool => $item['uid'] === $createdProduct['uid']
    ))[0] ?? null;
    check(is_array($publicProduct), 'The product mapping is incorrect.');
    check(!array_key_exists('costo', $publicProduct), 'A private cost field was exposed.');
    check(count($publicProduct['images']) === 2, 'The product gallery did not map multiple images.');
    $filteredCatalog = $storefronts->catalog($store, '', 'Celulares', [
        'min_price' => '16000',
        'availability' => 'in_stock',
        'sort' => 'price_asc',
    ]);
    check(count($filteredCatalog['products']) === 1, 'Category and price filters returned an incorrect result.');
    check($filteredCatalog['products'][0]['uid'] === $createdProduct['uid'], 'The filtered catalog returned the wrong product.');

    $detail = $storefronts->productDetail($store, $createdProduct['uid']);
    check($detail['product']['name'] === 'Teléfono de prueba', 'The product detail is incorrect.');
    check(count($detail['related']) === 1, 'Related products were not returned.');
    check(in_array('Color', array_column($detail['product']['specifications'], 'label'), true), 'Safe specifications were not mapped.');

    $commerce->saveMethods($project['uid'], ['payments' => [
        'cash' => ['enabled' => '1', 'display_name' => 'Efectivo al retirar'],
        'stripe' => [
            'enabled' => '1',
            'test_mode' => '1',
            'display_name' => 'Tarjeta',
            'publishable_key' => 'pk_test_demo',
            'secret_key' => 'sk_test_must_never_be_public',
        ],
    ]]);
    $methods = $commerce->adminMethods($project['uid']);
    $stripe = array_values(array_filter($methods, static fn (array $method): bool => $method['provider'] === 'stripe'))[0];
    check($stripe['credentials_configured'] === true, 'The Stripe secret was not encrypted and stored.');
    check(!array_key_exists('credentials_encrypted', $stripe), 'Encrypted credentials leaked through the admin method.');
    $encrypted = (string) $db->query("SELECT credentials_encrypted FROM storefront_payment_methods WHERE provider='stripe'")->fetchColumn();
    check($encrypted !== '' && !str_contains($encrypted, 'sk_test_must_never_be_public'), 'The Stripe secret was stored in plain text.');

    $checkoutWithoutEmailBlocked = false;
    try {
        $commerce->createOrder($store, [
            'customer_name' => 'Cliente Checkout',
            'customer_phone' => '8095550101',
        ]);
    } catch (InvalidArgumentException) {
        $checkoutWithoutEmailBlocked = true;
    }
    check($checkoutWithoutEmailBlocked, 'Checkout accepted an empty customer email.');

    $order = $commerce->createOrder($store, [
        'customer_name' => 'Cliente Checkout',
        'customer_phone' => '8095550101',
        'customer_email' => 'checkout@example.test',
        'delivery_method' => 'pickup',
        'payment_provider' => 'cash',
        'items' => [['uid' => $createdProduct['uid'], 'quantity' => 2]],
    ]);
    check((float) $order['total'] === 39980.0, 'The checkout did not use the server-side product price.');
    check(count($order['items']) === 1 && (int) $order['items'][0]['quantity'] === 2, 'The checkout order items are incorrect.');
    $payment = $commerce->beginPayment($store, $order);
    check($payment['type'] === 'confirmation', 'Cash checkout must return a local confirmation.');

    $schema->createTable($project, 'empresa', [
        ['name' => 'nombre', 'type' => 'TEXT'],
        ['name' => 'logo', 'type' => 'TEXT'],
    ]);
    $records->create($project, 'empresa', [
        'nombre' => 'Empresa con logo',
        'logo' => 'fil_company_logo_123',
    ]);
    $schema->createTable($project, 'categorias', [
        ['name' => 'nombre', 'type' => 'TEXT'],
    ]);
    $accessoryCategory = $records->create($project, 'categorias', ['nombre' => 'Cargadores']);
    $schema->createTable($project, 'marcas', [
        ['name' => 'nombre', 'type' => 'TEXT'],
    ]);
    $brand = $records->create($project, 'marcas', ['nombre' => 'Demo Brand']);
    $schema->createTable($project, 'accesorios', [
        ['name' => 'nombre', 'type' => 'TEXT'],
        ['name' => 'precio_venta', 'type' => 'REAL'],
        ['name' => 'cantidad', 'type' => 'INTEGER'],
        ['name' => 'categoria', 'type' => 'INTEGER'],
        ['name' => 'marca', 'type' => 'INTEGER'],
        ['name' => 'imagen', 'type' => 'IMAGE'],
    ]);
    $records->create($project, 'accesorios', [
        'nombre' => 'Cargador rápido',
        'precio_venta' => 1500,
        'cantidad' => 8,
        'categoria' => $accessoryCategory['id'],
        'marca' => $brand['id'],
    ]);
    $schema->createTable($project, 'telefonos', [
        ['name' => 'nombre', 'type' => 'TEXT'],
        ['name' => 'precio_minimo', 'type' => 'REAL'],
        ['name' => 'precio_venta', 'type' => 'REAL'],
        ['name' => 'imagen', 'type' => 'IMAGE'],
    ]);
    $phone = $records->create($project, 'telefonos', [
        'nombre' => 'Phone IMEI Demo',
        'precio_minimo' => 25990,
        'precio_venta' => 31990,
        'imagen' => 'fil_phone_image_123',
    ]);
    $schema->createTable($project, 'datos_config', [
        ['name' => 'nombre', 'type' => 'TEXT'],
        ['name' => 'valor', 'type' => 'TEXT'],
        ['name' => 'identificadordb', 'type' => 'TEXT'],
    ]);
    $phoneImagesKey = '__tmpos_imagen__:telefonos:' . $phone['uid'];
    $records->create($project, 'datos_config', [
        'nombre' => $phoneImagesKey,
        'valor' => json_encode(['fil_phone_image_123', 'fil_phone_image_back_456']),
        'identificadordb' => $phoneImagesKey,
    ]);
    $schema->createTable($project, 'imei', [
        ['name' => 'nombre', 'type' => 'TEXT'],
        ['name' => 'telefono_uid', 'type' => 'TEXT'],
        ['name' => 'id_equi', 'type' => 'INTEGER'],
        ['name' => 'equipo', 'type' => 'TEXT'],
        ['name' => 'precio_venta', 'type' => 'REAL'],
        ['name' => 'color', 'type' => 'TEXT'],
        ['name' => 'capacidad', 'type' => 'TEXT'],
        ['name' => 'estado', 'type' => 'TEXT'],
    ]);
    foreach ([
        ['imei-1', 26990, 'Negro', '128GB', 'DISPONIBLE'],
        ['imei-2', 27990, 'Azul', '256GB', 'DISPONIBLE'],
        ['imei-3', 28990, 'Rojo', '256GB', 'VENDIDO'],
    ] as [$imei, $price, $color, $capacity, $status]) {
        $records->create($project, 'imei', [
            'nombre' => $imei,
            'telefono_uid' => $phone['uid'],
            'id_equi' => $phone['id'],
            'equipo' => $phone['nombre'],
            'precio_venta' => $price,
            'color' => $color,
            'capacidad' => $capacity,
            'estado' => $status,
        ]);
    }
    $schema->createTable($project, 'electrodomesticos', [
        ['name' => 'nombre', 'type' => 'TEXT'],
        ['name' => 'imagen', 'type' => 'IMAGE'],
    ]);
    $electronic = $records->create($project, 'electrodomesticos', [
        'nombre' => 'Televisor Demo',
    ]);
    $electronicImagesKey = '__tmpos_imagen__:electrodomesticos:' . $electronic['uid'];
    $records->create($project, 'datos_config', [
        'nombre' => $electronicImagesKey,
        'valor' => json_encode(['fil_tv_front_123', 'fil_tv_back_456']),
        'identificadordb' => $electronicImagesKey,
    ]);
    $schema->createTable($project, 'serial', [
        ['name' => 'nombre', 'type' => 'TEXT'],
        ['name' => 'id_equi', 'type' => 'INTEGER'],
        ['name' => 'equipo_uid', 'type' => 'TEXT'],
        ['name' => 'equipo', 'type' => 'TEXT'],
        ['name' => 'precio_venta', 'type' => 'REAL'],
        ['name' => 'color', 'type' => 'TEXT'],
        ['name' => 'capacidad', 'type' => 'TEXT'],
        ['name' => 'estado', 'type' => 'TEXT'],
    ]);
    foreach ([
        ['SERIAL-TV-1', 45990, 'Negro', '55 pulgadas', 'DISPONIBLE'],
        ['SERIAL-TV-2', 39990, 'Negro', '55 pulgadas', 'VENDIDO'],
    ] as [$serial, $price, $color, $capacity, $status]) {
        $records->create($project, 'serial', [
            'nombre' => $serial,
            'id_equi' => $electronic['id'],
            'equipo_uid' => $electronic['uid'],
            'equipo' => $electronic['nombre'],
            'precio_venta' => $price,
            'color' => $color,
            'capacidad' => $capacity,
            'estado' => $status,
        ]);
    }
    $mobileCatalog = $storefronts->catalog($store);
    $phoneProduct = array_values(array_filter(
        $mobileCatalog['products'],
        static fn (array $item): bool => $item['uid'] === $phone['uid']
    ))[0] ?? null;
    check(is_array($phoneProduct), 'Phone models were not merged into the storefront catalog.');
    check((int) $phoneProduct['stock'] === 2, 'Phone stock was not calculated from available IMEIs.');
    check((float) $phoneProduct['price'] === 31990.0, 'Phone price did not use telefonos.precio_venta.');
    check($phoneProduct['category'] === 'Teléfonos', 'Phone category is incorrect.');
    check(
        str_contains((string) $phoneProduct['image'], '/api/' . $project['uid'] . '/storage/fil_phone_image_123'),
        'Phone image was not loaded from telefonos.imagen.'
    );
    check(count($phoneProduct['images']) === 2, 'Phone gallery was not loaded from datos_config.');
    check(
        str_contains((string) $phoneProduct['images'][1], '/api/' . $project['uid'] . '/storage/fil_phone_image_back_456'),
        'The second server-side phone image was not exposed by the storefront.'
    );
    check(in_array('Cargadores', $mobileCatalog['categories'], true), 'Accessory category IDs were not resolved to names.');
    $electronicProduct = array_values(array_filter(
        $mobileCatalog['products'],
        static fn (array $item): bool => $item['uid'] === $electronic['uid']
    ))[0] ?? null;
    check(is_array($electronicProduct), 'Electronics were not merged into the storefront catalog.');
    check($electronicProduct['category'] === 'Electrónicos', 'Electronic category label is incorrect.');
    check((int) $electronicProduct['stock'] === 1, 'Electronic stock did not use available serials.');
    check((float) $electronicProduct['price'] === 45990.0, 'Electronic price did not use an available serial.');
    check(count($electronicProduct['images']) === 2, 'Electronic gallery was not loaded from datos_config.');
    $companyStore = $storefronts->findBySlug($store['slug']);
    check(str_contains((string) $companyStore['logo_url'], 'fil_company_logo_123'), 'Company logo did not override storefront logo.');
    $phoneDetail = $storefronts->productDetail($store, $phone['uid']);
    check((int) $phoneDetail['product']['stock'] === 2, 'Phone detail did not preserve IMEI stock.');

    $found = $storefronts->locateInvoice($store, 'FAC-1001', 'cliente@example.test');
    check($found['invoice']['uid'] === $invoice['uid'], 'Invoice ownership verification failed.');
    $numberOnly = $storefronts->locateInvoiceByNumber($store, 'FAC-1001');
    check($numberOnly['invoice']['uid'] === $invoice['uid'], 'Invoice number-only portal access failed.');
    $documentAccess = $storefronts->locateCustomerByDocument($store, '001-1234567-8');
    check($documentAccess['customer']['uid'] === $customer['uid'], 'Customer document portal access failed.');
    $documentPortal = $storefronts->customerPortalForCustomer($store, $documentAccess['customer']);
    check(count($documentPortal['invoices']) === 2, 'Document portal did not group customer purchases.');
    check(count($documentPortal['orders']) === 1, 'Document portal did not group workshop orders.');
    $portal = $storefronts->customerPortal($store, $numberOnly['invoice']);
    check(count($portal['invoices']) === 2, 'Customer portal did not group customer purchases.');
    check((int) $portal['stats']['purchases'] === 2, 'Customer portal purchase stats are incorrect.');
    check(count($portal['orders']) === 1, 'Customer portal did not link workshop orders.');
    check(
        $storefronts->portalWorkshopOrder($portal, $workshopOrder['uid'])['no_orden'] === 'OT-1001',
        'Customer portal workshop detail authorization failed.'
    );
    check(
        $storefronts->portalInvoice($portal, $invoice['uid'])['invoice']['uid'] === $invoice['uid'],
        'Customer portal invoice authorization failed.'
    );
    $privateBlocked = false;
    try {
        $storefronts->portalInvoice($portal, $unrelatedInvoice['uid']);
    } catch (RuntimeException) {
        $privateBlocked = true;
    }
    check($privateBlocked, 'Customer portal exposed another customer invoice.');

    $blocked = false;
    try {
        $storefronts->locateInvoice($store, 'FAC-1001', 'otro@example.test');
    } catch (RuntimeException) {
        $blocked = true;
    }
    check($blocked, 'Invoice lookup accepted an invalid customer identity.');
    echo "STOREFRONT_SMOKE_OK\n";
} finally {
    if (isset($project['database_path'])) {
        Database::disconnect($project['database_path']);
    }
    Database::disconnect($database);
}
