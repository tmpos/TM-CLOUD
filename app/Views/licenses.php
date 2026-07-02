<?php use App\Core\Csrf; ?>
<div class="mb-4 flex justify-end"><button data-dialog-open="#new-license-dialog" class="btn-primary">New license</button></div>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Name</th><th>System</th><th>License key</th><th>Status</th><th>Type</th><th>Expires</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($licenses as $license): ?><tr><td class="text-white"><?= e($license['nombre'] ?: $license['system_name']) ?></td><td class="text-slate-400 text-xs"><?= e($license['system_name']) ?></td><td class="font-mono text-xs"><?= e($license['license_key']) ?></td>
<td><span class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold <?= $license['status'] === 'active' ? 'bg-emerald-500/15 text-emerald-300' : ($license['status'] === 'blocked' ? 'bg-rose-500/15 text-rose-300' : 'bg-slate-500/15 text-slate-400') ?>"><?= e($license['status']) ?></span></td>
<td class="text-xs"><?= e($license['tipo'] ?: '-') ?></td>
<td><?= $license['expires_at'] ? e($license['expires_at']) : '<span class="text-slate-500">Never</span>' ?></td>
<td><div class="flex flex-wrap gap-1">
<button class="btn-secondary text-xs px-2 py-1.5" data-dialog-open="#edit-license-<?= e($license['uid']) ?>">Edit</button>
<button class="btn-secondary text-xs px-2 py-1.5" data-dialog-open="#creds-license-<?= e($license['uid']) ?>">Keys</button>
<?php if ($license['status'] === 'active'): ?><form method="post" action="/projects/<?= e($project['uid']) ?>/licenses/<?= e($license['uid']) ?>/status" class="inline"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="status" value="blocked"><button class="btn-danger text-xs px-2 py-1.5">Block</button></form><?php endif; ?>
<?php if ($license['status'] === 'blocked'): ?><form method="post" action="/projects/<?= e($project['uid']) ?>/licenses/<?= e($license['uid']) ?>/status" class="inline"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="status" value="active"><button class="btn-secondary text-xs px-2 py-1.5">Unblock</button></form><?php endif; ?>
<?php if ((int)$license['current_uses'] > 0): ?><form method="post" data-confirm="Reset usage counter?" action="/projects/<?= e($project['uid']) ?>/licenses/<?= e($license['uid']) ?>/reset-uses" class="inline"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="btn-secondary text-xs px-2 py-1.5">Reset uses</button></form><?php endif; ?>
<form method="post" data-confirm="Delete this license permanently?" action="/projects/<?= e($project['uid']) ?>/licenses/<?= e($license['uid']) ?>/delete" class="inline"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="btn-danger text-xs px-2 py-1.5">Delete</button></form>
</div></td></tr><?php endforeach; ?>
<?php if (!$licenses): ?><tr><td colspan="7" class="py-12 text-center text-slate-600">No licenses yet. Create the first one.</td></tr><?php endif; ?>
</tbody></table></div>

