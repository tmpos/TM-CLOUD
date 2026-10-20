<?php
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$title = (string) ($page['title'] ?? 'Próximos eventos');
$design = $website['web_editor']['design'] ?? [];
$preset = preg_match('/^[a-z0-9_-]{1,40}$/', (string) ($design['preset'] ?? '')) ? $design['preset'] : 'classic';
$layout = in_array(($design['hero'] ?? ''), ['split', 'center', 'compact'], true) ? $design['hero'] : 'split';
$cards = in_array(($website['card_style'] ?? ''), ['elevated', 'outlined', 'minimal'], true) ? $website['card_style'] : 'outlined';
$safeColor = static fn (mixed $value, string $fallback): string => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $value) ? (string) $value : $fallback;
$color = $safeColor($preset === 'classic' ? ($page['color'] ?? null) : ($website['primary_color'] ?? null), '#2563eb');
$accent = $safeColor($website['accent_color'] ?? null, '#f59e0b');
$ink = $safeColor($website['text_color'] ?? null, '#14213d');
$muted = $safeColor($website['muted_color'] ?? null, '#64748b');
$surface = $safeColor($website['surface_color'] ?? null, '#ffffff');
$background = $safeColor($website['background_color'] ?? null, '#f5f8fc');
$header = $safeColor($website['header_color'] ?? null, '#ffffff');
$footer = $safeColor($website['footer_color'] ?? null, '#14213d');
$hero = $safeColor($website['hero_background_color'] ?? null, '#102039');
$radius = max(8, min(32, (int) ($website['border_radius'] ?? 22)));
$width = max(960, min(1600, (int) ($design['width'] ?? 1120)));
$font = match ($website['font_family'] ?? 'system') {
    'serif' => 'Georgia,serif',
    'poppins' => '"Trebuchet MS",Arial,sans-serif',
    'inter' => 'Inter,system-ui,-apple-system,"Segoe UI",sans-serif',
    default => 'system-ui,-apple-system,"Segoe UI",sans-serif',
};
$brand = trim((string) ($website['store_name'] ?? '')) ?: 'Eventos';
$submittedEvent = (string) ($_POST['event_uid'] ?? '');
$imageUrl = static function (mixed $uid) use ($config, $project): string {
    if (!preg_match('/^fil_[A-Za-z0-9_-]{1,100}$/', (string) $uid)) return '';
    return rtrim((string) ($config['url'] ?? ''), '/') . '/api/' . rawurlencode((string) $project['uid']) . '/storage/' . rawurlencode((string) $uid);
};
$heroImage = $imageUrl($page['hero_image'] ?? '');
$mapEmbed = static function (mixed $value, string $venue = ''): string {
    $url = trim((string) $value);
    if ($url === '') return '';
    $parts = parse_url($url);
    if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https') return '';
    $host = strtolower((string) ($parts['host'] ?? ''));
    if (in_array($host, ['openstreetmap.org', 'www.openstreetmap.org'], true)) {
        parse_str((string) ($parts['query'] ?? ''), $query);
        $lat = filter_var($query['mlat'] ?? null, FILTER_VALIDATE_FLOAT);
        $lon = filter_var($query['mlon'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($lat === false || $lon === false || $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) return '';
        $bbox = implode(',', [$lon - .008, $lat - .005, $lon + .008, $lat + .005]);
        return 'https://www.openstreetmap.org/export/embed.html?bbox=' . rawurlencode($bbox) . '&layer=mapnik&marker=' . rawurlencode($lat . ',' . $lon);
    }
    if (in_array($host, ['google.com', 'www.google.com', 'maps.google.com', 'maps.app.goo.gl', 'goo.gl'], true)) {
        $path = (string) ($parts['path'] ?? '');
        if (preg_match('/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $path, $match)) {
            $lat = (float) $match[1]; $lon = (float) $match[2];
            if ($lat >= -90 && $lat <= 90 && $lon >= -180 && $lon <= 180) return 'https://www.google.com/maps?q=' . rawurlencode($lat . ',' . $lon) . '&output=embed';
        }
        return $venue !== '' ? 'https://www.google.com/maps?q=' . rawurlencode($venue) . '&output=embed' : '';
    }
    return '';
};
$coverMode = in_array(($website['hero_background_mode'] ?? ''), ['color', 'image', 'carousel'], true)
    ? (string) $website['hero_background_mode'] : 'gradient';
$coverImages = is_array($website['hero_images'] ?? null)
    ? array_values(array_filter($website['hero_images'], static fn (mixed $url): bool => is_string($url) && (bool) preg_match('#^(?:https://|/(?:api|storage)/)#i', $url)))
    : [];
if (in_array($coverMode, ['image', 'carousel'], true) && !$coverImages) $coverMode = 'gradient';
$coverOpacity = max(0, min(85, (int) ($website['hero_overlay_opacity'] ?? 55))) / 100;
$coverInterval = max(3000, min(15000, (int) ($website['hero_carousel_interval'] ?? 6000)));
$coverIsCarousel = $coverMode === 'carousel' && count($coverImages) > 1;
$coverCount = count($coverImages);
$highlights = array_slice(array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) ($page['highlights'] ?? '')) ?: []))), 0, 6);
$next = $events[0] ?? null;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="index,follow"><title><?= $escape($title) ?> · Eventos</title>
<style>
:root{--brand:<?= $escape($color) ?>;--accent:<?= $escape($accent) ?>;--ink:<?= $escape($ink) ?>;--muted:<?= $escape($muted) ?>;--line:color-mix(in srgb,var(--ink) 16%,transparent);--surface:<?= $escape($surface) ?>;--page-bg:<?= $escape($background) ?>;--hero:<?= $escape($hero) ?>;--header:<?= $escape($header) ?>;--footer:<?= $escape($footer) ?>;--radius:<?= $radius ?>px;--page-width:<?= $width ?>px}
*{box-sizing:border-box}body{margin:0;font:16px/1.55 <?= $font ?>;color:var(--ink);background:var(--page-bg)}
.wrap{max-width:var(--page-width);margin:auto;padding:0 22px}.top{background:var(--header);border-bottom:1px solid var(--line)}.top .wrap{display:flex;align-items:center;justify-content:space-between;padding-top:15px;padding-bottom:15px}.brand{font-size:18px;font-weight:800;letter-spacing:-.03em}.top a{color:var(--brand);font-weight:700;text-decoration:none}
.hero{background:radial-gradient(circle at 85% 10%,color-mix(in srgb,var(--accent) 35%,transparent),transparent 26%),linear-gradient(125deg,var(--hero),var(--brand));color:#fff;padding:78px 0 84px}.eyebrow{font-size:12px;letter-spacing:.16em;text-transform:uppercase;font-weight:800;opacity:.82}.hero h1{font-size:clamp(38px,6vw,68px);line-height:1.05;letter-spacing:-.055em;max-width:800px;margin:15px 0 20px}.hero p{max-width:670px;font-size:18px;opacity:.9;margin:0}.intro{padding:45px 0 24px}.intro h2{font-size:28px;letter-spacing:-.03em;margin:0 0 7px}.intro p{color:var(--muted);margin:0}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,340px),1fr));gap:22px;padding-bottom:80px}
.card{background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);overflow:hidden;box-shadow:0 18px 45px #1932510d}.stripe{height:7px;background:var(--accent)}.content{padding:27px}.date{display:inline-block;font-size:13px;font-weight:800;color:var(--brand);background:color-mix(in srgb,var(--brand) 12%,white);border-radius:999px;padding:6px 12px}.card h3{font-size:24px;line-height:1.2;letter-spacing:-.035em;margin:19px 0 10px}.desc{white-space:pre-line;color:var(--muted);min-height:48px}.facts{list-style:none;padding:0;margin:21px 0;display:grid;gap:9px}.facts li{display:flex;gap:10px;color:var(--ink)}.facts strong{min-width:88px}.seats{color:#0f766e;font-size:13px;font-weight:700}.full{color:#b45309}.enroll{border-top:1px solid var(--line);padding-top:19px}.enroll summary{cursor:pointer;list-style:none;background:var(--brand);color:#fff;text-align:center;border-radius:var(--radius);padding:12px;font-weight:800}.enroll summary::-webkit-details-marker{display:none}.enroll[open] summary{margin-bottom:18px}.form{display:grid;grid-template-columns:1fr 1fr;gap:13px}.form .wide{grid-column:1/-1}.form label{display:block;font-size:13px;font-weight:700;margin-bottom:5px}.form input:not([type=checkbox]){width:100%;padding:11px;border:1px solid var(--line);border-radius:10px;font:inherit;background:#fff}.form input:focus{outline:2px solid var(--brand);outline-offset:2px}.check{display:flex;gap:9px;align-items:flex-start;font-size:13px;color:var(--muted)}.check input{margin-top:4px}.submit{border:0;background:var(--brand);color:#fff;border-radius:var(--radius);padding:12px 18px;font:inherit;font-weight:800;cursor:pointer;width:100%}.notice{padding:15px 18px;margin:25px 0 0;border-radius:12px;background:#e6f6ee;color:#14643e}.notice.error{background:#fff0ec;color:#9a3412}.empty{padding:45px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);text-align:center;color:var(--muted)}footer{border-top:1px solid var(--line);background:var(--footer);color:#fff;padding:25px 0;font-size:13px}
body[data-layout=center] .hero .wrap{text-align:center}body[data-layout=center] .hero h1,body[data-layout=center] .hero p{margin-left:auto;margin-right:auto}body[data-layout=compact] .hero{padding:46px 0 52px}body[data-layout=compact] .hero h1{font-size:clamp(32px,5vw,52px)}body[data-cards=minimal] .card{box-shadow:none}body[data-cards=outlined] .card{box-shadow:none;border-width:2px}body[data-template=events] .hero{background:radial-gradient(circle at 80% 20%,color-mix(in srgb,var(--accent) 45%,transparent),transparent 35%),linear-gradient(135deg,var(--hero),var(--brand));border-bottom:8px solid var(--accent)}body[data-template=events] .date{border-radius:var(--radius)}
.top .wrap{min-height:72px}.brand{display:flex;align-items:center;gap:12px}.brand::before{content:'✦';display:inline-grid;place-items:center;width:36px;height:36px;border-radius:12px;background:var(--brand);color:#fff;font-size:20px}.top a{display:inline-flex;align-items:center;gap:8px}.hero{padding:92px 0 100px;position:relative;overflow:hidden}.hero:after{content:'';position:absolute;right:-110px;bottom:-220px;width:480px;height:480px;border:1px solid #ffffff30;border-radius:50%;pointer-events:none}.hero-grid{display:grid;grid-template-columns:minmax(0,1.12fr) minmax(330px,.88fr);align-items:center;gap:64px;position:relative;z-index:1}.hero-copy{max-width:700px}.hero h1{font-size:clamp(43px,5.8vw,78px);line-height:1.02}.hero p{line-height:1.7}.hero-actions{display:flex;flex-wrap:wrap;gap:15px;align-items:center;margin-top:33px}.hero-button{display:inline-flex;align-items:center;justify-content:center;min-height:52px;background:var(--accent);color:var(--hero);border-radius:999px;padding:12px 25px;font-weight:850;text-decoration:none}.hero-meta{font-size:13px;font-weight:650;opacity:.85}.hero-visual{position:relative;min-height:420px;border:1px solid #ffffff40;border-radius:calc(var(--radius) + 10px);overflow:hidden;box-shadow:0 36px 80px #0005;background:linear-gradient(145deg,color-mix(in srgb,var(--accent) 32%,var(--hero)),var(--brand))}.hero-visual img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}.hero-visual:after{content:'';position:absolute;inset:0;background:linear-gradient(180deg,transparent 40%,#061325d9)}.hero-caption{position:absolute;left:28px;right:28px;bottom:27px;z-index:1}.hero-caption small{display:block;letter-spacing:.15em;text-transform:uppercase;font-size:11px;font-weight:850;color:var(--accent)}.hero-caption strong{display:block;font-size:23px;line-height:1.2;margin-top:7px}.hero-caption span{display:block;margin-top:6px;font-size:14px;opacity:.88}.hero-art{position:absolute;inset:0;display:grid;place-items:center;font-size:130px;font-weight:200;opacity:.13}.about{padding:75px 0 45px}.section-kicker{font-size:12px;letter-spacing:.16em;text-transform:uppercase;font-weight:850;color:var(--brand)}.about-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(280px,.9fr);gap:65px;align-items:start}.about h2,.intro h2{font-size:clamp(28px,3vw,42px);line-height:1.15;letter-spacing:-.04em;margin:9px 0 15px}.about p{color:var(--muted);white-space:pre-line;line-height:1.75;margin:0}.highlights{display:grid;gap:11px}.highlight{display:flex;gap:14px;align-items:flex-start;border:1px solid var(--line);border-radius:16px;background:var(--surface);padding:16px 19px;font-weight:700;box-shadow:0 8px 24px #19325108}.highlight:before{content:'✓';display:grid;place-items:center;flex:none;width:23px;height:23px;border-radius:50%;background:color-mix(in srgb,var(--brand) 15%,white);color:var(--brand);font-size:14px}.intro{padding-top:60px;padding-bottom:26px}.intro p{max-width:620px}.card-image{display:block;width:100%;height:225px;object-fit:cover}.card-placeholder{height:175px;background:linear-gradient(135deg,color-mix(in srgb,var(--brand) 77%,var(--hero)),var(--hero));display:flex;align-items:end;padding:22px;color:#fff;font-size:12px;letter-spacing:.15em;text-transform:uppercase;font-weight:800}.card .content{padding:27px}.card h3{font-size:27px}.facts{padding:16px 0;border-top:1px solid var(--line);border-bottom:1px solid var(--line)}.facts strong{color:var(--muted);font-size:13px}.seats{margin-bottom:19px}.empty{margin-bottom:70px}
@media(max-width:850px){.hero-grid,.about-grid{grid-template-columns:1fr;gap:35px}.hero{padding:65px 0 75px}.hero-visual{min-height:340px}.about{padding-top:55px}body[data-layout=center] .hero .wrap{text-align:left}}
@media(max-width:560px){.hero{padding:55px 0 65px}.form{grid-template-columns:1fr}.form .wide{grid-column:auto}.content{padding:21px}}
.hero-cover{position:absolute;inset:0;z-index:0;overflow:hidden}.hero-cover img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}.hero-cover:after{content:'';position:absolute;inset:0;background:#081525;opacity:var(--cover-opacity)}body[data-cover=color] .hero{background:var(--hero)}.hero-cover img.cover-slide{opacity:0;animation:cover-fade linear infinite}@keyframes cover-fade{0%,<?= $coverIsCarousel ? max(0, 100 / $coverCount - 1) : 0 ?>%{opacity:1}<?= $coverIsCarousel ? 100 / $coverCount : 0 ?>%,100%{opacity:0}}@media(prefers-reduced-motion:reduce){.hero-cover img.cover-slide{animation:none;opacity:0}.hero-cover img.cover-slide:first-child{opacity:1}}
.event-map{margin:18px 0 20px;border:1px solid var(--line);border-radius:16px;overflow:hidden;background:var(--page-bg)}.event-map iframe{display:block;width:100%;height:220px;border:0}.event-map a{display:block;padding:10px 14px;color:var(--brand);font-size:13px;font-weight:800;text-decoration:none}.event-map a:hover{text-decoration:underline}
.registration-success{max-width:900px;margin:32px auto 8px;padding:clamp(24px,4vw,42px);border-radius:24px;background:var(--surface);box-shadow:0 22px 60px #1932511c;border:1px solid var(--line)}.registration-success .success-icon{display:grid;place-items:center;width:58px;height:58px;border-radius:18px;background:#dcfce7;color:#15803d;font-size:30px;font-weight:800}.registration-success h2{font-size:clamp(28px,4vw,40px);line-height:1.15;margin:18px 0 8px}.registration-success p{color:var(--muted);margin:8px 0}.registration-facts{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:24px 0}.registration-facts div{padding:14px 16px;border-radius:14px;background:var(--page-bg)}.registration-facts small{display:block;color:var(--muted);font-size:12px}.registration-facts strong{display:block;margin-top:3px}.registration-success .email-note{font-size:14px}.registration-success .event-map{margin-bottom:0}
</style>
</head>
<body data-template="<?= $escape($preset) ?>" data-layout="<?= $escape($layout) ?>" data-cards="<?= $escape($cards) ?>" data-cover="<?= $escape($coverMode) ?>">
<header class="top"><div class="wrap"><div class="brand"><?= $escape($brand) ?></div><a href="#agenda">Ver agenda ↓</a></div></header>
<main>
<section class="hero"><?php if (in_array($coverMode, ['image', 'carousel'], true)): ?><div class="hero-cover" style="--cover-opacity:<?= $coverOpacity ?>"><?php foreach (($coverIsCarousel ? $coverImages : array_slice($coverImages, 0, 1)) as $coverIndex => $coverUrl): ?><img src="<?= $escape($coverUrl) ?>" alt="" aria-hidden="true"<?= $coverIsCarousel ? ' class="cover-slide" style="animation-duration:' . ($coverCount * $coverInterval) . 'ms;animation-delay:' . (($coverIndex - $coverCount) * $coverInterval) . 'ms"' : '' ?>><?php endforeach; ?></div><?php endif; ?><div class="wrap hero-grid"><div class="hero-copy"><div class="eyebrow"><?= $escape(trim((string) ($page['eyebrow'] ?? '')) ?: 'Encuentros · Charlas · Experiencias') ?></div><h1><?= $escape($title) ?></h1><p><?= $escape($page['introduction'] ?? '') ?></p><div class="hero-actions"><a class="hero-button" href="#agenda"><?= $escape(trim((string) ($page['cta_label'] ?? '')) ?: 'Explorar eventos') ?> &rarr;</a><span class="hero-meta"><?= count($events) ?> <?= count($events) === 1 ? 'evento abierto' : 'eventos abiertos' ?> para inscripción</span></div></div><div class="hero-visual"><?php if ($heroImage !== ''): ?><img src="<?= $escape($heroImage) ?>" alt="Portada de <?= $escape($title) ?>"><?php else: ?><div class="hero-art" aria-hidden="true">✦</div><?php endif; ?><div class="hero-caption"><small><?= $next ? 'Próximo encuentro' : 'Tu próxima experiencia' ?></small><strong><?= $escape($next['title'] ?? 'Descubre lo que viene') ?></strong><?php if ($next): ?><span><?= $escape((new DateTimeImmutable($next['event_date']))->format('d/m/Y')) ?> · <?= $escape($next['event_time']) ?> · <?= $escape($next['venue']) ?></span><?php endif; ?></div></div></div></section>
<div class="wrap" id="agenda">
<?php if ($registered && is_array($registration)): ?>
<section id="confirmacion" class="registration-success" role="status" aria-labelledby="registration-title">
  <span class="success-icon" aria-hidden="true">✓</span>
  <h2 id="registration-title">¡Gracias por inscribirte<?= ($registration['name'] ?? '') !== '' ? ', ' . $escape($registration['name']) : '' ?>!</h2>
  <p>Tu lugar para <strong><?= $escape($registration['event_title'] ?? 'el evento') ?></strong> quedó reservado. Guarda estos datos para el día del encuentro.</p>
  <div class="registration-facts">
    <div><small>Fecha y hora</small><strong><?= $escape(isset($registration['event_date']) ? (new DateTimeImmutable($registration['event_date']))->format('d/m/Y') : '') ?> · <?= $escape($registration['event_time'] ?? '') ?></strong></div>
    <div><small>Lugar</small><strong><?= $escape($registration['venue'] ?? '') ?><?= ($registration['room'] ?? '') !== '' ? ' · ' . $escape($registration['room']) : '' ?></strong></div>
    <div><small>Correo registrado</small><strong><?= $escape($registration['email'] ?? '') ?></strong></div>
  </div>
  <?php if (($registration['map_url'] ?? '') !== ''): $registrationMap = $mapEmbed($registration['map_url'], (string) ($registration['venue'] ?? '')); ?>
    <div class="event-map"><?php if ($registrationMap !== ''): ?><iframe src="<?= $escape($registrationMap) ?>" title="Mapa del lugar del evento" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe><?php endif; ?><a href="<?= $escape($registration['map_url']) ?>" target="_blank" rel="noopener noreferrer">Cómo llegar al evento ↗</a></div>
  <?php endif; ?>
  <p class="email-note"><?php if (($registration['email_status'] ?? '') === 'sent'): ?>Enviamos la confirmación a tu correo.<?php elseif (($registration['email_status'] ?? '') === 'pending'): ?>La confirmación por correo está en proceso.<?php else: ?>Tu inscripción está confirmada. La confirmación por correo aún no pudo enviarse; conserva esta página como comprobante.<?php endif; ?></p>
</section>
<?php endif; ?>
<?php if ($error !== ''): ?><div class="notice error" role="alert"><?= $escape($error) ?></div><?php endif; ?>
<?php if (trim((string) ($page['about_title'] ?? '')) !== '' || trim((string) ($page['about_text'] ?? '')) !== '' || $highlights): ?><section class="about"><div class="about-grid"><div><span class="section-kicker">La experiencia</span><h2><?= $escape(trim((string) ($page['about_title'] ?? '')) ?: 'Un encuentro para compartir y aprender') ?></h2><p><?= $escape($page['about_text'] ?? '') ?></p></div><?php if ($highlights): ?><div class="highlights"><?php foreach ($highlights as $highlight): ?><div class="highlight"><?= $escape($highlight) ?></div><?php endforeach; ?></div><?php endif; ?></div></section><?php endif; ?>
<section class="intro"><h2>Agenda de eventos</h2><p>Elige un evento y completa el formulario para reservar tu lugar.</p></section>
<?php if (!$events): ?><div class="empty">No hay eventos abiertos para inscripción en este momento.</div><?php endif; ?>
<div class="grid">
<?php foreach ($events as $event):
    $dateLabel = (new DateTimeImmutable($event['event_date']))->format('d/m/Y');
    $full = $event['remaining'] !== null && $event['remaining'] < 1;
?>
<article class="card"><?php $eventImage = $imageUrl($event['image'] ?? ''); if ($eventImage !== ''): ?><img class="card-image" src="<?= $escape($eventImage) ?>" alt="Imagen de <?= $escape($event['title']) ?>" loading="lazy"><?php else: ?><div class="card-placeholder">Encuentro · <?= $escape($brand) ?></div><?php endif; ?><div class="content">
<span class="date"><?= $escape($dateLabel) ?> · <?= $escape($event['event_time']) ?></span>
<h3><?= $escape($event['title']) ?></h3><p class="desc"><?= nl2br($escape($event['description'])) ?></p>
<ul class="facts"><li><strong>Expositor</strong><span><?= $escape($event['speaker']) ?></span></li><li><strong>Lugar</strong><span><?= $escape($event['venue']) ?><?= $event['room'] !== '' ? ' · ' . $escape($event['room']) : '' ?></span></li></ul>
<?php if (($event['map_url'] ?? '') !== ''): $eventMap = $mapEmbed($event['map_url'], (string) ($event['venue'] ?? '')); ?><div class="event-map"><?php if ($eventMap !== ''): ?><iframe src="<?= $escape($eventMap) ?>" title="Mapa de <?= $escape($event['title']) ?>" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe><?php endif; ?><a href="<?= $escape($event['map_url']) ?>" target="_blank" rel="noopener noreferrer">Ver cómo llegar ↗</a></div><?php endif; ?>
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
