<?php use App\Core\Csrf; ?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="<?= e($store['primary_color']) ?>">
<title>Portal de clientes | <?= e($store['store_name']) ?></title>
<style>
:root{--brand:<?= e($store['primary_color']) ?>;--accent:<?= e($store['accent_color']) ?>;--ink:#14213d;--muted:#66758a;--line:#dfe6ef}*{box-sizing:border-box}body{margin:0;min-height:100vh;background:radial-gradient(circle at 85% 5%,color-mix(in srgb,var(--accent) 18%,transparent),transparent 28%),#f4f7fb;color:var(--ink);font:15px/1.55 Inter,system-ui,sans-serif}.shell{min-height:100vh;display:grid;grid-template-columns:minmax(300px,.9fr) minmax(420px,1.1fr)}.side{background:linear-gradient(150deg,#0b1324,#173252);color:white;padding:clamp(34px,6vw,80px);display:flex;flex-direction:column;justify-content:space-between}.brand{display:flex;align-items:center;gap:12px;color:white;text-decoration:none;font-size:20px;font-weight:900}.mark{width:44px;height:44px;border-radius:13px;background:var(--brand);display:grid;place-items:center;overflow:hidden}.mark img{width:100%;height:100%;object-fit:cover}.side h1{font-size:clamp(34px,5vw,58px);line-height:1.04;letter-spacing:-.04em;margin:40px 0 18px}.side p{font-size:17px;color:#cad6e5;max-width:520px}.features{display:grid;gap:10px;margin-top:28px}.feature{display:flex;align-items:center;gap:10px;color:#dce8f6}.feature i{width:30px;height:30px;display:grid;place-items:center;border-radius:9px;background:#ffffff14;font-style:normal}.security{border-top:1px solid #ffffff25;padding-top:22px;color:#b8c7d9;font-size:13px}.content{display:grid;place-items:center;padding:28px}.card{width:min(520px,100%);background:white;border:1px solid var(--line);border-radius:24px;padding:clamp(25px,5vw,42px);box-shadow:0 28px 75px #10213b14}.back{color:var(--brand);font-weight:800;text-decoration:none}.card h2{font-size:30px;letter-spacing:-.03em;margin:25px 0 8px}.lead{color:var(--muted);margin:0 0 20px}.error{border:1px solid #fecaca;border-radius:12px;background:#fff1f2;color:#9f1239;padding:12px 14px;margin-bottom:18px}.access-tabs{display:grid;grid-template-columns:1fr 1fr;gap:5px;background:#f1f5f9;border-radius:13px;padding:5px}.access-tab{height:42px!important;margin:0!important;background:transparent!important;color:var(--muted)!important;border-radius:9px!important}.access-tab.active{background:#fff!important;color:var(--brand)!important;box-shadow:0 3px 12px #14213d12}.field{display:block;margin-top:18px}.field span{display:block;font-size:13px;font-weight:900;margin-bottom:7px}.field input{width:100%;height:52px;border:1px solid var(--line);border-radius:12px;padding:0 14px;outline:0;font-size:16px}.field input:focus{border-color:var(--brand);box-shadow:0 0 0 4px color-mix(in srgb,var(--brand) 12%,transparent)}button{width:100%;height:52px;border:0;border-radius:13px;background:var(--brand);color:white;font-weight:900;margin-top:24px;cursor:pointer}.note{display:flex;gap:10px;color:var(--muted);font-size:12px;margin-top:20px}.note b{color:var(--ink)}@media(max-width:820px){.shell{display:block}.side{min-height:340px;padding:30px}.side h1{margin:45px 0 12px}.security{display:none}.features{grid-template-columns:1fr 1fr}.content{padding:20px 14px 45px;margin-top:-35px;position:relative}.card{border-radius:20px}}@media(max-width:480px){.features{display:none}}
.register-link{display:flex;justify-content:center;align-items:center;height:48px;margin-top:12px;border:1px solid var(--line);border-radius:13px;color:var(--brand);font-weight:900;text-decoration:none}
</style>
</head>
<body><main class="shell">
<section class="side">
    <a class="brand" href="/store/<?= e($store['slug']) ?>"><span class="mark"><?php if($store['logo_url']): ?><img src="<?= e($store['logo_url']) ?>" alt=""><?php else: ?><?= e(mb_strtoupper(mb_substr($store['store_name'],0,1))) ?><?php endif; ?></span><?= e($store['store_name']) ?></a>
    <div><h1>Todo lo tuyo, en un solo lugar.</h1><p>Entra con tu cédula para consultar tus datos, pedidos, favoritos, facturas y servicios.</p><div class="features"><div class="feature"><i>▤</i> Facturas y compras</div><div class="feature"><i>□</i> Pedidos en línea</div><div class="feature"><i>♡</i> Productos favoritos</div><div class="feature"><i>✓</i> Datos actualizados</div></div></div>
    <div class="security">La sesión es privada, tiene límites de intentos y permanece activa hasta que la cierres.</div>
</section>
<section class="content"><form class="card" method="post" autocomplete="off">
    <a class="back" href="/store/<?= e($store['slug']) ?>">← Volver a la tienda</a>
    <h2>Portal de clientes</h2><p class="lead">Escribe tu cédula para entrar a tu panel privado.</p>
    <?php if($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
    <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
    <input type="hidden" name="access_type" value="document">
    <label class="field"><span>Cédula</span><input name="access_value" value="<?= e($old['access_value']??'') ?>" required minlength="5" maxlength="30" inputmode="numeric" autocomplete="username" autofocus placeholder="Ej. 00112345678"></label>
    <button>Entrar a mi portal</button>
    <a class="register-link" href="/store/<?= e($store['slug']) ?>/register">Crear mi cuenta</a>
    <div class="note"><span>🔒</span><span><b>Sesión privada.</b> Tus datos se mantendrán disponibles hasta que cierres sesión.</span></div>
</form></section>
</main></body></html>
