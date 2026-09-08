<?php

declare(strict_types=1);

namespace App\Services;

final class PdfService
{
    public function __construct(private SchemaService $schema, private ?LicenseService $licenses = null)
    {
    }

    public function invoice(array $project, string $table, array $record): string
    {
        $html = $this->invoiceHtml($project, $record);
        $html = preg_replace('#<footer class="footer">.*?</footer>#s', '', $html) ?? $html;
        $html = str_replace('@page{margin:0}', '@page{margin:0 0 32mm}', $html);
        $html = str_replace('</style>', '.brand-side{background:#0b3c46!important;color:#fff!important}</style>', $html);
        $footer = $this->invoiceFooterHtml($project, $record);
        if (!class_exists(\Mpdf\Mpdf::class)) {
            throw new \RuntimeException('PDF generator is not installed. Run composer install.');
        }
        try {
            $mpdf = new \Mpdf\Mpdf([
                'tempDir' => sys_get_temp_dir(),
                'format' => 'Letter',
                'margin_left' => 0,
                'margin_right' => 0,
                'margin_top' => 0,
                'margin_bottom' => 32,
                'margin_footer' => 0,
            ]);
            $mpdf->SetTitle($this->firstValue($record['documento_titulo'] ?? '', 'Factura') . ' ' . $this->firstValue(
                $record['no_factura'] ?? '',
                $record['numero'] ?? '',
                $record['uid'] ?? ''
            ));
            $mpdf->SetAuthor((string) ($project['name'] ?? 'TMPBase'));
            $mpdf->WriteHTML($html);
            $mpdf->SetHTMLFooter($footer);
            return $mpdf->Output('', 'S');
        } catch (\Throwable $e) {
            throw new \RuntimeException('Could not generate the invoice PDF.', 0, $e);
        }
    }

    public function receipt(array $project, array $record): string
    {
        if (!class_exists(\Mpdf\Mpdf::class)) {
            throw new \RuntimeException('PDF generator is not installed. Run composer install.');
        }
        $details = $this->inlineInvoiceDetails($record);
        $detailCharacters = 0;
        foreach ($details as $detail) {
            $detailCharacters += mb_strlen((string) ($detail['nombre'] ?? $detail['descripcion'] ?? ''));
            $detailCharacters += mb_strlen((string) ($detail['notas'] ?? ''));
        }
        // Thermal rolls do not need a Letter-sized canvas. Keep enough room for
        // the content while avoiding the large blank tail common in fixed tickets.
        $height = max(110, min(420, 84 + (count($details) * 9) + (int) ceil($detailCharacters / 42) * 2));
        try {
            $mpdf = new \Mpdf\Mpdf([
                'tempDir' => sys_get_temp_dir(),
                'format' => [80, $height],
                'margin_left' => 4.5,
                'margin_right' => 4.5,
                'margin_top' => 4,
                'margin_bottom' => 4,
            ]);
            $mpdf->SetTitle('Recibo ' . $this->firstValue(
                $record['no_factura'] ?? '',
                $record['numero'] ?? '',
                $record['uid'] ?? ''
            ));
            $mpdf->SetAuthor((string) ($project['name'] ?? 'TMPBase'));
            $mpdf->WriteHTML($this->receiptHtml($project, $record, $details));
            return $mpdf->Output('', 'S');
        } catch (\Throwable $e) {
            throw new \RuntimeException('No se pudo generar el recibo de 80 mm.', 0, $e);
        }
    }

