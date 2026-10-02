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
    $fiscalUrl = 'https://ecf.dgii.gov.do/ecf/ConsultaTimbre?ENCF=E310000000004&FechaFirma=30-09-2026%2011:24:26&CodigoSeguridad=example';
    $includedInvoice = array_replace($invoice, [
        'ncf' => 'E310000000004', 'subtotal' => 620, 'total' => 620, 'impuesto_monto' => '', 'impuesto' => '',
        'alanube_stamp_url' => '', 'alanube_security_code' => '',
        'otro' => json_encode([
            'alanube_payload' => ['idDoc' => ['encf' => 'E310000000004'], 'totals' => ['itbisTotal' => 107.6, 'itbis1Total' => 107.6, 'totalAmount' => 620], 'itemDetails' => [['lineNumber' => 1, 'itemName' => 'Cena especial', 'quantityItem' => 2, 'itemAmount' => 512.4, 'billingIndicator' => 1]]],
            'alanube_response' => ['documentStampUrl' => $fiscalUrl, 'securityCode' => 'example'],
        ]),
    ]);
    $includedHtml = $service->invoiceHtml($project, $includedInvoice);
    if (!preg_match('/class="num line-tax">[^<]*107\.60/', $includedHtml)) throw new RuntimeException('Fiscal line tax missing.');
    $items = [['nombre' => 'A', 'cantidad' => 1], ['nombre' => 'B', 'cantidad' => 1], ['nombre' => 'C', 'cantidad' => 1]];
    $allocation = App\Services\FiscalLineTaxes::calculate(['totals' => ['itbis1Total' => 0.01, 'itbisTotal' => 0.01], 'itemDetails' => [
        ['lineNumber' => 1, 'itemName' => 'A', 'quantityItem' => 1, 'itemAmount' => 1, 'billingIndicator' => 1],
        ['lineNumber' => 2, 'itemName' => 'B', 'quantityItem' => 1, 'itemAmount' => 1, 'billingIndicator' => 1],
        ['lineNumber' => 3, 'itemName' => 'C', 'quantityItem' => 1, 'itemAmount' => 1, 'billingIndicator' => 4],
    ]], $items);
    if ($allocation !== [0.01, 0, 0.0] && $allocation !== [0.01, 0.0, 0.0]) throw new RuntimeException('Tax rounding/exemption failed.');
    foreach (['107.60', '512.40', '620.00', htmlspecialchars($fiscalUrl, ENT_QUOTES, 'UTF-8'), 'example'] as $expected) {
        if (!str_contains($includedHtml, $expected)) throw new RuntimeException('Included fiscal tax or issued QR missing: ' . $expected);
    }
    $mismatch = array_replace($includedInvoice, ['total' => 621]);
    if (str_contains($service->invoiceHtml($project, $mismatch), '107.60')) throw new RuntimeException('Stale fiscal totals used.');
    $invalidUrlInvoice = array_replace($includedInvoice, ['otro' => json_encode(['alanube_response' => ['documentStampUrl' => 'https://dgii.gov.do.evil.test/qr']])]);
    if (str_contains($service->invoiceHtml($project, $invalidUrlInvoice), 'evil.test')) throw new RuntimeException('Untrusted QR accepted.');
    $datedInvoice = array_replace($invoice, ['fecha_emision' => '2026-09-28', 'hora' => '09:15']);
    if (!str_contains($service->invoiceHtml($project, $datedInvoice), '28/09/2026 09:15 AM')) throw new RuntimeException('Separate POS time was lost.');
    $datedInvoice['fecha_emision'] = '2026-09-29T02:15:00Z';
    if (!str_contains($service->invoiceHtml($project, $datedInvoice), '28/09/2026 10:15 PM')) throw new RuntimeException('UTC time did not convert to business timezone.');
    $db = $schema->connection($project);
    $db->exec("CREATE TABLE configuracion (clave TEXT, valor TEXT)");
    $db->exec("INSERT INTO configuracion VALUES ('sistema_zona_horaria', 'America/New_York')");
    $datedInvoice['fecha_emision'] = '2026-01-01T15:00:00Z';
    if (!str_contains($service->invoiceHtml($project, $datedInvoice), '01/01/2026 10:00 AM')) throw new RuntimeException('Configured timezone was ignored.');
    $db->exec("DELETE FROM configuracion");
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

    // POS references use cod_cliente or UID, and the tax ID must come from the customer.
    foreach (['cod_cliente' => '1', 'cliente_uid' => 'rec_cliente'] as $reference => $value) {
        $posInvoice = $inlineInvoice;
        unset($posInvoice['cliente_id']);
        $posInvoice[$reference] = $value;
        $posHtml = $service->invoiceHtml($project, $posInvoice);
        foreach (['Cliente de prueba', '00112345678', 'Santo Domingo', '809-555-0202', 'cliente@example.com'] as $expected) {
            if (!str_contains($posHtml, $expected)) throw new RuntimeException("POS client field missing: $expected");
        }
    }
    $unknownClient = $inlineInvoice;
    unset($unknownClient['cliente_id']);
    $unknownClient['cod_cliente'] = '99999';
    $unknownClient['nombre_cliente'] = 'Cliente no registrado';
    $unknownHtml = $service->invoiceHtml($project, $unknownClient);
    if (str_contains($unknownHtml, '00112345678') || str_contains($unknownHtml, 'cliente@example.com')) throw new RuntimeException('Unrelated customer data leaked into document.');
    $snapshot = $unknownClient + ['rnc_cliente' => '987654321', 'email_cliente' => 'snapshot@example.com'];
    foreach (['987654321', 'snapshot@example.com'] as $expected) if (!str_contains($service->invoiceHtml($project, $snapshot), $expected)) throw new RuntimeException('Customer snapshot missing.');

    $designs = new \App\Services\DocumentSettingsService($schema);
    $customDesign = $designs->save($project, array_merge($designs::defaults(), ['primary_color' => '#aa2244', 'heading_color' => '#112233', 'show_logo' => false, 'quote_validity_days' => 45]));
    if ($designs->get($project) !== $customDesign) throw new RuntimeException('Document design did not persist.');
    try { $designs->save($project, ['primary_color' => '#000000; color:red']); throw new RuntimeException('Unsafe color accepted.'); } catch (InvalidArgumentException) {}
    $quote = array_merge($inlineInvoice, ['tipo_factura' => 'COTIZACION', 'no_factura' => 'F000123', 'fecha_vencimiento' => '2026-10-31']);
    $quoteHtml = $service->invoiceHtml($project, $quote);
    foreach (['COT000123', '45', '#aa2244', '#112233', '00112345678', 'cliente@example.com'] as $expected) if (!str_contains($quoteHtml, $expected)) throw new RuntimeException("Quote field missing: $expected");
    foreach (['<barcode', 'E320000000005', 'AJIz2R', 'ENTREGADO POR', 'RESUMEN DE PAGO', 'Vencimiento', 'class="logo"'] as $unexpected) if (str_contains($quoteHtml, $unexpected)) throw new RuntimeException("Invoice content in quotation: $unexpected");
    if (preg_match('/\bfactura\b/i', strip_tags($quoteHtml))) throw new RuntimeException('Invoice wording in quotation.');
    if ($service->documentFilename($quote) !== 'Cotizacion_COT000123.pdf') throw new RuntimeException('Wrong quote filename.');
    $quotePdf = $service->invoice($project, 'facturas', $quote);
    if (!str_starts_with($quotePdf, '%PDF-')) throw new RuntimeException('Invalid quote PDF.');
    if (getenv('INVOICE_PDF_PREVIEW')) file_put_contents(getenv('INVOICE_PDF_PREVIEW') . '.quote.pdf', $quotePdf);
    $signatureImage = imagecreatetruecolor(260, 80);
    $white = imagecolorallocate($signatureImage, 255, 255, 255);
    $ink = imagecolorallocate($signatureImage, 16, 42, 67);
    imagefill($signatureImage, 0, 0, $white);
    imagestring($signatureImage, 5, 50, 30, 'FIRMA DE PRUEBA', $ink);
    ob_start(); imagepng($signatureImage); $signaturePng = 'data:image/png;base64,' . base64_encode(ob_get_clean());
    imagedestroy($signatureImage);
    $signedDesign = $designs->save($project, ['show_company_signature' => true, 'representative_name' => 'Representante & Empresa', 'representative_signature' => $signaturePng]);
    foreach (['FACTURA_VENTA', 'COTIZACION'] as $type) {
        $signedInvoice = array_merge($inlineInvoice, ['tipo_factura' => $type, 'productos' => json_encode([['nombre' => 'Mantenimiento', 'descripcion' => "Limpieza completa\nRevisar <equipo>", 'cantidad' => 1, 'precio' => 100]])]);
        $signedHtml = $service->invoiceHtml($project, $signedInvoice);
        foreach (['Limpieza completa', 'Revisar &lt;equipo&gt;', 'alt="Firma del representante"', 'Representante &amp; Empresa'] as $expected) if (!str_contains($signedHtml, $expected)) throw new RuntimeException("Signature/description missing: $expected");
        $signedPdf = $service->invoice($project, 'facturas', $signedInvoice);
        if (!str_starts_with($signedPdf, '%PDF-')) throw new RuntimeException('Invalid signed PDF.');
        if (getenv('INVOICE_PDF_PREVIEW')) file_put_contents(getenv('INVOICE_PDF_PREVIEW') . '.' . $type . '.signed.pdf', $signedPdf);
    }
    // Old clients saving only colors must not erase the representative signature.
    $designs->save($project, ['primary_color' => '#123456']);
    if (!$designs->get($project)['show_company_signature']) throw new RuntimeException('Old client erased signature settings.');
    $designs->save($project, ['show_company_signature' => false]);
    if ($designs->get($project)['representative_signature'] !== $signaturePng) throw new RuntimeException('Disabling erased the saved signature.');
    if (str_contains($service->invoiceHtml($project, $signedInvoice), 'alt="Firma del representante"')) throw new RuntimeException('Disabled signature was rendered.');
    foreach (['data:image/svg+xml;base64,PHN2Zz4=', 'data:image/png;base64,bm90LWEtcG5n', 'https://example.test/signature.png'] as $invalidSignature) {
        try { $designs->save($project, ['representative_signature' => $invalidSignature]); throw new LogicException('Invalid signature accepted.'); } catch (InvalidArgumentException) {}
    }
    $designs->save($project, $designs::defaults());


    foreach ([$invoice, array_replace($invoice, ['tipo_factura'=>'COTIZACION'])] as $visibilityInvoice) {
        $designs->save($project, ['visibility'=>['quantity'=>false, 'price'=>false, 'line_total'=>false, 'customer_email'=>false, 'total'=>false]]);
        $hidden = $service->invoiceHtml($project, $visibilityInvoice);
        foreach (['CANT.', 'P.U.', 'cliente@example.com', '<tr class="grand">'] as $unexpected) {
            if (str_contains($hidden, $unexpected)) throw new RuntimeException('Hidden field leaked: '.$unexpected);
        }
        if (!str_contains($hidden, 'Cena especial') || !str_contains($hidden, 'SUBTOTAL')) throw new RuntimeException('Unrelated field hidden');
        $designs->save($project, ['visibility'=>array_fill_keys(array_keys($designs::defaults()['visibility']), false)]);
        $hidden = $service->invoiceHtml($project, $visibilityInvoice);
        if (str_contains($hidden, '<table class="products">')) throw new RuntimeException('Empty product table rendered');
        $minimalPdf = $service->invoice($project, 'facturas', $visibilityInvoice);
        if (!str_starts_with($minimalPdf, '%PDF-')) throw new RuntimeException('All-hidden PDF failed');
        $designs->save($project, $designs::defaults());
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
        file_put_contents($previewPath . '.html', $html);
        file_put_contents($previewPath . '.quote.html', $quoteHtml);
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
    $db = $schema->connection($project);
    $db->exec("ALTER TABLE empresa ADD COLUMN uid TEXT; ALTER TABLE empresa ADD COLUMN almacen_id INTEGER");
    $db->exec("UPDATE empresa SET uid='main', almacen_id=1");
    $db->prepare('INSERT INTO empresa (id,uid,almacen_id,nombre,logo) VALUES (2,?,?,?,?)')->execute(['second',1,'SECOND COMPANY',$logo]);
    $scopedInvoice = array_replace($invoice, ['almacen_uid'=>'second', 'almacen_id'=>1, 'alanube_stamp_url'=>'']);
    $scopedHtml = $service->invoiceHtml($project, $scopedInvoice);
    if (!str_contains($scopedHtml,'SECOND COMPANY') || str_contains($scopedHtml,'TM RESTAURANTE')) throw new RuntimeException('Invoice used the wrong company.');
    // Imported company rows can retain another warehouse's almacen_uid. The
    // document's exact company UID must win, even with a stale numeric ID.
    $db->exec("ALTER TABLE empresa ADD COLUMN almacen_uid TEXT");
    $db->exec("UPDATE empresa SET almacen_uid='second'");
    $db->exec("UPDATE empresa SET rnc='SECOND-RNC', direccion='SECOND ADDRESS', telefono='809-555-0404', email='second@example.com' WHERE uid='second'");
    foreach (['FACTURA', 'COTIZACION'] as $documentType) {
        $scopedDocument = array_replace($scopedInvoice, ['tipo_factura' => $documentType]);
        $scopedHtml = $service->invoiceHtml($project, $scopedDocument);
        foreach (['SECOND COMPANY', 'SECOND-RNC', 'SECOND ADDRESS', '809-555-0404', 'second@example.com', $logo] as $expected) {
            if (!str_contains($scopedHtml, $expected)) throw new RuntimeException("Warehouse $documentType field missing with conflicting alias: $expected");
        }
        if (str_contains($scopedHtml, 'TM RESTAURANTE')) throw new RuntimeException('Conflicting warehouse alias selected the main company.');
        if (!str_starts_with($service->invoice($project, 'facturas', $scopedDocument), '%PDF-')) throw new RuntimeException('Warehouse document PDF did not render.');
    }
    $db->exec("UPDATE empresa SET almacen_uid='legacy-second' WHERE uid='second'");
    $legacyInvoice = array_replace($scopedInvoice, ['almacen_uid'=>'legacy-second']);
    if (!str_contains($service->invoiceHtml($project, $legacyInvoice), 'SECOND ADDRESS')) throw new RuntimeException('Unique legacy warehouse alias stopped resolving.');
    $db->exec("UPDATE empresa SET almacen_uid='ambiguous'");
    $ambiguousInvoice = array_replace($scopedInvoice, ['almacen_uid'=>'ambiguous']);
    if (str_contains($service->invoiceHtml($project, $ambiguousInvoice), 'SECOND ADDRESS')) throw new RuntimeException('Ambiguous legacy alias selected a company.');
    $db->exec("UPDATE empresa SET logo='' WHERE uid='second'");
    if (str_contains($service->invoiceHtml($project,$scopedInvoice), '<img src="data:image')) throw new RuntimeException('Empty company logo inherited another logo.');
    $scopedInvoice['almacen_uid']='missing';
    if (str_contains($service->invoiceHtml($project,$scopedInvoice),$logo)) throw new RuntimeException('Unknown warehouse inherited main logo.');
    echo 'INVOICE_PDF_SMOKE=OK bytes=' . strlen($content) . PHP_EOL;
} finally {
    \App\Core\Database::disconnect($databasePath);
    @unlink($databasePath);
}
