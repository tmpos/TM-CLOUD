<?php use App\Core\Csrf; ?>
<section class="grid gap-4 md:grid-cols-3">
    <?php foreach ([['Projects',$projectCount,'Active workspaces'],['Tables',$tableCount,'User-defined tables'],['Records',$recordCount,'Across all projects']] as [$label,$value,$hint]): ?>
    <article class="card p-5"><p class="text-sm text-slate-500"><?= e($label) ?></p><strong class="mt-2 block text-3xl text-white"><?= number_format($value) ?></strong><p class="mt-1 text-xs text-slate-600"><?= e($hint) ?></p></article>
    <?php endforeach; ?>
</section>

<section class="mt-8 grid gap-6 xl:grid-cols-[1.5fr_1fr]">
    <div id="projects" class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-line p-5"><div><h2 class="font-semibold text-white">Projects</h2><p class="mt-1 text-sm text-slate-500">Independent SQLite databases and API keys.</p></div><button data-dialog-open="#new-project-dialog" class="btn-primary">Create</button></div>
        <?php if (!$projects): ?><div class="p-12 text-center text-sm text-slate-500">No projects yet. Create the first workspace.</div><?php endif; ?>
        <?php foreach ($projects as $project): ?>
        <a href="/projects/<?= e($project['uid']) ?>" class="flex items-center justify-between border-b border-line/70 p-5 transition hover:bg-white/[.02]">
            <div><strong class="text-white"><?= e($project['name']) ?></strong><p class="mt-1 text-xs text-slate-500"><?= e($project['description'] ?: $project['slug']) ?></p></div>
            <span class="rounded-full bg-emerald-500/10 px-2.5 py-1 text-xs text-emerald-300"><?= e($project['status']) ?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <div id="activity" class="card">
        <div class="border-b border-line p-5"><h2 class="font-semibold text-white">Recent activity</h2></div>
        <div class="divide-y divide-line/70">
        <?php foreach ($logs as $log): ?><div class="p-4"><p class="text-sm text-slate-300"><?= e($log['action']) ?></p><p class="mt-1 truncate text-xs text-slate-600"><?= e(($log['table_name'] ?: 'system') . ' | ' . $log['created_at']) ?></p></div><?php endforeach; ?>
        <?php if (!$logs): ?><p class="p-8 text-center text-sm text-slate-600">No activity recorded.</p><?php endif; ?>
        </div>
    </div>
</section>

<dialog id="new-project-dialog" class="w-full max-w-lg rounded-2xl border border-line bg-panel p-0 text-slate-200">
    <form method="post" action="/projects" class="p-6">
        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
        <div class="mb-6 flex justify-between"><div><h2 class="text-lg font-semibold text-white">New project</h2><p class="text-sm text-slate-500">Creates an isolated SQLite database.</p></div><button type="button" data-dialog-close class="text-slate-500">Close</button></div>
        <div class="space-y-4">
            <label><span class="label">Name</span><input class="input" name="name" required maxlength="100"></label>
            <label><span class="label">Slug (optional)</span><input class="input" name="slug" pattern="[A-Za-z0-9_-]+"></label>
            <label><span class="label">Description</span><textarea class="input" name="description" rows="3"></textarea></label>
        </div>
        <button class="btn-primary mt-6 w-full">Create project</button>
    </form>
</dialog>
