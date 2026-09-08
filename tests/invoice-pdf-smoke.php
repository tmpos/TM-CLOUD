<?php

declare(strict_types=1);

use App\Services\LogService;
use App\Services\PdfService;
use App\Services\ProjectService;
use App\Services\SchemaService;

require dirname(__DIR__) . '/vendor/autoload.php';

$databasePath = sys_get_temp_dir() . '/tmpbase-invoice-' . bin2hex(random_bytes(6)) . '.sqlite';
$database = new PDO('sqlite:' . $databasePath);
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$database->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$database->exec(<<<'SQL'
CREATE TABLE empresa (id INTEGER PRIMARY KEY, nombre TEXT, rnc TEXT, direccion TEXT, telefono TEXT, email TEXT, logo TEXT, moneda TEXT);
CREATE TABLE clientes (id INTEGER PRIMARY KEY, uid TEXT, nombre TEXT, cedula_rnc TEXT, direccion TEXT, telefono TEXT, email TEXT);
CREATE TABLE factura_detalle (id INTEGER PRIMARY KEY, factura_id INTEGER, nombre TEXT, cantidad REAL, precio_unitario REAL, total REAL, notas TEXT);
CREATE TABLE factura_pagos (id INTEGER PRIMARY KEY, factura_id INTEGER, metodo_pago TEXT, monto REAL, referencia TEXT);
CREATE TABLE _system_files (id INTEGER PRIMARY KEY, uid TEXT, url TEXT, path TEXT, mime_type TEXT, size INTEGER);
SQL);
$logo = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
$database->prepare('INSERT INTO empresa VALUES (1,?,?,?,?,?,?,?)')->execute([
    'TM RESTAURANTE', '133130343', 'Santo Domingo, República Dominicana', '809-555-0101',
    'facturacion@example.com', $logo, 'RD$',
]);
$database->prepare('INSERT INTO clientes VALUES (1,?,?,?,?,?,?)')->execute([
    'rec_cliente', 'Cliente de prueba', '00112345678', 'Santo Domingo', '809-555-0202', 'cliente@example.com',
]);
$database->prepare('INSERT INTO factura_detalle VALUES (1,?,?,?,?,?,?)')->execute([
    1, 'Cena especial', 2, 1900, 3800, 'Preparación de prueba',
]);
$database->prepare('INSERT INTO factura_pagos VALUES (1,?,?,?,?)')->execute([
    1, 'TARJETA', 4487.78, 'AUTH-123',
]);
unset($database);

$central = new PDO('sqlite::memory:');
$logs = new LogService($central);
$projects = new ProjectService($central, ['storage' => sys_get_temp_dir()], $logs);
$schema = new SchemaService($projects, $logs);
$service = new PdfService($schema);
$project = ['uid' => 'prj_test', 'name' => 'TM RESTAURANTE', 'database_path' => $databasePath];
$invoice = [
    'id' => 1,
    'uid' => 'rec_invoice',
    'numero' => 'FAC-000005',
    'no_factura' => '000005',
    'cliente_id' => 1,
    'subtotal' => 3800,
    'descuento_monto' => 0,
    'impuesto_monto' => 687.78,
    'propina_monto' => 0,
    'total' => 4487.78,
    'metodo_pago' => 'tarjeta',
    'estado' => 'pagada',
    'ncf' => 'E320000000005',
    'tipo_comprobante' => 'E32',
    'alanube_security_code' => 'AJIz2R',
    'alanube_stamp_url' => 'https://fc.dgii.gov.do/ecf/ConsultaTimbreFC?RncEmisor=133130343&ENCF=E320000000005&MontoTotal=4487.78&CodigoSeguridad=AJIz2R',
    'created_at' => '2026-07-21 14:30:00',
];

