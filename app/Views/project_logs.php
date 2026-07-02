<div class="mb-4 flex flex-wrap gap-3 items-end">
  <label><span class="label">Acción</span><select id="flt-action" class="input" onchange="location.search='?tab=logs&action='+this.value"><?php foreach ([''=>'Todas','record.created'=>'Creado','record.updated'=>'Actualizado','record.deleted'=>'Eliminado','table.created'=>'Tabla creada','table.deleted'=>'Tabla eliminada'] as $v=>$l): ?><option value="<?= $v ?>" <?= ($_GET['action']??'')===$v?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select></label>
  <label><span class="label">Tabla</span><input class="input" id="flt-table" placeholder="Filtrar por tabla" value="<?= e($_GET['t']??'') ?>"></label>
  <button onclick="location.search='?tab=logs&action='+document.getElementById('flt-action').value+'&t='+document.getElementById('flt-table').value" class="btn-secondary">Filtrar</button>
  <span class="text-sm text-slate-500"><?= count($logs) ?> eventos</span>
</div>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Fecha</th><th>Acción</th><th>Tabla</th><th>Usuario</th><th>IP</th></tr></thead><tbody>
<?php foreach ($logs as $log): ?>
<tr><td class="text-xs text-slate-400"><?= e($log['created_at']) ?></td><td><span class="rounded bg-brand/10 px-2 py-0.5 text-xs text-brand"><?= e($log['action']) ?></span></td><td><?= e($log['table_name'] ?? '-') ?></td><td class="text-xs"><?= e($log['user_uid'] ? substr($log['user_uid'],0,8).'...' : '-') ?></td><td class="text-xs text-slate-500"><?= e($log['ip_address'] ?? '-') ?></td></tr>
<?php endforeach; ?>
<?php if (!$logs): ?><tr><td colspan="5" class="py-14 text-center text-slate-600">No hay registros de actividad.</td></tr><?php endif; ?>
</tbody></table></div>
