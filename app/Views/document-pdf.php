<?php
// Table layout deliberately supports both browser previews and mPDF.
$primary = $e($design['primary_color']);
$heading = $e($design['heading_color']);
$tax = (float) ($invoice['impuesto_monto'] ?? $invoice['impuesto'] ?? 0);
$discount = (float) ($invoice['descuento_monto'] ?? $invoice['descuento'] ?? 0);
$total = (float) ($invoice['total'] ?? 0);
$subtotal = (float) ($invoice['subtotal'] ?? ($total + $discount - $tax));
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
<div class="company-name"><?= $companyName ?></div><div class="company-meta"><?= $companyRnc !== '' ? 'RNC ' . $companyRnc . '<br>' : '' ?><?= $companyPhone ?><?= $companyEmail !== '' ? ' | ' . $companyEmail : '' ?><br><?= $companyAddress ?></div>
</td><td><table class="invoice-box"><tr><td class="document-heading" style="background:<?= $heading ?>;color:#fff;padding:11px 13px"><span class="document-type"><?= $documentTitle ?></span><br><span class="document-number"><?= $invoiceNumber ?></span></td></tr><tr><td style="padding:0">
<table class="meta"><tr><td>Fecha</td><td class="right"><?= $e($issuedAt) ?></td></tr>
<?php if (!$quote && $ncf !== ''): ?><tr><td>COMPROBANTE FISCAL</td><td class="right"><?= $e($ncf) ?></td></tr><?php endif; ?>
<?php if ($dueAt !== ''): ?><tr><td>Vencimiento</td><td class="right"><?= $e($dueAt) ?></td></tr><?php endif; ?>
<?php if (!$quote): ?><tr><td>Estado</td><td class="right"><?= $e($status) ?></td></tr><?php endif; ?></table></td></tr></table></td></tr></table>
<?php if ($quote): ?><div class="validity">Esta cotización tiene una validez de <?= (int) $design['quote_validity_days'] ?> días</div><?php endif; ?>
<div class="client-box"><div class="section-label">DATOS DEL CLIENTE</div><table><tr><td><table class="client-grid">
<tr><td><strong>CLIENTE</strong><br><?= $clientName ?: 'N/A' ?></td><td><strong>TELÉFONO</strong><br><?= $clientPhone ?: 'N/A' ?></td></tr>
<tr><td><strong>RNC/CÉDULA</strong><br><?= $clientTaxId ?: 'N/A' ?></td><td><strong>EMAIL</strong><br><?= $clientEmail ?: 'N/A' ?></td></tr>
<tr><td colspan="2"><strong>DIRECCIÓN</strong><br><?= $clientAddress ?: 'N/A' ?></td></tr>
<?php if (!$quote): ?><tr><td colspan="2"><strong>MÉTODO DE PAGO</strong><br><?= $e($invoice['metodo_pago'] ?? 'N/A') ?></td></tr><?php endif; ?>
</table></td><?php if (!$quote && $qrBlock !== ''): ?><td class="qr"><?= $qrBlock ?><?php if ($securityCode !== ''): ?><div>Código de seguridad: <?= $e($securityCode) ?></div><?php endif; ?></td><?php endif; ?></tr></table></div>
<table class="products"><thead><tr><th style="width:12%">CÓD.</th><th>DESCRIPCIÓN</th><th style="width:10%">CANT.</th><th style="width:17%">P.U.</th><th style="width:18%">TOTAL</th></tr></thead><tbody>
<?php foreach ($details as $item): $quantity = (float) ($item['cantidad'] ?? 1); $unit = (float) ($item['precio_unitario'] ?? $item['precio'] ?? 0); ?>
<tr><td><?= $e($item['codigo'] ?? '') ?></td><td><strong><?= $e($item['nombre'] ?? $item['descripcion'] ?? 'Producto') ?></strong><?php if (!empty($item['notas'])): ?><div class="item-note"><?= $e($item['notas']) ?></div><?php endif; ?></td><td class="center"><?= $money($quantity) ?></td><td class="num"><?= $currencyMoney($unit) ?></td><td class="num"><?= $currencyMoney($item['total'] ?? ($quantity * $unit)) ?></td></tr>
<?php endforeach; ?>
<?php if (!$details): ?><tr><td colspan="5" class="empty">Detalle de productos no disponible</td></tr><?php endif; ?>
</tbody></table>
<?php if ($notes !== '' || !$quote): ?><div class="note"><strong>OBSERVACIÓN:</strong><br><?= $notes !== '' ? nl2br($e($notes)) : '¡Gracias por su compra!' ?></div><?php endif; ?>
<table class="bottom"><tr><td class="signatures"><?php if (!$quote): ?><table><tr><td><div class="signature-line"><strong>ENTREGADO POR</strong><br><?= $e($invoice['usuario'] ?? $invoice['cajero'] ?? '') ?></div></td><td><?= $signatureHtml ?></td></tr></table><?php endif; ?></td><td class="totals-panel"><table class="totals"><tr><td colspan="2" class="totals-title" style="text-align:left;background:#eaf4f9;color:<?= $primary ?>;padding:8px 10px"><?= $quote ? 'RESUMEN DE COTIZACIÓN' : 'RESUMEN DE PAGO' ?></td></tr>
<tr><td>SUBTOTAL</td><td><?= $currencyMoney($subtotal) ?></td></tr>
<?php if ($tax > 0): ?><tr><td>ITBIS</td><td><?= $currencyMoney($tax) ?></td></tr><?php endif; ?>
<?php if ($discount > 0): ?><tr><td>DESCUENTO</td><td><?= $currencyMoney($discount) ?></td></tr><?php endif; ?>
<?= $shippingRow ?>
<?php if ((float) ($invoice['propina_monto'] ?? 0) > 0): ?><tr><td>Propina</td><td><?= $currencyMoney($invoice['propina_monto']) ?></td></tr><?php endif; ?>
<?php if (!$quote && strtoupper((string) ($invoice['metodo_pago'] ?? '')) === 'MIXTO'): foreach ($payments as $payment): ?><tr><td><?= $e($payment['metodo_pago'] ?? '') ?></td><td><?= $currencyMoney($payment['monto'] ?? 0) ?></td></tr><?php endforeach; endif; ?>
<tr class="grand"><td>TOTAL</td><td><?= $currencyMoney($total) ?></td></tr></table></td></tr></table>
<footer class="footer"><table><tr><td><?= $companyName ?></td><td class="right"><?= $documentTitle ?> <?= $invoiceNumber ?></td></tr></table></footer>
</main></body></html>
