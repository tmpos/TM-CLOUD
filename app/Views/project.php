<?php use App\Core\Csrf; ?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div><a href="/dashboard" class="text-sm text-slate-500 hover:text-brand">&lt;- Projects</a><h2 class="mt-2 flex items-center gap-3 text-2xl font-bold text-white"><?= e($project['name']) ?><?php if (($project['status'] ?? '') === 'blocked'): ?><span class="rounded-full bg-rose-500/15 px-2.5 py-1 text-xs font-semibold text-rose-300">Blocked</span><?php endif; ?></h2><p class="mt-1 text-sm text-slate-500"><?= e($project['description'] ?: 'No description') ?></p><?php if (($project['status'] ?? '') === 'blocked' && !empty($project['blocked_reason'])): ?><p class="mt-1 text-xs text-rose-300">Reason: <?= e($project['blocked_reason']) ?></p><?php endif; ?></div>
    <div class="flex items-center gap-2"><a href="/projects/<?= e($project['uid']) ?>/storefront" class="btn-secondary">Tienda web</a><a href="/sistema/<?= e(rawurlencode($project['slug'])) ?>" class="btn-secondary" target="_blank" rel="noopener">Abrir sistema</a><a href="/projects/<?= e($project['uid']) ?>/tables/import" class="btn-secondary">Import tables</a><button data-dialog-open="#new-table-dialog" class="btn-primary">New table</button>
    <?php if (($project['status'] ?? '') === 'blocked'): ?>
    <form method="post" action="/projects/<?= e($project['uid']) ?>/unblock" class="inline"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="btn-secondary">Desbloquear proyecto</button></form>
    <?php else: ?>
    <button type="button" data-dialog-open="#block-project-dialog" class="btn-secondary">Bloquear proyecto</button>
    <?php endif; ?>
    <form method="post" action="/projects/<?= e($project['uid']) ?>/delete" data-confirm="Move this project to the trash? You can restore it later from Papelera." class="inline"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="btn-danger">Delete project</button></form></div>
</div>

<dialog id="block-project-dialog" class="w-full max-w-md rounded-2xl border border-line bg-panel p-0 text-slate-200"><form class="p-6" method="post" action="/projects/<?= e($project['uid']) ?>/block"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><div class="mb-5 flex justify-between"><h3 class="text-lg font-semibold text-white">Bloquear proyecto</h3><button type="button" data-dialog-close>Close</button></div><label><span class="label">Reason</span><textarea class="input" name="reason" rows="3" placeholder="e.g. Payment overdue"></textarea></label><p class="mt-2 text-xs text-slate-500">API access and system login will be blocked until the project is unblocked.</p><button class="btn-danger mt-5 w-full">Bloquear proyecto</button></form></dialog>
<nav class="mb-6 flex gap-6 overflow-x-auto border-b border-line">
<?php foreach (['tables'=>'Tables','database'=>'MySQL Sync','sql'=>'SQL Editor','settings'=>'Configuracion','backups'=>'Backups','storage'=>'Storage','webhooks'=>'Webhooks','licenses'=>'Licenses','logs'=>'Logs','diagram'=>'Diagram','functions'=>'Functions','migrations'=>'Migrations','metrics'=>'Metrics'] as $key=>$label): ?>
    <a class="tab <?= $tab === $key ? 'active' : '' ?>" href="?tab=<?= e($key) ?>"><?= e($label) ?></a>
<?php endforeach; ?>
</nav>

