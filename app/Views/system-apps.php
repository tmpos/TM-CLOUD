<?php use App\Core\Csrf; ?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h2 class="text-2xl font-bold text-white">Sistemas web</h2>
        <p class="mt-1 text-sm text-slate-500">Una carpeta independiente para cada tipo de proyecto: gimnasios, restaurantes, tiendas y otros.</p>
    </div>
    <button type="button" class="btn-primary" data-dialog-open="#upload-system-dialog">Subir sistema</button>
</div>

<div class="grid gap-5 lg:grid-cols-2 2xl:grid-cols-3">
    <?php foreach ($apps as $app): ?>
        <article class="card flex flex-col p-6">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <h3 class="truncate font-semibold text-white"><?= e($app['name']) ?></h3>
                    <p class="mt-1 font-mono text-xs text-brand"><?= e($app['url']) ?></p>
                </div>
                <?php if ($app['is_default']): ?>
                    <span class="rounded-full bg-brand/10 px-2.5 py-1 text-xs text-brand">Predeterminado</span>
                <?php endif; ?>
            </div>
            <p class="mt-4 flex-1 text-sm text-slate-500"><?= e($app['description'] ?: 'Sin descripcion.') ?></p>
            <div class="mt-5 flex items-center justify-between border-t border-line pt-4">
                <span class="text-xs text-slate-500"><?= number_format((int) $app['project_count']) ?> proyecto(s)</span>
                <?php if ($app['is_default']): ?>
                    <button type="button" class="btn-secondary" data-dialog-open="#replace-default-dialog">Reemplazar</button>
                <?php else: ?>
                    <form method="post" action="/system-apps/<?= e(rawurlencode($app['slug'])) ?>/delete" data-confirm="¿Eliminar esta carpeta y todos sus archivos?">
                        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                        <button class="btn-danger" type="submit" <?= $app['project_count'] ? 'disabled title="Esta carpeta esta en uso"' : '' ?>>Eliminar</button>
                    </form>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if (!$apps): ?>
        <div class="card p-10 text-center text-slate-500">Todavia no hay sistemas web publicados.</div>
    <?php endif; ?>
</div>

<dialog id="upload-system-dialog" class="w-full max-w-2xl rounded-2xl border border-line bg-panel p-0 text-slate-200">
    <form method="post" enctype="multipart/form-data" action="/system-apps/upload" class="p-6">
        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
        <div class="mb-6 flex items-start justify-between gap-4">
            <div><h3 class="text-lg font-semibold text-white">Subir un sistema web</h3><p class="mt-1 text-sm text-slate-500">Se creara una carpeta nueva sin alterar las existentes.</p></div>
            <button type="button" data-dialog-close class="text-slate-500">Cerrar</button>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <label><span class="label">Nombre</span><input class="input" name="name" required maxlength="100" placeholder="Sistema para gimnasios"></label>
            <label><span class="label">Nombre de carpeta</span><input class="input font-mono" name="slug" maxlength="80" pattern="[A-Za-z0-9_-]+" placeholder="gimnasio"></label>
        </div>
        <label class="mt-4 block"><span class="label">Descripcion</span><textarea class="input" name="description" rows="2" placeholder="Interfaz utilizada por los proyectos de gimnasios"></textarea></label>
        <label class="mt-4 block">
            <span class="label">Proyecto compilado (.zip)</span>
            <input class="input" type="file" name="file" accept=".zip,application/zip" required>
            <span class="mt-2 block text-xs text-slate-500">El ZIP debe contener un unico <code>index.html</code> y sus archivos estaticos. Maximo <?= e((string) $maxUploadMb) ?> MB.</span>
        </label>
        <div class="mt-5 rounded-xl border border-amber-500/20 bg-amber-500/5 p-4 text-sm text-amber-200">
            Compile Vue, React u otra interfaz usando rutas relativas (<code>base: './'</code>) para que imagenes, CSS y JavaScript carguen desde su propia carpeta.
        </div>
        <button class="btn-primary mt-6 w-full" type="submit">Subir y crear carpeta</button>
    </form>
</dialog>

<dialog id="replace-default-dialog" class="w-full max-w-lg rounded-2xl border border-line bg-panel p-0 text-slate-200">
    <form method="post" enctype="multipart/form-data" action="/system-apps/default/replace" class="p-6" data-confirm="Esto borra TODO el contenido actual de la interfaz por defecto y lo reemplaza con el ZIP subido. ¿Continuar?">
        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
        <div class="mb-6 flex items-start justify-between gap-4">
            <div><h3 class="text-lg font-semibold text-white">Reemplazar interfaz por defecto</h3><p class="mt-1 text-sm text-slate-500">Borra todo lo que hay en <code>public/sistema/app</code> y sube el contenido del ZIP en su lugar.</p></div>
            <button type="button" data-dialog-close class="text-slate-500">Cerrar</button>
        </div>
        <div class="rounded-xl border border-red-500/20 bg-red-500/5 p-4 text-sm text-red-200">
            Esto elimina por completo la version actual antes de publicar la nueva. Si el ZIP no tiene un <code>index.html</code> valido, se restaura automaticamente la version anterior.
        </div>
        <label class="mt-4 block">
            <span class="label">Build compilado (.zip)</span>
            <input class="input" type="file" name="file" accept=".zip,application/zip" required>
            <span class="mt-2 block text-xs text-slate-500">Comprime el contenido de <code>dist/</code> (los archivos, no la carpeta) en un ZIP. Maximo <?= e((string) $maxUploadMb) ?> MB.</span>
        </label>
        <button class="btn-danger mt-6 w-full" type="submit">Borrar y reemplazar</button>
    </form>
</dialog>
