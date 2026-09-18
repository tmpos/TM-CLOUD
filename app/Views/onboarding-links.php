<?php use App\Core\Csrf; ?>
<?php if (!empty($newLink)): ?>
<div class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4">
    <p class="mb-2 text-sm font-semibold text-emerald-300">Enlace generado. Copialo ahora y envialo al cliente; no se volvera a mostrar.</p>
    <div class="flex flex-col gap-2 sm:flex-row"><input class="input min-w-0 flex-1 font-mono text-xs" readonly value="<?= e($newLink) ?>" aria-label="Enlace de registro"><button type="button" data-copy="<?= e($newLink) ?>" class="btn-primary shrink-0">Copiar</button></div>
</div>
<?php endif; ?>
<div class="mb-4 flex items-center justify-between gap-3">
    <p class="text-sm text-slate-500">Genera un enlace de un solo uso para que un cliente registre su empresa y reciba su proyecto listo, con su licencia activa.</p>
    <form method="post" action="/onboarding-links"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="btn-primary shrink-0">Generar enlace</button></form>
</div>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Estado</th><th>Empresa</th><th>Creado</th><th>Usado</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($links as $link): ?><tr>
<td><span class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold <?= $link['status'] === 'pending' ? 'bg-amber-500/15 text-amber-300' : ($link['status'] === 'used' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-500/15 text-slate-400') ?>"><?= e($link['status']) ?></span></td>
<td class="text-white"><?php if ($link['project_uid']): ?><a class="font-semibold text-brand" href="/projects/<?= e($link['project_uid']) ?>"><?= e($link['company_name'] ?: $link['project_uid']) ?></a><?php else: ?><span class="text-slate-600">Pendiente</span><?php endif; ?></td>
<td class="text-xs text-slate-500"><?= e($link['created_at']) ?><?php if ($link['created_by']): ?><br><span class="text-slate-600"><?= e($link['created_by']) ?></span><?php endif; ?></td>
<td class="text-xs text-slate-500"><?= $link['used_at'] ? e($link['used_at']) : '-' ?></td>
<td><?php if ($link['status'] === 'pending'): ?><form method="post" data-confirm="Eliminar este enlace?" action="/onboarding-links/<?= e($link['uid']) ?>/delete" class="inline"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="btn-danger text-xs px-2 py-1.5">Eliminar</button></form><?php else: ?><span class="text-slate-600 text-xs">-</span><?php endif; ?></td>
</tr><?php endforeach; ?>
<?php if (!$links): ?><tr><td colspan="5" class="py-12 text-center text-slate-600">No hay enlaces generados. Crea el primero.</td></tr><?php endif; ?>
</tbody></table></div>
