<?php use App\Core\Csrf; ?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div><a href="/dashboard" class="text-sm text-slate-500 hover:text-brand">&lt;- Projects</a><h2 class="mt-2 text-2xl font-bold text-white"><?= e($project['name']) ?></h2><p class="mt-1 text-sm text-slate-500"><?= e($project['description'] ?: 'No description') ?></p></div>
    <div class="flex items-center gap-2"><button data-dialog-open="#new-table-dialog" class="btn-primary">New table</button><form method="post" action="/projects/<?= e($project['uid']) ?>/delete" data-confirm="Permanently delete this project, database, uploads and backups?" class="inline"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="btn-danger">Delete project</button></form></div>
</div>
<nav class="mb-6 flex gap-6 overflow-x-auto border-b border-line">
<?php foreach (['tables'=>'Tables','database'=>'MySQL Sync','sql'=>'SQL Editor','settings'=>'API keys','backups'=>'Backups','storage'=>'Storage','webhooks'=>'Webhooks','licenses'=>'Licenses','logs'=>'Logs'] as $key=>$label): ?>
    <a class="tab <?= $tab === $key ? 'active' : '' ?>" href="?tab=<?= e($key) ?>"><?= e($label) ?></a>
<?php endforeach; ?>
</nav>

<?php if ($tab === 'tables'): ?>
<?php if ($tables): ?>
<div class="mb-3 flex flex-wrap items-center justify-between gap-2">
<input id="table-filter" class="input max-w-xs" type="text" placeholder="Filter tables..." autocomplete="off">
<form method="post" data-confirm="Delete ALL tables and their data permanently?" action="/projects/<?= e($project['uid']) ?>/tables/delete-all"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="btn-danger text-xs">Delete all tables</button></form>
</div>
<?php endif; ?>
<div class="table-wrap"><table class="data-table" id="tables-list"><thead><tr><th>Table</th><th>Records</th><th>Endpoint</th><th></th></tr></thead><tbody>
<?php foreach ($tables as $table): ?><tr data-table-name="<?= e($table['name']) ?>"><td><a class="font-semibold text-brand" href="/projects/<?= e($project['uid']) ?>/tables/<?= e($table['name']) ?>"><?= e($table['name']) ?></a></td><td><?= number_format($table['count']) ?></td><td class="font-mono text-xs">/api/<?= e($project['uid']) ?>/<?= e($table['name']) ?></td><td><a class="btn-secondary" href="/projects/<?= e($project['uid']) ?>/tables/<?= e($table['name']) ?>">Open</a></td></tr><?php endforeach; ?>
<?php if (!$tables): ?><tr><td colspan="4" class="py-12 text-center">No user tables yet.</td></tr><?php endif; ?>
</tbody></table></div>
<script>document.getElementById('table-filter')?.addEventListener('input', function(){const q=this.value.toLowerCase();document.querySelectorAll('#tables-list tbody tr[data-table-name]').forEach(r=>r.style.display=r.dataset.tableName.toLowerCase().includes(q)?'':'none')})</script>
<?php elseif ($tab === 'settings'): ?>
<?php $tmCloudConfig = "URL API del proyecto: {$projectApiUrl}\nPublic Key: {$project['public_key']}\nSecret Key: {$project['secret_key']}"; ?>
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
<div class="table-wrap"><table class="data-table"><thead><tr><th>Created</th><th>Size</th><th>UID</th><th>Actions</th></tr></thead><tbody><?php foreach ($backups as $backup): ?><tr><td><?= e($backup['created_at']) ?></td><td><?= number_format($backup['size']/1024,1) ?> KB</td><td class="font-mono text-xs"><?= e($backup['uid']) ?></td><td class="flex gap-2"><a class="btn-secondary" href="/projects/<?= e($project['uid']) ?>/backups/<?= e($backup['uid']) ?>/download">Download</a><form method="post" data-confirm="Restore this backup? A safety backup will be created first." action="/projects/<?= e($project['uid']) ?>/backups/<?= e($backup['uid']) ?>/restore"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="btn-danger">Restore</button></form><form method="post" data-confirm="Delete this backup file?" action="/projects/<?= e($project['uid']) ?>/backups/<?= e($backup['uid']) ?>/delete"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="btn-danger">Delete</button></form></td></tr><?php endforeach; ?></tbody></table></div>
<?php elseif ($tab === 'storage'): ?>
<form class="card mb-5 flex flex-wrap items-end gap-3 p-5" method="post" enctype="multipart/form-data" action="/projects/<?= e($project['uid']) ?>/storage"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><label class="min-w-64 flex-1"><span class="label">Upload file</span><input class="input" type="file" name="file" required></label><button class="btn-primary">Upload</button></form>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Name</th><th>Type</th><th>Size</th><th>Actions</th></tr></thead><tbody><?php foreach ($files as $file): ?><tr><td><?= e($file['original_name']) ?></td><td><?= e($file['mime_type']) ?></td><td><?= number_format($file['size']/1024,1) ?> KB</td><td class="flex gap-2"><button data-copy="<?= e($file['url']) ?>" class="btn-secondary">Copy URL</button><form method="post" data-confirm="Delete this file?" action="/projects/<?= e($project['uid']) ?>/storage/<?= e($file['uid']) ?>/delete"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="btn-danger">Delete</button></form></td></tr><?php endforeach; ?></tbody></table></div>
<?php elseif ($tab === 'webhooks'): ?>
<form class="card mb-5 grid gap-4 p-5 md:grid-cols-[1fr_2fr_auto]" method="post" action="/projects/<?= e($project['uid']) ?>/webhooks"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><label><span class="label">Event</span><select class="input" name="event"><?php foreach (['record.created','record.updated','record.deleted','table.created','table.updated','table.truncated','table.deleted'] as $event): ?><option><?= e($event) ?></option><?php endforeach; ?></select></label><label><span class="label">URL</span><input class="input" type="url" name="url" required placeholder="https://example.com/webhook"></label><button class="btn-primary self-end">Add webhook</button></form>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Event</th><th>URL</th><th>Status</th></tr></thead><tbody><?php foreach ($webhooks as $hook): ?><tr><td><?= e($hook['event']) ?></td><td><?= e($hook['url']) ?></td><td><?= $hook['is_active'] ? 'Active' : 'Disabled' ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php elseif ($tab === 'licenses'): ?>
<?php require __DIR__ . '/licenses.php'; ?>
<?php elseif ($tab === 'logs'): ?>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Date</th><th>Action</th><th>Table</th><th>Record</th><th>IP</th></tr></thead><tbody><?php foreach ($logs as $log): ?><tr><td><?= e($log['created_at']) ?></td><td><?= e($log['action']) ?></td><td><?= e($log['table_name']) ?></td><td class="font-mono text-xs"><?= e($log['record_uid']) ?></td><td><?= e($log['ip_address']) ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php endif; ?>

<dialog id="new-table-dialog" class="w-full max-w-md rounded-2xl border border-line bg-panel p-0 text-slate-200"><form class="p-6" method="post" action="/projects/<?= e($project['uid']) ?>/tables"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><div class="mb-5 flex justify-between"><h3 class="text-lg font-semibold text-white">Create table</h3><button type="button" data-dialog-close>Close</button></div><label><span class="label">Table name</span><input class="input" name="name" required pattern="[A-Za-z][A-Za-z0-9_]{0,62}" placeholder="customers"></label><p class="mt-2 text-xs text-slate-500">id, uid, created_at and updated_at are added automatically.</p><button class="btn-primary mt-5 w-full">Create table</button></form></dialog>
