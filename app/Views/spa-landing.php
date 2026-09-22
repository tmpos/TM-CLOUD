<?php
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$nombre = (string) ($settings['nombre'] ?? 'Spa');
$primario = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) ($settings['color_primario'] ?? '')) ? $settings['color_primario'] : '#8347d9';
$whatsappDigits = preg_replace('/\D+/', '', (string) ($settings['whatsapp'] ?? $settings['telefono'] ?? ''));
$moneyFmt = static fn (float $value): string => number_format($value, 2);
$value = static fn (string $key, string $fallback = ''): string => $escape($_POST[$key] ?? $fallback);
$hoy = gmdate('Y-m-d');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $escape($nombre) ?><?= $settings['tagline'] !== '' ? ' — ' . $escape($settings['tagline']) : '' ?></title>
<style>
:root{--primary:<?= $escape($primario) ?>;}
*{box-sizing:border-box}
body{margin:0;font-family:'Segoe UI',system-ui,-apple-system,sans-serif;color:#241b2e;background:#fbf9fd;line-height:1.5}
a{color:inherit}
.container{max-width:1080px;margin:0 auto;padding:0 20px}
.nav{position:sticky;top:0;z-index:20;background:#fffffff2;backdrop-filter:blur(6px);border-bottom:1px solid #eee2f7}
.nav-inner{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;max-width:1080px;margin:0 auto}
.brand{font-weight:800;font-size:20px;letter-spacing:.2px}
.nav a.cta{background:var(--primary);color:#fff;padding:9px 18px;border-radius:999px;text-decoration:none;font-weight:700;font-size:14px}
.hero{position:relative;min-height:56vh;display:flex;align-items:center;justify-content:center;text-align:center;color:#fff;background:linear-gradient(180deg,#000000a6,#00000073),<?= $settings['imagen_portada'] !== '' ? 'url(' . $escape($settings['imagen_portada']) . ')' : 'linear-gradient(135deg,var(--primary),#3a1d57)' ?>;background-size:cover;background-position:center}
.hero-inner{padding:60px 20px;max-width:760px}
.hero h1{font-size:clamp(32px,5vw,52px);margin:0 0 12px;font-weight:800}
.hero p{font-size:clamp(16px,2vw,20px);opacity:.95;margin:0 0 26px}
.hero a.cta{display:inline-block;background:var(--primary);color:#fff;padding:14px 30px;border-radius:999px;text-decoration:none;font-weight:700;font-size:16px;box-shadow:0 12px 30px #00000040}
section{padding:60px 0}
h2.section-title{font-size:clamp(24px,3vw,34px);text-align:center;margin:0 0 8px;font-weight:800}
p.section-sub{text-align:center;color:#6b6178;max-width:620px;margin:0 auto 40px}
.about{background:#fff}
.about-text{max-width:760px;margin:0 auto;text-align:center;font-size:17px;color:#3a3145;white-space:pre-line}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:22px}
.card{background:#fff;border:1px solid #eee2f7;border-radius:18px;overflow:hidden;box-shadow:0 10px 30px #3a1d5708;transition:transform .15s}
.card:hover{transform:translateY(-4px)}
.card img{width:100%;height:160px;object-fit:cover;background:#f1eaf9}
.card-body{padding:18px}
.card-body h3{margin:0 0 6px;font-size:18px}
.card-body p{margin:0 0 10px;font-size:14px;color:#6b6178}
.price{font-weight:800;color:var(--primary);font-size:16px}
.duration{font-size:13px;color:#8a8095}
.gallery{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}
.gallery img{width:100%;height:180px;object-fit:cover;border-radius:14px}
.contact{background:linear-gradient(135deg,var(--primary),#3a1d57);color:#fff}
.contact .grid{grid-template-columns:repeat(auto-fit,minmax(200px,1fr));text-align:center}
.contact .item i{display:block;font-size:14px;text-transform:uppercase;letter-spacing:.08em;opacity:.75;margin-bottom:6px}
.contact .item strong{font-size:17px}
.social{display:flex;justify-content:center;gap:14px;margin-top:26px}
.social a{background:#ffffff26;padding:10px 18px;border-radius:999px;text-decoration:none;font-weight:700;font-size:14px}
.book{background:#fff}
.book-card{max-width:640px;margin:0 auto;background:#fbf9fd;border:1px solid #eee2f7;border-radius:22px;padding:34px}
.book label{display:block;font-weight:700;font-size:14px;margin-bottom:7px}
.book input,.book select,.book textarea{width:100%;border:1px solid #d8cbef;border-radius:11px;padding:12px 13px;font:inherit;color:inherit;background:#fff;margin-bottom:16px}
.book textarea{min-height:90px;resize:vertical}
.book button{width:100%;border:0;border-radius:12px;padding:15px;background:var(--primary);color:#fff;font:700 16px inherit;cursor:pointer}
.book .grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.error{padding:12px 14px;border-radius:10px;background:#fff0f0;color:#b42318;margin-bottom:18px}
.success{text-align:center;padding:20px 10px}
.success .icon{font-size:52px;color:var(--primary)}
footer{padding:30px 20px;text-align:center;color:#8a8095;font-size:13px}
@media(max-width:560px){.book .grid2{grid-template-columns:1fr}}
</style>
</head>
<body>

<nav class="nav"><div class="nav-inner">
  <div class="brand"><?= $escape($nombre) ?></div>
  <a class="cta" href="#reservar">Reservar cita</a>
</div></nav>

<div class="hero"><div class="hero-inner">
  <h1><?= $escape($nombre) ?></h1>
  <?php if ($settings['tagline'] !== ''): ?><p><?= $escape($settings['tagline']) ?></p><?php endif; ?>
  <a class="cta" href="#reservar">Reservar cita</a>
</div></div>

<?php if ($settings['descripcion'] !== ''): ?>
<section class="about"><div class="container">
  <h2 class="section-title">Sobre nosotros</h2>
  <p class="about-text"><?= $escape($settings['descripcion']) ?></p>
</div></section>
<?php endif; ?>

<?php if ($services): ?>
<section><div class="container">
  <h2 class="section-title">Servicios</h2>
  <p class="section-sub">Elige el servicio que se ajuste a lo que buscas.</p>
  <div class="grid">
    <?php foreach ($services as $servicio): ?>
    <div class="card">
      <?php if ($servicio['imagen'] !== ''): ?><img src="<?= $escape($servicio['imagen']) ?>" alt="<?= $escape($servicio['nombre']) ?>" loading="lazy"><?php endif; ?>
      <div class="card-body">
        <h3><?= $escape($servicio['nombre']) ?></h3>
        <?php if ($servicio['descripcion'] !== ''): ?><p><?= $escape($servicio['descripcion']) ?></p><?php endif; ?>
        <div class="price">$<?= $moneyFmt($servicio['precio_venta']) ?></div>
        <?php if ($servicio['duracion_minutos'] > 0): ?><div class="duration"><?= (int) $servicio['duracion_minutos'] ?> min</div><?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div></section>
<?php endif; ?>

<?php if ($settings['galeria']): ?>
<section><div class="container">
  <h2 class="section-title">Galeria</h2>
  <div class="gallery">
    <?php foreach ($settings['galeria'] as $imagen): ?>
    <img src="<?= $escape($imagen) ?>" alt="<?= $escape($nombre) ?>" loading="lazy">
    <?php endforeach; ?>
  </div>
</div></section>
<?php endif; ?>

<section class="contact"><div class="container">
  <h2 class="section-title">Visitanos</h2>
  <div class="grid">
    <?php if ($settings['direccion'] !== ''): ?><div class="item"><i>Direccion</i><strong><?= $escape($settings['direccion']) ?></strong></div><?php endif; ?>
    <?php if ($settings['horario'] !== ''): ?><div class="item"><i>Horario</i><strong><?= nl2br($escape($settings['horario'])) ?></strong></div><?php endif; ?>
    <?php if ($settings['telefono'] !== ''): ?><div class="item"><i>Telefono</i><strong><?= $escape($settings['telefono']) ?></strong></div><?php endif; ?>
    <?php if ($settings['email'] !== ''): ?><div class="item"><i>Correo</i><strong><?= $escape($settings['email']) ?></strong></div><?php endif; ?>
  </div>
  <?php if ($settings['instagram'] !== '' || $settings['facebook'] !== '' || $settings['tiktok'] !== '' || $whatsappDigits !== ''): ?>
  <div class="social">
    <?php if ($whatsappDigits !== ''): ?><a href="https://wa.me/<?= $escape($whatsappDigits) ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a><?php endif; ?>
    <?php if ($settings['instagram'] !== ''): ?><a href="<?= $escape($settings['instagram']) ?>" target="_blank" rel="noopener noreferrer">Instagram</a><?php endif; ?>
    <?php if ($settings['facebook'] !== ''): ?><a href="<?= $escape($settings['facebook']) ?>" target="_blank" rel="noopener noreferrer">Facebook</a><?php endif; ?>
    <?php if ($settings['tiktok'] !== ''): ?><a href="<?= $escape($settings['tiktok']) ?>" target="_blank" rel="noopener noreferrer">TikTok</a><?php endif; ?>
  </div>
  <?php endif; ?>
</div></section>

<section class="book" id="reservar"><div class="container">
  <h2 class="section-title">Reserva tu cita</h2>
  <p class="section-sub">Completa el formulario y te confirmaremos tu cita a la brevedad.</p>
  <div class="book-card">
    <?php if ($booked): ?>
      <div class="success"><div class="icon">&#10003;</div><h3>Solicitud enviada</h3><p>Recibimos tu solicitud de cita. Nos pondremos en contacto para confirmar fecha y hora.</p></div>
    <?php else: ?>
      <?php if ($error !== ''): ?><div class="error"><?= $escape($error) ?></div><?php endif; ?>
      <form method="post">
        <label>Nombre completo</label>
        <input name="nombre" maxlength="160" required value="<?= $value('nombre') ?>">
        <label>Telefono</label>
        <input name="telefono" inputmode="tel" maxlength="24" required value="<?= $value('telefono') ?>">
        <label>Servicio</label>
        <select name="servicio">
          <option value="">Selecciona un servicio</option>
          <?php foreach ($services as $servicio): ?>
          <option value="<?= $escape($servicio['nombre']) ?>" <?= ($_POST['servicio'] ?? '') === $servicio['nombre'] ? 'selected' : '' ?>><?= $escape($servicio['nombre']) ?></option>
          <?php endforeach; ?>
          <option value="OTRO" <?= ($_POST['servicio'] ?? '') === 'OTRO' ? 'selected' : '' ?>>Otro</option>
        </select>
        <div class="grid2">
          <div><label>Fecha</label><input name="fecha" type="date" min="<?= $escape($hoy) ?>" required value="<?= $value('fecha') ?>"></div>
          <div><label>Hora</label><input name="hora" type="time" required value="<?= $value('hora') ?>"></div>
        </div>
        <label>Nota (opcional)</label>
        <textarea name="nota" maxlength="500"><?= $value('nota') ?></textarea>
        <button type="submit">Reservar cita</button>
      </form>
    <?php endif; ?>
  </div>
</div></section>

<footer>© <?= date('Y') ?> <?= $escape($nombre) ?><?= $settings['direccion'] !== '' ? ' · ' . $escape($settings['direccion']) : '' ?></footer>

</body>
</html>
