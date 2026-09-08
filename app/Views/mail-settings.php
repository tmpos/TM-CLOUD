<?php use App\Core\Csrf; ?>
<section class="grid gap-6 xl:grid-cols-[1.5fr_1fr]">
    <div class="card p-6">
        <div class="mb-6">
            <h2 class="text-lg font-semibold text-white">Correo general para códigos OTP</h2>
            <p class="mt-1 text-sm text-slate-500">Configura una cuenta de Google con contraseña de aplicación. La credencial se guarda cifrada fuera del directorio público.</p>
        </div>
        <form method="post" action="/mail-settings" class="space-y-5">
            <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
            <label class="flex items-center justify-between rounded-xl border border-line bg-black/10 p-4">
                <span><strong class="block text-sm text-white">Habilitar envío de correo</strong><small class="text-slate-500">Los endpoints de OTP solo enviarán cuando esté activo.</small></span>
                <input type="checkbox" name="enabled" value="1" class="h-5 w-5 accent-teal-400" <?= !empty($settings['enabled']) ? 'checked' : '' ?>>
            </label>
            <div class="grid gap-4 md:grid-cols-2">
                <label><span class="label">Servidor SMTP</span><input class="input" name="host" required value="<?= e($settings['host'] ?? 'smtp.gmail.com') ?>"></label>
                <label><span class="label">Puerto</span><input class="input" name="port" type="number" min="1" max="65535" required value="<?= e($settings['port'] ?? 587) ?>"></label>
                <label><span class="label">Seguridad</span><select class="input" name="encryption"><option value="tls" <?= ($settings['encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS (587)</option><option value="ssl" <?= ($settings['encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL (465)</option><option value="none" <?= ($settings['encryption'] ?? '') === 'none' ? 'selected' : '' ?>>Sin cifrado</option></select></label>
                <label><span class="label">Correo de Google</span><input class="input" name="username" type="email" autocomplete="username" value="<?= e($settings['username'] ?? '') ?>" placeholder="empresa@gmail.com"></label>
                <label><span class="label">Contraseña de aplicación</span><input class="input" name="password" type="password" autocomplete="new-password" placeholder="<?= !empty($settings['password_configured']) ? 'Configurada; dejar vacío para conservar' : '16 caracteres de Google' ?>"><small class="mt-1 block text-xs text-slate-600">No uses la contraseña normal de Gmail.</small></label>
                <label><span class="label">Correo remitente</span><input class="input" name="from_email" type="email" value="<?= e($settings['from_email'] ?? '') ?>" placeholder="empresa@gmail.com"></label>
                <label><span class="label">Nombre remitente</span><input class="input" name="from_name" maxlength="100" value="<?= e($settings['from_name'] ?? 'TMPBase') ?>"></label>
                <label><span class="label">Responder a (opcional)</span><input class="input" name="reply_to" type="email" value="<?= e($settings['reply_to'] ?? '') ?>"></label>
            </div>
            <button class="btn-primary">Guardar configuración</button>
        </form>
    </div>

    <aside class="card p-6">
        <h2 class="font-semibold text-white">Enviar prueba</h2>
        <p class="mt-1 text-sm text-slate-500">Guarda primero la configuración y luego comprueba la entrega.</p>
        <form method="post" action="/mail-settings/test" class="mt-5 space-y-4">
            <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
            <label><span class="label">Correo destino</span><input class="input" name="to" type="email" required placeholder="cliente@example.com"></label>
            <button class="btn-primary w-full">Enviar OTP de prueba</button>
        </form>
        <div class="mt-6 rounded-xl border border-amber-500/20 bg-amber-500/10 p-4 text-xs leading-5 text-amber-200">
            En Google activa la verificación en dos pasos y crea una “Contraseña de aplicación”. Pega ese código aquí; TMPBase lo cifra antes de almacenarlo.
        </div>
    </aside>
</section>
