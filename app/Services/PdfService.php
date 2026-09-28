<?php

declare(strict_types=1);

namespace App\Services;

final class PdfService
{
    public function __construct(private SchemaService $schema, private ?LicenseService $licenses = null, private ?InvoiceSignatureService $signatures = null)
    {
    }

    public function invoice(array $project, string $table, array $record): string
    {
        $record = $this->documentIdentity($record);
        $html = $this->invoiceHtml($project, $record);
        $html = preg_replace('#<footer class="footer">.*?</footer>#s', '', $html) ?? $html;
        $html = str_replace('@page{margin:10mm}', '@page{margin:10mm 10mm 18mm}', $html);
        $footer = $this->invoiceFooterHtml($project, $record);
        if (!class_exists(\Mpdf\Mpdf::class)) {
            throw new \RuntimeException('PDF generator is not installed. Run composer install.');
        }
        try {
            $mpdf = new \Mpdf\Mpdf([
                'tempDir' => sys_get_temp_dir(),
                'format' => 'Letter',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 10,
                'margin_bottom' => 18,
                'margin_footer' => 7,
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
            . '<tr><td class="label">Fecha</td><td class="value">' . $e($this->issuedDate($project, $record)) . '</td></tr>'
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
        $invoice = $this->documentIdentity($invoice);
        $quote = $this->isQuotation($invoice);
        $design = (new DocumentSettingsService($this->schema))->get($project);
        $company = $this->companyData($project, $invoice);
        $client = $this->documentCustomer($project, $invoice);

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
        $ncf = $quote ? '' : trim((string) ($invoice['ncf'] ?? $invoice['comprobante'] ?? ''));
        $securityCode = trim((string) ($invoice['alanube_security_code'] ?? $invoice['codigo_seguridad'] ?? ''));
        $verificationUrl = $this->dgiiVerificationUrl($company, $client, $invoice, $ncf, $securityCode);
        $logo = $this->companyLogoDataUri($project, (string) ($company['logo'] ?? ''));

        $status = strtoupper(trim((string) ($invoice['estado_factura'] ?? $invoice['estado'] ?? 'PAGADA')));
        $issuedAt = $this->issuedDate($project, $invoice);
        $dueAt = $this->formatDate((string) ($invoice['fecha_vencimiento'] ?? $invoice['vence_at'] ?? ''));
        $clientName = $e($this->firstValue($invoice['nombre_cliente'] ?? '', $invoice['customer_name'] ?? '', $client['nombre'] ?? '', 'Consumidor final'));
        $clientTaxId = $e($this->firstValue($invoice['rnc_cliente'] ?? '', $invoice['cedula_cliente'] ?? '', $invoice['cedula_rnc'] ?? '', $invoice['customer_document'] ?? '', $client['rnc'] ?? '', $client['cedula'] ?? '', $client['cedula_rnc'] ?? ''));
        $clientAddress = $e($this->firstValue($invoice['direccion_cliente'] ?? '', $invoice['delivery_address'] ?? '', $client['direccion'] ?? ''));
        $clientPhone = $e($this->firstValue($invoice['telefono_cliente'] ?? '', $invoice['customer_phone'] ?? '', $client['telefono'] ?? '', $client['whatsapp'] ?? ''));
        $clientEmail = $e($this->firstValue($invoice['email_cliente'] ?? '', $invoice['correo_cliente'] ?? '', $invoice['customer_email'] ?? '', $client['email'] ?? '', $client['correo'] ?? ''));
        $companyRnc = $e($company['rnc'] ?? '');
        $companyAddress = $e($company['direccion'] ?? '');
        $companyPhone = $e($company['telefono'] ?? '');
        $companyEmail = $e($company['email'] ?? '');
        $notes = trim((string) ($invoice['notas'] ?? $invoice['nota'] ?? ''));
        $shippingRow = (float) ($invoice['envio_monto'] ?? 0) > 0
            ? '<tr><td>Envío</td><td><span class="currency">' . $currency . '</span> ' . $money($invoice['envio_monto']) . '</td></tr>'
            : '';

        $qrBlock = '';
        if ($verificationUrl !== '') {
            $qrBlock = '<table class="qr-box" style="width:100%;text-align:center;line-height:1.15">'
                . '<tr><td style="padding-bottom:2mm;color:#078b8f;font-size:6pt;font-weight:bold;text-align:center">VERIFICAR EN DGII</td></tr>'
                . '<tr><td style="text-align:center"><barcode code="' . $e($verificationUrl) . '" type="QR" size="0.68" error="M" disableborder="1" /></td></tr>'
                . '<tr><td style="padding-top:1.5mm;color:#66777d;font-size:5.5pt;text-align:center">Escanee para validar el comprobante</td></tr></table>';
        }

        $documentTitle = $e(strtoupper($this->firstValue($invoice['documento_titulo'] ?? '', 'Factura')));
        $signature = $quote ? ['status' => 'none'] : ($this->signatures?->status($project, $invoice, true) ?? ['status' => 'none']);
        $signatureHtml = '<div class="signature-line">Firma del cliente</div>';
        if ($signature['status'] === 'signed') {
            $signatureHtml = '<img src="' . $e($signature['signature_data_uri']) . '" alt="Firma del cliente" style="max-width:100%;max-height:65px">'
                . '<div class="signature-line" style="margin-top:2px">Firmado por ' . $e($signature['signer_name']) . '</div>'
                . '<div style="font-size:7px;color:#66777d">' . $e($signature['signed_at']) . ' UTC</div>';
        }

        ob_start();
        try {
            require dirname(__DIR__) . '/Views/document-pdf.php';
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    public function documentFilename(array $invoice): string
    {
        $invoice = $this->documentIdentity($invoice);
        $number = $this->firstValue($invoice['no_factura'] ?? '', $invoice['numero'] ?? '', $invoice['uid'] ?? '');
        return ($this->isQuotation($invoice) ? 'Cotizacion_' : 'Factura_') . preg_replace('/[^a-zA-Z0-9_-]/', '_', $number) . '.pdf';
    }

    private function isQuotation(array $invoice): bool
    {
        foreach (['tipo_factura', 'estado_factura'] as $key) {
            if (in_array(mb_strtoupper(trim((string) ($invoice[$key] ?? ''))), ['COTIZACION', 'COTIZACIÓN'], true)) return true;
        }
        return false;
    }

    private function documentIdentity(array $invoice): array
    {
        if ($this->isQuotation($invoice)) {
            $number = $this->firstValue($invoice['no_factura'] ?? '', $invoice['numero'] ?? '', $invoice['id'] ?? '', $invoice['uid'] ?? '');
            $number = preg_replace('/^(?:COT|F)[-\s]*/i', '', $number) ?? $number;
            $invoice['no_factura'] = 'COT' . str_pad($number, 6, '0', STR_PAD_LEFT);
            $invoice['documento_titulo'] = 'Cotización';
            $invoice['documento_numero_label'] = 'Cotización No.';
        }
        return $invoice;
    }

    private function documentCustomer(array $project, array $invoice): array
    {
        try {
            $columns = array_column($this->schema->columns($project, 'clientes'), 'name');
            $db = $this->schema->connection($project);
            foreach (['cliente_uid', 'cliente_id', 'cod_cliente'] as $key) {
                $reference = trim((string) ($invoice[$key] ?? ''));
                if ($reference === '') continue;
                foreach (['uid', 'id', 'codigo'] as $column) {
                    if (!in_array($column, $columns, true)) continue;
                    $query = $db->prepare('SELECT * FROM clientes WHERE "' . $column . '" = ? LIMIT 2');
                    $query->execute([$reference]);
                    $matches = $query->fetchAll();
                    if (count($matches) === 1) return $matches[0];
                }
            }
            foreach ([
                [$this->firstValue($invoice['rnc_cliente'] ?? '', $invoice['cedula_cliente'] ?? '', $invoice['cedula_rnc'] ?? ''), ['rnc', 'cedula', 'cedula_rnc']],
                [$this->firstValue($invoice['telefono_cliente'] ?? '', $invoice['customer_phone'] ?? ''), ['telefono', 'whatsapp']],
                [$this->firstValue($invoice['nombre_cliente'] ?? '', $invoice['customer_name'] ?? ''), ['nombre']],
            ] as [$reference, $fields]) {
                if ($reference === '' || mb_strtoupper($reference) === 'CONSUMIDOR FINAL') continue;
                $fields = array_values(array_intersect($fields, $columns));
                if (!$fields) continue;
                $query = $db->prepare('SELECT * FROM clientes WHERE ' . implode(' OR ', array_map(static fn ($field) => 'TRIM("' . $field . '") = ? COLLATE NOCASE', $fields)) . ' LIMIT 2');
                $query->execute(array_fill(0, count($fields), $reference));
                $matches = $query->fetchAll();
                if (count($matches) === 1) return $matches[0];
            }
        } catch (\Throwable) { }
        return [];
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
            'rnc' => $this->firstValue($table['rnc'] ?? '', $table['legal'] ?? '', $license['rnc'] ?? '', $invoice['rnc_emisor'] ?? '', $invoice['emisor_rnc'] ?? '', $invoice['empresa_rnc'] ?? '', $stampRnc),
            'telefono' => $this->firstValue($table['telefono'] ?? '', $license['telefono'] ?? '', $invoice['empresa_telefono'] ?? '', $invoice['emisor_telefono'] ?? ''),
            'email' => $this->firstValue($table['email'] ?? '', $license['email'] ?? '', $invoice['empresa_email'] ?? '', $invoice['emisor_email'] ?? ''),
            'direccion' => $this->firstValue($table['direccion'] ?? '', $license['direccion'] ?? '', $invoice['empresa_direccion'] ?? '', $invoice['emisor_direccion'] ?? ''),
            'logo' => $this->firstValue($table['logoprinter'] ?? '', $table['logo'] ?? '', $license['logo'] ?? '', $invoice['empresa_logo'] ?? '', $invoice['emisor_logo'] ?? ''),
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
        return '<div style="border-top:1px solid #d8e2ea;padding-top:3mm;font-size:8px;color:#8494a3"><table width="100%"><tr><td>' . $companyName . '</td><td style="text-align:right">' . $documentTitle . ' ' . $invoiceNumber . ' | {PAGENO}/{nbpg}</td></tr></table></div>';
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

    private function issuedDate(array $project, array $record): string
    {
        $zone = new \DateTimeZone('America/Santo_Domingo');
        try {
            $stmt = $this->schema->connection($project)->prepare("SELECT valor FROM configuracion WHERE clave = 'sistema_zona_horaria' LIMIT 1");
            $stmt->execute();
            $configured = trim((string) $stmt->fetchColumn());
            if ($configured !== '') $zone = new \DateTimeZone($configured);
        } catch (\Throwable) {
            // Older projects may not have regional settings yet.
        }
        $value = trim((string) ($record['fecha_emision'] ?? $record['created_at'] ?? ''));
        if ($value === '') return '';
        $time = trim((string) ($record['hora'] ?? ''));
        // POS stores business date and time separately, without a UTC offset.
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            if (preg_match('/^\d{1,2}:\d{2}(?::\d{2})?$/', $time)) $value .= ' ' . $time;
            else return (new \DateTimeImmutable($value, $zone))->format('d/m/Y');
        }
        try {
            // Only explicit offsets/Z are converted; naive stored dates are business time.
            return (new \DateTimeImmutable($value, $zone))->setTimezone($zone)->format('d/m/Y h:i A');
        } catch (\Throwable) {
            return $value;
        }
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
                'codigo' => $item['codigo'] ?? $item['codigo_barra'] ?? $item['sku'] ?? '',
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
