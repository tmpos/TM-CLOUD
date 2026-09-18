<?php
declare(strict_types=1);
/** @var array $project */
/** @var array $invoice */
/** @var array $request */
/** @var string $token */
/** @var string $error */
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$number = $invoice['no_factura'] ?? $invoice['numero'] ?? $invoice['uid'] ?? '';
$customer = $invoice['nombre_cliente'] ?? $invoice['cliente'] ?? '';
$signed = $request['signed_at'] !== null;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Firma de factura <?= $escape($number) ?></title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f2f6f7;color:#173239;font:16px system-ui,sans-serif}.wrap{max-width:700px;margin:32px auto;padding:0 16px}.card{background:#fff;border:1px solid #d9e5e7;border-radius:18px;padding:26px;box-shadow:0 12px 36px #0b3c4612}h1{margin:0 0 8px;font-size:26px}.muted{color:#61777c}.summary{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:22px 0}.item{border:1px solid #e0e9eb;border-radius:10px;padding:12px}.item small{display:block;color:#64787c;margin-bottom:4px}.item strong{overflow-wrap:anywhere}.notice{padding:12px;border-radius:9px;margin:16px 0;background:#eaf8f8}.error{background:#fff0ef;color:#9a2822}.canvas-box{border:2px solid #c7d9dc;border-radius:10px;background:#fff;touch-action:none;margin:12px 0}canvas{display:block;width:100%;height:190px;touch-action:none}.row{display:flex;align-items:center;gap:10px;margin:12px 0}input[type=text]{width:100%;padding:12px;border:1px solid #bdcfd2;border-radius:9px;font:inherit}button,.button{display:inline-block;border:0;border-radius:9px;padding:12px 17px;background:#087f83;color:#fff;font:600 15px system-ui;cursor:pointer;text-decoration:none}.secondary{background:#e5eff0;color:#174149}button:disabled{opacity:.6;cursor:not-allowed}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}label{line-height:1.45}@media(max-width:520px){.summary{grid-template-columns:1fr}.card{padding:18px}}
</style>
</head>
<body><main class="wrap"><section class="card">
<h1><?= $signed ? 'Factura firmada' : 'Firmar factura' ?></h1>
<p class="muted">Empresa: <?= $escape($project['name'] ?? '') ?></p>
<div class="summary">
<div class="item"><small>Factura</small><strong><?= $escape($number) ?></strong></div>
<div class="item"><small>Cliente</small><strong><?= $escape($customer) ?></strong></div>
<div class="item"><small>Total</small><strong><?= $escape(number_format((float) ($invoice['total'] ?? 0), 2)) ?></strong></div>
<div class="item"><small>Enlace válido hasta</small><strong><?= $escape($request['expires_at']) ?> UTC</strong></div>
</div>
<?php if ($signed): ?>
<div class="notice">La firma fue recibida el <?= $escape($request['signed_at']) ?>. Este enlace ya no permite firmar otra vez.</div>
<?php else: ?>
<?php if ($error !== ''): ?><div class="notice error" role="alert"><?= $escape($error) ?></div><?php endif; ?>
<p>Revise la <a href="/sign/invoice/<?= $escape($token) ?>/preview" target="_blank" rel="noopener noreferrer">factura completa</a> antes de firmar. La firma se vinculará a esta versión exacta del documento.</p>
<form method="post" action="/sign/invoice/<?= $escape($token) ?>" id="signature-form">
<label for="signer_name">Nombre de quien firma</label>
<input id="signer_name" type="text" name="signer_name" maxlength="120" required autocomplete="name">
<p class="muted">Firme con el dedo, mouse o lápiz en el recuadro.</p>
<div class="canvas-box"><canvas id="signature-canvas" width="900" height="285" aria-label="Espacio para dibujar la firma"></canvas></div>
<input type="hidden" id="signature" name="signature">
<div class="row"><button type="button" class="secondary" id="clear-signature">Borrar firma</button></div>
<label class="row"><input type="checkbox" name="consent" value="1" required> <span><?= $escape(\App\Services\InvoiceSignatureService::CONSENT) ?></span></label>
<div class="actions"><button type="submit">Confirmar firma</button></div>
</form>
<?php endif; ?>
</section></main>
<?php if (!$signed): ?>
<script>
(() => {
  const canvas = document.getElementById('signature-canvas');
  const context = canvas.getContext('2d');
  const form = document.getElementById('signature-form');
  let drawing = false;
  let ink = false;
  context.lineWidth = 3;
  context.lineCap = 'round';
  context.lineJoin = 'round';
  context.strokeStyle = '#123b43';
  const point = event => {
    const rect = canvas.getBoundingClientRect();
    return {x:(event.clientX-rect.left)*canvas.width/rect.width,y:(event.clientY-rect.top)*canvas.height/rect.height};
  };
  canvas.addEventListener('pointerdown', event => {
    event.preventDefault();
    canvas.setPointerCapture(event.pointerId);
    const p = point(event);
    context.beginPath();
    context.moveTo(p.x,p.y);
    drawing = true;
  });
  canvas.addEventListener('pointermove', event => {
    if (!drawing) return;
    event.preventDefault();
    const p = point(event);
    context.lineTo(p.x,p.y);
    context.stroke();
    ink = true;
  });
  for (const name of ['pointerup','pointercancel']) canvas.addEventListener(name, () => { drawing = false; });
  document.getElementById('clear-signature').addEventListener('click', () => {
    context.clearRect(0,0,canvas.width,canvas.height);
    ink = false;
  });
  form.addEventListener('submit', event => {
    if (!ink) { event.preventDefault(); alert('Dibuje la firma antes de continuar.'); return; }
    document.getElementById('signature').value = canvas.toDataURL('image/png');
  });
})();
</script>
<?php endif; ?>
</body></html>
