<?php
$humanSize = function ($bytes) {
    $bytes = (float) $bytes;
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
};
$projectsUsage = $usage['projects'];
$totals = $usage['totals'];
?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div><h2 class="text-xl font-bold text-white">Uso de espacio</h2><p class="mt-1 text-sm text-slate-500">Cuanto espacio en disco ocupa cada proyecto (base de datos, archivos y respaldos).</p></div>
</div>

<section class="grid gap-4 md:grid-cols-4 mb-6">
    <article class="card p-5"><p class="text-sm text-slate-500">Total ocupado</p><strong class="mt-2 block text-2xl text-white"><?= e($humanSize($totals['total'])) ?></strong></article>
    <article class="card p-5"><p class="text-sm text-slate-500">Bases de datos</p><strong class="mt-2 block text-2xl text-white"><?= e($humanSize($totals['database'])) ?></strong></article>
    <article class="card p-5"><p class="text-sm text-slate-500">Archivos subidos</p><strong class="mt-2 block text-2xl text-white"><?= e($humanSize($totals['uploads'])) ?></strong></article>
    <article class="card p-5"><p class="text-sm text-slate-500">Respaldos</p><strong class="mt-2 block text-2xl text-white"><?= e($humanSize($totals['backups'])) ?></strong></article>
</section>

<section class="grid gap-6 xl:grid-cols-[1.6fr_1fr] mb-6">
    <div class="card p-5">
        <h4 class="font-semibold text-white mb-3">Espacio por proyecto</h4>
        <canvas id="chart-projects" height="260"></canvas>
    </div>
    <div class="card p-5">
        <h4 class="font-semibold text-white mb-3">Distribucion global</h4>
        <canvas id="chart-global" height="260"></canvas>
    </div>
</section>

<div class="card overflow-hidden">
    <div class="border-b border-line p-5"><h2 class="font-semibold text-white">Detalle por proyecto</h2></div>
    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>Proyecto</th><th>Base de datos</th><th>Archivos</th><th>Respaldos</th><th>Total</th><th>Cuota</th></tr></thead>
        <tbody>
        <?php foreach ($projectsUsage as $row): ?>
        <tr>
            <td><a class="font-semibold text-brand" href="/projects/<?= e($row['uid']) ?>"><?= e($row['name']) ?></a></td>
            <td><?= e($humanSize($row['database'])) ?></td>
            <td><?= e($humanSize($row['uploads'])) ?></td>
            <td><?= e($humanSize($row['backups'])) ?></td>
            <td class="font-semibold text-white"><?= e($humanSize($row['total'])) ?></td>
            <td>
                <?php if ($row['percent'] !== null): ?>
                <div class="flex items-center gap-2">
                    <div class="h-2 w-28 overflow-hidden rounded-full bg-line"><div class="h-full rounded-full <?= $row['percent'] >= 90 ? 'bg-rose-500' : ($row['percent'] >= 70 ? 'bg-amber-400' : 'bg-brand') ?>" style="width: <?= e((string) $row['percent']) ?>%"></div></div>
                    <span class="text-xs text-slate-500"><?= e((string) $row['percent']) ?>%</span>
                </div>
                <?php else: ?>
                <span class="text-xs text-slate-600">Sin limite</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$projectsUsage): ?><tr><td colspan="6" class="py-12 text-center text-slate-600">No hay proyectos todavia.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
const projectsData = <?= json_encode($projectsUsage) ?>;
const globalData = <?= json_encode($global['details']) ?>;
const colBrand = '#2dd4bf', colDim = '#22303d';

new Chart(document.getElementById('chart-projects'), {
    type: 'bar',
    data: {
        labels: projectsData.map(p => p.name),
        datasets: [{ label: 'Espacio (bytes)', data: projectsData.map(p => p.total), backgroundColor: colBrand, borderRadius: 4 }]
    },
    options: {
        responsive: true,
        indexAxis: 'y',
        plugins: { legend: { display: false } },
        scales: {
            x: { beginAtZero: true, ticks: { color: '#64748b' }, grid: { color: colDim } },
            y: { ticks: { color: '#64748b' }, grid: { color: colDim } }
        }
    }
});

new Chart(document.getElementById('chart-global'), {
    type: 'doughnut',
    data: {
        labels: Object.keys(globalData),
        datasets: [{ data: Object.values(globalData), backgroundColor: ['#2dd4bf', '#06b6d4', '#0ea5e9', '#8b5cf6'], borderWidth: 0 }]
    },
    options: { responsive: true, plugins: { legend: { labels: { color: '#94a3b8' } } } }
});
</script>
