<?php use App\Core\Csrf; ?>
<div class="mb-6">
    <a href="/projects/<?= e($project['uid']) ?>?tab=tables" class="text-sm text-slate-500 hover:text-brand">&lt;- <?= e($project['name']) ?></a>
    <h2 class="mt-2 text-2xl font-bold text-white">Import tables</h2>
    <p class="mt-1 text-sm text-slate-500">Copy tables (structure and data) from another project into <?= e($project['name']) ?>.</p>
</div>

<form method="get" action="/projects/<?= e($project['uid']) ?>/tables/import" class="card mb-6 flex flex-wrap items-end gap-4 p-5">
    <label class="min-w-[240px] flex-1">
        <span class="label">Source project</span>
        <select class="input" name="source" onchange="this.form.submit()">
            <option value="">Select a project...</option>
            <?php foreach ($projects as $p): ?>
                <option value="<?= e($p['uid']) ?>" <?= $source && $source['uid'] === $p['uid'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <noscript><button class="btn-secondary">Load tables</button></noscript>
</form>

<?php if ($source): ?>
<?php if (!$sourceTables): ?>
<p class="py-12 text-center text-slate-600">"<?= e($source['name']) ?>" has no user tables.</p>
<?php else: ?>
<form method="post" action="/projects/<?= e($project['uid']) ?>/tables/import">
    <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
    <input type="hidden" name="source_project" value="<?= e($source['uid']) ?>">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" id="select-all-tables"> Select all</label>
        <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" name="with_data" value="1" checked> Include data</label>
    </div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th></th><th>Table</th><th>Records</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($sourceTables as $t): $conflict = in_array($t['name'], $existing, true); ?>
    <tr>
        <td><input type="checkbox" class="import-table-checkbox" name="tables[]" value="<?= e($t['name']) ?>" <?= $conflict ? 'disabled' : '' ?>></td>
        <td class="font-semibold text-white"><?= e($t['name']) ?></td>
        <td><?= number_format($t['count']) ?></td>
        <td><?php if ($conflict): ?><span class="rounded bg-amber-500/10 px-2 py-0.5 text-xs text-amber-400">Already exists</span><?php else: ?><span class="rounded bg-emerald-500/10 px-2 py-0.5 text-xs text-emerald-400">New</span><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <button class="btn-primary mt-5" type="submit">Import selected tables</button>
</form>
<script>document.getElementById('select-all-tables')?.addEventListener('change', function () {
    var checked = this.checked;
    document.querySelectorAll('.import-table-checkbox:not(:disabled)').forEach(function (cb) { cb.checked = checked; });
});</script>
<?php endif; ?>
<?php endif; ?>
