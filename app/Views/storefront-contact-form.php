<?php
$old = $old ?? [];
$isSpa = !empty($store['is_spa']);
$ready = !$isSpa || (!empty($schedule['enabled']) && !empty($services));
?>
<style>
.registration{margin:32px 0;padding:28px;border:1px solid #8883;border-radius:var(--radius);background:var(--surface);scroll-margin-top:24px}.registration h2{font-size:26px;line-height:1.25;margin:0 0 10px}.registration .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.registration label{display:block;font-size:14px;font-weight:650}.registration input,.registration select,.registration textarea{display:block;width:100%;margin-top:7px;padding:13px;border:1px solid #8885;border-radius:10px;font:inherit;background:var(--bg);color:var(--text);min-width:0}.registration textarea{resize:vertical}.registration .full{grid-column:1/-1}.registration button[type=submit]{border:0;cursor:pointer;font:inherit}.registration button:disabled{opacity:.5;cursor:not-allowed}.registration :focus-visible{outline:3px solid var(--primary);outline-offset:3px}.registration .notice{padding:15px;border-radius:10px;margin:16px 0;background:var(--bg);border:1px solid #8884}.registration .form-error{border-color:#be123c;color:#9f1239;background:#fff1f2}.registration .form-success{border-color:#15803d;color:#166534;background:#f0fdf4}.registration small{display:block;color:var(--muted);margin-top:16px}.registration p{margin-top:0}@media(max-width:600px){.registration{padding:20px}.registration .form-grid{grid-template-columns:1fr}}
</style>
<section class="registration" id="registro" aria-labelledby="registro-title">
<h2 id="registro-title"><?= $isSpa ? 'Reserva tu cita' : 'Registro de cliente' ?></h2>
<p class="muted"><?= $isSpa ? 'Elige tu servicio, la fecha y uno de los horarios disponibles.' : 'Crea tu cuenta para consultar tus compras y servicios desde el portal de clientes.' ?></p>
<?php if (!empty($booked)): ?><div class="notice form-success" role="status">Tu cita fue registrada y está pendiente de confirmación. El horario quedó reservado. Contacta al spa si necesitas cambiarla.</div><?php endif; ?>
<?php if (!empty($error)): ?><div class="notice form-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if (!$ready): ?><div class="notice">Las reservas online no están disponibles por ahora. Comunícate con el spa mediante los canales de contacto de esta página.</div>
<?php else: ?>
<form method="post" action="<?= e($base) ?>/pages/contacto#registro" autocomplete="on">
<input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
<?php if ($isSpa): ?><input type="hidden" name="booking_token" value="<?= e($bookingToken) ?>"><?php endif; ?>
<div class="form-grid">
<label class="full">Nombre completo *<input name="nombre" required minlength="2" maxlength="<?= $isSpa ? 160 : 120 ?>" autocomplete="name" value="<?= e($old['nombre'] ?? '') ?>"></label>
<label>Teléfono<?= $isSpa ? ' *' : '' ?><input type="tel" name="telefono" <?= $isSpa ? 'required' : '' ?> maxlength="40" autocomplete="tel" value="<?= e($old['telefono'] ?? '') ?>"></label>
<?php if ($isSpa): ?>
<label>Servicio *<select name="servicio_uid" required><option value="">Selecciona un servicio</option><?php foreach ($services as $service): ?><option value="<?= e($service['uid']) ?>" <?= ($old['servicio_uid'] ?? '') === $service['uid'] ? 'selected' : '' ?>><?= e($service['nombre']) ?></option><?php endforeach; ?></select></label>
<label class="full">Fecha de tu visita *<input type="date" name="fecha" required value="<?= e($old['fecha'] ?? '') ?>"></label>
<div class="full"><?php require __DIR__ . '/spa-shift-picker.php'; ?></div>
<label class="full">Nota (opcional)<textarea name="nota" rows="3" maxlength="500"><?= e($old['nota'] ?? '') ?></textarea></label>
<?php else: ?>
<label>Cédula *<input name="cedula" required minlength="5" maxlength="30" autocomplete="username" value="<?= e($old['cedula'] ?? '') ?>"></label>
<label class="full">Correo electrónico *<input type="email" name="email" required maxlength="160" autocomplete="email" value="<?= e($old['email'] ?? '') ?>"></label>
<label class="full">Dirección<textarea name="direccion" rows="3" maxlength="250" autocomplete="street-address"><?= e($old['direccion'] ?? '') ?></textarea></label>
<?php endif; ?>
</div>
<button class="action" type="submit" <?= $isSpa ? 'disabled' : '' ?>><?= $isSpa ? 'Reservar cita' : 'Crear cuenta y verificar correo' ?></button>
<small><?= $isSpa ? 'La cita se registra como pendiente de confirmación. No se solicita ningún pago en este formulario.' : 'Recibirás un código por correo para activar tu cuenta.' ?> <a href="<?= e($base) ?>/pages/privacidad">Consultar privacidad</a>.</small>
<?php if (!$isSpa): ?><small>¿Ya tienes cuenta? <a href="<?= e($base) ?>/invoices">Entrar al portal de clientes</a>.</small><?php endif; ?>
</form>
<?php endif; ?>
</section>
