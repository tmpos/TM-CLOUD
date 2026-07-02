<div class="mb-4 flex justify-between items-center">
  <h3 class="font-semibold text-white">Advanced filters</h3>
  <button onclick="document.getElementById('filter-panel').classList.toggle('hidden')" class="btn-secondary text-sm">Toggle filters</button>
</div>
<div id="filter-panel" class="hidden mb-4 rounded-xl border border-line bg-panel p-4">
  <form method="get" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <input type="hidden" name="tab" value="data">
    <?php foreach (array_slice($columns, 0, 6) as $col): if ($col['protected']) continue; ?>
    <label><span class="label text-xs"><?= e($col['name']) ?></span><input class="input text-sm" name="flt[<?= e($col['name']) ?>]" value="<?= e($_GET['flt'][$col['name']] ?? '') ?>" placeholder="<?= e($col['name']) ?>"></label>
    <?php endforeach; ?>
    <div class="flex items-end gap-2">
      <button class="btn-primary text-sm">Apply</button>
      <a href="?tab=data" class="btn-secondary text-sm">Clear</a>
    </div>
  </form>
</div>