<?php if ($tab === 'tables'): ?>
<?php if ($tables): ?>
<div class="mb-3 flex flex-wrap items-center justify-between gap-2">
<input id="table-filter" class="input max-w-xs" type="text" placeholder="Filter tables..." autocomplete="off">
<div class="flex items-center gap-2"><button type="button" data-dialog-open="#truncate-tables-dialog" class="btn-secondary text-xs">Vaciar tablas</button><form method="post" data-confirm="Delete ALL tables and their data permanently?" action="/projects/<?= e($project['uid']) ?>/tables/delete-all"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="btn-danger text-xs">Delete all tables</button></form></div>
</div>
<?php endif; ?>
<div class="table-wrap"><table class="data-table" id="tables-list"><thead><tr><th>Table</th><th>Records</th><th>Endpoint</th><th></th></tr></thead><tbody>
<?php foreach ($tables as $table): ?><tr data-table-name="<?= e($table['name']) ?>"><td><a class="font-semibold text-brand" href="/projects/<?= e($project['uid']) ?>/tables/<?= e($table['name']) ?>"><?= e($table['name']) ?></a></td><td><?= number_format($table['count']) ?></td><td class="font-mono text-xs">/api/<?= e($project['uid']) ?>/<?= e($table['name']) ?></td><td><a class="btn-secondary" href="/projects/<?= e($project['uid']) ?>/tables/<?= e($table['name']) ?>">Open</a></td></tr><?php endforeach; ?>
<?php if (!$tables): ?><tr><td colspan="4" class="py-12 text-center">No user tables yet.</td></tr><?php endif; ?>
</tbody></table></div>
<script>document.getElementById('table-filter')?.addEventListener('input', function(){const q=this.value.toLowerCase();document.querySelectorAll('#tables-list tbody tr[data-table-name]').forEach(r=>r.style.display=r.dataset.tableName.toLowerCase().includes(q)?'':'none')})</script>
<?php elseif ($tab === 'settings'): ?>
<?php $tmCloudConfig = "URL API del proyecto: {$projectApiUrl}\nPublic Key: {$project['public_key']}\nSecret Key: {$project['secret_key']}"; ?>
<form class="card mb-5 max-w-3xl p-6" method="post" action="/projects/<?= e($project['uid']) ?>/system-app">
    <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h3 class="font-semibold text-white">Ubicacion del sistema</h3>
            <p class="mt-1 text-sm text-slate-500">Seleccione la carpeta de interfaz que abrira este proyecto.</p>
        </div>
        <a href="/system-apps" class="btn-secondary">Administrar carpetas</a>
    </div>
    <label class="mt-5 block">
        <span class="label">Sistema web asignado</span>
        <select class="input" name="system_app" required>
            <?php foreach ($systemApps as $systemApp): ?>
                <option value="<?= e($systemApp['slug']) ?>" <?= ($project['system_app'] ?? 'default') === $systemApp['slug'] ? 'selected' : '' ?>>
                    <?= e($systemApp['name']) ?> — <?= e($systemApp['url']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <div class="mt-5 flex flex-wrap items-center gap-3">
        <button class="btn-primary" type="submit">Guardar ubicacion</button>
        <a class="btn-secondary" href="/sistema/<?= e(rawurlencode($project['slug'])) ?>" target="_blank" rel="noopener">Probar sistema</a>
    </div>
</form>
<div class="card max-w-3xl p-6"><h3 class="font-semibold text-white">Project API keys</h3><p class="mt-1 text-sm text-slate-500">Public keys can read private tables. Secret keys can write and perform destructive actions.</p>
<div class="mt-5 rounded-xl border border-brand/20 bg-brand/5 p-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div><h4 class="text-sm font-semibold text-white">TM Cloud configuration</h4><p class="mt-1 text-xs text-slate-500">Paste these values into SistemaApi &gt; Configuration &gt; TM Cloud.</p></div>
        <button type="button" data-copy="<?= e($tmCloudConfig) ?>" class="btn-primary">Copy configuration</button>
    </div>
    <pre class="mt-4 overflow-x-auto whitespace-pre-wrap rounded-lg bg-black/20 p-4 font-mono text-xs leading-6 text-slate-300"><?= e($tmCloudConfig) ?></pre>
</div>
<label class="mt-5 block"><span class="label">Project API URL</span><div class="flex gap-2"><input class="input font-mono text-xs" readonly value="<?= e($projectApiUrl) ?>"><button type="button" data-copy="<?= e($projectApiUrl) ?>" class="btn-secondary">Copy</button></div></label>
<?php foreach (['public_key'=>'Public key','secret_key'=>'Secret key'] as $field=>$label): ?><label class="mt-5 block"><span class="label"><?= e($label) ?></span><div class="flex gap-2"><input id="<?= e($field) ?>" class="input font-mono text-xs" type="password" readonly value="<?= e($project[$field]) ?>"><button type="button" data-secret-toggle="#<?= e($field) ?>" class="btn-secondary">Show</button><button type="button" data-copy="<?= e($project[$field]) ?>" class="btn-secondary">Copy</button></div></label><?php endforeach; ?>
<div class="mt-6 flex flex-wrap gap-3"><form method="post" action="/projects/<?= e($project['uid']) ?>/keys" data-confirm="Existing integrations will stop working. Regenerate both API keys?"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="btn-danger">Regenerate keys</button></form></div></div>
<?php elseif ($tab === 'database'): ?>
<?php require __DIR__ . '/database-sync.php'; ?>
<?php elseif ($tab === 'sql'): ?>
<?php require __DIR__ . '/sql-editor.php'; ?>
<?php elseif ($tab === 'backups'): ?>
<div class="mb-4 flex justify-end"><form method="post" action="/projects/<?= e($project['uid']) ?>/backups"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="btn-primary">Create backup</button></form></div>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Created</th><th>Size</th><th>UID</th><th>Off-site</th><th>Actions</th></tr></thead><tbody><?php foreach ($backups as $backup): ?><tr><td><?= e($backup['created_at']) ?></td><td><?= number_format($backup['size']/1024,1) ?> KB</td><td class="font-mono text-xs"><?= e($backup['uid']) ?></td><td><?php if (!empty($backup['remote_path'])): ?><span class="inline-block rounded-full bg-emerald-500/15 px-2 py-0.5 text-xs font-semibold text-emerald-300" title="Mirrored to <?= e((string) $backup['remote_path']) ?>">Mirrored</span><?php else: ?><span class="text-xs text-slate-600">&mdash;</span><?php endif; ?></td><td class="flex gap-2"><a class="btn-secondary" href="/projects/<?= e($project['uid']) ?>/backups/<?= e($backup['uid']) ?>/download">Download</a><form method="post" data-confirm="Restore this backup? A safety backup will be created first." action="/projects/<?= e($project['uid']) ?>/backups/<?= e($backup['uid']) ?>/restore"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="btn-danger">Restore</button></form><form method="post" data-confirm="Delete this backup file?" action="/projects/<?= e($project['uid']) ?>/backups/<?= e($backup['uid']) ?>/delete"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="btn-danger">Delete</button></form></td></tr><?php endforeach; ?></tbody></table></div>
<?php elseif ($tab === 'storage'): ?>
<?php
$storageUploadEndpoint = $projectApiUrl . '/storage/upload';
$storageCurl = implode("\n", [
    'curl -X POST "' . $storageUploadEndpoint . '" \\',
    '  -H "Authorization: Bearer YOUR_SECRET_KEY" \\',
    '  -F "file=@documento.pdf" \\',
    '  -F "directory=documentos/clientes"',
]);
?>
<div class="mb-5 grid gap-5 xl:grid-cols-2">
    <form class="card p-6" method="post" enctype="multipart/form-data" action="/projects/<?= e($project['uid']) ?>/storage">
        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
        <div>
            <h3 class="font-semibold text-white">Subir un archivo</h3>
            <p class="mt-1 text-sm text-slate-500">El archivo quedará almacenado de forma segura dentro de este proyecto.</p>
        </div>
        <label class="mt-5 block">
            <span class="label">Archivo</span>
            <input class="input" type="file" name="file" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.txt,.csv,.json,.zip,.xlsx,.docx" required>
            <span class="mt-2 block text-xs text-slate-500">Permitidos: imágenes, PDF, TXT, CSV, JSON, ZIP, XLSX y DOCX.</span>
        </label>
        <label class="mt-4 block">
            <span class="label">Carpeta (opcional)</span>
            <input class="input font-mono text-sm" type="text" name="directory" placeholder="productos/imagenes" autocomplete="off">
            <span class="mt-2 block text-xs text-slate-500">Puede usar subcarpetas separadas por una barra. Si la deja vacía, se guardará en la raíz.</span>
        </label>
        <button class="btn-primary mt-5" type="submit">Subir archivo</button>
    </form>

    <div class="card p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="font-semibold text-white">Endpoint de subida</h3>
                <p class="mt-1 text-sm text-slate-500">Use la clave secreta del proyecto y envíe el archivo como multipart/form-data.</p>
            </div>
            <span class="rounded bg-amber-500/15 px-2 py-1 text-xs font-bold text-amber-400">POST</span>
        </div>
        <div class="mt-5 flex gap-2">
            <input class="input min-w-0 font-mono text-xs" readonly value="<?= e($storageUploadEndpoint) ?>">
            <button type="button" data-copy="<?= e($storageUploadEndpoint) ?>" class="btn-secondary">Copiar</button>
        </div>
        <div class="mt-4 rounded-lg bg-black/30 p-4">
            <pre class="overflow-x-auto whitespace-pre-wrap font-mono text-xs leading-6 text-slate-300"><?= e($storageCurl) ?></pre>
        </div>
        <button type="button" data-copy="<?= e($storageCurl) ?>" class="btn-secondary mt-3">Copiar ejemplo</button>
        <p class="mt-4 text-xs text-slate-500"><strong class="text-slate-400">Campos:</strong> <code>file</code> es obligatorio y <code>directory</code> es opcional. La respuesta incluye UID, nombre, tipo, tamaño, URL y carpeta.</p>
    </div>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Nombre</th><th>Carpeta</th><th>Tipo</th><th>Tamaño</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($files as $file): ?>
            <tr>
                <td><?= e($file['original_name']) ?></td>
                <td class="font-mono text-xs"><?= e($file['directory'] ?? '/') ?></td>
                <td><?= e($file['mime_type']) ?></td>
                <td><?= number_format($file['size']/1024,1) ?> KB</td>
                <td class="flex gap-2">
                    <button type="button" data-copy="<?= e($file['url']) ?>" class="btn-secondary">Copiar URL</button>
                    <form method="post" data-confirm="Delete this file?" action="/projects/<?= e($project['uid']) ?>/storage/<?= e($file['uid']) ?>/delete">
                        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                        <button class="btn-danger">Eliminar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$files): ?><tr><td colspan="5" class="py-12 text-center">Todavía no hay archivos en este proyecto.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php elseif ($tab === 'webhooks'): ?>
