<?php
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$isCrm = !empty($request['crm_json']);
$used = ($request['status'] ?? '') === 'used';
$settings = $presentation['settings'] ?? \App\Services\CustomerRegistrationSettingsService::defaults();
$branding = $presentation['branding'] ?? ['name' => $project['name'] ?? 'Empresa', 'logo_url' => ''];
$fields = $settings['fields'];
$value = static fn (string $key, string $fallback = ''): string => $escape(is_scalar($_POST[$key] ?? $fallback) ? ($_POST[$key] ?? $fallback) : '');
$definitions = [
    'nombre' => ['Nombre o razón social', 'text', 160, 'Como aparece en su documento'],
    'telefono' => ['Teléfono', 'tel', 24, '809 555 0100'],
    'documento' => ['Cédula o RNC', 'text', 20, 'Número de identificación'],
    'email' => ['Correo electrónico', 'email', 160, 'nombre@ejemplo.com'],
    'direccion' => ['Dirección', 'textarea', 300, 'Calle, número y sector'],
    'tipo_cliente' => ['Tipo de cliente', 'select', 0, ''],
    'producto_interes' => ['Producto o servicio de interés', 'text', 500, '¿Qué está buscando?'],
    'necesidad' => ['Cuéntenos qué necesita', 'textarea', 2000, 'Comparta los detalles de su consulta'],
];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $escape($settings['title']) ?> · <?= $escape($branding['name']) ?></title>
<style>
:root{--brand:<?= $escape($settings['primary_color']) ?>}*{box-sizing:border-box}body{margin:0;background:#f3f5f8;color:#1c293b;font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;line-height:1.5}.wrap{max-width:720px;margin:48px auto;padding:0 20px}.brand{display:flex;align-items:center;justify-content:center;gap:14px;margin-bottom:24px;font-weight:650;font-size:17px}.brand img{max-width:160px;max-height:64px;object-fit:contain}.card{background:#fff;border:1px solid #e2e7ee;border-radius:24px;box-shadow:0 16px 48px #182d4810;overflow:hidden}.head{padding:32px 36px 24px;border-top:5px solid var(--brand);border-bottom:1px solid #edf0f4}.eyebrow{font-size:11px;letter-spacing:.14em;text-transform:uppercase;font-weight:700;color:var(--brand);margin:0 0 10px}.head h1{font-size:28px;line-height:1.2;letter-spacing:-.035em;margin:0 0 12px}.head p:last-child{margin:0;color:#637084;font-size:15px}.body{padding:26px 36px 34px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:22px 18px}.full{grid-column:1/-1}label{display:block;font-weight:600;font-size:13px;margin-bottom:8px}input,select,textarea{width:100%;border:1px solid #cfd7e2;border-radius:10px;padding:12px 13px;font:inherit;font-size:15px;color:inherit;background:#fff;transition:border-color .15s,box-shadow .15s}input:focus,select:focus,textarea:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px #0f766e18}input::placeholder,textarea::placeholder{color:#8994a4}textarea{min-height:96px;resize:vertical}button{width:100%;border:0;border-radius:11px;padding:15px;background:var(--brand);color:#fff;font:600 15px system-ui;cursor:pointer}button:hover{filter:brightness(.9)}button:focus-visible{outline:3px solid #172033;outline-offset:3px}.note{font-size:12px;color:#718096;margin:0 0 24px}.error{padding:14px;border-radius:10px;background:#fff1f1;border:1px solid #f6caca;color:#a31b28;margin-bottom:22px;font-size:14px}.success{text-align:center;padding:25px 0}.success .icon{display:inline-grid;place-items:center;width:64px;height:64px;border-radius:50%;background:#e8f6f0;color:#08744f;font-size:30px}.success h2{font-size:24px;margin:18px 0 10px}.success p{color:#637084}.required{color:#b42318}.optional{font-weight:400;color:#8994a4;font-size:11px}.consent{display:flex;align-items:flex-start;gap:10px;font-weight:400;line-height:1.6}.consent input{width:18px;height:18px;margin-top:3px;flex-shrink:0;accent-color:var(--brand)}.footer{text-align:center;font-size:12px;color:#718096;margin:22px 0 30px}.trap{display:none}@media(max-width:580px){.wrap{margin:24px auto;padding:0 14px}.brand{font-size:15px}.card{border-radius:18px}.grid{grid-template-columns:1fr;gap:18px}.head{padding:26px 22px 22px}.body{padding:22px}.head h1{font-size:25px}.full{grid-column:auto}}
</style>
</head>
<body><main class="wrap">
<div class="brand"><?php if ($settings['show_logo'] && $branding['logo_url'] !== ''): ?><img src="<?= $escape($branding['logo_url']) ?>" alt="Logo de <?= $escape($branding['name']) ?>"><?php endif; ?><span><?= $escape($branding['name']) ?></span></div>
<section class="card">
<header class="head"><p class="eyebrow"><?= $isCrm ? 'Estamos para ayudarle' : 'Bienvenido a nuestra empresa' ?></p><h1><?= $escape($settings['title']) ?></h1><?php if ($settings['description'] !== ''): ?><p><?= $escape($settings['description']) ?></p><?php endif; ?></header>
<div class="body">
<?php if ($used): ?>
<div class="success"><div class="icon" aria-hidden="true">✓</div><h2>¡Gracias por registrarse!</h2><p>Sus datos fueron recibidos correctamente.<br>Ya puede cerrar esta página.</p></div>
<?php else: ?>
<p class="note">Los campos con <span class="required">*</span> son obligatorios.</p>
<?php if ($error !== ''): ?><div class="error" role="alert"><?= $escape($error) ?></div><?php endif; ?>
<form method="post" autocomplete="on"><div class="grid">
<?php foreach ($definitions as $key => [$label, $type, $max, $placeholder]):
    if (!$fields[$key]['visible'] || (!$isCrm && in_array($key, ['producto_interes', 'necesidad'], true))) continue;
    $required = $fields[$key]['required']; ?>
<div class="<?= in_array($key, ['nombre', 'direccion', 'producto_interes', 'necesidad'], true) ? 'full' : '' ?>">
<label for="<?= $key ?>"><?= $escape($label) ?> <?php if ($required): ?><span class="required">*</span><?php else: ?><span class="optional">Opcional</span><?php endif; ?></label>
<?php if ($type === 'textarea'): ?>
<textarea id="<?= $key ?>" name="<?= $key ?>" maxlength="<?= $max ?>" placeholder="<?= $escape($placeholder) ?>" <?= $required ? 'required' : '' ?>><?= $value($key) ?></textarea>
<?php elseif ($type === 'select'): ?>
<select id="<?= $key ?>" name="<?= $key ?>" <?= $required ? 'required' : '' ?>>
<?php foreach (['NORMAL'=>'Normal', 'CONSUMO'=>'Consumo', 'FISCAL'=>'Fiscal', 'GUBERNAMENTAL'=>'Gubernamental', 'REGIMEN_ESPECIAL'=>'Régimen especial', 'EXPORTACION'=>'Exportación'] as $option => $text): ?>
<option value="<?= $option ?>" <?= (($_POST[$key] ?? 'NORMAL') === $option) ? 'selected' : '' ?>><?= $text ?></option>
<?php endforeach; ?></select>
<?php else: ?>
<input id="<?= $key ?>" name="<?= $key ?>" type="<?= $type ?>" <?= $key === 'documento' ? 'inputmode="numeric"' : '' ?> maxlength="<?= $max ?>" placeholder="<?= $escape($placeholder) ?>" <?= $required ? 'required' : '' ?> value="<?= $value($key, $key === 'telefono' ? (string) ($request['phone'] ?? '') : '') ?>">
<?php endif; ?></div>
<?php endforeach; ?>
<?php if ($isCrm): ?>
<div class="trap" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
<div class="full"><label class="consent"><input type="checkbox" name="consentimiento" value="1" required <?= ($_POST['consentimiento'] ?? '') === '1' ? 'checked' : '' ?>><span>Autorizo que me contacten para atender esta consulta. <span class="required">*</span></span></label></div>
<?php endif; ?>
<div class="full"><button type="submit"><?= $isCrm ? 'Enviar consulta' : 'Completar registro' ?> &rarr;</button></div>
</div></form>
<?php endif; ?>
</div></section><p class="footer">Sus datos serán recibidos por <?= $escape($branding['name']) ?>.</p>
</main></body></html>