<?php foreach ($licenses as $license): ?>
<dialog id="edit-license-<?= e($license['uid']) ?>" class="w-full max-w-2xl rounded-2xl border border-line bg-panel p-0 text-slate-200"><div class="max-h-[90vh] overflow-y-auto p-6"><div class="mb-5 flex justify-between"><h3 class="text-lg font-semibold text-white">Edit license</h3><button type="button" data-dialog-close class="text-slate-500">Close</button></div><form method="post" action="/projects/<?= e($project['uid']) ?>/licenses/<?= e($license['uid']) ?>/update"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><div class="grid gap-4 md:grid-cols-2">
<label><span class="label">Name / Company</span><input class="input" name="nombre" value="<?= e($license['nombre'] ?? '') ?>"></label>
<label><span class="label">System name</span><input class="input" name="system_name" required value="<?= e($license['system_name']) ?>"></label>
<label><span class="label">License key</span><input class="input font-mono text-xs" value="<?= e($license['license_key']) ?>" disabled></label>
<label><span class="label">Type</span><input class="input" name="tipo" value="<?= e($license['tipo'] ?? '') ?>" placeholder="e.g. monthly, yearly, trial"></label>
<label><span class="label">Max uses <span class="text-slate-600">(0 = unlimited)</span></span><input class="input" type="number" name="max_uses" value="<?= (int)$license['max_uses'] ?>" min="0"></label>
<label><span class="label">Devices allowed</span><input class="input" name="dispositivos" value="<?= e($license['dispositivos'] ?? '') ?>" placeholder="e.g. 5"></label>
<label><span class="label">Expires at</span><input class="input" type="datetime-local" name="expires_at" value="<?= $license['expires_at'] ? e(substr($license['expires_at'], 0, 16)) : '' ?>"></label>
<label><span class="label">Last payment</span><input class="input" name="ultimopago" value="<?= e($license['ultimopago'] ?? '') ?>"></label>
<label><span class="label">Next payment</span><input class="input" name="proximopago" value="<?= e($license['proximopago'] ?? '') ?>"></label>
<label><span class="label">Price</span><input class="input" name="precio" value="<?= e($license['precio'] ?? '') ?>" placeholder="e.g. 29.99"></label>
<label><span class="label">Person in charge</span><input class="input" name="encargado" value="<?= e($license['encargado'] ?? '') ?>"></label>
<label><span class="label">Phone</span><input class="input" name="telefono" value="<?= e($license['telefono'] ?? '') ?>"></label>
<label><span class="label">Email</span><input class="input" type="email" name="email" value="<?= e($license['email'] ?? '') ?>"></label>
<label><span class="label">Address</span><input class="input" name="direccion" value="<?= e($license['direccion'] ?? '') ?>"></label>
<label><span class="label">Username</span><input class="input" name="usuario" value="<?= e($license['usuario'] ?? '') ?>"></label>
<label><span class="label">DB identifier</span><input class="input" name="identificadordb" value="<?= e($license['identificadordb'] ?? '') ?>"></label>
<label><span class="label">Token</span><input class="input font-mono text-xs" name="token" value="<?= e($license['token'] ?? '') ?>"></label>
<label><span class="label">Link</span><input class="input" name="link" value="<?= e($license['link'] ?? '') ?>"></label>
<label><span class="label">Store / Almacen</span><input class="input" name="almacen" value="<?= e($license['almacen'] ?? '') ?>"></label>
<label><span class="label">Role key</span><input class="input font-mono text-xs" name="role_key" value="<?= e($license['role_key'] ?? '') ?>"></label>
<label class="md:col-span-2"><span class="label">Metadata (JSON)</span><textarea class="input font-mono text-xs" name="metadata" rows="2"><?= e($license['metadata'] ?? '') ?></textarea></label>
</div><button class="btn-primary mt-6 w-full">Save changes</button></form><div class="mt-6 border-t border-line pt-4"><?php $authDevices = $license['dispositivos'] ? (json_decode($license['dispositivos'], true) ?? []) : []; $blockedDevices = $license['equipos_no_autorizados'] ? (json_decode($license['equipos_no_autorizados'], true) ?? []) : []; ?><h4 class="mb-3 text-sm font-semibold text-white">Authorized devices</h4><?php if ($authDevices): ?><div class="mb-4 space-y-1"><?php foreach ($authDevices as $ad): ?><div class="flex items-center justify-between rounded bg-slate-800/50 px-3 py-1.5 text-xs"><span class="font-mono text-slate-300"><?= e($ad) ?></span><form method="post" action="/projects/<?= e($project['uid']) ?>/licenses/<?= e($license['uid']) ?>/block-device" class="inline"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="device_id" value="<?= e($ad) ?>"><button class="btn-danger text-xs px-2 py-0.5">Block</button></form></div><?php endforeach; ?></div><?php else: ?><p class="mb-4 text-xs text-slate-500">No authorized devices.</p><?php endif; ?><h4 class="mb-3 text-sm font-semibold text-white">Unauthorized devices</h4><?php if ($blockedDevices): ?><div class="space-y-1"><?php foreach ($blockedDevices as $bd): ?><div class="flex items-center justify-between rounded bg-slate-800/50 px-3 py-1.5 text-xs"><span class="font-mono text-slate-300"><?= e($bd) ?></span><form method="post" action="/projects/<?= e($project['uid']) ?>/licenses/<?= e($license['uid']) ?>/authorize-device" class="inline"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="device_id" value="<?= e($bd) ?>"><button class="btn-secondary text-xs px-2 py-0.5">Authorize</button></form></div><?php endforeach; ?></div><?php else: ?><p class="text-xs text-slate-500">No unauthorized devices.</p><?php endif; ?></div></div></dialog>
<?php endforeach; ?>