try {
    $html = $service->invoiceHtml($project, $invoice);
    foreach (['TM RESTAURANTE', '000005', 'E320000000005', 'AJIz2R', 'RncEmisor=133130343', 'MontoTotal=4487.78', 'CodigoSeguridad=AJIz2R', '<barcode'] as $expected) {
        if (!str_contains($html, $expected)) {
            throw new RuntimeException("Invoice HTML is missing: $expected");
        }
    }
    if (str_contains($html, 'Factura No.</td><td class="meta-value">FAC-000005')) {
        throw new RuntimeException('Invoice PDF preferred numero instead of no_factura.');
    }
    $inlineInvoice = $invoice;
    $inlineInvoice['id'] = 2;
    $inlineInvoice['uid'] = 'rec_inline_invoice';
    $inlineInvoice['productos'] = json_encode([
        [
            'tipo' => 'accesorio',
            'nombre' => 'GLASS 15 PRO MAX',
            'codigo' => '9556386583',
            'cantidad' => 1,
            'precio' => 150,
        ],
        [
            'tipo' => 'imei',
            'nombre' => 'IPHONE 11',
            'codigo' => '333333333333333',
            'cantidad' => 1,
            'precio' => 2000,
            'imei' => '333333333333333',
            'imeis' => ['333333333333333'],
            'color' => 'BLACK',
            'capacidad' => '512GB',
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $inlineHtml = $service->invoiceHtml($project, $inlineInvoice);
    foreach (['GLASS 15 PRO MAX', '150.00', 'IPHONE 11', '2,000.00', 'IMEI: 333333333333333', 'Color: BLACK', 'Capacidad: 512GB'] as $expected) {
        if (!str_contains($inlineHtml, $expected)) {
            throw new RuntimeException("Inline invoice product is missing: $expected");
        }
    }
    if (str_contains($inlineHtml, 'Detalle de productos no disponible')) {
        throw new RuntimeException('Inline invoice products were replaced with the empty detail message.');
    }
    $tmposInvoice = $inlineInvoice;
    unset($tmposInvoice['estado'], $tmposInvoice['created_at'], $tmposInvoice['descuento_monto'], $tmposInvoice['impuesto_monto'], $tmposInvoice['notas']);
    $tmposInvoice['estado_factura'] = 'ANULADA';
    $tmposInvoice['fecha_emision'] = '2026-09-07 15:45:00';
    $tmposInvoice['descuento'] = 125;
    $tmposInvoice['impuesto'] = 342;
    $tmposInvoice['nota'] = 'Nota guardada por TMPOS';
    $tmposHtml = $service->invoiceHtml($project, $tmposInvoice);
    foreach (['ANULADA', '07/09/2026 03:45 PM', '125.00', '342.00', 'Nota guardada por TMPOS'] as $expected) {
        if (!str_contains($tmposHtml, $expected)) {
            throw new RuntimeException("TMPOS invoice field is missing: $expected");
        }
    }

    $inlineContent = $service->invoice($project, 'facturas', $inlineInvoice);
    if (!str_starts_with($inlineContent, '%PDF-') || strlen($inlineContent) < 10000) {
        throw new RuntimeException('The invoice PDF with inline products is invalid.');
    }
    $content = $service->invoice($project, 'facturas', $invoice);
    if (!str_starts_with($content, '%PDF-') || strlen($content) < 10000) {
        throw new RuntimeException('The professional invoice PDF is invalid.');
    }
    $previewPath = getenv('INVOICE_PDF_PREVIEW');
    if (is_string($previewPath) && $previewPath !== '') {
        file_put_contents($previewPath, $content);
    }
    $invoiceWithoutReceipt = $invoice;
    $invoiceWithoutReceipt['ncf'] = '';
    $invoiceWithoutReceipt['tipo_comprobante'] = 'SIN COMPROBANTE';
    $invoiceWithoutReceipt['alanube_stamp_url'] = '';
    $htmlWithoutReceipt = $service->invoiceHtml($project, $invoiceWithoutReceipt);
    if (str_contains($htmlWithoutReceipt, '<barcode') || str_contains($htmlWithoutReceipt, 'COMPROBANTE FISCAL')) {
        throw new RuntimeException('An invoice without a tax receipt must not show the DGII QR block.');
    }
    $schema->connection($project)->exec("UPDATE empresa SET rnc='', direccion='', telefono='', email=''");
    $invoiceWithEmbeddedCompany = $invoice;
    $invoiceWithEmbeddedCompany['empresa_telefono'] = '809-555-0303';
    $invoiceWithEmbeddedCompany['empresa_email'] = 'empresa@example.com';
    $invoiceWithEmbeddedCompany['empresa_direccion'] = 'Santiago, Republica Dominicana';
    $fallbackHtml = $service->invoiceHtml($project, $invoiceWithEmbeddedCompany);
    foreach (['RNC 133130343', '809-555-0303', 'empresa@example.com', 'Santiago, Republica Dominicana'] as $expected) {
        if (!str_contains($fallbackHtml, $expected)) {
            throw new RuntimeException("Invoice company fallback is missing: $expected");
        }
    }
    echo 'INVOICE_PDF_SMOKE=OK bytes=' . strlen($content) . PHP_EOL;
} finally {
    \App\Core\Database::disconnect($databasePath);
    @unlink($databasePath);
}
