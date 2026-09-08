<?php use App\Core\Csrf; ?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="<?= e($store['primary_color']) ?>">
<title>Crear cuenta | <?= e($store['store_name']) ?></title>
<style>
:root{--brand:<?= e($store['primary_color']) ?>;--accent:<?= e($store['accent_color']) ?>;--ink:#14213d;--muted:#66758a;--line:#dfe6ef}*{box-sizing:border-box}body{margin:0;min-height:100vh;background:radial-gradient(circle at 85% 5%,color-mix(in srgb,var(--accent) 18%,transparent),transparent 28%),#f4f7fb;color:var(--ink);font:15px/1.55 Inter,system-ui,sans-serif}.shell{min-height:100vh;display:grid;grid-template-columns:minmax(300px,.8fr) minmax(520px,1.2fr)}.side{background:linear-gradient(150deg,#0b1324,#173252);color:white;padding:clamp(34px,6vw,80px);display:flex;flex-direction:column;justify-content:space-between}.brand{display:flex;align-items:center;gap:12px;color:white;text-decoration:none;font-size:20px;font-weight:900}.mark{width:44px;height:44px;border-radius:13px;background:var(--brand);display:grid;place-items:center;overflow:hidden}.mark img{width:100%;height:100%;object-fit:cover}.side h1{font-size:clamp(34px,5vw,56px);line-height:1.04;letter-spacing:-.04em;margin:40px 0 18px}.side p{font-size:17px;color:#cad6e5;max-width:500px}.benefits{display:grid;gap:12px;margin-top:30px;color:#dce8f6}.benefits span{display:flex;gap:10px;align-items:center}.benefits i{width:29px;height:29px;display:grid;place-items:center;border-radius:9px;background:#ffffff14;font-style:normal}.content{display:grid;place-items:center;padding:30px}.card{width:min(650px,100%);background:white;border:1px solid var(--line);border-radius:24px;padding:clamp(25px,5vw,42px);box-shadow:0 28px 75px #10213b14}.back{color:var(--brand);font-weight:800;text-decoration:none}.card h2{font-size:30px;letter-spacing:-.03em;margin:22px 0 5px}.lead{color:var(--muted);margin:0 0 20px}.error{border:1px solid #fecaca;border-radius:12px;background:#fff1f2;color:#9f1239;padding:12px 14px;margin-bottom:18px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.field{display:block}.field.full{grid-column:1/-1}.field span{display:block;font-size:13px;font-weight:900;margin-bottom:7px}.field input,.field textarea{width:100%;border:1px solid var(--line);border-radius:12px;padding:0 14px;outline:0;font:inherit}.field input{height:50px}.field textarea{min-height:84px;padding-top:12px;resize:vertical}.field input:focus,.field textarea:focus{border-color:var(--brand);box-shadow:0 0 0 4px color-mix(in srgb,var(--brand) 12%,transparent)}.required{color:#dc2626}button{width:100%;height:52px;border:0;border-radius:13px;background:var(--brand);color:white;font-weight:900;margin-top:22px;cursor:pointer}.login{text-align:center;margin:17px 0 0;color:var(--muted)}.login a{color:var(--brand);font-weight:900}.note{color:var(--muted);font-size:12px;margin-top:16px}@media(max-width:850px){.shell{display:block}.side{min-height:290px;padding:30px}.side h1{margin:40px 0 10px}.benefits{display:none}.content{padding:18px 14px 40px;margin-top:-25px;position:relative}}@media(max-width:560px){.grid{grid-template-columns:1fr}.field.full{grid-column:auto}.card{padding:25px 20px}}
</style>
</head>
<body><main class="shell">
<section class="side">
    <a class="brand" href="/store/<?= e($store['slug']) ?>"><span class="mark"><?php if($store['logo_url']): ?><img src="<?= e($store['logo_url']) ?>" alt=""><?php else: ?><?= e(mb_strtoupper(mb_substr($store['store_name'],0,1))) ?><?php endif; ?></span><?= e($store['store_name']) ?></a>
    <div><h1>Crea tu cuenta de cliente.</h1><p>Registra tus datos una sola vez para consultar compras, facturas, pedidos y servicios desde tu portal.</p><div class="benefits"><span><i>✓</i> Checkout con tus datos</span><span><i>▤</i> Historial de compras</span><span><i>⌁</i> Seguimiento de servicios</span></div></div>
    <small>Tus datos se guardan directamente en el sistema de la tienda.</small>
</section>
<section class="content"><form class="card" method="post" autocomplete="on">
    <a class="back" href="/store/<?= e($store['slug']) ?>/invoices">← Ya tengo una cuenta</a>
    <h2>Registro de cliente</h2><p class="lead">Completa tus datos básicos. La cédula será tu forma de acceso.</p>
    <?php if($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
    <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
    <div class="grid">
        <label class="field full"><span>Nombre completo <b class="required">*</b></span><input name="nombre" value="<?= e($old['nombre']??'') ?>" required minlength="2" maxlength="120" autocomplete="name" style="text-transform:uppercase" data-uppercase autofocus></label>
        <label class="field"><span>Cédula <b class="required">*</b></span><input name="cedula" value="<?= e($old['cedula']??'') ?>" required minlength="5" maxlength="30" inputmode="numeric" autocomplete="username" placeholder="001-1234567-8"></label>
        <label class="field"><span>Teléfono</span><input name="telefono" value="<?= e($old['telefono']??'') ?>" maxlength="40" inputmode="tel" autocomplete="tel" placeholder="809-555-0000"></label>
        <label class="field full"><span>Correo electrónico <b class="required">*</b></span><input type="email" name="email" value="<?= e($old['email']??'') ?>" required maxlength="160" autocomplete="email" placeholder="cliente@correo.com"></label>
        <label class="field full"><span>Dirección</span><textarea name="direccion" maxlength="250" autocomplete="street-address" placeholder="Calle, sector, ciudad"><?= e($old['direccion']??'') ?></textarea></label>
    </div>
    <button type="submit">Crear cuenta y verificar correo</button>
    <p class="login">¿Ya estás registrado? <a href="/store/<?= e($store['slug']) ?>/invoices">Entrar con mi cédula</a></p>
    <p class="note">Te enviaremos un código OTP para activar la cuenta. Después, tu sesión permanecerá activa hasta que uses “Cerrar sesión”.</p>
</form></section>
</main>
<script>document.querySelector('[data-uppercase]')?.addEventListener('input',event=>{const start=event.target.selectionStart;event.target.value=event.target.value.toLocaleUpperCase('es');event.target.setSelectionRange(start,start)});</script>
</body></html>
