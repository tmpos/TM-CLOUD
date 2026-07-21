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
    foreach (['TM RESTAURANTE', 'E320000000005', 'AJIz2R', 'RncEmisor=133130343', 'MontoTotal=4487.78', 'CodigoSeguridad=AJIz2R', '<barcode'] as $expected) {
        if (!str_contains($html, $expected)) {
            throw new RuntimeException("Invoice HTML is missing: $expected");
        }
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
