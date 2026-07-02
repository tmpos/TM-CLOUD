<div class="overflow-auto" style="min-height:400px">
  <svg id="schema-svg" width="100%" height="600" class="border border-line rounded-xl bg-[#0a0f14]"></svg>
</div>
<script>
const tables = <?= json_encode($tables) ?>;
const relations = <?= json_encode($relations) ?>;
const cols = <?= json_encode($columnsByTable) ?>;

function drawSchema() {
  const svg = document.getElementById('schema-svg');
  const ns = 'http://www.w3.org/2000/svg';
  let html = '<defs><marker id="arrow" viewBox="0 0 10 10" refX="10" refY="5" markerWidth="6" markerHeight="6" orient="auto"><path d="M0,0 L10,5 L0,10 z" fill="#2dd4bf"/></marker></defs>';
  let x = 40, y = 30;
  const positions = {};
  for (const t of tables) {
    const h = 30 + (cols[t.name]?.length||0) * 26 + 10;
    html += `<rect x="${x}" y="${y}" width="220" height="${h}" rx="8" fill="#111820" stroke="#22303d" stroke-width="1"/>`;
    html += `<text x="${x+110}" y="${y+20}" text-anchor="middle" fill="#2dd4bf" font-size="13" font-weight="bold">${t.name}</text>`;
    let cy = y + 35;
    for (const c of cols[t.name]||[]) {
      const icon = c.protected ? '🔒' : (c.unique ? '🔑' : '');
      html += `<text x="${x+10}" y="${cy}" fill="#94a3b8" font-size="11" font-family="monospace">${icon} ${c.name} <tspan fill="#64748b">${c.type}</tspan></text>`;
      cy += 26;
    }
    positions[t.name] = { x: x + 110, y: y + h, cx: x, cy: y };
    x += 260;
    if (x > 800) { x = 40; y += Math.max(h, 150) + 40; }
  }
  for (const r of relations) {
    const from = positions[r.table_name];
    const to = positions[r.ref_table];
    if (!from || !to) continue;
    const x1 = from.x, y1 = from.y + 10;
    const x2 = to.x, y2 = to.cy - 10;
    const mid = (y1 + y2) / 2;
    html += `<path d="M${x1},${y1} C${x1},${mid} ${x2},${mid} ${x2},${y2}" fill="none" stroke="#2dd4bf" stroke-width="1.5" stroke-dasharray="4,3" marker-end="url(#arrow)"/>`;
    html += `<text x="${(x1+x2)/2}" y="${mid-5}" text-anchor="middle" fill="#64748b" font-size="9">${r.column_name}</text>`;
  }
  svg.innerHTML = html;
}
drawSchema();
</script>
