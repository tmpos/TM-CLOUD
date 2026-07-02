<?php use App\Core\Csrf; ?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-4"><div><h2 class="text-xl font-bold text-white">All Storage Files</h2><p class="mt-1 text-sm text-slate-500">Uploaded files across all projects.</p></div></div>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Project</th><th>Name</th><th>Type</th><th>Size</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($files as $file): ?><tr><td><a class="font-semibold text-brand" href="/projects/<?= e($file['project_uid']) ?>"><?= e($file['project_name']) ?></a></td><td><?= e($file['original_name']) ?></td><td><?= e($file['mime_type']) ?></td><td><?= number_format((int)$file['size']/1024, 1) ?> KB</td><td><div class="flex gap-1"><button data-copy="<?= e($file['url']) ?>" class="btn-secondary text-xs px-2 py-1.5">Copy URL</button></div></td></tr><?php endforeach; ?>
<?php if (!$files): ?><tr><td colspan="5" class="py-12 text-center text-slate-600">No files uploaded yet.</td></tr><?php endif; ?>
</tbody></table></div>
