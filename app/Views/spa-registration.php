<?php
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$done = ($request['status'] ?? '') === 'used' || $appointment !== null;
$value = static fn (string $key, string $fallback = ''): string => $escape($_POST[$key] ?? $fallback);
$hoy = (new DateTimeImmutable('now', new DateTimeZone($scheduleSettings['timezone'] ?? 'America/Santo_Domingo')))->format('Y-m-d');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Solicitar cita de spa - <?= $escape($project['name'] ?? 'TMPOS') ?></title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f6f2fb;color:#241b33;font-family:Inter,system-ui,-apple-system,sans-serif}.wrap{max-width:620px;margin:32px auto;padding:18px}.card{background:#fff;border:1px solid #e6dcf5;border-radius:20px;box-shadow:0 18px 55px #3a1d571a;overflow:hidden}.head{padding:26px 28px;background:linear-gradient(135deg,#8347d9,#c2609f);color:#fff}.head h1{font-size:25px;margin:0 0 6px}.head p{margin:0;opacity:.92}.body{padding:26px 28px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.full{grid-column:1/-1}label{display:block;font-weight:650;font-size:14px;margin-bottom:7px}input,select,textarea{width:100%;border:1px solid #d8cbef;border-radius:11px;padding:12px 13px;font:inherit;color:inherit;background:#fff}textarea{min-height:90px;resize:vertical}button{width:100%;border:0;border-radius:12px;padding:14px;background:#8347d9;color:#fff;font:700 16px inherit;cursor:pointer}.note{font-size:13px;color:#6b6178;margin:0 0 20px}.error{padding:12px 14px;border-radius:10px;background:#fff0f0;color:#b42318;margin-bottom:18px}.success{text-align:center;padding:28px 10px}.success .icon{font-size:54px;color:#8347d9}.success h2{margin:12px 0 8px}.required{color:#d92d20}@media(max-width:580px){.wrap{margin:0;padding:0}.card{border-radius:0;min-height:100vh}.grid{grid-template-columns:1fr}.body,.head{padding:22px}.full{grid-column:auto}}
</style>
</head>
<body><main class="wrap"><section class="card">
<header class="head"><h1>Solicitar cita de spa</h1><p><?= $escape($project['name'] ?? '') ?></p></header>
<div class="body">
<?php if ($done): ?>
  <div class="success"><div class="icon">&#10003;</div><h2>Solicitud enviada</h2><p>Recibimos su solicitud de cita. Nos pondremos en contacto para confirmar fecha y hora.</p></div>
<?php else: ?>
  <p class="note">Complete sus datos para solicitar su cita. Los campos marcados con <span class="required">*</span> son obligatorios.</p>
  <?php if ($error !== ''): ?><div class="error"><?= $escape($error) ?></div><?php endif; ?>
  <form method="post" autocomplete="on"><div class="grid">
    <div class="full"><label>Nombre completo <span class="required">*</span></label><input name="nombre" maxlength="160" required value="<?= $value('nombre') ?>"></div>
    <div><label>Telefono <span class="required">*</span></label><input name="telefono" inputmode="tel" maxlength="24" required value="<?= $value('telefono', (string) ($request['phone'] ?? '')) ?>"></div>
    <div><label for="spa-service">Servicio deseado <span class="required">*</span></label>
      <select id="spa-service" name="servicio_uid" required>
        <option value=""><?= empty($services) ? 'No hay servicios disponibles' : 'Selecciona un servicio' ?></option>
        <?php foreach ($services as $service): ?>
        <option value="<?= $escape($service['uid']) ?>" <?= (string) ($_POST['servicio_uid'] ?? '') === $service['uid'] ? 'selected' : '' ?>><?= $escape($service['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (empty($services)): ?><p class="note">Contacta al spa para consultar sus servicios.</p><?php endif; ?>
    </div>
    <div><label>Fecha deseada <span class="required">*</span></label><input name="fecha" type="date" min="<?= $escape($hoy) ?>" required value="<?= $value('fecha') ?>"></div>
    <?php if ($scheduleSettings['enabled'] ?? false): ?>
    <?php require __DIR__ . '/spa-shift-picker.php'; ?>
    <?php else: ?>
    <div><label>Hora deseada <span class="required">*</span></label><input name="hora" type="time" required value="<?= $value('hora') ?>"></div>
    <?php endif; ?>
    <div class="full"><label>Nota (opcional)</label><textarea name="nota" maxlength="500"><?= $value('nota') ?></textarea></div>
    <div class="full"><button type="submit">Solicitar cita</button></div>
  </div></form>
<?php endif; ?>
</div></section></main></body></html>
