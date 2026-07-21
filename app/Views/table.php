<?php
use App\Core\Csrf;

function formatDateValue(string $value): string
{
    if (is_numeric($value) && strlen($value) >= 13) {
        return date('d/m/Y H:i:s', (int) ($value / 1000));
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $value);
        return $dt ? $dt->format('d/m/Y H:i:s') : $value;
    }
    return $value;
}

$editable = array_values(array_filter($columns, fn($c) => !$c['protected']));
$endpoint = $baseUrl . '/api/' . $project['uid'] . '/' . $table;
$columnNames = array_column($columns, 'name');
$orderBy = in_array($_GET['order_by'] ?? '', $columnNames, true) ? (string) $_GET['order_by'] : 'id';
$orderDir = strtoupper((string) ($_GET['order_dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
$queryUrl = static function (array $changes = []): string {
    $query = array_merge($_GET, $changes);
    foreach ($query as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
        }
    }
    return '?' . e(http_build_query($query));
};
?>
<div class="mb-5 flex flex-wrap items-center justify-between gap-4"><div><a href="/projects/<?= e($project['uid']) ?>" class="text-sm text-slate-500 hover:text-brand">&lt;- <?= e($project['name']) ?></a><h2 class="mt-2 font-mono text-xl font-bold text-white"><?= e($table) ?></h2></div><div class="flex gap-2"><button data-dialog-open="#import-dialog" class="btn-secondary">Import</button><a class="btn-secondary" href="/projects/<?= e($project['uid']) ?>/tables/<?= e($table) ?>/export?format=csv">Export CSV</a><button data-dialog-open="#record-dialog" class="btn-primary">Add record</button></div></div>
<nav class="mb-6 flex gap-6 overflow-x-auto border-b border-line"><?php foreach (['data'=>'Data','images'=>'Imágenes','structure'=>'Structure','api'=>'API docs','settings'=>'Settings'] as $key=>$label): ?><a class="tab <?= $tab === $key ? 'active' : '' ?>" href="?tab=<?= e($key) ?>"><?= e($label) ?></a><?php endforeach; ?></nav>

<?php if ($tab === 'data'): ?>
<form class="mb-4 flex gap-2" method="get">
<?php if (isset($_GET['order_by'])): ?><input type="hidden" name="order_by" value="<?= e($orderBy) ?>"><input type="hidden" name="order_dir" value="<?= e($orderDir) ?>"><?php endif; ?>
<input class="input max-w-md" name="search" value="<?= e($_GET['search'] ?? '') ?>" placeholder="Search all columns..."><button class="btn-secondary">Search</button><a href="?tab=data" class="btn-secondary text-sm" title="Advanced filters">Filtros</a></form>
<?php require __DIR__ . '/advanced_filters.php'; ?>

<form id="bulk-form" method="post" action="/projects/<?= e($project['uid']) ?>/tables/<?= e($table) ?>/records/bulk-delete"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"></form>
<div class="mb-3 flex flex-wrap items-center gap-2">
<label class="flex items-center gap-1.5 text-sm text-slate-400"><input type="checkbox" id="select-all" class="accent-brand"> Select all</label>
<button id="delete-selected" form="bulk-form" class="btn-danger text-xs disabled:opacity-30" disabled>Delete selected</button>
<label class="flex items-center gap-1.5 text-sm text-slate-400" title="Check for changes every 10 seconds"><input type="checkbox" id="auto-sync" class="accent-brand" data-sync-url="/projects/<?= e($project['uid']) ?>/tables/<?= e($table) ?>/records/sync" data-sync-key="<?= e($project['uid']) ?>_<?= e($table) ?>" data-ws-project="<?= e($project['uid']) ?>" data-ws-token="<?= e($project['public_key']) ?>"> Auto-sync</label>
<span class="ml-auto flex gap-2">
<form method="post" data-confirm="Delete every record and reset IDs to 1?" action="/projects/<?= e($project['uid']) ?>/tables/<?= e($table) ?>/actions" class="inline"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="_action" value="truncate"><button class="btn-danger text-xs">Clear table</button></form>
<form method="post" data-confirm="Permanently delete this table and all data?" action="/projects/<?= e($project['uid']) ?>/tables/<?= e($table) ?>/actions" class="inline"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="_action" value="drop"><button class="btn-danger text-xs">Delete table</button></form>
</span>
</div>

<div class="table-wrap"><table class="data-table"><thead><tr><th class="w-10"><input type="checkbox" id="select-all-header" class="accent-brand"></th><th class="w-28"></th><?php foreach ($columns as $column): ?><?php $isSorted = $orderBy === $column['name']; $nextDir = $isSorted && $orderDir === 'ASC' ? 'DESC' : 'ASC'; ?><th aria-sort="<?= $isSorted ? ($orderDir === 'ASC' ? 'ascending' : 'descending') : 'none' ?>"><a class="table-sort <?= $isSorted ? 'active' : '' ?>" href="<?= $queryUrl(['page' => 1, 'order_by' => $column['name'], 'order_dir' => $nextDir]) ?>" title="Sort <?= $nextDir === 'ASC' ? 'ascending' : 'descending' ?>"><?= e($column['name']) ?><span class="table-sort-icon" aria-hidden="true"><?= $isSorted ? ($orderDir === 'ASC' ? '&uarr;' : '&darr;') : '&harr;' ?></span></a></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><input form="bulk-form" type="checkbox" name="uids[]" value="<?= e($row['uid']) ?>" class="row-checkbox accent-brand"></td><td><div class="flex gap-1"><?php if ($table === 'facturas'): ?><a class="btn-primary text-xs px-2 py-1.5" href="/projects/<?= e($project['uid']) ?>/tables/facturas/<?= e($row['uid']) ?>/pdf" target="_blank" rel="noopener" title="Generar PDF profesional con los datos actuales">PDF</a><?php endif; ?><button class="btn-secondary text-xs px-2 py-1.5" data-dialog-open="#edit-<?= e($row['uid']) ?>">Edit</button><form method="post" data-confirm="Delete this record permanently?" action="/projects/<?= e($project['uid']) ?>/tables/<?= e($table) ?>/records/<?= e($row['uid']) ?>" class="inline"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="_action" value="delete"><button class="btn-danger text-xs px-2 py-1.5">Del</button></form></div></td><?php foreach ($columns as $column): ?><?php $raw = (string) ($row[$column['name']] ?? ''); $value = formatDateValue($raw); $image = $uploadedImages[$raw] ?? null; ?><td title="<?= e($raw) ?>"><?php if ($image): ?><a href="<?= e($image['url']) ?>" target="_blank" rel="noopener" class="inline-block"><img src="<?= e($image['url']) ?>" alt="<?= e($image['original_name']) ?>" loading="lazy" class="h-16 w-16 rounded-lg border border-line object-cover"></a><?php else: ?><?= e($value) ?><?php endif; ?></td><?php endforeach; ?></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="<?= count($columns)+2 ?>" class="py-14 text-center text-slate-600">No records found.</td></tr><?php endif; ?>
</tbody></table></div>
<?php $totalPages = max(1, (int) $meta['pages']); $currentPage = (int) $meta['page']; ?>
<div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm">
<span class="text-slate-500"><?= number_format($meta['total']) ?> records</span>
<?php if ($totalPages > 1): ?>
<div class="flex items-center gap-1">
<?php if ($currentPage > 1): ?><a class="btn-secondary px-3 py-1.5 text-xs" href="<?= $queryUrl(['page' => 1]) ?>">&laquo;</a><a class="btn-secondary px-3 py-1.5 text-xs" href="<?= $queryUrl(['page' => $currentPage - 1]) ?>">&lsaquo;</a><?php endif; ?>
<?php $start = max(1, $currentPage - 2); $end = min($totalPages, $currentPage + 2); if ($start > 1): ?><span class="px-2 text-slate-600">...</span><?php endif; ?>
<?php for ($i = $start; $i <= $end; $i++): ?><a class="px-3 py-1.5 rounded-lg text-xs font-semibold <?= $i === $currentPage ? 'bg-brand text-ink' : 'btn-secondary' ?>" href="<?= $queryUrl(['page' => $i]) ?>"><?= $i ?></a><?php endfor; ?>
<?php if ($end < $totalPages): ?><span class="px-2 text-slate-600">...</span><?php endif; ?>
<?php if ($currentPage < $totalPages): ?><a class="btn-secondary px-3 py-1.5 text-xs" href="<?= $queryUrl(['page' => $currentPage + 1]) ?>">&rsaquo;</a><a class="btn-secondary px-3 py-1.5 text-xs" href="<?= $queryUrl(['page' => $totalPages]) ?>">&raquo;</a><?php endif; ?>
</div>
<?php endif; ?>
</div>
<?php foreach ($rows as $row): ?><dialog id="edit-<?= e($row['uid']) ?>" class="w-full max-w-xl rounded-2xl border border-line bg-panel p-0 text-slate-200"><form class="p-6" method="post" action="/projects/<?= e($project['uid']) ?>/tables/<?= e($table) ?>/records/<?= e($row['uid']) ?>"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><div class="mb-5 flex justify-between"><h3 class="font-semibold text-white">Edit record</h3><button type="button" data-dialog-close>Close</button></div><div class="grid gap-4 md:grid-cols-2"><?php foreach ($editable as $column): ?><label><span class="label"><?= e($column['name']) ?></span><input class="input" name="<?= e($column['name']) ?>" value="<?= e(formatDateValue($row[$column['name']] ?? '')) ?>" <?= $column['notnull'] ? 'required' : '' ?>></label><?php endforeach; ?></div><div class="mt-6 flex justify-between"><button class="btn-primary">Save changes</button><button class="btn-danger" name="_action" value="delete" onclick="return confirm('Delete this record permanently?')">Delete</button></div></form></dialog><?php endforeach; ?>
<?php elseif ($tab === 'images'): ?>
<?php if ($tableImages): ?>
<form id="image-bulk-form" method="post" action="/projects/<?= e($project['uid']) ?>/tables/<?= e($table) ?>/images/bulk-delete"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"></form>
<div class="mb-4 flex flex-wrap items-center justify-between gap-3"><div><h3 class="font-semibold text-white">Table images</h3><p class="mt-1 text-sm text-slate-500"><?= count($tableImages) ?> uploaded image(s) associated with <?= e($table) ?>.</p></div><div class="flex items-center gap-3"><label class="text-sm text-slate-400"><input id="select-all-images" type="checkbox" class="accent-brand"> Select all</label><button id="delete-selected-images" form="image-bulk-form" class="btn-danger text-xs" disabled data-confirm="Delete the selected images and clear their record references?">Delete selected</button></div></div>
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
<?php foreach ($tableImages as $item): ?><?php $file = $item['file']; $references = $item['references']; ?>
<article class="card relative overflow-hidden p-0"><label class="absolute left-3 top-3 z-10 grid h-8 w-8 place-items-center rounded-lg bg-black/70"><input form="image-bulk-form" type="checkbox" name="files[]" value="<?= e($file['uid']) ?>" class="image-checkbox accent-brand"></label>
<a href="<?= e($file['url']) ?>" target="_blank" rel="noopener" class="block aspect-video bg-black/20"><img src="<?= e($file['url']) ?>" alt="<?= e($file['original_name']) ?>" loading="lazy" class="h-full w-full object-contain"></a>
<div class="space-y-2 p-4">
<div class="flex items-start justify-between gap-2"><strong class="min-w-0 truncate text-sm text-white" title="<?= e($file['original_name']) ?>"><?= e($file['original_name']) ?></strong><span class="rounded px-2 py-0.5 text-[10px] font-bold <?= $references ? 'bg-emerald-500/15 text-emerald-400' : 'bg-amber-500/15 text-amber-400' ?>"><?= $references ? 'LINKED' : 'UNLINKED' ?></span></div>
<p class="text-xs text-slate-500"><?= number_format(((int) $file['size']) / 1024, 1) ?> KB · <?= e($file['directory'] ?? '/') ?></p>
<?php foreach ($references as $reference): ?><p class="text-xs text-slate-400"><span class="font-mono text-brand"><?= e($reference['column']) ?></span> · <?= e($reference['record_uid']) ?></p><?php endforeach; ?>
<div class="grid grid-cols-2 gap-2"><button data-copy="<?= e($file['url']) ?>" class="btn-secondary text-xs">Copy URL</button><form method="post" action="/projects/<?= e($project['uid']) ?>/tables/<?= e($table) ?>/images/<?= e($file['uid']) ?>/delete" data-confirm="Delete this image and clear its record references?"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="btn-danger w-full text-xs">Delete</button></form></div>
</div></article>
<?php endforeach; ?>
</div>
<script>(()=>{const boxes=[...document.querySelectorAll('.image-checkbox')],all=document.getElementById('select-all-images'),button=document.getElementById('delete-selected-images');const sync=()=>{const selected=boxes.filter(box=>box.checked).length;button.disabled=!selected;all.checked=selected===boxes.length;all.indeterminate=selected>0&&selected<boxes.length};all.addEventListener('change',()=>{boxes.forEach(box=>box.checked=all.checked);sync()});boxes.forEach(box=>box.addEventListener('change',sync));sync()})()</script>
<?php else: ?><div class="card py-16 text-center"><p class="font-semibold text-slate-300">No images associated with this table.</p><p class="mt-2 text-sm text-slate-500">Uploaded images will appear here after a record stores their URL.</p></div><?php endif; ?>
<?php elseif ($tab === 'structure'): ?>
<div class="grid gap-6 xl:grid-cols-[1.5fr_1fr]"><div class="table-wrap"><table class="data-table"><thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Index</th><th></th></tr></thead><tbody><?php foreach ($columns as $column): ?><tr><td class="font-mono"><?= e($column['name']) ?></td><td><?= e($column['type']) ?></td><td><?= $column['notnull'] ? 'Yes' : 'No' ?></td><td><?= $column['unique'] ? 'Unique' : ($column['indexed'] ? 'Indexed' : '-') ?></td><td><?php if (!$column['protected']): ?><form data-confirm="Remove this field and all its data?" method="post" action="/projects/<?= e($project['uid']) ?>/tables/<?= e($table) ?>/fields/<?= e($column['name']) ?>/delete"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="text-xs text-rose-400">Remove</button></form><?php else: ?><span class="text-xs text-slate-600">Protected</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
<form class="card h-fit p-5" method="post" action="/projects/<?= e($project['uid']) ?>/tables/<?= e($table) ?>/fields"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><h3 class="font-semibold text-white">Add field</h3><div class="mt-4 space-y-4"><label><span class="label">Name</span><input class="input" name="name" required pattern="[A-Za-z][A-Za-z0-9_]{0,62}"></label><label><span class="label">Type</span><select class="input" name="type"><?php foreach (['TEXT','INTEGER','REAL','BOOLEAN','DATE','DATETIME','JSON','EMAIL','PHONE','URL','FILE','IMAGE'] as $type): ?><option><?= $type ?></option><?php endforeach; ?></select></label><label><span class="label">Default</span><input class="input" name="default"></label><div class="grid grid-cols-3 gap-2 text-sm text-slate-400"><?php foreach (['required'=>'Required','unique'=>'Unique','indexed'=>'Indexed'] as $name=>$label): ?><label><input type="checkbox" name="<?= $name ?>" value="1"> <?= $label ?></label><?php endforeach; ?></div></div><button class="btn-primary mt-5 w-full">Add field</button></form></div>
<?php elseif ($tab === 'api'): ?>
<div class="grid gap-5 lg:grid-cols-2"><?php foreach ([['GET','List records',$endpoint],['POST','Create record',$endpoint],['GET','Read record',$endpoint.'/{uid}'],['PUT','Update record',$endpoint.'/{uid}'],['DELETE','Delete record',$endpoint.'/{uid}'],['POST','Bulk insert',$endpoint.'/bulk'],['GET','Sync range',$endpoint.'/sync?from=2026-06-01 00:00:00&to=2026-06-06 23:59:59'],['GET','Export',$endpoint.'/export?format=json']] as [$method,$label,$url]): ?><article class="card p-5"><div class="flex items-center justify-between"><div><span class="mr-2 rounded bg-brand/10 px-2 py-1 text-xs font-bold text-brand"><?= $method ?></span><strong class="text-sm text-white"><?= e($label) ?></strong></div><button data-copy="<?= e($url) ?>" class="text-xs text-slate-500">Copy</button></div><code class="mt-4 block overflow-x-auto rounded-lg bg-black/20 p-3 text-xs text-slate-400"><?= e($url) ?></code></article><?php endforeach; ?></div>
<div class="card mt-5 p-5"><h3 class="font-semibold text-white">cURL example</h3><pre class="mt-4 overflow-auto rounded-xl bg-black/30 p-4 text-xs text-slate-300"><code>curl "<?= e($endpoint) ?>?page=1&amp;limit=20" \
  -H "Authorization: Bearer YOUR_API_KEY"</code></pre></div>
<?php elseif ($tab === 'settings'): ?>
<div class="grid gap-6 lg:grid-cols-2"><form class="card p-6" method="post" action="/projects/<?= e($project['uid']) ?>/tables/<?= e($table) ?>/actions"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="_action" value="access"><h3 class="font-semibold text-white">API access</h3><p class="mt-1 text-sm text-slate-500">Controls anonymous, public-key and secret-key requests.</p><select class="input mt-5" name="access_mode"><?php foreach (['public_read'=>'Public read','private'=>'Public key read / secret write','secret_only'=>'Secret key only','blocked'=>'Blocked'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= $accessMode===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select><button class="btn-primary mt-4">Save access</button></form>
<div class="card border-rose-500/20 p-6"><h3 class="font-semibold text-rose-300">Danger zone</h3><div class="mt-5 flex flex-wrap gap-3"><form method="post" data-confirm="Delete every record and reset IDs to 1?" action="/projects/<?= e($project['uid']) ?>/tables/<?= e($table) ?>/actions"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="_action" value="truncate"><button class="btn-danger">Empty table</button></form><form method="post" data-confirm="Permanently delete this table and all data?" action="/projects/<?= e($project['uid']) ?>/tables/<?= e($table) ?>/actions"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="_action" value="drop"><button class="btn-danger">Delete table</button></form></div></div></div>
<?php endif; ?>

<dialog id="record-dialog" class="w-full max-w-xl rounded-2xl border border-line bg-panel p-0 text-slate-200"><form class="p-6" method="post" action="/projects/<?= e($project['uid']) ?>/tables/<?= e($table) ?>/records"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><div class="mb-5 flex justify-between"><h3 class="font-semibold text-white">New record</h3><button type="button" data-dialog-close>Close</button></div><div class="grid gap-4 md:grid-cols-2"><?php foreach ($editable as $column): ?><label><span class="label"><?= e($column['name']) ?></span><input class="input" name="<?= e($column['name']) ?>" <?= $column['notnull'] ? 'required' : '' ?>></label><?php endforeach; ?></div><button class="btn-primary mt-6">Create record</button></form></dialog>
<dialog id="import-dialog" class="w-full max-w-md rounded-2xl border border-line bg-panel p-0 text-slate-200"><form class="p-6" enctype="multipart/form-data" method="post" action="/projects/<?= e($project['uid']) ?>/tables/<?= e($table) ?>/import"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><div class="mb-5 flex justify-between"><h3 class="font-semibold text-white">Import records</h3><button type="button" data-dialog-close>Close</button></div><input class="input" type="file" name="file" accept=".json,.csv" required><p class="mt-2 text-xs text-slate-500">JSON array or CSV with a header row. Maximum 1,000 records.</p><button class="btn-primary mt-5 w-full">Import</button></form></dialog>
