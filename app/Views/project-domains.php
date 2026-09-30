<?php
use App\Core\Csrf;
$byType = array_column($domains, null, 'destination');
$storeDomain = $byType['store']['hostname'] ?? '';
$systemDomain = $byType['system']['hostname'] ?? '';
$workerHealthy = !empty($routing['updated_at']) && time() - (int) $routing['updated_at'] < 120;
?>
<style>.domain-dns td{white-space:normal;overflow-wrap:anywhere;overflow:visible;text-overflow:clip}.domain-dns button{display:block;margin-top:.4rem}</style>
<div class="mb-6">
    <a class="text-brand text-sm" href="/projects/<?= e($project['uid']) ?>">← <?= e($project['name']) ?></a>
    <h2 class="mt-3 text-2xl font-bold text-white">Dominios del proyecto</h2>
    <p class="mt-2 text-slate-400">Tu dominio principal abre la tienda. Añade un subdominio si también quieres una dirección propia para el sistema.</p>
</div>
<?php if (!$ips || !$workerHealthy): ?>
<div class="card mb-5 p-4 text-amber-300">La activación automática del servidor todavía no está disponible. Puedes guardar tus dominios y preparar el DNS; la activación requiere que el servicio de dominios esté conectado.</div>
<?php endif; ?>
<form class="card p-6 mb-6 max-w-3xl space-y-5" method="post" action="/projects/<?= e($project['uid']) ?>/domains">
    <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
    <label class="block"><span class="label">Dominio de la tienda</span><input id="store-domain" name="store_domain" class="input" value="<?= e($storeDomain) ?>" placeholder="eldominio.com" required maxlength="253" autocapitalize="none" spellcheck="false"></label>
    <label class="flex items-center gap-2"><input id="enable-system" name="enable_system" type="checkbox" value="1" <?= $systemDomain ? 'checked' : '' ?>> Añadir subdominio para el sistema</label>
    <label id="system-domain-field" class="block" <?= !$systemDomain ? 'hidden' : '' ?>><span class="label">Subdominio del sistema</span><input id="system-domain" name="system_domain" class="input" value="<?= e($systemDomain) ?>" placeholder="sistema.eldominio.com" maxlength="253" autocapitalize="none" spellcheck="false"><span class="mt-2 block text-xs text-slate-400">Si lo dejas vacío se usará sistema seguido del dominio de la tienda. Se mantiene el inicio de sesión y los permisos del proyecto.</span></label>
    <p class="text-xs text-slate-400">Cambiar una dirección requiere verificarla nuevamente. Para quitar una dirección existente usa Desactivar o Desvincular en su tarjeta. Si necesitas www, puedes usarlo como dominio de la tienda.</p>
    <button class="btn-primary">Guardar dominios</button>
</form>
<?php foreach ($domains as $domain):
    $applied = $workerHealthy && in_array($domain['hostname'], $routing['hosts'] ?? [], true);
    $state = match ($domain['status']) { 'disabled' => 'Desactivado', 'active' => $applied ? 'Dirección activada' : 'Activación pendiente', default => 'DNS pendiente' };
?>
<section class="card p-6 mb-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div><p class="text-sm text-slate-400"><?= $domain['destination'] === 'store' ? 'Tienda pública' : 'Sistema de gestión' ?></p><h3 class="text-lg font-semibold text-white break-all"><?= e($domain['hostname']) ?></h3></div>
        <span class="text-sm text-brand"><?= e($state) ?></span>
    </div>
    <p class="mt-4 mb-3 text-sm text-slate-400">Crea estos registros donde administras el DNS de tu dominio. Si el proveedor añade automáticamente tu dominio al nombre, introduce solo @ o el subdominio correspondiente. Usa modo «Solo DNS» durante la verificación.</p>
    <div class="table-wrap"><table class="data-table domain-dns"><thead><tr><th>Tipo</th><th>Nombre completo</th><th>Valor</th></tr></thead><tbody>
        <?php foreach ($ips as $ip): ?><tr><td><?= str_contains($ip, ':') ? 'AAAA' : 'A' ?></td><td class="font-mono text-xs"><?= e($domain['hostname']) ?></td><td class="font-mono text-xs select-all"><?= e($ip) ?></td></tr><?php endforeach; ?>
        <tr><td>TXT</td><td class="font-mono text-xs">_tmpbase.<?= e($domain['hostname']) ?><button type="button" class="text-brand" data-copy="_tmpbase.<?= e($domain['hostname']) ?>">Copiar nombre</button></td><td class="font-mono text-xs break-all select-all">tmpbase=<?= e($domain['verification_token']) ?><button type="button" class="text-brand" data-copy="tmpbase=<?= e($domain['verification_token']) ?>">Copiar valor</button></td></tr>
    </tbody></table></div>
    <p class="mt-3 text-xs text-slate-400">HTTPS: <?= $domain['tls_ok'] ? 'Certificado verificado el ' . e($domain['tls_checked_at']) : 'El certificado se solicita automáticamente tras la activación; comprueba su estado con el botón HTTPS.' ?></p>
    <?php if ($domain['last_error']): ?><p role="alert" class="mt-3 text-sm text-amber-300"><?= e($domain['last_error']) ?></p><?php endif; ?>
    <div class="mt-5 flex flex-wrap gap-2">
        <?php foreach (['verify' => 'Verificar DNS y activar', 'https' => 'Comprobar HTTPS', 'disable' => 'Desactivar', 'remove' => 'Desvincular'] as $operation => $label): ?>
        <form method="post" action="/projects/<?= e($project['uid']) ?>/domains/<?= (int) $domain['id'] ?>/<?= e($operation) ?>" <?= in_array($operation, ['disable','remove'], true) ? 'data-confirm="¿Quieres dejar de usar este dominio para el proyecto?"' : '' ?>>
            <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="btn-secondary" <?= $operation === 'https' && $domain['status'] !== 'active' ? 'disabled' : '' ?>><?= e($label) ?></button>
        </form>
        <?php endforeach; ?>
        <?php if ($domain['status'] === 'active'): ?><a class="btn-primary" href="https://<?= e($domain['hostname']) ?>" target="_blank" rel="noopener">Abrir <?= $domain['destination'] === 'store' ? 'tienda' : 'sistema' ?></a><?php endif; ?>
    </div>
</section>
<?php endforeach; ?>
<script>
const domainToggle = document.getElementById('enable-system');
const domainField = document.getElementById('system-domain-field');
const domainInput = document.getElementById('system-domain');
function updateSystemDomain() {
    domainField.hidden = !domainToggle.checked;
    domainInput.placeholder = 'sistema.' + (document.getElementById('store-domain').value.trim() || 'eldominio.com');
}
domainToggle.addEventListener('change', updateSystemDomain);
document.getElementById('store-domain').addEventListener('input', updateSystemDomain);
updateSystemDomain();
</script>
