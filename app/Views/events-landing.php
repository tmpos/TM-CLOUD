<?php
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$title = (string) ($page['title'] ?? 'Próximos eventos');
$color = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($page['color'] ?? '')) ? $page['color'] : '#2563eb';
$submittedEvent = (string) ($_POST['event_uid'] ?? '');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="index,follow"><title><?= $escape($title) ?> · Eventos</title>
<style>
:root{--brand:<?= $escape($color) ?>;--ink:#14213d;--muted:#64748b;--line:#dbe4ef;--surface:#fff}
*{box-sizing:border-box}body{margin:0;font:16px/1.55 system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--ink);background:#f5f8fc}
.wrap{max-width:1120px;margin:auto;padding:0 22px}.top{background:#fff;border-bottom:1px solid var(--line)}.top .wrap{display:flex;align-items:center;justify-content:space-between;padding-top:15px;padding-bottom:15px}.brand{font-size:18px;font-weight:800;letter-spacing:-.03em}.top a{color:var(--brand);font-weight:700;text-decoration:none}
.hero{background:radial-gradient(circle at 85% 10%,#ffffff30,transparent 26%),linear-gradient(125deg,#102039,var(--brand));color:#fff;padding:78px 0 84px}.eyebrow{font-size:12px;letter-spacing:.16em;text-transform:uppercase;font-weight:800;opacity:.82}.hero h1{font-size:clamp(38px,6vw,68px);line-height:1.05;letter-spacing:-.055em;max-width:800px;margin:15px 0 20px}.hero p{max-width:670px;font-size:18px;opacity:.9;margin:0}.intro{padding:45px 0 24px}.intro h2{font-size:28px;letter-spacing:-.03em;margin:0 0 7px}.intro p{color:var(--muted);margin:0}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,340px),1fr));gap:22px;padding-bottom:80px}
.card{background:var(--surface);border:1px solid var(--line);border-radius:22px;overflow:hidden;box-shadow:0 18px 45px #1932510d}.stripe{height:7px;background:var(--brand)}.content{padding:27px}.date{display:inline-block;font-size:13px;font-weight:800;color:var(--brand);background:#eef4ff;border-radius:999px;padding:6px 12px}.card h3{font-size:24px;line-height:1.2;letter-spacing:-.035em;margin:19px 0 10px}.desc{white-space:pre-line;color:#475569;min-height:48px}.facts{list-style:none;padding:0;margin:21px 0;display:grid;gap:9px}.facts li{display:flex;gap:10px;color:#334155}.facts strong{min-width:88px}.seats{color:#0f766e;font-size:13px;font-weight:700}.full{color:#b45309}.enroll{border-top:1px solid var(--line);padding-top:19px}.enroll summary{cursor:pointer;list-style:none;background:var(--brand);color:#fff;text-align:center;border-radius:12px;padding:12px;font-weight:800}.enroll summary::-webkit-details-marker{display:none}.enroll[open] summary{margin-bottom:18px}.form{display:grid;grid-template-columns:1fr 1fr;gap:13px}.form .wide{grid-column:1/-1}.form label{display:block;font-size:13px;font-weight:700;margin-bottom:5px}.form input:not([type=checkbox]){width:100%;padding:11px;border:1px solid #bdcbdc;border-radius:10px;font:inherit;background:#fff}.form input:focus{outline:2px solid var(--brand);outline-offset:2px}.check{display:flex;gap:9px;align-items:flex-start;font-size:13px;color:#475569}.check input{margin-top:4px}.submit{border:0;background:var(--brand);color:#fff;border-radius:10px;padding:12px 18px;font:inherit;font-weight:800;cursor:pointer;width:100%}.notice{padding:15px 18px;margin:25px 0 0;border-radius:12px;background:#e6f6ee;color:#14643e}.notice.error{background:#fff0ec;color:#9a3412}.empty{padding:45px;background:#fff;border:1px solid var(--line);border-radius:20px;text-align:center;color:var(--muted)}footer{border-top:1px solid var(--line);padding:25px 0;color:var(--muted);font-size:13px}
@media(max-width:560px){.hero{padding:55px 0 65px}.form{grid-template-columns:1fr}.form .wide{grid-column:auto}.content{padding:21px}}
</style>
</head>
<body>
<header class="top"><div class="wrap"><div class="brand">Eventos</div><a href="#agenda">Ver agenda ↓</a></div></header>
<main>
<section class="hero"><div class="wrap"><div class="eyebrow">Encuentros · Charlas · Experiencias</div><h1><?= $escape($title) ?></h1><p><?= $escape($page['introduction'] ?? '') ?></p></div></section>
<div class="wrap" id="agenda">
<?php if ($registered): ?><div class="notice" role="status"><strong>Inscripción confirmada.</strong> Tu plaza quedó reservada. Conserva los datos del evento.</div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice error" role="alert"><?= $escape($error) ?></div><?php endif; ?>
<section class="intro"><h2>Agenda de eventos</h2><p>Elige un evento y completa el formulario para reservar tu lugar.</p></section>
<?php if (!$events): ?><div class="empty">No hay eventos abiertos para inscripción en este momento.</div><?php endif; ?>
<div class="grid">
<?php foreach ($events as $event):
    $dateLabel = (new DateTimeImmutable($event['event_date']))->format('d/m/Y');
    $full = $event['remaining'] !== null && $event['remaining'] < 1;
?>
<article class="card"><div class="stripe"></div><div class="content">
<span class="date"><?= $escape($dateLabel) ?> · <?= $escape($event['event_time']) ?></span>
<h3><?= $escape($event['title']) ?></h3><p class="desc"><?= nl2br($escape($event['description'])) ?></p>
<ul class="facts"><li><strong>Expositor</strong><span><?= $escape($event['speaker']) ?></span></li><li><strong>Lugar</strong><span><?= $escape($event['venue']) ?><?= $event['room'] !== '' ? ' · ' . $escape($event['room']) : '' ?></span></li></ul>
<p class="seats <?= $full ? 'full' : '' ?>"><?= $full ? 'Cupos agotados' : ($event['remaining'] === null ? 'Cupos ilimitados' : $event['remaining'] . ' cupos disponibles') ?></p>
<?php if (!$full): ?><details class="enroll" <?= $submittedEvent === $event['uid'] && $error !== '' ? 'open' : '' ?>><summary>Inscribirme</summary>
<form method="post" class="form"><input type="hidden" name="event_uid" value="<?= $escape($event['uid']) ?>">
<div><label for="name-<?= $escape($event['uid']) ?>">Nombre completo *</label><input id="name-<?= $escape($event['uid']) ?>" name="name" maxlength="160" autocomplete="name" required value="<?= $submittedEvent === $event['uid'] ? $escape($_POST['name'] ?? '') : '' ?>"></div>
<div><label for="email-<?= $escape($event['uid']) ?>">Correo electrónico *</label><input id="email-<?= $escape($event['uid']) ?>" type="email" name="email" maxlength="180" autocomplete="email" required value="<?= $submittedEvent === $event['uid'] ? $escape($_POST['email'] ?? '') : '' ?>"></div>
<div><label for="phone-<?= $escape($event['uid']) ?>">Teléfono</label><input id="phone-<?= $escape($event['uid']) ?>" type="tel" name="phone" maxlength="30" autocomplete="tel" value="<?= $submittedEvent === $event['uid'] ? $escape($_POST['phone'] ?? '') : '' ?>"></div>
<div><label for="org-<?= $escape($event['uid']) ?>">Empresa u organización</label><input id="org-<?= $escape($event['uid']) ?>" name="organization" maxlength="180" value="<?= $submittedEvent === $event['uid'] ? $escape($_POST['organization'] ?? '') : '' ?>"></div>
<div class="wide" style="position:absolute;left:-9999px" aria-hidden="true"><label>Sitio web<input name="website" tabindex="-1" autocomplete="off"></label></div>
<label class="wide check"><input type="checkbox" name="consent" value="1" required><span>Autorizo el uso de estos datos para gestionar mi inscripción y recibir información sobre este evento.</span></label>
<div class="wide"><button class="submit" type="submit">Confirmar inscripción</button></div></form></details><?php endif; ?>
</div></article>
<?php endforeach; ?>
</div></div>
</main><footer><div class="wrap">Inscripciones administradas por el organizador del evento.</div></footer>
</body></html>
