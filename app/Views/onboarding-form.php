<?php
declare(strict_types=1);
/** @var array|null $link */
/** @var string $error */
/** @var array|null $result */
/** @var string $token */
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$done = $result !== null;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Registro de empresa - TMPOS</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f2f6f7;color:#173239;font:16px system-ui,sans-serif}.wrap{max-width:640px;margin:32px auto;padding:0 16px}.card{background:#fff;border:1px solid #d9e5e7;border-radius:18px;padding:26px;box-shadow:0 12px 36px #0b3c4612}h1{margin:0 0 8px;font-size:26px}.muted{color:#61777c}.notice{padding:12px;border-radius:9px;margin:16px 0;background:#eaf8f8}.error{background:#fff0ef;color:#9a2822}label{display:block;margin:14px 0}span.field-label{display:block;font-weight:600;margin-bottom:5px;font-size:14px}input[type=text],input[type=email],input[type=tel]{width:100%;padding:12px;border:1px solid #bdcfd2;border-radius:9px;font:inherit}input[type=file]{width:100%;padding:10px;border:1px dashed #bdcfd2;border-radius:9px;background:#fafcfc}button{display:inline-block;border:0;border-radius:9px;padding:13px 20px;background:#087f83;color:#fff;font:600 15px system-ui;cursor:pointer;width:100%;margin-top:18px}button:disabled{opacity:.6;cursor:not-allowed}.grid{display:grid;grid-template-columns:1fr 1fr;gap:0 14px}@media(max-width:560px){.grid{grid-template-columns:1fr}.card{padding:18px}}.key-box{display:flex;gap:8px;margin-top:8px}.key-box input{font-family:ui-monospace,monospace;background:#f7fafa}
</style>
</head>
<body><main class="wrap"><section class="card">
<?php if ($done): ?>
<h1>Empresa registrada</h1>
<div class="notice">Tu empresa <strong><?= $escape($result['project']['name'] ?? '') ?></strong> quedo creada. Usa esta licencia para activar TMPOS en tu equipo:</div>
<label><span class="field-label">Clave de licencia</span><div class="key-box"><input type="text" readonly value="<?= $escape($result['license_key'] ?? '') ?>" id="license-key" aria-label="Clave de licencia"><button type="button" style="width:auto;margin-top:0" onclick="navigator.clipboard.writeText(document.getElementById('license-key').value)">Copiar</button></div></label>
<p class="muted">Tambien te enviamos estos datos y el enlace de tu sistema por correo. Guarda esta clave; no volvera a mostrarse desde este enlace. Descarga TMPOS e ingresa la clave para comenzar a facturar.</p>
<?php else: ?>
<h1>Registra tu empresa</h1>
<p class="muted">Completa estos datos para crear tu sistema TMPOS. Este enlace es de un solo uso.</p>
<?php if ($error !== ''): ?><div class="notice error" role="alert"><?= $escape($error) ?></div><?php endif; ?>
<form method="post" action="/onboarding/<?= $escape($token) ?>" enctype="multipart/form-data" id="onboarding-form">
<label><span class="field-label">Nombre de la empresa</span><input type="text" name="nombre" maxlength="100" required autocomplete="organization"></label>
<div class="grid">
<label><span class="field-label">RNC</span><input type="text" name="rnc" maxlength="50"></label>
<label><span class="field-label">Encargado</span><input type="text" name="encargado" maxlength="100" autocomplete="name"></label>
<label><span class="field-label">Telefono</span><input type="tel" name="telefono" maxlength="50" autocomplete="tel"></label>
<label><span class="field-label">Email</span><input type="email" name="email" maxlength="150" required autocomplete="email"></label>
</div>
<label><span class="field-label">Direccion</span><input type="text" name="direccion" maxlength="255" autocomplete="street-address"></label>
<label><span class="field-label">Logo (opcional)</span><input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/gif"></label>
<button type="submit" id="onboarding-submit">Crear mi empresa</button>
</form>
<script>
document.getElementById('onboarding-form').addEventListener('submit', function () {
  var btn = document.getElementById('onboarding-submit')
  btn.disabled = true
  btn.textContent = 'Creando...'
})
</script>
<?php endif; ?>
</section></main>
</body></html>
