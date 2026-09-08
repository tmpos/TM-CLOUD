<?php use App\Core\Csrf; ?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="<?= e($store['primary_color']) ?>"><title>Activar cuenta | <?= e($store['store_name']) ?></title>
<style>
:root{--brand:<?= e($store['primary_color']) ?>;--ink:#14213d;--muted:#64748b;--line:#e2e8f0}*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:22px;background:radial-gradient(circle at 80% 5%,color-mix(in srgb,var(--brand) 18%,transparent),transparent 32%),#f1f5f9;color:var(--ink);font:15px/1.55 Inter,system-ui,sans-serif}.card{width:min(500px,100%);padding:clamp(26px,6vw,44px);background:#fff;border:1px solid var(--line);border-radius:24px;box-shadow:0 28px 75px #10213b18;text-align:center}.mark{width:64px;height:64px;margin:auto;display:grid;place-items:center;border-radius:20px;background:var(--brand);color:#fff;font-size:26px;font-weight:950;overflow:hidden}.mark img{width:100%;height:100%;object-fit:cover}.card h1{margin:22px 0 8px;font-size:30px;letter-spacing:-.03em}.lead{margin:0;color:var(--muted)}.email{display:inline-block;margin-top:8px;color:var(--ink);font-weight:900}.message{margin:20px 0 0;padding:12px 14px;border:1px solid #bfdbfe;border-radius:12px;background:#eff6ff;color:#1e40af;text-align:left}.otp{width:100%;height:64px;margin-top:22px;border:1px solid var(--line);border-radius:14px;text-align:center;font-size:28px;font-weight:900;letter-spacing:10px;outline:0}.otp:focus{border-color:var(--brand);box-shadow:0 0 0 4px color-mix(in srgb,var(--brand) 13%,transparent)}button{width:100%;height:52px;border:0;border-radius:13px;background:var(--brand);color:#fff;font-weight:900;margin-top:16px;cursor:pointer}.resend button{background:transparent;color:var(--brand);border:1px solid var(--line);margin-top:10px}.note{margin:18px 0 0;color:var(--muted);font-size:12px}.back{display:inline-block;margin-top:20px;color:var(--brand);font-weight:850;text-decoration:none}
</style></head><body><main class="card">
<div class="mark"><?php if($store['logo_url']): ?><img src="<?= e($store['logo_url']) ?>" alt=""><?php else: ?>✉<?php endif; ?></div>
<h1>Revisa tu correo</h1><p class="lead">Enviamos un código de 6 dígitos a</p><span class="email"><?= e($email) ?></span>
<?php if($error): ?><div class="message"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="/store/<?= e($store['slug']) ?>/verify" autocomplete="off">
<input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
<input class="otp" name="otp" required inputmode="numeric" autocomplete="one-time-code" minlength="6" maxlength="6" pattern="[0-9]{6}" autofocus aria-label="Código de activación">
<button type="submit">Activar mi cuenta</button>
</form>
<form class="resend" method="post" action="/store/<?= e($store['slug']) ?>/verify/resend"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button type="submit">Enviar un código nuevo</button></form>
<p class="note">El código vence en 10 minutos y permite hasta 5 intentos.</p><a class="back" href="/store/<?= e($store['slug']) ?>">← Volver a la tienda</a>
</main></body></html>
