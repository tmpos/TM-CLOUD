<?php
// Table layout deliberately supports both browser previews and mPDF.
$visible = static fn (string $key): bool => ($design['visibility'][$key] ?? true) !== false;
$detailColumn = $visible('item_name') || $visible('description') || $visible('identifiers');
$showLineTax = $visible('line_tax');
$columnCount = (int) $showLineTax + (int) $detailColumn + count(array_filter(['code', 'quantity', 'price', 'line_total'], $visible));
$showTotals = count(array_filter(['subtotal', 'tax', 'discount', 'shipping', 'tip', 'payment', 'total'], $visible)) > 0;
$showClient = count(array_filter(['customer_name', 'customer_phone', 'customer_tax_id', 'customer_email', 'customer_address', 'payment', 'qr'], $visible)) > 0;
$primary = $e($design['primary_color']);
$heading = $e($design['heading_color']);
$tax = (float) ($invoice['impuesto_monto'] ?? $invoice['impuesto'] ?? 0);
$discount = (float) ($invoice['descuento_monto'] ?? $invoice['descuento'] ?? 0);
$total = (float) ($invoice['total'] ?? 0);
$subtotal = (float) ($invoice['subtotal'] ?? ($total + $discount - $tax));
$companySignature = $design['show_company_signature'] && $design['representative_signature'] !== '';
$currencyMoney = static fn ($value) => $currency . ' ' . $money($value);
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><title><?= $documentTitle ?> <?= $invoiceNumber ?></title>
<style>
@page{margin:10mm}*{box-sizing:border-box}body{margin:0;background:#fff;color:#172b3a;font-family:Arial,Helvetica,sans-serif;font-size:11px}
.page{max-width:760px;margin:0 auto;border-top:6px solid <?= $primary ?>;padding:18px 20px 14px}
table{border-collapse:collapse;width:100%}td{vertical-align:top}.header{border-bottom:1px solid #d8e2ea;margin-bottom:14px}.header td{padding-bottom:16px}
.company{width:59%;padding-right:28px}.company-name{color:<?= $heading ?>;font-size:20px;font-weight:bold;margin-bottom:6px}.company-meta{color:#52667a;font-size:10px;line-height:1.45}
.logo{max-width:<?= (int) $design['logo_width'] ?>px;max-height:<?= (int) $design['logo_height'] ?>px;margin-bottom:8px}
.invoice-box{border:1px solid #d8e2ea;background:#f7fafc;border-radius:12px}.document-heading{background:<?= $heading ?>;color:#fff;padding:11px 13px}.document-type{font-size:10px;font-weight:bold}.document-number{font-size:15px;font-weight:bold;margin-top:3px}.meta td{padding:5px 10px;border-bottom:1px solid #d8e2ea;font-size:10px;color:#52667a}.right,.num{text-align:right}.center{text-align:center}
.client-box{border:1px solid #d8e2ea;border-radius:12px;padding:11px 13px;margin-bottom:14px}.section-label{color:<?= $primary ?>;font-size:9px;font-weight:bold;letter-spacing:1px;margin-bottom:7px}.client-grid td{width:50%;padding:3px 12px 3px 0;color:#52667a;overflow-wrap:anywhere}.client-grid strong{color:<?= $heading ?>;font-size:9px}.qr{width:90px;text-align:center}
.products{border:1px solid #d8e2ea;border-radius:10px}.products th{background:<?= $heading ?>;color:#fff;padding:8px 7px;font-size:9px;text-align:left}.products td{padding:7px;border-bottom:1px solid #e8eef3;font-size:10px}.products tr:nth-child(even) td{background:#f7fafc}.item-note{font-size:8px;color:#52667a;margin-top:3px}.empty{text-align:center}
.note{margin-top:10px;padding:9px 11px;border-left:3px solid <?= $primary ?>;background:#f7fafc;font-size:10px;color:#52667a;line-height:1.4}.bottom{margin-top:14px}.signatures{width:55%;padding-right:34px;padding-top:26px;font-size:9px;color:#52667a}.signature-line{border-top:1px solid #7b8b99;padding-top:5px;text-align:center;margin-top:26px}.totals-panel{width:45%;border:1px solid #d8e2ea;border-radius:11px}.totals-title{padding:8px 10px;background:#eaf4f9;color:<?= $primary ?>;font-size:9px;font-weight:bold}.totals td{padding:6px 10px;border-bottom:1px solid #e8eef3}.totals td:last-child{text-align:right}.grand td{background:<?= $heading ?>;color:#fff;font-size:14px;font-weight:bold}.footer{border-top:1px solid #d8e2ea;margin-top:16px;padding-top:8px;color:#8494a3;font-size:8px}.validity{text-align:center;color:#666;font-size:10px;margin:8px 0}.payment-line{padding:3px 10px;font-size:10px;color:<?= $primary ?>}.payment-line strong{float:right}
@media print{.page{padding:0}*{print-color-adjust:exact;-webkit-print-color-adjust:exact}}
@media(max-width:600px){.page{padding:10px}.company{padding-right:10px}.company-name{font-size:16px}.products th,.products td{padding:5px 3px;font-size:8px}}
</style></head><body><main class="page">
<table class="header"><tr><td class="company">
<?php if ($design['show_logo'] && $logo !== ''): ?><img class="logo" src="<?= $e($logo) ?>" alt="Logo"><br><?php endif; ?>
<?php if ($visible('company_name')): ?><div class="company-name"><?= $companyName ?></div><?php endif; ?><div class="company-meta"><?php if ($companyTrademark !== ''): ?><div class="company-trademark" style="font-weight:bold;margin-bottom:3px"><?= $companyTrademark ?></div><?php endif; ?><?php if ($visible('company_tax_id')): ?><?= $companyRnc !== '' ? 'RNC ' . $companyRnc . '<br>' : '' ?><?php endif; ?><?php if ($visible('company_phone')): ?><?= $companyPhone ?><?php endif; ?><?php if ($visible('company_email')): ?><?= $companyEmail !== '' ? ' | ' . $companyEmail : '' ?><?php endif; ?><br><?php if ($visible('company_address')): ?><?= $companyAddress ?><?php endif; ?></div>
</td><td><table class="invoice-box"><tr><td class="document-heading" style="background:<?= $heading ?>;color:#fff;padding:11px 13px"><?php if ($visible('document_title')): ?><span class="document-type"><?= $documentTitle ?></span><?php endif; ?><br><?php if ($visible('document_number')): ?><span class="document-number"><?= $invoiceNumber ?></span><?php endif; ?></td></tr><tr><td style="padding:0">
<table class="meta"><?php if ($visible('date')): ?><tr><td>Fecha</td><td class="right"><?= $e($issuedAt) ?></td></tr><?php endif; ?>
<?php if ($visible('fiscal') && !$quote && $ncf !== ''): ?><tr><td>COMPROBANTE FISCAL</td><td class="right"><?= $e($ncf) ?></td></tr><?php endif; ?>
<?php if ($visible('due_date') && !$quote && $dueAt !== ''): ?><tr><td>Vencimiento</td><td class="right"><?= $e($dueAt) ?></td></tr><?php endif; ?>
<?php if ($visible('status') && !$quote): ?><tr><td>Estado</td><td class="right"><?= $e($status) ?></td></tr><?php endif; ?></table></td></tr></table></td></tr></table>
<?php if ($visible('validity') && $quote): ?><div class="validity">Esta cotización tiene una validez de <?= (int) $design['quote_validity_days'] ?> días</div><?php endif; ?>
<?php if ($showClient): ?><div class="client-box"><div class="section-label">DATOS DEL CLIENTE</div><table><tr><td><table class="client-grid">
<tr><?php if ($visible('customer_name')): ?><td><strong>CLIENTE</strong><br><?= $clientName ?: 'N/A' ?></td><?php endif; ?><?php if ($visible('customer_phone')): ?><td><strong>TELÉFONO</strong><br><?= $clientPhone ?: 'N/A' ?></td><?php endif; ?></tr>
<tr><?php if ($visible('customer_tax_id')): ?><td><strong>RNC/CÉDULA</strong><br><?= $clientTaxId ?: 'N/A' ?></td><?php endif; ?><?php if ($visible('customer_email')): ?><td><strong>EMAIL</strong><br><?= $clientEmail ?: 'N/A' ?></td><?php endif; ?></tr>
<tr><?php if ($visible('customer_address')): ?><td colspan="2"><strong>DIRECCIÓN</strong><br><?= $clientAddress ?: 'N/A' ?></td><?php endif; ?></tr>
<?php if ($visible('payment') && !$quote): ?><tr><td colspan="2"><strong>MÉTODO DE PAGO</strong><br><?= $e($invoice['metodo_pago'] ?? 'N/A') ?></td></tr><?php endif; ?>
</table></td><?php if ($visible('qr') && !$quote && $qrBlock !== ''): ?><td class="qr"><?= $qrBlock ?><?php if ($securityCode !== ''): ?><div>Código de seguridad: <?= $e($securityCode) ?></div><?php endif; ?></td><?php endif; ?></tr></table></div><?php endif; ?>
<?php if ($columnCount > 0): ?><table class="products"><thead><tr><?php if ($visible('code')): ?><th style="width:12%">CÓD.</th><?php endif; ?><?php if ($detailColumn): ?><th>DESCRIPCIÓN</th><?php endif; ?><?php if ($visible('quantity')): ?><th style="width:10%">CANT.</th><?php endif; ?><?php if ($visible('price')): ?><th style="width:17%">P.U.</th><?php endif; ?><?php if ($showLineTax): ?><th>ITBIS</th><?php endif; ?><?php if ($visible('line_total')): ?><th style="width:18%">TOTAL</th><?php endif; ?></tr></thead><tbody>
<?php foreach ($details as $itemIndex => $item): $quantity = (float) ($item['cantidad'] ?? 1); $unit = (float) ($item['precio_unitario'] ?? $item['precio'] ?? 0); ?>
<tr><?php if ($visible('code')): ?><td><?= $e($item['codigo'] ?? '') ?></td><?php endif; ?><?php if ($detailColumn): ?><td><?php if ($visible('item_name')): ?><strong><?= $e($item['nombre'] ?? $item['descripcion'] ?? 'Producto') ?></strong><?php endif; ?><?php if ($visible('description') && !empty($item['descripcion']) && trim((string) $item['descripcion']) !== trim((string) ($item['nombre'] ?? ''))): ?><div style="margin-top:4px;font-size:10px;line-height:1.45;color:#52667a"><?= nl2br($e($item['descripcion'])) ?></div><?php endif; ?><?php if ($visible('identifiers') && !empty($item['notas'])): ?><div class="item-note"><?= $e($item['notas']) ?></div><?php endif; ?></td><?php endif; ?><?php if ($visible('quantity')): ?><td class="center"><?= $money($quantity) ?></td><?php endif; ?><?php if ($visible('price')): ?><td class="num"><?= $currencyMoney($unit) ?></td><?php endif; ?><?php if ($showLineTax): ?><td class="num line-tax"><?= $currencyMoney($lineTaxes[$itemIndex] ?? 0) ?></td><?php endif; ?><?php if ($visible('line_total')): ?><td class="num"><?= $currencyMoney($item['total'] ?? ($quantity * $unit)) ?></td><?php endif; ?></tr>
<?php endforeach; ?>
<?php if (!$details): ?><tr><td colspan="<?= $columnCount ?>" class="empty">Detalle de productos no disponible</td></tr><?php endif; ?>
</tbody></table><?php endif; ?>
<?php if ($visible('notes') && ($notes !== '' || !$quote)): ?><div class="note"><strong>OBSERVACIÓN:</strong><br><?= $notes !== '' ? nl2br($e($notes)) : '¡Gracias por su compra!' ?></div><?php endif; ?>
<table class="bottom"><tr><td class="signatures"><?php if ($companySignature || !$quote): ?><table><tr><td style="text-align:center;width:50%">
<?php if ($companySignature): ?><img src="<?= $e($design['representative_signature']) ?>" alt="Firma del representante" style="max-width:150px;max-height:54px"><div class="signature-line" style="margin-top:4px"><strong><?= $e($design['representative_name']) ?></strong><br>Representante de la empresa</div>
<?php elseif ($visible('delivered_by')): ?><div class="signature-line"><strong>ENTREGADO POR</strong><br><?= $e($invoice['usuario'] ?? $invoice['cajero'] ?? '') ?></div><?php endif; ?>
</td><?php if ($visible('customer_signature') && !$quote): ?><td><?= $signatureHtml ?></td><?php endif; ?></tr></table><?php endif; ?></td><?php if ($showTotals): ?><td class="totals-panel"><table class="totals"><tr><td colspan="2" class="totals-title" style="text-align:left;background:#eaf4f9;color:<?= $primary ?>;padding:8px 10px"><?= $quote ? 'RESUMEN DE COTIZACIÓN' : 'RESUMEN DE PAGO' ?></td></tr>
<?php if ($visible('subtotal')): ?><tr><td>SUBTOTAL</td><td><?= $currencyMoney($subtotal) ?></td></tr><?php endif; ?>
<?php if ($visible('tax')): ?><tr><td>ITBIS</td><td><?= $currencyMoney($tax) ?></td></tr><?php endif; ?>
<?php if ($visible('discount') && $discount > 0): ?><tr><td>DESCUENTO</td><td><?= $currencyMoney($discount) ?></td></tr><?php endif; ?>
<?php if ($visible('shipping')): ?><?= $shippingRow ?><?php endif; ?>
<?php if ($visible('tip') && (float) ($invoice['propina_monto'] ?? 0) > 0): ?><tr><td>Propina</td><td><?= $currencyMoney($invoice['propina_monto']) ?></td></tr><?php endif; ?>
<?php if ($visible('payment') && !$quote && strtoupper((string) ($invoice['metodo_pago'] ?? '')) === 'MIXTO'): foreach ($payments as $payment): ?><tr><td><?= $e($payment['metodo_pago'] ?? '') ?></td><td><?= $currencyMoney($payment['monto'] ?? 0) ?></td></tr><?php endforeach; endif; ?>
<?php if ($visible('total')): ?><tr class="grand"><td>TOTAL</td><td><?= $currencyMoney($total) ?></td></tr><?php endif; ?></table></td><?php endif; ?></tr></table>
<?php if ($visible('footer')): ?><footer class="footer"><table><tr><td><?= $visible('company_name') ? $companyName : '' ?></td><td class="right"><?= $visible('document_title') ? $documentTitle : '' ?> <?= $visible('document_number') ? $invoiceNumber : '' ?></td></tr></table></footer><?php endif; ?>
</main></body></html>
