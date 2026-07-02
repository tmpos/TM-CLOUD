<?php use App\Core\Csrf; ?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-4"><div><h2 class="text-xl font-bold text-white">All Backups</h2><p class="mt-1 text-sm text-slate-500">Database snapshots across all projects.</p></div></div>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Project</th><th>Created</th><th>Size</th><th>UID</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($backups as $backup): ?><tr><td><a class="font-semibold text-brand" href="/projects/<?= e($backup['project_uid']) ?>"><?= e($backup['project_name']) ?></a></td><td><?= e($backup['created_at']) ?></td><td><?= number_format((int)$backup['size']/1024, 1) ?> KB</td><td class="font-mono text-xs"><?= e($backup['uid']) ?></td><td><div class="flex gap-1"><a class="btn-secondary text-xs px-2 py-1.5" href="/projects/<?= e($backup['project_uid']) ?>/backups/<?= e($backup['uid']) ?>/download">Download</a></div></td></tr><?php endforeach; ?>
<?php if (!$backups): ?><tr><td colspan="5" class="py-12 text-center text-slate-600">No backups yet.</td></tr><?php endif; ?>
</tbody></table></div>
