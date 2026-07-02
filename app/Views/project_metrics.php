<div class="grid gap-6 lg:grid-cols-2 xl:grid-cols-4 mb-6">
  <div class="card p-4"><p class="text-xs text-slate-500">API Requests</p><p class="mt-1 text-2xl font-bold text-white"><?= number_format($summary['api_request'] ?? 0) ?></p></div>
  <div class="card p-4"><p class="text-xs text-slate-500">Records created</p><p class="mt-1 text-2xl font-bold text-white"><?= number_format($summary['record.created'] ?? 0) ?></p></div>
  <div class="card p-4"><p class="text-xs text-slate-500">Storage</p><p class="mt-1 text-2xl font-bold text-white"><?= e($storageHuman) ?></p></div>
  <div class="card p-4"><p class="text-xs text-slate-500">Functions</p><p class="mt-1 text-2xl font-bold text-white"><?= $fnCount ?></p></div>
</div>
<div class="grid gap-6 lg:grid-cols-2 mb-6">
  <div class="card p-5"><h4 class="font-semibold text-white mb-3">API Requests (24h)</h4><canvas id="chart-requests" height="200"></canvas></div>
  <div class="card p-5"><h4 class="font-semibold text-white mb-3">Storage usage</h4><canvas id="chart-storage" height="200"></canvas></div>
</div>
<div class="card p-5"><h4 class="font-semibold text-white mb-3">Timeline</h4><canvas id="chart-timeline" height="180"></canvas></div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
const reqData = <?= json_encode($requestsTimeline) ?>;
const storageData = <?= json_encode($storageUsage) ?>;
const colBrand = '#2dd4bf', colDim = '#22303d';

new Chart(document.getElementById('chart-requests'), {
  type: 'bar',
  data: { labels: reqData.map(d=>d.bucket), datasets: [{ label:'Requests', data: reqData.map(d=>d.total), backgroundColor: colBrand, borderRadius: 4 }] },
  options: { responsive: true, plugins: { legend: { display: false } }, scales: { x: { ticks: { color:'#64748b', maxTicksLimit:12 }, grid: { color: colDim } }, y: { beginAtZero: true, ticks: { color:'#64748b' }, grid: { color: colDim } } } }
});

new Chart(document.getElementById('chart-storage'), {
  type: 'doughnut',
  data: { labels: Object.keys(storageData.details), datasets: [{ data: Object.values(storageData.details), backgroundColor: ['#2dd4bf','#06b6d4','#0ea5e9','#8b5cf6'], borderWidth: 0 }] },
  options: { responsive: true, plugins: { legend: { labels: { color:'#94a3b8' } } } }
});

new Chart(document.getElementById('chart-timeline'), {
  type: 'line',
  data: { labels: reqData.map(d=>d.bucket), datasets: [{ label:'Requests', data: reqData.map(d=>d.total), borderColor: colBrand, backgroundColor: colBrand+'33', fill: true, tension: 0.3 }] },
  options: { responsive: true, plugins: { legend: { labels: { color:'#94a3b8' } } }, scales: { x: { ticks: { color:'#64748b', maxTicksLimit:10 }, grid: { color: colDim } }, y: { beginAtZero: true, ticks: { color:'#64748b' }, grid: { color: colDim } } } }
});
</script>
