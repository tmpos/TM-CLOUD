<?php use App\Core\Csrf; ?>
<p class="mb-4 text-sm text-slate-500">Invita a otras personas al panel y dales acceso solo a las secciones que necesiten. Un usuario "Administrador" siempre tiene acceso total.</p>
<div class="mb-4 flex justify-end"><button data-dialog-open="#invite-user-dialog" class="btn-primary">Invitar usuario</button></div>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Permisos</th><th>Creado</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($users as $user): ?><tr>
<td class="text-white"><?= e($user['name']) ?><?= $user['uid'] === $currentUid ? ' <span class="text-slate-600 text-xs">(tu)</span>' : '' ?></td>
<td class="text-xs text-slate-400"><?= e($user['email']) ?></td>
<td><span class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold <?= $user['role'] === 'admin' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-500/15 text-slate-400' ?>"><?= $user['role'] === 'admin' ? 'Administrador' : 'Limitado' ?></span></td>
<td class="max-w-xs"><?php if ($user['role'] === 'admin'): ?><span class="text-xs text-slate-500">Acceso total</span><?php else: ?><div class="flex flex-wrap gap-1"><?php $granted = json_decode((string) $user['permissions'], true) ?: []; foreach ($granted as $key): ?><span class="rounded-full bg-slate-800 px-2 py-0.5 text-[11px] text-slate-300"><?= e($components[$key] ?? $key) ?></span><?php endforeach; ?><?php if (!$granted): ?><span class="text-xs text-rose-400">Sin permisos</span><?php endif; ?></div><?php endif; ?></td>
<td class="text-xs text-slate-500"><?= e($user['created_at']) ?></td>
<td><div class="flex flex-wrap gap-1">
<button class="btn-secondary text-xs px-2 py-1.5" data-dialog-open="#edit-user-<?= e($user['uid']) ?>">Editar</button>
<button class="btn-secondary text-xs px-2 py-1.5" data-dialog-open="#reset-password-<?= e($user['uid']) ?>">Contrasena</button>
<?php if ($user['uid'] !== $currentUid): ?><form method="post" data-confirm="Eliminar este usuario?" action="/users/<?= e($user['uid']) ?>/delete" class="inline"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="btn-danger text-xs px-2 py-1.5">Eliminar</button></form><?php endif; ?>
</div></td>
</tr><?php endforeach; ?>
<?php if (!$users): ?><tr><td colspan="6" class="py-12 text-center text-slate-600">No hay usuarios.</td></tr><?php endif; ?>
</tbody></table></div>

<?php foreach ($users as $user): ?>
<dialog id="edit-user-<?= e($user['uid']) ?>" class="w-full max-w-lg rounded-2xl border border-line bg-panel p-0 text-slate-200"><form class="p-6" method="post" action="/users/<?= e($user['uid']) ?>/update"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
<div class="mb-5 flex justify-between"><h3 class="text-lg font-semibold text-white">Editar acceso</h3><button type="button" data-dialog-close class="text-slate-500">Close</button></div>
<p class="mb-4 text-sm text-slate-500"><?= e($user['name']) ?> · <?= e($user['email']) ?></p>
<label class="block mb-4"><span class="label">Rol</span><select class="input" name="role" data-role-select="edit-<?= e($user['uid']) ?>"><option value="limited" <?= $user['role'] !== 'admin' ? 'selected' : '' ?>>Limitado (elige secciones)</option><option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Administrador (acceso total)</option></select></label>
<div id="permissions-edit-<?= e($user['uid']) ?>" class="grid grid-cols-2 gap-2" <?= $user['role'] === 'admin' ? 'style="display:none"' : '' ?>>
<?php $granted = json_decode((string) $user['permissions'], true) ?: []; foreach ($components as $key => $label): ?>
<label class="flex items-center gap-2 rounded-lg border border-line/70 px-3 py-2 text-sm"><input type="checkbox" name="permissions[]" value="<?= e($key) ?>" <?= in_array($key, $granted, true) ? 'checked' : '' ?>><?= e($label) ?></label>
<?php endforeach; ?>
</div>
<button class="btn-primary mt-6 w-full">Guardar cambios</button>
</form></dialog>
<dialog id="reset-password-<?= e($user['uid']) ?>" class="w-full max-w-sm rounded-2xl border border-line bg-panel p-0 text-slate-200"><form class="p-6" method="post" action="/users/<?= e($user['uid']) ?>/reset-password"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
<div class="mb-5 flex justify-between"><h3 class="text-lg font-semibold text-white">Nueva contrasena</h3><button type="button" data-dialog-close class="text-slate-500">Close</button></div>
<label class="block"><span class="label">Contrasena</span><input class="input" type="password" name="password" minlength="10" required autocomplete="new-password" placeholder="Minimo 10 caracteres"></label>
<button class="btn-primary mt-6 w-full">Actualizar</button>
</form></dialog>
<?php endforeach; ?>

<dialog id="invite-user-dialog" class="w-full max-w-lg rounded-2xl border border-line bg-panel p-0 text-slate-200"><form class="p-6" method="post" action="/users"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
<div class="mb-5 flex justify-between"><h3 class="text-lg font-semibold text-white">Invitar usuario</h3><button type="button" data-dialog-close class="text-slate-500">Close</button></div>
<div class="space-y-4">
<label class="block"><span class="label">Nombre</span><input class="input" name="name" required maxlength="100"></label>
<label class="block"><span class="label">Correo</span><input class="input" type="email" name="email" required></label>
<label class="block"><span class="label">Contrasena</span><input class="input" type="password" name="password" minlength="10" required autocomplete="new-password" placeholder="Minimo 10 caracteres"></label>
<label class="block"><span class="label">Rol</span><select class="input" name="role" data-role-select="invite"><option value="limited">Limitado (elige secciones)</option><option value="admin">Administrador (acceso total)</option></select></label>
<div id="permissions-invite" class="grid grid-cols-2 gap-2">
<?php foreach ($components as $key => $label): ?><label class="flex items-center gap-2 rounded-lg border border-line/70 px-3 py-2 text-sm"><input type="checkbox" name="permissions[]" value="<?= e($key) ?>"><?= e($label) ?></label><?php endforeach; ?>
</div>
</div>
<button class="btn-primary mt-6 w-full">Crear usuario</button>
</form></dialog>

<script>
document.querySelectorAll('[data-role-select]').forEach(function (select) {
    var target = document.getElementById('permissions-' + select.getAttribute('data-role-select'));
    if (!target) return;
    var sync = function () { target.style.display = select.value === 'admin' ? 'none' : 'grid'; };
    select.addEventListener('change', sync);
    sync();
});
</script>
