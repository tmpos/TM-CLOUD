<?php
if (($order['payment_provider']??'') !== 'external_link' || ($order['payment_status']??'') === 'paid') return;
$externalPayment = json_decode((string)($order['provider_payload']??''),true) ?: [];
$externalUrl = (string)($externalPayment['external_payment_url']??'');
$externalParts = parse_url($externalUrl);
if (!filter_var($externalUrl,FILTER_VALIDATE_URL) || ($externalParts['scheme']??'') !== 'https' || isset($externalParts['user']) || isset($externalParts['pass']) || isset($externalParts['fragment'])) return;
?>
<div class="note" data-external-payment>
  <strong>Completa el pago con el proveedor</strong>
  <p>Utiliza el importe y el número de pedido indicados arriba. La tienda verificará el pago antes de confirmarlo.</p>
  <?php if (!empty($externalPayment['instructions'])): ?><p style="white-space:pre-line"><?= e($externalPayment['instructions']) ?></p><?php endif; ?>
  <a class="button" href="<?= e($externalUrl) ?>" target="_blank" rel="noopener noreferrer">Abrir enlace de pago</a>
</div>
