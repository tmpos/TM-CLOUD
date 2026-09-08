<?php use App\Core\Csrf; $slug = (string) ($project['slug'] ?? ''); ?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($project['name'] ?? 'TMPOS') ?> | Acceso</title>
<style>
*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at top,#172554,#070b14 58%);color:#e2e8f0;font:15px system-ui;min-height:100vh;display:grid;place-items:center;padding:20px}.box{width:min(390px,100%);background:#111827ef;border:1px solid #334155;border-radius:20px;padding:32px;box-shadow:0 30px 90px #000a;text-align:center}.brand{font-weight:900;color:#60a5fa;letter-spacing:.08em}h1{margin:7px 0;color:#fff}p{color:#94a3b8}.pin{width:100%;padding:14px;margin-top:12px;border:1px solid #475569;border-radius:12px;background:#0f172a;color:#fff;font:700 27px ui-monospace,monospace;text-align:center;letter-spacing:.65em;text-indent:.65em}.pin:focus{outline:2px solid #3b82f6;border-color:transparent}.keypad{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:18px}.key{min-height:50px;border:1px solid #334155;border-radius:11px;background:#172033;color:#fff;font-size:19px;font-weight:800;cursor:pointer}.key:hover{background:#22304a}.key.clear{color:#fca5a5;font-size:14px}.submit{width:100%;margin-top:18px;padding:13px;border:0;border-radius:10px;background:#2563eb;color:#fff;font-weight:800;cursor:pointer}.submit:disabled{opacity:.45;cursor:not-allowed}.error{background:#450a0a;color:#fecaca;padding:11px;border-radius:9px;margin-top:16px}.hint{font-size:12px;margin-bottom:0}
</style>
</head>
<body>
<form class="box" method="post" action="/sistema/<?= e(rawurlencode($slug)) ?>/login" id="pin-form">
<div class="brand">TMPOS</div>
<h1><?= e($project['name'] ?? 'Sistema empresarial') ?></h1>
<p>Ingresa tu PIN de acceso</p>
<?php if(!empty($error)):?><div class="error"><?= e($error) ?></div><?php endif?>
<input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
<input class="pin" id="pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" required autofocus autocomplete="one-time-code" aria-label="PIN de cuatro digitos">
<div class="keypad" aria-label="Teclado numerico">
<?php foreach ([1,2,3,4,5,6,7,8,9] as $number): ?><button class="key" type="button" data-number="<?= $number ?>"><?= $number ?></button><?php endforeach ?>
<button class="key clear" type="button" id="clear">Borrar</button><button class="key" type="button" data-number="0">0</button><button class="key clear" type="button" id="backspace">&#9003;</button>
</div>
<p class="hint">Se validara el PIN de un usuario activo de este proyecto.</p>
<button class="submit" id="submit" disabled>Entrar al sistema</button>
</form>
<script>
const pin=document.getElementById('pin'),submit=document.getElementById('submit');
const sync=()=>{pin.value=pin.value.replace(/\D/g,'').slice(0,4);submit.disabled=pin.value.length!==4};
pin.addEventListener('input',sync);
document.querySelectorAll('[data-number]').forEach(button=>button.addEventListener('click',()=>{if(pin.value.length<4)pin.value+=button.dataset.number;sync();pin.focus()}));
document.getElementById('clear').addEventListener('click',()=>{pin.value='';sync();pin.focus()});
document.getElementById('backspace').addEventListener('click',()=>{pin.value=pin.value.slice(0,-1);sync();pin.focus()});
</script>
</body>
</html>