<form class="card mb-5 grid gap-4 p-5 md:grid-cols-[1fr_2fr_auto]" method="post" action="/projects/<?= e($project['uid']) ?>/webhooks"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><label><span class="label">Event</span><select class="input" name="event"><?php foreach (['record.created','record.updated','record.deleted','table.created','table.updated','table.truncated','table.deleted'] as $event): ?><option><?= e($event) ?></option><?php endforeach; ?></select></label><label><span class="label">URL</span><input class="input" type="url" name="url" required placeholder="https://example.com/webhook"></label><button class="btn-primary self-end">Add webhook</button></form>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Event</th><th>URL</th><th>Status</th></tr></thead><tbody><?php foreach ($webhooks as $hook): ?><tr><td><?= e($hook['event']) ?></td><td><?= e($hook['url']) ?></td><td><?= $hook['is_active'] ? 'Active' : 'Disabled' ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php elseif ($tab === 'licenses'): ?>
<?php require __DIR__ . '/licenses.php'; ?>
<?php elseif ($tab === 'logs'): ?>
<?php require __DIR__ . '/project_logs.php'; ?>
<?php elseif ($tab === 'diagram'): ?>
<?php require __DIR__ . '/schema_diagram.php'; ?>
<?php elseif ($tab === 'functions'): ?>
<?php require __DIR__ . '/project_functions.php'; ?>
<?php elseif ($tab === 'migrations'): ?>
<?php require __DIR__ . '/project_migrations.php'; ?>
<?php elseif ($tab === 'metrics'): ?>
<?php require __DIR__ . '/project_metrics.php'; ?>
<?php endif; ?>

