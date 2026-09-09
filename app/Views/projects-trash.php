<?php use App\Core\Csrf; ?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-4"><div><h2 class="text-xl font-bold text-white">Papelera</h2><p class="mt-1 text-sm text-slate-500">Archived projects. Restore them or permanently delete their data.</p></div></div>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Project</th><th>Slug</th><th>Archived at</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($projects as $project): ?><tr><td class="text-white"><?= e($project['name']) ?></td><td class="font-mono text-xs text-slate-400"><?= e($project['slug']) ?></td><td class="text-xs text-slate-500"><?= e($project['archived_at'] ?? '') ?></td><td><div class="flex gap-1">
<form method="post" action="/projects/<?= e($project['uid']) ?>/restore" class="inline"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="btn-secondary text-xs px-2 py-1.5">Restore</button></form>
<form method="post" action="/projects/<?= e($project['uid']) ?>/purge" data-confirm="Permanently delete this project, database, uploads and backups? This cannot be undone." class="inline"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="btn-danger text-xs px-2 py-1.5">Delete permanently</button></form>
</div></td></tr><?php endforeach; ?>
<?php if (!$projects): ?><tr><td colspan="4" class="py-12 text-center text-slate-600">Trash is empty.</td></tr><?php endif; ?>
</tbody></table></div>
