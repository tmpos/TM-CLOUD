<div class="mb-4 flex items-center gap-3">
  <h3 class="font-semibold text-white">Migrations</h3>
  <form method="post" action="/projects/<?= e($project['uid']) ?>/migrations/migrate" class="inline"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="btn-primary text-sm">Run pending</button></form>
  <form method="post" data-confirm="Rollback last batch?" action="/projects/<?= e($project['uid']) ?>/migrations/rollback" class="inline"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="btn-secondary text-sm">Rollback</button></form>
</div>
<?php if ($output): ?>
<div class="mb-4 rounded-xl border border-line bg-black/20 p-4 font-mono text-xs"><?php foreach ($output as $line): ?><div class="<?= str_starts_with($line,'OK') ? 'text-emerald-400' : (str_starts_with($line,'ERROR') ? 'text-rose-400' : 'text-slate-400') ?>"><?= e($line) ?></div><?php endforeach; ?></div>
<?php endif; ?>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Version</th><th>Name</th><th>Status</th><th>Applied</th><th>Batch</th></tr></thead><tbody>
<?php foreach ($migrations as $m): ?>
<tr><td class="font-mono"><?= e($m['version']) ?></td><td><?= e($m['name']) ?></td><td><?php if ($m['applied']): ?><span class="rounded bg-emerald-500/10 px-2 py-0.5 text-xs text-emerald-400">Applied</span><?php else: ?><span class="rounded bg-amber-500/10 px-2 py-0.5 text-xs text-amber-400">Pending</span><?php endif; ?></td><td class="text-xs text-slate-400"><?= e($m['applied_at'] ?? '-') ?></td><td class="text-xs"><?= e($m['batch'] ?? '-') ?></td></tr>
<?php endforeach; ?>
<?php if (!$migrations): ?><tr><td colspan="5" class="py-14 text-center text-slate-600">No migration files found. Create files in <code class="text-brand">storage/migrations/</code></td></tr><?php endif; ?>
</tbody></table></div>