    private function receiptHtml(array $project, array $record, array $details): string
    {
        $company = $this->companyData($project, $record);
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $money = static fn (mixed $value): string => number_format((float) $value, 2, '.', ',');
        $currency = $e($company['moneda'] ?? $record['moneda'] ?? 'RD$');
        $number = $e($this->firstValue($record['no_factura'] ?? '', $record['numero'] ?? '', $record['uid'] ?? ''));
        $customer = $e($this->firstValue($record['nombre_cliente'] ?? '', $record['customer_name'] ?? '', 'Consumidor final'));
        $logo = $this->companyLogoDataUri($project, (string) ($company['logo'] ?? ''));
        $logoHtml = $logo !== ''
            ? '<img class="logo" src="' . $e($logo) . '" alt="Logo">'
            : '';
        $rows = '';
        foreach ($details as $item) {
            $quantity = (float) ($item['cantidad'] ?? 1);
            $unit = (float) ($item['precio_unitario'] ?? $item['precio'] ?? 0);
            $total = (float) ($item['total'] ?? $item['importe'] ?? ($quantity * $unit));
            $name = $e($item['nombre'] ?? $item['descripcion'] ?? $item['producto'] ?? 'Producto');
            $note = trim((string) ($item['notas'] ?? ''));
            $rows .= '<tr class="product-row"><td class="qty">' . $money($quantity) . '</td><td class="description"><strong>' . $name . '</strong>'
                . '<div class="unit">' . $currency . ' ' . $money($unit) . ' c/u</div>'
                . ($note !== '' ? '<div class="note">' . $e($note) . '</div>' : '')
                . '</td><td class="right amount">' . $money($total) . '</td></tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="3" class="center">Sin detalle de productos</td></tr>';
        }
        $contact = array_values(array_filter([
            trim((string) ($company['rnc'] ?? '')) !== '' ? 'RNC ' . trim((string) $company['rnc']) : '',
            trim((string) ($company['telefono'] ?? '')),
            trim((string) ($company['direccion'] ?? '')),
        ]));
        return '<!doctype html><html><head><meta charset="utf-8"><style>'
            . '@page{margin:0}body{font-family:DejaVu Sans,Arial,sans-serif;color:#111;margin:0;font-size:8px;line-height:1.32}'
            . '.ticket{padding:0 1mm}.center{text-align:center}.right{text-align:right}.logo{display:block;max-width:27mm;max-height:12mm;margin:0 auto 2mm}'
            . '.brand{font-size:13px;font-weight:bold;line-height:1.15;text-transform:uppercase}.meta{margin:2mm 0 1.5mm;font-size:7px;line-height:1.4}'
            . '.rule{border-top:.35mm dashed #333;margin:2.2mm 0}.document-title{font-size:9px;font-weight:bold;letter-spacing:.5px}'
            . '.info{width:100%;border-collapse:collapse}.info td{padding:.6mm 0;vertical-align:top}.label{width:18mm;color:#444;font-size:7px;text-transform:uppercase}.value{font-weight:bold}'
            . '.items{width:100%;border-collapse:collapse}.items th{padding:1.3mm .5mm;border-bottom:.4mm solid #111;font-size:6.7px;text-transform:uppercase}'
            . '.items td{padding:1.7mm .5mm;border-bottom:.2mm dotted #999;vertical-align:top}.qty{width:9mm;text-align:center}.description{width:auto}.amount{width:19mm;font-weight:bold}'
            . '.unit{font-size:6.7px;color:#555;margin-top:.5mm}.note{font-size:6.5px;color:#333;margin-top:.5mm}.totals{width:100%;border-collapse:collapse;margin-top:1.5mm}'
            . '.totals td{padding:.7mm .5mm}.grand td{padding-top:1.5mm;border-top:.55mm solid #111;font-size:11px;font-weight:bold}.payment{margin-top:1.5mm;font-size:7px}'
            . '.thanks{margin-top:3mm;font-size:9px;font-weight:bold}.footer-copy{margin-top:1mm;font-size:6.5px;color:#555}'
            . '</style></head><body><main class="ticket"><div class="center">' . $logoHtml
            . '<div class="brand">' . $e($company['nombre'] ?? $project['name'] ?? 'Empresa') . '</div>'
            . '<div class="meta">' . implode('<br>', array_map($e, $contact)) . '</div></div><div class="rule"></div>'
            . '<div class="center document-title">COMPROBANTE DE VENTA</div><table class="info">'
            . '<tr><td class="label">Recibo</td><td class="value">' . $number . '</td></tr>'
            . '<tr><td class="label">Fecha</td><td class="value">' . $e($this->formatDate((string) ($record['fecha_emision'] ?? $record['created_at'] ?? ''))) . '</td></tr>'
            . '<tr><td class="label">Cliente</td><td class="value">' . $customer . '</td></tr>'
            . '<tr><td class="label">Estado</td><td class="value">' . $e($record['estado_factura'] ?? $record['estado'] ?? '') . '</td></tr></table><div class="rule"></div>'
            . '<table class="items"><thead><tr><th class="qty">Cant.</th><th>Descripción</th><th class="right amount">Importe</th></tr></thead><tbody>' . $rows . '</tbody></table>'
            . '<table class="totals">'
            . '<tr><td>Subtotal</td><td class="right">' . $currency . ' ' . $money($record['subtotal'] ?? 0) . '</td></tr>'
            . '<tr><td>Descuento</td><td class="right">' . $currency . ' ' . $money($record['descuento_monto'] ?? $record['descuento'] ?? 0) . '</td></tr>'
            . '<tr><td>Impuestos</td><td class="right">' . $currency . ' ' . $money($record['impuesto_monto'] ?? $record['impuesto'] ?? 0) . '</td></tr>'
            . ((float) ($record['envio_monto'] ?? 0) > 0 ? '<tr><td>Envío</td><td class="right">' . $currency . ' ' . $money($record['envio_monto']) . '</td></tr>' : '')
            . '<tr class="grand"><td>TOTAL</td><td class="right">' . $currency . ' ' . $money($record['total'] ?? 0) . '</td></tr></table>'
            . '<div class="payment"><b>Método de pago:</b> ' . $e(strtoupper((string) ($record['metodo_pago'] ?? ''))) . '</div>'
            . '<div class="center thanks">¡Gracias por su compra!</div><div class="center">Conserve este comprobante.</div>'
            . '<div class="center footer-copy">Documento generado por TMPOS</div></main></body></html>';
    }

