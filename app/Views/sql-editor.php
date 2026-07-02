<?php
use App\Core\Csrf;

$editorTarget = (string) ($_GET['target'] ?? ($sqlEditor['target'] ?? 'sqlite'));
$sqlResult = $sqlEditor['result'] ?? null;
$cell = static function (mixed $value): string {
    if ($value === null) return 'NULL';
    if (is_bool($value)) return $value ? 'true' : 'false';
    if (is_array($value) || is_object($value)) return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    $text = (string) $value;
    return strlen($text) > 2000 ? substr($text, 0, 2000) . '…' : $text;
};
?>
<section class="card p-6">
    <div class="flex flex-wrap items-start justify-between gap-3"><div><h3 class="font-semibold text-white">SQL Editor</h3><p class="mt-1 text-sm text-slate-500">Run SQL directly against the project SQLite database or a saved MySQL connection.</p></div><span class="rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-1.5 text-xs text-amber-200">Administrator access</span></div>
    <form class="mt-5" method="post" action="/projects/<?= e($project['uid']) ?>/sql">
        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
        <label class="block max-w-xl"><span class="label">Database target</span><select class="input" name="target"><option value="sqlite" <?= $editorTarget === 'sqlite' ? 'selected' : '' ?>>Project SQLite</option><?php foreach ($connections as $connection): ?><option value="<?= e($connection['uid']) ?>" <?= $editorTarget === $connection['uid'] ? 'selected' : '' ?>>MySQL — <?= e($connection['name']) ?> (<?= e($connection['database_name']) ?>)</option><?php endforeach; ?></select></label>
        <label class="mt-4 block"><span class="label">SQL statement</span><textarea class="input min-h-64 resize-y font-mono text-sm leading-6" name="sql" required spellcheck="false"><?= e($sqlEditor['query'] ?? '') ?></textarea></label>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <label class="flex items-center gap-2 text-sm text-amber-200"><input type="checkbox" name="allow_write" value="1" class="accent-brand"> Allow statements that modify schema or data</label>
            <button class="btn-primary">Run SQL</button>
        </div>
    </form>
</section>

<?php if (is_array($sqlResult)): ?>
<section class="mt-6">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3"><h3 class="font-semibold text-white">Query result</h3><div class="flex gap-2 text-xs text-slate-400"><span class="rounded bg-panel px-2 py-1"><?= e($sqlResult['target']) ?></span><span class="rounded bg-panel px-2 py-1"><?= e($sqlResult['duration_ms']) ?> ms</span><span class="rounded bg-panel px-2 py-1"><?= (int) $sqlResult['affected'] ?> affected</span></div></div>
    <?php if ($sqlResult['columns']): ?>
    <div class="table-wrap"><table class="data-table"><thead><tr><?php foreach ($sqlResult['columns'] as $column): ?><th><?= e($column) ?></th><?php endforeach; ?></tr></thead><tbody>
    <?php foreach ($sqlResult['rows'] as $row): ?><tr><?php foreach ($sqlResult['columns'] as $column): ?><td title="<?= e($cell($row[$column] ?? null)) ?>"><?= e($cell($row[$column] ?? null)) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
    <?php if (!$sqlResult['rows']): ?><tr><td colspan="<?= count($sqlResult['columns']) ?>" class="py-10 text-center text-slate-500">The query returned no rows.</td></tr><?php endif; ?>
    </tbody></table></div>
    <?php else: ?><div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-4 text-sm text-emerald-300">Statement executed successfully. <?= (int) $sqlResult['affected'] ?> rows affected.</div><?php endif; ?>
    <?php if ($sqlResult['truncated']): ?><p class="mt-3 text-xs text-amber-300">Only the first 200 rows are displayed. Add LIMIT to refine the result.</p><?php endif; ?>
</section>
<?php endif; ?>
