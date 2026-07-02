<div class="mb-4 flex justify-between items-center">
  <h3 class="font-semibold text-white">Edge Functions</h3>
  <button data-dialog-open="#fn-dialog" class="btn-primary">New function</button>
</div>
<?php if ($functions): ?>
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
<?php foreach ($functions as $fn): ?>
<article class="card p-4 border border-line">
  <div class="flex items-start justify-between"><div><strong class="text-white"><?= e($fn['name']) ?></strong><?php if ($fn['event']): ?><span class="ml-2 rounded bg-brand/10 px-2 py-0.5 text-xs text-brand"><?= e($fn['event']) ?></span><?php endif; ?></div><span class="text-xs <?= $fn['is_active'] ? 'text-emerald-400' : 'text-slate-600' ?>"><?= $fn['is_active'] ? 'Active' : 'Inactive' ?></span></div>
  <?php if ($fn['description']): ?><p class="mt-2 text-xs text-slate-500"><?= e($fn['description']) ?></p><?php endif; ?>
  <div class="mt-3 flex gap-2">
    <a class="btn-secondary text-xs px-2 py-1" href="/projects/<?= e($project['uid']) ?>/functions/<?= e($fn['uid']) ?>/edit">Edit</a>
    <form method="post" action="/projects/<?= e($project['uid']) ?>/functions/<?= e($fn['uid']) ?>/toggle" class="inline"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="btn-secondary text-xs px-2 py-1"><?= $fn['is_active'] ? 'Deactivate' : 'Activate' ?></button></form>
    <form method="post" data-confirm="Delete this function?" action="/projects/<?= e($project['uid']) ?>/functions/<?= e($fn['uid']) ?>/delete" class="inline"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="btn-danger text-xs px-2 py-1">Del</button></form>
  </div>
</article>
<?php endforeach; ?>
</div>
<?php else: ?>
<div class="card py-16 text-center"><p class="font-semibold text-slate-300">No functions yet.</p><p class="mt-2 text-sm text-slate-500">Create serverless functions that run on database events or API calls.</p></div>
<?php endif; ?>
<dialog id="fn-dialog" class="w-full max-w-2xl rounded-2xl border border-line bg-panel p-0 text-slate-200"><form class="p-6" method="post" action="/projects/<?= e($project['uid']) ?>/functions"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><div class="mb-5 flex justify-between"><h3 class="font-semibold text-white">New function</h3><button type="button" data-dialog-close>Close</button></div>
<div class="space-y-4">
<label><span class="label">Name</span><input class="input" name="name" required placeholder="my_function"></label>
<label><span class="label">Event trigger (optional)</span><select class="input" name="event"><option value="">Manual only</option><option value="record.created">record.created</option><option value="record.updated">record.updated</option><option value="record.deleted">record.deleted</option></select></label>
<label><span class="label">Description</span><input class="input" name="description" placeholder="What does this function do?"></label>
<label><span class="label">PHP code</span><textarea class="input font-mono text-xs" name="code" rows="12" required placeholder="&#x3C;?php&#x0A;// $data contains the event payload&#x0A;// return anything you want&#x0A;return ['processed' => true];"><?php echo htmlentities('<?php

// $data contains the event payload (record, event, project)
// return the result or null

return ["processed" => true];') ?></textarea></label>
</div>
<button class="btn-primary mt-5 w-full">Create function</button></form></dialog>