    public function invoiceHtml(array $project, array $invoice): string
    {
        $company = $this->companyData($project, $invoice);
        $client = [];
        if (!empty($invoice['cliente_id'])) {
            $client = $this->byLocalReference($project, 'clientes', $invoice['cliente_id']);
        }

        $details = $this->byForeignReference($project, 'factura_detalle', 'factura_id', $invoice);
        if (!$details && !empty($invoice['orden_id'])) {
            $details = $this->byValueReference($project, 'orden_detalle', 'orden_id', $invoice['orden_id']);
        }
        if (!$details) {
            $details = $this->inlineInvoiceDetails($invoice);
        }
        $payments = $this->byForeignReference($project, 'factura_pagos', 'factura_id', $invoice);

        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $money = static fn (mixed $value): string => number_format((float) $value, 2, '.', ',');
        $companyName = $e($company['nombre'] ?? $project['name'] ?? 'Empresa');
        $invoiceNumber = $e($this->firstValue(
            $invoice['no_factura'] ?? '',
            $invoice['numero'] ?? '',
            $invoice['uid'] ?? ''
        ));
        $currency = $e($company['moneda'] ?? 'RD$');
        $ncf = trim((string) ($invoice['ncf'] ?? ''));
        $securityCode = trim((string) ($invoice['alanube_security_code'] ?? $invoice['codigo_seguridad'] ?? ''));
        $verificationUrl = $this->dgiiVerificationUrl($company, $client, $invoice, $ncf, $securityCode);
        $logo = $this->companyLogoDataUri($project, (string) ($company['logo'] ?? ''));

        $logoHtml = $logo !== ''
            ? '<img class="brand-logo" src="' . $e($logo) . '" alt="Logo">'
            : '<div class="brand-mark">' . $e($this->initials((string) ($company['nombre'] ?? $project['name'] ?? 'TM'))) . '</div>';

        $detailRows = '';
        $position = 1;
        foreach ($details as $item) {
            $quantity = (float) ($item['cantidad'] ?? 1);
            $unit = (float) ($item['precio_unitario'] ?? $item['precio'] ?? 0);
            $total = (float) ($item['total'] ?? $item['importe'] ?? ($quantity * $unit));
            $description = $item['nombre'] ?? $item['descripcion'] ?? $item['producto'] ?? 'Producto';
            $detailRows .= '<tr>'
                . '<td class="line-number">' . $position++ . '</td>'
                . '<td><strong>' . $e($description) . '</strong>'
                . (!empty($item['notas']) ? '<div class="item-note">' . $e($item['notas']) . '</div>' : '') . '</td>'
                . '<td class="num">' . $money($unit) . '</td>'
                . '<td class="num">' . $money($quantity) . '</td>'
                . '<td class="num total-cell">' . $money($total) . '</td>'
                . '</tr>';
        }
        if ($detailRows === '') {
            $detailRows = '<tr><td colspan="5" class="empty">Detalle de productos no disponible</td></tr>';
        }

        $paymentRows = '';
        foreach ($payments as $payment) {
            $method = strtoupper((string) ($payment['metodo_pago'] ?? 'PAGO'));
            $reference = trim((string) ($payment['referencia'] ?? ''));
            $paymentRows .= '<div class="payment-line"><span>' . $e($method) . ($reference !== '' ? ' · ' . $e($reference) : '')
                . '</span><strong>' . $currency . ' ' . $money($payment['monto'] ?? 0) . '</strong></div>';
        }
        if ($paymentRows === '') {
            $paymentRows = '<div class="payment-line"><span>' . $e(strtoupper((string) ($invoice['metodo_pago'] ?? 'PAGO')))
                . '</span><strong>' . $currency . ' ' . $money($invoice['total'] ?? 0) . '</strong></div>';
        }

        $status = strtoupper(trim((string) ($invoice['estado_factura'] ?? $invoice['estado'] ?? 'PAGADA')));
        $statusClass = in_array(strtolower($status), ['anulada', 'cancelada'], true) ? 'cancelled' : 'paid';
        $issuedAt = $this->formatDate((string) ($invoice['fecha_emision'] ?? $invoice['created_at'] ?? ''));
        $dueAt = $this->formatDate((string) ($invoice['fecha_vencimiento'] ?? $invoice['vence_at'] ?? ''));
        $clientName = $e($this->firstValue($client['nombre'] ?? '', $invoice['nombre_cliente'] ?? '', $invoice['customer_name'] ?? '', 'Consumidor final'));
        $clientTaxId = $e($this->firstValue($client['cedula_rnc'] ?? '', $client['rnc'] ?? '', $invoice['cedula_rnc'] ?? '', $invoice['customer_document'] ?? ''));
        $clientAddress = $e($this->firstValue($client['direccion'] ?? '', $invoice['direccion_cliente'] ?? '', $invoice['delivery_address'] ?? ''));
        $clientPhone = $e($this->firstValue($client['telefono'] ?? '', $invoice['telefono_cliente'] ?? '', $invoice['customer_phone'] ?? ''));
        $clientEmail = $e($this->firstValue($client['email'] ?? '', $invoice['email_cliente'] ?? '', $invoice['customer_email'] ?? ''));
        $companyRnc = $e($company['rnc'] ?? '');
        $companyAddress = $e($company['direccion'] ?? '');
        $companyPhone = $e($company['telefono'] ?? '');
        $companyEmail = $e($company['email'] ?? '');
        $notes = trim((string) ($invoice['notas'] ?? $invoice['nota'] ?? ''));
        $shippingRow = (float) ($invoice['envio_monto'] ?? 0) > 0
            ? '<tr><td>Envío</td><td><span class="currency">' . $currency . '</span> ' . $money($invoice['envio_monto']) . '</td></tr>'
            : '';

        $fiscalBlock = '';
        if ($ncf !== '') {
            $fiscalBlock = '<div class="fiscal-card"><div class="fiscal-title">COMPROBANTE FISCAL</div>'
                . '<div><span>Tipo:</span><strong>' . $e($invoice['tipo_comprobante'] ?? 'Fiscal') . '</strong></div>'
                . '<div><span>NCF / e-NCF:</span><strong>' . $e($ncf) . '</strong></div>'
                . ($securityCode !== '' ? '<div><span>Código de seguridad:</span><strong>' . $e($securityCode) . '</strong></div>' : '')
                . '</div>';
        }

        $qrBlock = '';
        if ($verificationUrl !== '') {
            $qrBlock = '<div class="qr-box" style="width:100%;text-align:center;line-height:1.15">'
                . '<div style="margin-bottom:2mm;color:#078b8f;font-size:7pt;font-weight:bold;letter-spacing:.5pt">VERIFICAR EN DGII</div>'
                . '<barcode code="' . $e($verificationUrl) . '" type="QR" size="0.68" error="M" disableborder="1" />'
                . '<div style="margin-top:1.5mm;color:#66777d;font-size:5.5pt">Escanee para validar el comprobante</div></div>';
        }

        $documentTitle = $e(strtoupper($this->firstValue($invoice['documento_titulo'] ?? '', 'Factura')));
        $documentSubtitle = $e(strtoupper($this->firstValue($invoice['documento_subtitulo'] ?? '', 'Documento comercial')));
        $documentNumberLabel = $e($this->firstValue($invoice['documento_numero_label'] ?? '', 'Factura No.'));

        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>' . $documentTitle . ' ' . $invoiceNumber . '</title><style>'
            . '@page{margin:0}*{box-sizing:border-box}body{font-family:DejaVu Sans,Arial,sans-serif;color:#17212b;background:#fff;margin:0;font-size:10px}.page{position:relative;min-height:1056px;background:#fff;padding-bottom:92px}.hero{height:170px;background:#0b3c46;color:#fff;position:relative;overflow:hidden;padding:34px 42px}.hero-accent{position:absolute;left:-35px;top:-70px;width:430px;height:185px;background:#078b8f;border-radius:0 0 260px 0}.hero-line{position:absolute;left:0;bottom:0;width:100%;height:13px;background:#08a1a3}.hero-table{position:relative;z-index:2;width:100%;border-collapse:collapse}.hero-table td{border:0;padding:0;vertical-align:middle}.invoice-title{font-size:30px;font-weight:700;letter-spacing:1.7px}.invoice-subtitle{font-size:9px;letter-spacing:2px;margin-top:5px;color:#c5f5f2}.brand-side{text-align:right;width:48%}.brand-logo{max-width:155px;max-height:72px;background:#fff;border-radius:7px;padding:6px}.brand-mark{display:inline-block;background:#fff;color:#078b8f;font-size:24px;font-weight:bold;border-radius:50%;width:62px;height:62px;line-height:62px;text-align:center}.brand-name{font-size:15px;font-weight:bold;margin-top:7px}.brand-rnc{font-size:9px;color:#c9e7e8;margin-top:3px}.wave{height:30px;background:#f1f5f5;border-radius:0 0 70% 0;margin-top:-1px}.content{padding:15px 42px 0}.info-table{width:100%;border-collapse:collapse;margin-bottom:18px}.info-table td{border:0;vertical-align:top;padding:0}.bill-to{width:55%;padding-right:25px!important}.invoice-meta{width:45%}.section-kicker{font-size:9px;color:#078b8f;font-weight:bold;letter-spacing:1.4px;margin-bottom:5px}.client-name{font-size:15px;font-weight:bold;color:#123b43;margin-bottom:5px}.contact-line{color:#5f6d73;line-height:1.55}.meta-grid{width:100%;border-collapse:collapse}.meta-grid td{padding:3px 0;border:0}.meta-grid .meta-label{color:#66777d;width:45%}.meta-grid .meta-value{text-align:right;font-weight:bold;color:#153e46}.status{display:inline-block;border-radius:12px;padding:3px 9px;font-size:8px;font-weight:bold}.paid{background:#dcfce7;color:#166534}.cancelled{background:#fee2e2;color:#991b1b}.fiscal-card{background:#eefafa;border-left:4px solid #078b8f;padding:9px 12px;margin:2px 0 16px}.fiscal-title{font-size:9px;letter-spacing:1px;color:#067276;font-weight:bold;margin-bottom:5px}.fiscal-card div:not(.fiscal-title){line-height:1.55}.fiscal-card span{display:inline-block;color:#617277;width:115px}.items{width:100%;border-collapse:collapse}.items thead th{background:#102e3a;color:#fff;padding:8px 8px;font-size:8px;text-transform:uppercase;letter-spacing:.4px;text-align:left}.items thead th.num{text-align:right}.items tbody td{padding:8px;border-bottom:3px solid #fff;background:#f1f3f4;color:#29373c}.items tbody tr:nth-child(even) td{background:#e5e8e9}.line-number{width:28px;text-align:center;color:#65757a!important}.num{text-align:right;white-space:nowrap}.total-cell{font-weight:bold;color:#0b555a!important}.item-note{font-size:8px;color:#718087;margin-top:2px}.empty{text-align:center;color:#849399;padding:22px!important}.summary{width:100%;border-collapse:collapse;margin-top:18px}.summary td{border:0;vertical-align:top;padding:0}.payment-panel{width:49%;padding-right:28px!important}.totals-panel{width:51%;padding-left:20px!important}.panel-title{font-size:9px;color:#123b43;font-weight:bold;letter-spacing:1px;border-bottom:1px solid #d5dddf;padding-bottom:5px;margin-bottom:5px}.payment-line{border-bottom:1px solid #e4e9ea;padding:5px 0}.payment-line span{display:inline-block;width:65%;color:#66777d}.payment-line strong{display:inline-block;width:34%;text-align:right}.totals{width:100%;border-collapse:collapse}.totals td{padding:4px 6px;border:0}.totals td:last-child{text-align:right;font-weight:bold}.totals .grand td{background:#102e3a;color:#fff;font-size:13px;padding:8px}.lower{width:100%;border-collapse:collapse;margin-top:18px}.lower td{border:0;vertical-align:top;padding:0}.terms{width:60%;padding-right:22px!important}.terms p{color:#66777d;line-height:1.55;margin:5px 0}.qr-cell{width:18%;text-align:center}.signature{width:22%;text-align:center;padding-left:15px!important}.signature-line{border-top:1px solid #263b42;margin-top:48px;padding-top:5px;font-weight:bold}.qr-box{text-align:center}.qr-box barcode{display:block;margin:auto}.qr-title{color:#078b8f;font-size:8px;font-weight:bold;margin-top:4px}.qr-caption{font-size:6.5px;color:#67777c}.qr-link{font-size:4.7px;color:#8a969a;word-break:break-all;margin-top:3px}.footer{position:absolute;left:0;right:0;bottom:0;height:80px;background:#0b3c46;color:#fff;padding:19px 42px;border-top:9px solid #08a1a3}.footer-table{width:100%;border-collapse:collapse}.footer-table td{border:0;padding:0;vertical-align:middle}.footer-contact{line-height:1.55;color:#d0e5e6}.footer-note{text-align:right;color:#9fc1c3;font-size:8px}.currency{font-size:8px;color:#819096;font-weight:normal}'
            . '.page{min-height:0;overflow:visible;padding-bottom:0}.hero{height:auto;padding:0;background:#0b3c46;overflow:hidden}.hero-layout{width:100%;border-collapse:collapse}.hero-layout td{height:142px;border:0;vertical-align:middle}.hero-title-cell{width:55%;padding:30px 42px;background:#078b8f;border-radius:0 0 105px 0}.invoice-title{color:#fff;line-height:1;font-size:31px}.invoice-subtitle{color:#d6ffff}.brand-side{position:static;width:45%;padding:20px 42px 16px 20px;text-align:right;color:#fff}.brand-name{color:#fff}.hero-line{position:static;height:9px;background:#12aeb0}.wave,.hero-accent,.hero-accent-back,.hero-cut,.hero-copy{display:none}.content{padding-top:20px}.footer{position:fixed;height:90px;padding:19px 42px 14px;border-top:9px solid #12aeb0;overflow:hidden}.footer:before,.footer:after{display:none}.footer-table{position:static}.footer-contact{width:55%;font-size:7.5px;line-height:1.55}.footer-contact strong{display:block;color:#fff;font-size:10px;letter-spacing:.5px;margin-bottom:2px}.footer-note{color:#c2e0e1;font-size:7.5px;line-height:1.5}.qr-box barcode{margin-top:0}.signature-line{margin-top:38px}</style></head><body><main class="page"><header class="hero"><table class="hero-layout"><tr><td class="hero-title-cell"><div class="invoice-title">' . $documentTitle . '</div><div class="invoice-subtitle">' . $documentSubtitle . '</div></td><td class="brand-side">' . $logoHtml . '<div class="brand-name">' . $companyName . '</div><div class="brand-rnc">' . ($companyRnc !== '' ? 'RNC ' . $companyRnc : '') . '</div></td></tr></table><div class="hero-line"></div></header>'
            . '<section class="content"><table class="info-table"><tr><td class="bill-to"><div class="section-kicker">FACTURAR A</div><div class="client-name">' . $clientName . '</div>'
            . ($clientTaxId !== '' ? '<div class="contact-line">RNC/Cédula: ' . $clientTaxId . '</div>' : '')
            . ($clientAddress !== '' ? '<div class="contact-line">' . $clientAddress . '</div>' : '')
            . ($clientPhone !== '' ? '<div class="contact-line">Tel: ' . $clientPhone . '</div>' : '')
            . ($clientEmail !== '' ? '<div class="contact-line">' . $clientEmail . '</div>' : '')
            . '</td><td class="invoice-meta"><table class="meta-grid"><tr><td class="meta-label">' . $documentNumberLabel . '</td><td class="meta-value">' . $invoiceNumber . '</td></tr><tr><td class="meta-label">Fecha</td><td class="meta-value">' . $e($issuedAt) . '</td></tr>'
            . ($dueAt !== '' ? '<tr><td class="meta-label">Vencimiento</td><td class="meta-value">' . $e($dueAt) . '</td></tr>' : '')
            . '<tr><td class="meta-label">Estado</td><td class="meta-value"><span class="status ' . $statusClass . '">' . $e($status) . '</span></td></tr></table></td></tr></table>'
            . $fiscalBlock
            . '<table class="items"><thead><tr><th style="width:6%">No.</th><th style="width:46%">Descripción</th><th class="num" style="width:16%">Precio</th><th class="num" style="width:12%">Cant.</th><th class="num" style="width:20%">Total</th></tr></thead><tbody>' . $detailRows . '</tbody></table>'
            . '<table class="summary"><tr><td class="payment-panel"><div class="panel-title">INFORMACIÓN DE PAGO</div>' . $paymentRows . '</td><td class="totals-panel"><table class="totals"><tr><td>Subtotal</td><td><span class="currency">' . $currency . '</span> ' . $money($invoice['subtotal'] ?? 0) . '</td></tr><tr><td>Descuento</td><td><span class="currency">' . $currency . '</span> ' . $money($invoice['descuento_monto'] ?? $invoice['descuento'] ?? 0) . '</td></tr><tr><td>Impuestos</td><td><span class="currency">' . $currency . '</span> ' . $money($invoice['impuesto_monto'] ?? $invoice['impuesto'] ?? 0) . '</td></tr>' . $shippingRow . '<tr><td>Propina</td><td><span class="currency">' . $currency . '</span> ' . $money($invoice['propina_monto'] ?? 0) . '</td></tr><tr class="grand"><td>TOTAL</td><td>' . $currency . ' ' . $money($invoice['total'] ?? 0) . '</td></tr></table></td></tr></table>'
            . '<table class="lower"><tr><td class="terms"><div class="panel-title">TÉRMINOS Y OBSERVACIONES</div><p>' . ($notes !== '' ? nl2br($e($notes)) : 'Gracias por su compra. Conserve este documento para futuras referencias.') . '</p></td><td class="qr-cell">' . $qrBlock . '</td><td class="signature"><div class="signature-line">Firma autorizada</div></td></tr></table></section>'
            . '<footer class="footer"><table class="footer-table"><tr><td class="footer-contact"><strong>' . $companyName . '</strong><br>' . $companyAddress . ($companyPhone !== '' ? '<br>Tel: ' . $companyPhone : '') . ($companyEmail !== '' ? '<br>' . $companyEmail : '') . '</td><td class="footer-note">Documento generado desde la última versión sincronizada.<br>Los enlaces compartidos reflejan las modificaciones de la factura.</td></tr></table></footer></main></body></html>';
    }

