<?php use App\Core\Csrf; ?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h2 class="text-2xl font-bold text-white">Archivos APK</h2>
        <p class="mt-1 text-sm text-slate-500">Archivos disponibles en el directorio general <code>storage/apk-files</code>.</p>
    </div>
    <a href="/apk-files/upload" class="btn-primary">Subir APK</a>
</div>

<div class="mb-5 grid gap-4 md:grid-cols-2">
    <div class="card p-5">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Listado JSON público</span>
        <div class="mt-3 flex gap-2">
            <input class="input min-w-0 font-mono text-xs" readonly value="<?= e($baseUrl) ?>/api/apk-files">
            <button class="btn-secondary" type="button" data-copy="<?= e($baseUrl) ?>/api/apk-files">Copiar</button>
        </div>
    </div>
    <div class="card p-5">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Descargar el APK más reciente</span>
        <div class="mt-3 flex gap-2">
            <input class="input min-w-0 font-mono text-xs" readonly value="<?= e($baseUrl) ?>/downloads/apk/latest">
            <button class="btn-secondary" type="button" data-copy="<?= e($baseUrl) ?>/downloads/apk/latest">Copiar</button>
        </div>
    </div>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Archivo</th><th>Tamaño</th><th>Modificado</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($files as $file): ?>
            <tr>
                <td class="font-semibold text-white"><?= e($file['name']) ?></td>
                <td><?= number_format((int) $file['size'] / 1048576, 2) ?> MB</td>
                <td><?= e($file['modified_at']) ?> UTC</td>
                <td>
                    <div class="flex flex-wrap gap-2">
                        <a class="btn-primary" href="<?= e($file['download_url']) ?>">Descargar</a>
                        <button class="btn-secondary" type="button" data-copy="<?= e($file['download_url']) ?>">Copiar enlace</button>
                        <form method="post" action="/apk-files/<?= e(rawurlencode($file['name'])) ?>/delete" data-confirm="¿Eliminar este APK del directorio?">
                            <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                            <button class="btn-danger" type="submit">Eliminar</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$files): ?><tr><td colspan="4" class="py-12 text-center text-slate-600">El directorio todavía no contiene archivos APK.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