<?php foreach ($licenses as $license): ?>
<dialog id="creds-license-<?= e($license['uid']) ?>" class="w-full max-w-xl rounded-2xl border border-line bg-panel p-0 text-slate-200"><form class="p-6"><div class="mb-5 flex justify-between"><h3 class="text-lg font-semibold text-white">Project credentials</h3><button type="button" data-dialog-close class="text-slate-500">Close</button></div>
<div class="space-y-4 text-sm">
<label><span class="label">API URL</span><div class="flex gap-2"><input class="input font-mono text-xs" readonly value="<?= e($license['project_url'] ?? '') ?>"><button type="button" data-copy="<?= e($license['project_url'] ?? '') ?>" class="btn-secondary shrink-0">Copy</button></div></label>
<label><span class="label">Public Key</span><div class="flex gap-2"><input id="pub-<?= e($license['uid']) ?>" class="input font-mono text-xs" type="password" readonly value="<?= e($license['public_key'] ?? '') ?>"><button type="button" data-secret-toggle="#pub-<?= e($license['uid']) ?>" class="btn-secondary shrink-0">Show</button><button type="button" data-copy="<?= e($license['public_key'] ?? '') ?>" class="btn-secondary shrink-0">Copy</button></div></label>
<label><span class="label">Secret Key</span><div class="flex gap-2"><input id="sec-<?= e($license['uid']) ?>" class="input font-mono text-xs" type="password" readonly value="<?= e($license['secret_key'] ?? '') ?>"><button type="button" data-secret-toggle="#sec-<?= e($license['uid']) ?>" class="btn-secondary shrink-0">Show</button><button type="button" data-copy="<?= e($license['secret_key'] ?? '') ?>" class="btn-secondary shrink-0">Copy</button></div></label>
<label><span class="label">Role Key</span><div class="flex gap-2"><input id="rol-<?= e($license['uid']) ?>" class="input font-mono text-xs" type="password" readonly value="<?= e($license['role_key'] ?? '') ?>"><button type="button" data-secret-toggle="#rol-<?= e($license['uid']) ?>" class="btn-secondary shrink-0">Show</button><button type="button" data-copy="<?= e($license['role_key'] ?? '') ?>" class="btn-secondary shrink-0">Copy</button></div></label>
<label><span class="label">DB identifier</span><div class="flex gap-2"><input class="input font-mono text-xs" readonly value="<?= e($license['identificadordb'] ?? '') ?>"><button type="button" data-copy="<?= e($license['identificadordb'] ?? '') ?>" class="btn-secondary shrink-0">Copy</button></div></label>
<label><span class="label">Token</span><div class="flex gap-2"><input class="input font-mono text-xs" readonly value="<?= e($license['token'] ?? '') ?>"><button type="button" data-copy="<?= e($license['token'] ?? '') ?>" class="btn-secondary shrink-0">Copy</button></div></label>
</div></form></dialog>
<?php endforeach; ?>

<dialog id="new-license-dialog" class="w-full max-w-2xl rounded-2xl border border-line bg-panel p-0 text-slate-200"><form class="max-h-[90vh] overflow-y-auto p-6" method="post" action="/projects/<?= e($project['uid']) ?>/licenses"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><div class="mb-5 flex justify-between"><h3 class="text-lg font-semibold text-white">New license</h3><button type="button" data-dialog-close class="text-slate-500">Close</button></div><div class="grid gap-4 md:grid-cols-2">
<label><span class="label">Name / Company</span><input class="input" name="nombre" placeholder="e.g. Acme Corp"></label>
<label><span class="label">System name</span><input class="input" name="system_name" required placeholder="e.g. SistemaFacturacion"></label>
<label><span class="label">License key <span class="text-slate-600">(leave empty to auto-generate)</span></span><input class="input font-mono text-xs" name="license_key" placeholder="Auto-generated"></label>
<label><span class="label">Type</span><input class="input" name="tipo" placeholder="e.g. monthly, yearly, trial"></label>
<label><span class="label">Max uses <span class="text-slate-600">(0 = unlimited)</span></span><input class="input" type="number" name="max_uses" value="0" min="0"></label>
<label><span class="label">Devices allowed</span><input class="input" name="dispositivos" placeholder="e.g. 5"></label>
<label><span class="label">Expires at</span><input class="input" type="datetime-local" name="expires_at"></label>
<label><span class="label">Last payment</span><input class="input" name="ultimopago"></label>
<label><span class="label">Next payment</span><input class="input" name="proximopago"></label>
<label><span class="label">Price</span><input class="input" name="precio" placeholder="e.g. 29.99"></label>
<label><span class="label">Person in charge</span><input class="input" name="encargado"></label>
<label><span class="label">Phone</span><input class="input" name="telefono"></label>
<label><span class="label">Email</span><input class="input" type="email" name="email"></label>
<label><span class="label">Address</span><input class="input" name="direccion"></label>
<label><span class="label">Username</span><input class="input" name="usuario"></label>
<label><span class="label">DB identifier</span><input class="input" name="identificadordb"></label>
<label><span class="label">Token</span><input class="input font-mono text-xs" name="token"></label>
<label><span class="label">Link</span><input class="input" name="link"></label>
<label><span class="label">Store / Almacen</span><input class="input" name="almacen"></label>
<label><span class="label">Role key</span><input class="input font-mono text-xs" name="role_key"></label>
<label><span class="label">Unauthorized devices</span><input class="input" name="equipos_no_autorizados"></label>
<label class="md:col-span-2"><span class="label">Metadata (JSON)</span><textarea class="input font-mono text-xs" name="metadata" rows="2" placeholder='{"version":"2.0","modules":["inventory","sales"]}'></textarea></label>
</div><button class="btn-primary mt-6 w-full">Create license</button></form></dialog>
