<?php use App\Core\Csrf; ?>
<div class="grid gap-6 xl:grid-cols-[1fr_1.4fr]">
    <section class="card p-6">
        <h3 class="font-semibold text-white">New MySQL connection</h3>
        <p class="mt-1 text-sm text-slate-500">The password is encrypted with a local key stored outside the public directory.</p>
        <form class="mt-5 grid gap-4 md:grid-cols-2" method="post" action="/projects/<?= e($project['uid']) ?>/database/connections">
            <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
            <label><span class="label">Connection name</span><input class="input" name="name" required maxlength="100" placeholder="Production MySQL"></label>
            <label><span class="label">Host</span><input class="input" name="host" required maxlength="255" placeholder="127.0.0.1"></label>
            <label><span class="label">Port</span><input class="input" name="port" type="number" min="1" max="65535" value="3306" required></label>
            <label><span class="label">Database</span><input class="input" name="database_name" required maxlength="128"></label>
            <label><span class="label">Username</span><input class="input" name="username" required maxlength="128" autocomplete="off"></label>
            <label><span class="label">Password</span><input class="input" name="password" type="password" autocomplete="new-password"></label>
            <label><span class="label">Charset</span><select class="input" name="charset"><option value="utf8mb4">utf8mb4</option><option value="utf8">utf8</option></select></label>
            <div class="flex items-end"><button class="btn-primary w-full">Connect and save</button></div>
        </form>
    </section>

    <section class="card p-6">
        <h3 class="font-semibold text-white">Saved connections</h3>
        <div class="mt-5 space-y-3">
            <?php foreach ($connections as $connection): ?>
            <article class="rounded-xl border <?= $selectedConnection === $connection['uid'] ? 'border-brand/50 bg-brand/5' : 'border-line bg-black/10' ?> p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div><strong class="text-sm text-white"><?= e($connection['name']) ?></strong><p class="mt-1 font-mono text-xs text-slate-500"><?= e($connection['username']) ?>@<?= e($connection['host']) ?>:<?= (int) $connection['port'] ?>/<?= e($connection['database_name']) ?></p></div>
                    <div class="flex flex-wrap gap-2">
                        <a class="btn-secondary text-xs" href="?tab=database&amp;connection=<?= e($connection['uid']) ?>">Use</a>
                        <a class="btn-secondary text-xs" href="?tab=sql&amp;target=<?= e($connection['uid']) ?>">SQL</a>
                        <form method="post" action="/projects/<?= e($project['uid']) ?>/database/connections/<?= e($connection['uid']) ?>/test"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="btn-secondary text-xs">Test</button></form>
                        <form method="post" action="/projects/<?= e($project['uid']) ?>/database/connections/<?= e($connection['uid']) ?>/delete" data-confirm="Delete this saved connection? No database data will be deleted."><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="btn-danger text-xs">Delete</button></form>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
            <?php if (!$connections): ?><p class="rounded-xl border border-dashed border-line p-8 text-center text-sm text-slate-500">No MySQL connections saved.</p><?php endif; ?>
        </div>
    </section>
</div>

<?php if ($connections && $selectedConnection): ?>
<section class="card mt-6 p-6">
    <div class="flex flex-wrap items-start justify-between gap-3"><div><h3 class="font-semibold text-white">Transfer data</h3><p class="mt-1 text-sm text-slate-500">Copy up to 10,000 rows per operation. Missing destination tables are created automatically.</p></div><span class="rounded-lg bg-brand/10 px-3 py-1.5 text-xs font-semibold text-brand"><?= count($mysqlTables) ?> MySQL tables detected</span></div>
    <?php if ($mysqlTablesError): ?><div class="mt-4 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300"><?= e($mysqlTablesError) ?></div><?php endif; ?>
    <form class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3" method="post" action="/projects/<?= e($project['uid']) ?>/database/transfer" id="database-transfer-form" data-confirm="Start this database transfer?">
        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
        <input type="hidden" name="connection_uid" value="<?= e($selectedConnection) ?>">
        <label><span class="label">Direction</span><select class="input" name="direction" id="transfer-direction"><option value="sqlite_to_mysql">SQLite → MySQL</option><option value="mysql_to_sqlite">MySQL → SQLite</option></select></label>
        <label><span class="label">Source table</span><input class="input font-mono" name="source_table" id="transfer-source" list="sqlite-table-options" required pattern="[A-Za-z][A-Za-z0-9_]{0,62}"></label>
        <label><span class="label">Destination table</span><input class="input font-mono" name="target_table" id="transfer-target" list="mysql-table-options" pattern="[A-Za-z][A-Za-z0-9_]{0,62}" placeholder="Same as source"></label>
        <label><span class="label">Mode</span><select class="input" name="mode"><option value="upsert">Update existing / insert new</option><option value="append">Append only</option><option value="replace">Delete destination rows and replace</option></select></label>
        <label><span class="label">Maximum rows</span><input class="input" name="limit" type="number" min="1" max="10000" value="1000"></label>
        <div class="flex items-end"><button class="btn-primary w-full">Transfer now</button></div>
    </form>
</section>
<datalist id="sqlite-table-options"><?php foreach ($tables as $table): ?><option value="<?= e($table['name']) ?>"><?php endforeach; ?></datalist>
<datalist id="mysql-table-options"><?php foreach ($mysqlTables as $table): ?><option value="<?= e($table) ?>"><?php endforeach; ?></datalist>
<script>
(() => {
    const direction = document.getElementById('transfer-direction');
    const source = document.getElementById('transfer-source');
    const target = document.getElementById('transfer-target');
    const updateLists = () => {
        const sqliteToMysql = direction.value === 'sqlite_to_mysql';
        source.setAttribute('list', sqliteToMysql ? 'sqlite-table-options' : 'mysql-table-options');
        target.setAttribute('list', sqliteToMysql ? 'mysql-table-options' : 'sqlite-table-options');
    };
    direction.addEventListener('change', updateLists);
    updateLists();
})();
</script>
<?php endif; ?>