<dialog id="new-table-dialog" class="w-full max-w-md rounded-2xl border border-line bg-panel p-0 text-slate-200"><form class="p-6" method="post" action="/projects/<?= e($project['uid']) ?>/tables"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><div class="mb-5 flex justify-between"><h3 class="text-lg font-semibold text-white">Create table</h3><button type="button" data-dialog-close>Close</button></div><label><span class="label">Table name</span><input class="input" name="name" required pattern="[A-Za-z][A-Za-z0-9_]{0,62}" placeholder="customers"></label><p class="mt-2 text-xs text-slate-500">id, uid, created_at and updated_at are added automatically.</p><button class="btn-primary mt-5 w-full">Create table</button></form></dialog>

<dialog id="truncate-tables-dialog" class="w-full max-w-md rounded-2xl border border-line bg-panel p-0 text-slate-200"><form class="p-6" method="post" data-confirm="Delete all data from the selected tables? IDs will restart from 1." action="/projects/<?= e($project['uid']) ?>/tables/truncate-bulk"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><div class="mb-5 flex justify-between"><h3 class="text-lg font-semibold text-white">Vaciar tablas</h3><button type="button" data-dialog-close>Close</button></div><p class="mb-3 text-xs text-slate-500">Selecciona las tablas a vaciar. Los registros se eliminan y el ID vuelve a empezar desde 1.</p><label class="mb-3 flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" id="truncate-select-all"> Seleccionar todas</label><div class="max-h-64 overflow-y-auto rounded-lg border border-line"><?php foreach ($tables as $table): ?><label class="flex items-center gap-2 border-b border-line/50 px-3 py-2 text-sm text-slate-300 last:border-0"><input type="checkbox" class="truncate-table-checkbox" name="tables[]" value="<?= e($table['name']) ?>"><span class="font-mono"><?= e($table['name']) ?></span><span class="ml-auto text-xs text-slate-500"><?= number_format($table['count']) ?></span></label><?php endforeach; ?><?php if (!$tables): ?><p class="px-3 py-4 text-center text-xs text-slate-600">No user tables yet.</p><?php endif; ?></div><button class="btn-danger mt-5 w-full" <?= $tables ? '' : 'disabled' ?>>Vaciar tablas seleccionadas</button></form></dialog>
<script>document.getElementById('truncate-select-all')?.addEventListener('change', function () {
    var checked = this.checked;
    document.querySelectorAll('.truncate-table-checkbox').forEach(function (cb) { cb.checked = checked; });
});</script>