    private function companyData(array $project, array $invoice): array
    {
        $table = $this->firstIfTable($project, 'empresa');
        $license = $this->licenses?->companyData((string) ($project['uid'] ?? '')) ?? [];
        $stampRnc = '';
        $stampUrl = html_entity_decode(trim((string) ($invoice['alanube_stamp_url'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($this->isTrustedDgiiUrl($stampUrl)) {
            parse_str((string) (parse_url($stampUrl, PHP_URL_QUERY) ?? ''), $stampParameters);
            $stampRnc = (string) ($stampParameters['RncEmisor'] ?? $stampParameters['RNC'] ?? '');
        }

        return [
            'nombre' => $this->firstValue($table['nombre'] ?? '', $license['nombre'] ?? '', $invoice['empresa_nombre'] ?? '', $invoice['emisor_nombre'] ?? '', $project['name'] ?? ''),
            'rnc' => $this->firstValue($table['rnc'] ?? '', $license['rnc'] ?? '', $invoice['rnc_emisor'] ?? '', $invoice['emisor_rnc'] ?? '', $invoice['empresa_rnc'] ?? '', $stampRnc),
            'telefono' => $this->firstValue($table['telefono'] ?? '', $license['telefono'] ?? '', $invoice['empresa_telefono'] ?? '', $invoice['emisor_telefono'] ?? ''),
            'email' => $this->firstValue($table['email'] ?? '', $license['email'] ?? '', $invoice['empresa_email'] ?? '', $invoice['emisor_email'] ?? ''),
            'direccion' => $this->firstValue($table['direccion'] ?? '', $license['direccion'] ?? '', $invoice['empresa_direccion'] ?? '', $invoice['emisor_direccion'] ?? ''),
            'logo' => $this->firstValue($table['logo'] ?? '', $license['logo'] ?? '', $invoice['empresa_logo'] ?? '', $invoice['emisor_logo'] ?? ''),
            'moneda' => $this->firstValue($table['moneda'] ?? '', $license['moneda'] ?? '', $invoice['moneda'] ?? '', 'RD$'),
        ];
    }

    private function firstValue(mixed ...$values): string
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }
        return '';
    }

    private function invoiceFooterHtml(array $project, array $invoice): string
    {
        $company = $this->companyData($project, $invoice);
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $companyName = $e($company['nombre'] ?? $project['name'] ?? 'Empresa');
        $documentTitle = $e($this->firstValue($invoice['documento_titulo'] ?? '', 'Factura'));
        $invoiceNumber = $e($this->firstValue(
            $invoice['no_factura'] ?? '',
            $invoice['numero'] ?? '',
            $invoice['uid'] ?? ''
        ));
        $contact = array_values(array_filter([
            trim((string) ($company['rnc'] ?? '')) !== '' ? 'RNC: ' . trim((string) $company['rnc']) : '',
            trim((string) ($company['direccion'] ?? '')),
            trim((string) ($company['telefono'] ?? '')) !== '' ? 'Tel: ' . trim((string) $company['telefono']) : '',
            trim((string) ($company['email'] ?? '')),
        ], static fn (string $value): bool => $value !== ''));
        $contactHtml = $contact
            ? implode('<br>', array_map($e, $contact))
            : 'Documento comercial emitido electronicamente';

        return '<div style="height:27mm;background:#0b3c46;border-top:2.5mm solid #12aeb0;color:#d7eff0;padding:4mm 9mm 3mm;box-sizing:border-box">'
            . '<table style="width:100%;border-collapse:collapse"><tr>'
            . '<td style="width:56%;border:0;vertical-align:middle;font-size:8pt;line-height:1.45;color:#d7eff0"><div style="font-size:10pt;font-weight:bold;color:#fff;letter-spacing:.4pt;margin-bottom:1.5mm">' . $companyName . '</div>' . $contactHtml . '</td>'
            . '<td style="width:44%;border:0;vertical-align:middle;text-align:right;font-size:7.5pt;line-height:1.45;color:#b9d9da"><div style="font-size:10pt;font-weight:bold;color:#fff;letter-spacing:.7pt;margin-bottom:1.5mm">GRACIAS POR SU COMPRA</div>' . $documentTitle . ' ' . $invoiceNumber . '<br>Documento actualizado en linea &nbsp;|&nbsp; Pagina {PAGENO} de {nbpg}</td>'
            . '</tr></table></div>';
    }

    private function dgiiVerificationUrl(array $company, array $client, array $invoice, string $ncf, string $securityCode): string
    {
        if ($ncf === '') {
            return '';
        }
        $stampUrl = html_entity_decode(trim((string) ($invoice['alanube_stamp_url'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($this->isTrustedDgiiUrl($stampUrl)) {
            return $stampUrl;
        }

        $issuerRnc = preg_replace('/\D+/', '', (string) (
            $company['rnc']
            ?? $invoice['rnc_emisor']
            ?? $invoice['emisor_rnc']
            ?? $invoice['empresa_rnc']
            ?? ''
        )) ?? '';
        if ($issuerRnc === '' || $ncf === '') {
            return '';
        }
        $buyerRnc = preg_replace('/\D+/', '', (string) ($client['cedula_rnc'] ?? $client['rnc'] ?? '')) ?? '';

        if (str_starts_with(strtoupper($ncf), 'E')) {
            $parameters = [
                'RncEmisor' => $issuerRnc,
                'ENCF' => $ncf,
                'MontoTotal' => number_format((float) ($invoice['total'] ?? 0), 2, '.', ''),
                'CodigoSeguridad' => $securityCode,
            ];
            if ($buyerRnc !== '') {
                $parameters['RncComprador'] = $buyerRnc;
            }
            return 'https://fc.dgii.gov.do/ecf/ConsultaTimbreFC?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
        }

        $parameters = ['RNC' => $issuerRnc, 'NCF' => $ncf];
        if ($buyerRnc !== '') {
            $parameters['RNCComprador'] = $buyerRnc;
        }
        return 'https://dgii.gov.do/app/WebApps/ConsultasWeb2/ConsultasWeb/consultas/ncf.aspx?'
            . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }

    private function isTrustedDgiiUrl(string $url): bool
    {
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        return strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && ($host === 'fc.dgii.gov.do' || str_ends_with($host, '.dgii.gov.do'));
    }

    private function companyLogoDataUri(array $project, string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('#^data:image/(?:png|jpeg|gif|webp);base64,([A-Za-z0-9+/=\r\n]+)$#i', $value, $match)) {
            $decoded = base64_decode($match[1], true);
            return $decoded !== false && strlen($decoded) <= 5 * 1024 * 1024 ? $value : '';
        }

        try {
            $db = $this->schema->connection($project);
            $stmt = $db->prepare('SELECT path,mime_type,size FROM _system_files WHERE uid = ? OR url = ? LIMIT 1');
            $stmt->execute([$value, $value]);
            $file = $stmt->fetch();
            if (!$file || !is_file((string) $file['path']) || (int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
                return '';
            }
            $mime = (string) ($file['mime_type'] ?? '');
            if (!in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
                return '';
            }
            $contents = file_get_contents((string) $file['path']);
            return $contents === false ? '' : 'data:' . $mime . ';base64,' . base64_encode($contents);
        } catch (\Throwable) {
            return '';
        }
    }

    private function initials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name)) ?: [];
        $letters = '';
        foreach (array_slice($words, 0, 2) as $word) {
            $letters .= mb_strtoupper(mb_substr($word, 0, 1));
        }
        return $letters !== '' ? $letters : 'TM';
    }

    private function formatDate(string $value): string
    {
        if (trim($value) === '') {
            return '';
        }
        try {
            return (new \DateTimeImmutable($value))->format('d/m/Y h:i A');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function firstIfTable(array $project, string $table): array
    {
        try {
            $this->schema->columns($project, $table);
            return $this->schema->connection($project)->query('SELECT * FROM "' . $table . '" ORDER BY id ASC LIMIT 1')->fetch() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function byLocalReference(array $project, string $table, mixed $reference): array
    {
        try {
            $this->schema->columns($project, $table);
            $stmt = $this->schema->connection($project)->prepare('SELECT * FROM "' . $table . '" WHERE id = ? OR uid = ? LIMIT 1');
            $stmt->execute([$reference, (string) $reference]);
            return $stmt->fetch() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function byForeignReference(array $project, string $table, string $column, array $record): array
    {
        try {
            $this->schema->columns($project, $table);
            $references = array_values(array_unique(array_filter(
                [$record['id'] ?? null, $record['uid'] ?? null],
                static fn ($value): bool => $value !== null && $value !== ''
            )));
            if (!$references) {
                return [];
            }
            $stmt = $this->schema->connection($project)->prepare(
                'SELECT * FROM "' . $table . '" WHERE "' . $column . '" IN ('
                . implode(',', array_fill(0, count($references), '?')) . ') ORDER BY id ASC'
            );
            $stmt->execute($references);
            return $stmt->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }

    private function byValueReference(array $project, string $table, string $column, mixed $reference): array
    {
        try {
            $columns = array_column($this->schema->columns($project, $table), 'name');
            if (!in_array($column, $columns, true)) {
                return [];
            }
            $stmt = $this->schema->connection($project)->prepare(
                'SELECT * FROM "' . $table . '" WHERE "' . $column . '" = ? ORDER BY id ASC'
            );
            $stmt->execute([$reference]);
            return $stmt->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }

    private function inlineInvoiceDetails(array $invoice): array
    {
        $raw = null;
        foreach (['productos', 'products', 'items', 'detalle', 'detalles'] as $column) {
            if (array_key_exists($column, $invoice) && $invoice[$column] !== null && $invoice[$column] !== '') {
                $raw = $invoice[$column];
                break;
            }
        }
        if (is_string($raw)) {
            for ($attempt = 0; $attempt < 2; $attempt++) {
                $decoded = json_decode($raw, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return [];
                }
                $raw = $decoded;
                if (!is_string($raw)) {
                    break;
                }
            }
        }
        if (is_array($raw) && !array_is_list($raw)) {
            $raw = $raw['items'] ?? $raw['productos'] ?? $raw['products'] ?? [];
        }
        if (!is_array($raw)) {
            return [];
        }

        $details = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = trim((string) ($item['nombre'] ?? $item['descripcion'] ?? $item['name'] ?? $item['producto'] ?? $item['equipo'] ?? 'Producto'));
            $quantity = max(0.0, (float) ($item['cantidad'] ?? $item['quantity'] ?? $item['qty'] ?? 1));
            $unit = (float) ($item['precio_unitario'] ?? $item['precio'] ?? $item['price'] ?? $item['unit_price'] ?? $item['precio_normal'] ?? 0);
            $notes = [];
            $imeiValues = $item['imeis'] ?? [];
            if (!is_array($imeiValues)) {
                $imeiValues = [];
            }
            $imei = trim((string) ($item['imei'] ?? ''));
            if ($imei !== '' && !in_array($imei, $imeiValues, true)) {
                array_unshift($imeiValues, $imei);
            }
            $imeiValues = array_values(array_filter(array_map(
                static fn (mixed $value): string => trim((string) $value),
                $imeiValues
            )));
            if ($imeiValues) {
                $notes[] = 'IMEI: ' . implode(', ', $imeiValues);
            }
            $serialValues = $item['seriales'] ?? [];
            if (!is_array($serialValues)) {
                $serialValues = [];
            }
            $serial = trim((string) ($item['serial'] ?? ''));
            if ($serial !== '' && !in_array($serial, $serialValues, true)) {
                array_unshift($serialValues, $serial);
            }
            $serialValues = array_values(array_filter(array_map(
                static fn (mixed $value): string => trim((string) $value),
                $serialValues
            )));
            if ($serialValues) {
                $notes[] = 'Serial: ' . implode(', ', $serialValues);
            }
            foreach (['color' => 'Color', 'capacidad' => 'Capacidad', 'codigo' => 'Código'] as $key => $label) {
                $value = trim((string) ($item[$key] ?? ''));
                if ($value !== '') {
                    $notes[] = $label . ': ' . $value;
                }
            }
            $existingNotes = trim((string) ($item['notas'] ?? $item['nota'] ?? ''));
            if ($existingNotes !== '') {
                $notes[] = $existingNotes;
            }
            $details[] = [
                'nombre' => $name !== '' ? $name : 'Producto',
                'cantidad' => $quantity > 0 ? $quantity : 1,
                'precio_unitario' => $unit,
                'total' => (float) ($item['total'] ?? $item['importe'] ?? (($quantity > 0 ? $quantity : 1) * $unit)),
                'notas' => implode(' · ', array_values(array_unique($notes))),
            ];
        }
        return $details;
    }
}
