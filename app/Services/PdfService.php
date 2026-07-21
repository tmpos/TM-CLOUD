<?php

declare(strict_types=1);

namespace App\Services;

final class PdfService
{
    public function __construct(private SchemaService $schema)
    {
    }

    public function invoice(array $project, string $table, array $record): string
    {
        $html = $this->invoiceHtml($project, $record);
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
                'margin_bottom' => 0,
            ]);
            $mpdf->SetTitle('Factura ' . (string) ($record['numero'] ?? $record['uid'] ?? ''));
            $mpdf->SetAuthor((string) ($project['name'] ?? 'TMPBase'));
            $mpdf->WriteHTML($html);
            return $mpdf->Output('', 'S');
        } catch (\Throwable $e) {
            throw new \RuntimeException('Could not generate the invoice PDF.', 0, $e);
        }
    }

    public function invoiceHtml(array $project, array $invoice): string
    {
        $company = $this->firstIfTable($project, 'empresa');
        $client = [];
        if (!empty($invoice['cliente_id'])) {
            $client = $this->byLocalReference($project, 'clientes', $invoice['cliente_id']);
        }

        $details = $this->byForeignReference($project, 'factura_detalle', 'factura_id', $invoice);
        if (!$details && !empty($invoice['orden_id'])) {
            $details = $this->byValueReference($project, 'orden_detalle', 'orden_id', $invoice['orden_id']);
        }
        $payments = $this->byForeignReference($project, 'factura_pagos', 'factura_id', $invoice);

        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $money = static fn (mixed $value): string => number_format((float) $value, 2, '.', ',');
        $companyName = $e($company['nombre'] ?? $project['name'] ?? 'Empresa');
        $invoiceNumber = $e($invoice['numero'] ?? $invoice['uid'] ?? '');
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
            $unit = (float) ($item['precio_unitario'] ?? 0);
            $total = (float) ($item['total'] ?? ($quantity * $unit));
            $description = $item['nombre'] ?? $item['descripcion'] ?? 'Producto';
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

        $status = strtoupper(trim((string) ($invoice['estado'] ?? 'PAGADA')));
        $statusClass = in_array(strtolower($status), ['anulada', 'cancelada'], true) ? 'cancelled' : 'paid';
        $issuedAt = $this->formatDate((string) ($invoice['created_at'] ?? ''));
        $dueAt = $this->formatDate((string) ($invoice['fecha_vencimiento'] ?? $invoice['vence_at'] ?? ''));
        $clientName = $e($client['nombre'] ?? 'Consumidor final');
        $clientTaxId = $e($client['cedula_rnc'] ?? $client['rnc'] ?? '');
        $clientAddress = $e($client['direccion'] ?? '');
        $clientPhone = $e($client['telefono'] ?? '');
        $clientEmail = $e($client['email'] ?? '');
        $companyRnc = $e($company['rnc'] ?? '');
        $companyAddress = $e($company['direccion'] ?? '');
        $companyPhone = $e($company['telefono'] ?? '');
        $companyEmail = $e($company['email'] ?? '');
        $notes = trim((string) ($invoice['notas'] ?? ''));

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
            $qrBlock = '<div class="qr-box"><barcode code="' . $e($verificationUrl) . '" type="QR" size="0.82" error="M" disableborder="1" />'
                . '<div class="qr-title">VERIFICAR EN DGII</div><div class="qr-caption">Escanee para validar el comprobante</div>'
                . '<div class="qr-link">' . $e($verificationUrl) . '</div></div>';
        }

        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Factura ' . $invoiceNumber . '</title><style>'
            . '@page{margin:0}*{box-sizing:border-box}body{font-family:DejaVu Sans,Arial,sans-serif;color:#17212b;background:#fff;margin:0;font-size:10px}.page{position:relative;min-height:1056px;background:#fff;padding-bottom:92px}.hero{height:170px;background:#0b3c46;color:#fff;position:relative;overflow:hidden;padding:34px 42px}.hero-accent{position:absolute;left:-35px;top:-70px;width:430px;height:185px;background:#078b8f;border-radius:0 0 260px 0}.hero-line{position:absolute;left:0;bottom:0;width:100%;height:13px;background:#08a1a3}.hero-table{position:relative;z-index:2;width:100%;border-collapse:collapse}.hero-table td{border:0;padding:0;vertical-align:middle}.invoice-title{font-size:30px;font-weight:700;letter-spacing:1.7px}.invoice-subtitle{font-size:9px;letter-spacing:2px;margin-top:5px;color:#c5f5f2}.brand-side{text-align:right;width:48%}.brand-logo{max-width:155px;max-height:72px;background:#fff;border-radius:7px;padding:6px}.brand-mark{display:inline-block;background:#fff;color:#078b8f;font-size:24px;font-weight:bold;border-radius:50%;width:62px;height:62px;line-height:62px;text-align:center}.brand-name{font-size:15px;font-weight:bold;margin-top:7px}.brand-rnc{font-size:9px;color:#c9e7e8;margin-top:3px}.wave{height:30px;background:#f1f5f5;border-radius:0 0 70% 0;margin-top:-1px}.content{padding:15px 42px 0}.info-table{width:100%;border-collapse:collapse;margin-bottom:18px}.info-table td{border:0;vertical-align:top;padding:0}.bill-to{width:55%;padding-right:25px!important}.invoice-meta{width:45%}.section-kicker{font-size:9px;color:#078b8f;font-weight:bold;letter-spacing:1.4px;margin-bottom:5px}.client-name{font-size:15px;font-weight:bold;color:#123b43;margin-bottom:5px}.contact-line{color:#5f6d73;line-height:1.55}.meta-grid{width:100%;border-collapse:collapse}.meta-grid td{padding:3px 0;border:0}.meta-grid .meta-label{color:#66777d;width:45%}.meta-grid .meta-value{text-align:right;font-weight:bold;color:#153e46}.status{display:inline-block;border-radius:12px;padding:3px 9px;font-size:8px;font-weight:bold}.paid{background:#dcfce7;color:#166534}.cancelled{background:#fee2e2;color:#991b1b}.fiscal-card{background:#eefafa;border-left:4px solid #078b8f;padding:9px 12px;margin:2px 0 16px}.fiscal-title{font-size:9px;letter-spacing:1px;color:#067276;font-weight:bold;margin-bottom:5px}.fiscal-card div:not(.fiscal-title){line-height:1.55}.fiscal-card span{display:inline-block;color:#617277;width:115px}.items{width:100%;border-collapse:collapse}.items thead th{background:#102e3a;color:#fff;padding:8px 8px;font-size:8px;text-transform:uppercase;letter-spacing:.4px;text-align:left}.items thead th.num{text-align:right}.items tbody td{padding:8px;border-bottom:3px solid #fff;background:#f1f3f4;color:#29373c}.items tbody tr:nth-child(even) td{background:#e5e8e9}.line-number{width:28px;text-align:center;color:#65757a!important}.num{text-align:right;white-space:nowrap}.total-cell{font-weight:bold;color:#0b555a!important}.item-note{font-size:8px;color:#718087;margin-top:2px}.empty{text-align:center;color:#849399;padding:22px!important}.summary{width:100%;border-collapse:collapse;margin-top:18px}.summary td{border:0;vertical-align:top;padding:0}.payment-panel{width:49%;padding-right:28px!important}.totals-panel{width:51%;padding-left:20px!important}.panel-title{font-size:9px;color:#123b43;font-weight:bold;letter-spacing:1px;border-bottom:1px solid #d5dddf;padding-bottom:5px;margin-bottom:5px}.payment-line{border-bottom:1px solid #e4e9ea;padding:5px 0}.payment-line span{display:inline-block;width:65%;color:#66777d}.payment-line strong{display:inline-block;width:34%;text-align:right}.totals{width:100%;border-collapse:collapse}.totals td{padding:4px 6px;border:0}.totals td:last-child{text-align:right;font-weight:bold}.totals .grand td{background:#102e3a;color:#fff;font-size:13px;padding:8px}.lower{width:100%;border-collapse:collapse;margin-top:18px}.lower td{border:0;vertical-align:top;padding:0}.terms{width:60%;padding-right:22px!important}.terms p{color:#66777d;line-height:1.55;margin:5px 0}.qr-cell{width:18%;text-align:center}.signature{width:22%;text-align:center;padding-left:15px!important}.signature-line{border-top:1px solid #263b42;margin-top:48px;padding-top:5px;font-weight:bold}.qr-box{text-align:center}.qr-box barcode{display:block;margin:auto}.qr-title{color:#078b8f;font-size:8px;font-weight:bold;margin-top:4px}.qr-caption{font-size:6.5px;color:#67777c}.qr-link{font-size:4.7px;color:#8a969a;word-break:break-all;margin-top:3px}.footer{position:absolute;left:0;right:0;bottom:0;height:80px;background:#0b3c46;color:#fff;padding:19px 42px;border-top:9px solid #08a1a3}.footer-table{width:100%;border-collapse:collapse}.footer-table td{border:0;padding:0;vertical-align:middle}.footer-contact{line-height:1.55;color:#d0e5e6}.footer-note{text-align:right;color:#9fc1c3;font-size:8px}.currency{font-size:8px;color:#819096;font-weight:normal}'
            . '.page{min-height:0;overflow:visible;padding-bottom:0}.hero{height:auto;padding:0;background:#0b3c46;overflow:hidden}.hero-layout{width:100%;border-collapse:collapse}.hero-layout td{height:142px;border:0;vertical-align:middle}.hero-title-cell{width:55%;padding:30px 42px;background:#078b8f;border-radius:0 0 105px 0}.invoice-title{color:#fff;line-height:1;font-size:31px}.invoice-subtitle{color:#d6ffff}.brand-side{position:static;width:45%;padding:20px 42px 16px 20px;text-align:right;color:#fff}.brand-name{color:#fff}.hero-line{position:static;height:9px;background:#12aeb0}.wave,.hero-accent,.hero-accent-back,.hero-cut,.hero-copy{display:none}.content{padding-top:20px}.footer{position:fixed;height:90px;padding:19px 42px 14px;border-top:9px solid #12aeb0;overflow:hidden}.footer:before,.footer:after{display:none}.footer-table{position:static}.footer-contact{width:55%;font-size:7.5px;line-height:1.55}.footer-contact strong{display:block;color:#fff;font-size:10px;letter-spacing:.5px;margin-bottom:2px}.footer-note{color:#c2e0e1;font-size:7.5px;line-height:1.5}.qr-box barcode{margin-top:0}.signature-line{margin-top:38px}</style></head><body><main class="page"><header class="hero"><table class="hero-layout"><tr><td class="hero-title-cell"><div class="invoice-title">FACTURA</div><div class="invoice-subtitle">DOCUMENTO COMERCIAL</div></td><td class="brand-side">' . $logoHtml . '<div class="brand-name">' . $companyName . '</div><div class="brand-rnc">' . ($companyRnc !== '' ? 'RNC ' . $companyRnc : '') . '</div></td></tr></table><div class="hero-line"></div></header>'
            . '<section class="content"><table class="info-table"><tr><td class="bill-to"><div class="section-kicker">FACTURAR A</div><div class="client-name">' . $clientName . '</div>'
            . ($clientTaxId !== '' ? '<div class="contact-line">RNC/Cédula: ' . $clientTaxId . '</div>' : '')
            . ($clientAddress !== '' ? '<div class="contact-line">' . $clientAddress . '</div>' : '')
            . ($clientPhone !== '' ? '<div class="contact-line">Tel: ' . $clientPhone . '</div>' : '')
            . ($clientEmail !== '' ? '<div class="contact-line">' . $clientEmail . '</div>' : '')
            . '</td><td class="invoice-meta"><table class="meta-grid"><tr><td class="meta-label">Factura No.</td><td class="meta-value">' . $invoiceNumber . '</td></tr><tr><td class="meta-label">Fecha</td><td class="meta-value">' . $e($issuedAt) . '</td></tr>'
            . ($dueAt !== '' ? '<tr><td class="meta-label">Vencimiento</td><td class="meta-value">' . $e($dueAt) . '</td></tr>' : '')
            . '<tr><td class="meta-label">Estado</td><td class="meta-value"><span class="status ' . $statusClass . '">' . $e($status) . '</span></td></tr></table></td></tr></table>'
            . $fiscalBlock
            . '<table class="items"><thead><tr><th style="width:6%">No.</th><th style="width:46%">Descripción</th><th class="num" style="width:16%">Precio</th><th class="num" style="width:12%">Cant.</th><th class="num" style="width:20%">Total</th></tr></thead><tbody>' . $detailRows . '</tbody></table>'
            . '<table class="summary"><tr><td class="payment-panel"><div class="panel-title">INFORMACIÓN DE PAGO</div>' . $paymentRows . '</td><td class="totals-panel"><table class="totals"><tr><td>Subtotal</td><td><span class="currency">' . $currency . '</span> ' . $money($invoice['subtotal'] ?? 0) . '</td></tr><tr><td>Descuento</td><td><span class="currency">' . $currency . '</span> ' . $money($invoice['descuento_monto'] ?? 0) . '</td></tr><tr><td>Impuestos</td><td><span class="currency">' . $currency . '</span> ' . $money($invoice['impuesto_monto'] ?? 0) . '</td></tr><tr><td>Propina</td><td><span class="currency">' . $currency . '</span> ' . $money($invoice['propina_monto'] ?? 0) . '</td></tr><tr class="grand"><td>TOTAL</td><td>' . $currency . ' ' . $money($invoice['total'] ?? 0) . '</td></tr></table></td></tr></table>'
            . '<table class="lower"><tr><td class="terms"><div class="panel-title">TÉRMINOS Y OBSERVACIONES</div><p>' . ($notes !== '' ? nl2br($e($notes)) : 'Gracias por su compra. Conserve este documento para futuras referencias.') . '</p></td><td class="qr-cell">' . $qrBlock . '</td><td class="signature"><div class="signature-line">Firma autorizada</div></td></tr></table></section>'
            . '<footer class="footer"><table class="footer-table"><tr><td class="footer-contact"><strong>' . $companyName . '</strong><br>' . $companyAddress . ($companyPhone !== '' ? '<br>Tel: ' . $companyPhone : '') . ($companyEmail !== '' ? '<br>' . $companyEmail : '') . '</td><td class="footer-note">Documento generado desde la última versión sincronizada.<br>Los enlaces compartidos reflejan las modificaciones de la factura.</td></tr></table></footer></main></body></html>';
    }

    private function dgiiVerificationUrl(array $company, array $client, array $invoice, string $ncf, string $securityCode): string
    {
        $issuerRnc = preg_replace('/\D+/', '', (string) ($company['rnc'] ?? '')) ?? '';
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
}
