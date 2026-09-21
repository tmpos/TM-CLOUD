<?php
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$used = ($request['status'] ?? '') === 'used';
$value = static fn (string $key, string $fallback = ''): string => $escape($_POST[$key] ?? $fallback);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Registro de cliente - <?= $escape($project['name'] ?? 'TMPOS') ?></title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f4f7fb;color:#172033;font-family:Inter,system-ui,-apple-system,sans-serif}.wrap{max-width:620px;margin:32px auto;padding:18px}.card{background:#fff;border:1px solid #dde5ef;border-radius:20px;box-shadow:0 18px 55px #1d35571a;overflow:hidden}.head{padding:26px 28px;background:linear-gradient(135deg,#058ac4,#06b981);color:#fff}.head h1{font-size:25px;margin:0 0 6px}.head p{margin:0;opacity:.92}.body{padding:26px 28px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.full{grid-column:1/-1}label{display:block;font-weight:650;font-size:14px;margin-bottom:7px}input,select,textarea{width:100%;border:1px solid #cad5e3;border-radius:11px;padding:12px 13px;font:inherit;color:inherit;background:#fff}textarea{min-height:90px;resize:vertical}button{width:100%;border:0;border-radius:12px;padding:14px;background:#069f70;color:#fff;font:700 16px inherit;cursor:pointer}.note{font-size:13px;color:#667085;margin:0 0 20px}.error{padding:12px 14px;border-radius:10px;background:#fff0f0;color:#b42318;margin-bottom:18px}.success{text-align:center;padding:28px 10px}.success .icon{font-size:54px;color:#069f70}.success h2{margin:12px 0 8px}.required{color:#d92d20}@media(max-width:580px){.wrap{margin:0;padding:0}.card{border-radius:0;min-height:100vh}.grid{grid-template-columns:1fr}.body,.head{padding:22px}.full{grid-column:auto}}
</style>
</head>
<body><main class="wrap"><section class="card">
<header class="head"><h1>Registro de cliente</h1><p><?= $escape($project['name'] ?? '') ?></p></header>
<div class="body">
<?php if ($used): ?>
  <div class="success"><div class="icon">&#10003;</div><h2>Registro completado</h2><p>Sus datos fueron recibidos correctamente. Ya puede cerrar esta pagina.</p></div>
<?php else: ?>
  <p class="note">Complete sus datos. Los campos marcados con <span class="required">*</span> son obligatorios.</p>
  <?php if ($error !== ''): ?><div class="error"><?= $escape($error) ?></div><?php endif; ?>
  <form method="post" autocomplete="on"><div class="grid">
    <div class="full"><label>Nombre o razon social <span class="required">*</span></label><input name="nombre" maxlength="160" required value="<?= $value('nombre') ?>"></div>
    <div><label>Telefono <span class="required">*</span></label><input name="telefono" inputmode="tel" maxlength="24" required value="<?= $value('telefono', (string) ($request['phone'] ?? '')) ?>"></div>
    <div><label>Correo electronico</label><input name="email" type="email" maxlength="160" value="<?= $value('email') ?>"></div>
    <div><label>Documento (RNC o cedula)</label><input name="documento" inputmode="numeric" maxlength="20" value="<?= $value('documento') ?>"></div>
    <div><label>Tipo de cliente</label><select name="tipo_cliente">
      <?php foreach (['NORMAL'=>'Normal','CONSUMO'=>'Consumo','FISCAL'=>'Fiscal','GUBERNAMENTAL'=>'Gubernamental','REGIMEN_ESPECIAL'=>'Regimen especial','EXPORTACION'=>'Exportacion'] as $key => $label): ?>
      <option value="<?= $key ?>" <?= (($_POST['tipo_cliente'] ?? 'NORMAL') === $key) ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select></div>
    <div class="full"><label>Direccion</label><textarea name="direccion" maxlength="300"><?= $value('direccion') ?></textarea></div>
    <div class="full"><button type="submit">Completar registro</button></div>
  </div></form>
<?php endif; ?>
</div></section></main></body></html>
