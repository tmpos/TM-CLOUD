<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Http;
use App\Core\View;
use App\Services\BackupService;
use App\Services\ImportExportService;
use App\Services\InstallerService;
use App\Services\LogService;
use App\Services\ProjectService;
use App\Services\RecordService;
use App\Services\SchemaService;
use App\Services\StorageService;
use App\Services\LicenseService;
use App\Services\FunctionService;
use App\Services\MetricsService;
use App\Services\MigrationService;
use App\Services\PdfService;
use App\Services\WebhookService;
use App\Services\DatabaseBridgeService;
use Flight;
use PDO;

final class WebController
{
    public function __construct(
        private array $config,
        private Auth $auth,
        private InstallerService $installer,
        private PDO $db,
        private ProjectService $projects,
        private SchemaService $schema,
        private RecordService $records,
        private ImportExportService $transfer,
        private LogService $logs,
        private BackupService $backups,
        private StorageService $storage,
        private WebhookService $webhooks,
        private LicenseService $licenses,
        private DatabaseBridgeService $databaseBridge,
        private PdfService $pdf,
        private FunctionService $functions,
        private MetricsService $metrics,
        private MigrationService $migrations,
    ) {
    }

    public function register(): void
    {
        Flight::route('GET /', fn () => $this->home());
        Flight::route('GET /install', fn () => $this->installPage());
        Flight::route('POST /install', fn () => $this->action(function (array $in): void {
            $this->installer->install($in);
            $this->auth->attempt((string) $in['email'], (string) $in['password']);
            Http::flash('success', 'TMPBase installed successfully.');
            Flight::redirect('/dashboard');
        }, false));
        Flight::route('POST /login', fn () => $this->action(function (array $in): void {
            if (!$this->auth->attempt((string) ($in['email'] ?? ''), (string) ($in['password'] ?? ''))) {
                throw new \RuntimeException('Invalid email or password.');
            }
            Flight::redirect('/dashboard');
        }, false));
        Flight::route('POST /logout', fn () => $this->action(function (): void {
            Auth::logout();
            Flight::redirect('/');
        }));
        Flight::route('GET /dashboard', fn () => $this->page(fn () => $this->dashboard()));
        Flight::route('GET /api-docs', fn () => $this->page(fn () => $this->apiDocs()));
        Flight::route('GET /backups', fn () => $this->page(fn () => $this->backupsPage()));
        Flight::route('GET /storage', fn () => $this->page(fn () => $this->storagePage()));
        Flight::route('POST /projects', fn () => $this->action(function (array $in): void {
            $project = $this->projects->create($in);
            Http::flash('success', 'Project created.');
            Flight::redirect('/projects/' . $project['uid']);
        }));
        Flight::route('GET /projects/@uid', fn (string $uid) => $this->page(fn () => $this->project($uid)));
        Flight::route('POST /projects/@uid/database/connections', fn (string $uid) => $this->action(function (array $in) use ($uid): void {
            $connection = $this->databaseBridge->create($uid, $in);
            Http::flash('success', 'MySQL connection created and verified.');
            Flight::redirect("/projects/$uid?tab=database&connection=" . $connection['uid']);
        }));
        Flight::route('POST /projects/@uid/database/connections/@connection/test', fn (string $uid, string $connection) => $this->action(function () use ($uid, $connection): void {
            $this->databaseBridge->test($uid, $connection);
            Http::flash('success', 'MySQL connection successful.');
            Flight::redirect("/projects/$uid?tab=database&connection=$connection");
        }));
        Flight::route('POST /projects/@uid/database/connections/@connection/delete', fn (string $uid, string $connection) => $this->action(function () use ($uid, $connection): void {
            $this->databaseBridge->delete($uid, $connection);
            Http::flash('success', 'Database connection deleted.');
            Flight::redirect("/projects/$uid?tab=database");
        }));
        Flight::route('POST /projects/@uid/database/transfer', fn (string $uid) => $this->action(function (array $in) use ($uid): void {
            $connection = (string) ($in['connection_uid'] ?? '');
            $result = $this->databaseBridge->transfer($this->projects->find($uid), $connection, $in);
            $message = "Transfer completed: {$result['inserted']} inserted, {$result['updated']} updated, {$result['failed']} failed.";
            if ($result['errors']) {
                $message .= ' ' . implode(' | ', $result['errors']);
            }
            Http::flash($result['failed'] ? 'warning' : 'success', $message);
            Flight::redirect("/projects/$uid?tab=database&connection=$connection");
        }));
        Flight::route('POST /projects/@uid/sql', fn (string $uid) => $this->action(function (array $in) use ($uid): void {
            $sql = (string) ($in['sql'] ?? '');
            $target = (string) ($in['target'] ?? 'sqlite');
            $_SESSION['_sql_editor'] = ['query' => $sql, 'target' => $target, 'result' => null];
            $_SESSION['_sql_editor']['result'] = $this->databaseBridge->executeSql(
                $this->projects->find($uid), $target, $sql, isset($in['allow_write'])
            );
            Flight::redirect("/projects/$uid?tab=sql");
        }));
        Flight::route('POST /projects/@uid/keys', fn (string $uid) => $this->action(function () use ($uid): void {
            $this->projects->regenerateKeys($uid);
            Http::flash('success', 'API keys regenerated.');
            Flight::redirect('/projects/' . $uid . '?tab=settings');
        }));
        Flight::route('POST /projects/@uid/delete', fn (string $uid) => $this->action(function () use ($uid): void {
            $this->projects->delete($uid);
            Http::flash('success', 'Project and its stored data were deleted.');
            Flight::redirect('/dashboard');
        }));
        Flight::route('POST /projects/@uid/tables', fn (string $uid) => $this->action(function (array $in) use ($uid): void {
            $project = $this->projects->find($uid);
            $this->schema->createTable($project, (string) ($in['name'] ?? ''));
            $this->webhooks->dispatch('table.created', $project, (string) $in['name'], null);
            Http::flash('success', 'Table created.');
            Flight::redirect('/projects/' . $uid . '/tables/' . $in['name']);
        }));
        Flight::route('GET /projects/@uid/tables/@table', fn (string $uid, string $table) => $this->page(fn () => $this->table($uid, $table)));
        Flight::route('POST /projects/@uid/tables/@table/images/@file/delete', fn (string $uid, string $table, string $file) => $this->action(function () use ($uid, $table, $file): void {
            $cleared = $this->storage->deleteAndClearReferences($this->projects->find($uid), $file);
            Http::flash('success', "Image deleted; $cleared record reference(s) cleared.");
            Flight::redirect("/projects/$uid/tables/$table?tab=images");
        }));
        Flight::route('POST /projects/@uid/tables/@table/images/bulk-delete', fn (string $uid, string $table) => $this->action(function (array $in) use ($uid, $table): void {
            $files = $in['files'] ?? [];
            if (!is_array($files) || !$files) throw new \InvalidArgumentException('Select at least one image.');
            $project = $this->projects->find($uid);
            $deleted = 0;
            $failed = 0;
            $cleared = 0;
            foreach (array_unique($files) as $file) {
                try {
                    $cleared += $this->storage->deleteAndClearReferences($project, (string) $file);
                    $deleted++;
                } catch (\Throwable) {
                    $failed++;
                }
            }
            Http::flash($failed ? 'warning' : 'success', "$deleted image(s) deleted, $cleared reference(s) cleared, $failed failed.");
            Flight::redirect("/projects/$uid/tables/$table?tab=images");
        }));
        Flight::route('POST /projects/@uid/tables/@table/fields', fn (string $uid, string $table) => $this->action(function (array $in) use ($uid, $table): void {
            $this->schema->addColumn($this->projects->find($uid), $table, $in);
            Http::flash('success', 'Field added.');
            Flight::redirect("/projects/$uid/tables/$table?tab=structure");
        }));
        Flight::route('POST /projects/@uid/tables/@table/fields/@column/delete', fn (string $uid, string $table, string $column) => $this->action(function () use ($uid, $table, $column): void {
            $this->schema->dropColumn($this->projects->find($uid), $table, $column);
            Http::flash('success', 'Field removed.');
            Flight::redirect("/projects/$uid/tables/$table?tab=structure");
        }));
        Flight::route('GET /projects/@uid/tables/@table/records/sync', fn (string $uid, string $table) => $this->page(fn () => $this->syncJson($uid, $table)));
        Flight::route('POST /projects/@uid/tables/@table/records', fn (string $uid, string $table) => $this->action(function (array $in) use ($uid, $table): void {
            unset($in['_csrf']);
            $project = $this->projects->find($uid);
            $record = $this->records->create($project, $table, $in);
            $this->webhooks->dispatch('record.created', $project, $table, $record);
            Http::flash('success', 'Record created.');
            Flight::redirect("/projects/$uid/tables/$table");
        }));
        Flight::route('POST /projects/@uid/tables/@table/records/@record', fn (string $uid, string $table, string $record) => $this->action(function (array $in) use ($uid, $table, $record): void {
            $action = $in['_action'] ?? 'update';
            unset($in['_csrf'], $in['_action']);
            $project = $this->projects->find($uid);
            if ($action === 'delete') {
                $old = $this->records->find($project, $table, $record);
                $this->records->delete($project, $table, $record);
                $this->storage->deleteImagesFromRowsIfUnreferenced($project, [$old]);
                $this->webhooks->dispatch('record.deleted', $project, $table, $old);
            } else {
                $updated = $this->records->update($project, $table, $record, $in);
                $this->webhooks->dispatch('record.updated', $project, $table, $updated);
            }
            Http::flash('success', $action === 'delete' ? 'Record deleted.' : 'Record updated.');
            Flight::redirect("/projects/$uid/tables/$table");
        }));
        Flight::route('POST /projects/@uid/tables/@table/actions', fn (string $uid, string $table) => $this->action(function (array $in) use ($uid, $table): void {
            $project = $this->projects->find($uid);
            if (($in['_action'] ?? '') === 'truncate') {
                $rows = $this->records->all($project, $table);
                $this->schema->truncate($project, $table);
                $imagesDeleted = $this->storage->deleteImagesFromRowsIfUnreferenced($project, $rows);
                $this->webhooks->dispatch('table.truncated', $project, $table, null);
                Http::flash('success', "Table emptied; $imagesDeleted unreferenced image(s) deleted.");
                Flight::redirect("/projects/$uid/tables/$table");
                return;
            }
            if (($in['_action'] ?? '') === 'drop') {
                $rows = $this->records->all($project, $table);
                $this->webhooks->dispatch('table.deleted', $project, $table, null);
                $this->schema->dropTable($project, $table);
                $imagesDeleted = $this->storage->deleteImagesFromRowsIfUnreferenced($project, $rows);
                Http::flash('success', "Table deleted; $imagesDeleted unreferenced image(s) deleted.");
                Flight::redirect("/projects/$uid");
                return;
            }
            if (($in['_action'] ?? '') === 'access') {
                $this->schema->setAccessMode($project, $table, (string) ($in['access_mode'] ?? 'private'));
                Http::flash('success', 'API access updated.');
                Flight::redirect("/projects/$uid/tables/$table?tab=api");
                return;
            }
            throw new \InvalidArgumentException('Unknown table action.');
        }));
        Flight::route('POST /projects/@uid/tables/@table/records/bulk-delete', fn (string $uid, string $table) => $this->action(function (array $in) use ($uid, $table): void {
            $uids = $in['uids'] ?? [];
            if (!is_array($uids) || !$uids) {
                throw new \InvalidArgumentException('Select at least one record.');
            }
            $project = $this->projects->find($uid);
            $rows = [];
            foreach ($uids as $recordUid) {
                try { $rows[] = $this->records->find($project, $table, (string) $recordUid); } catch (\Throwable) {}
            }
            $result = $this->records->deleteBulk($project, $table, $uids);
            $imagesDeleted = $this->storage->deleteImagesFromRowsIfUnreferenced($project, $rows);
            Http::flash($result['failed'] ? 'warning' : 'success', "{$result['deleted']} records and $imagesDeleted image(s) deleted; {$result['failed']} failed.");
            Flight::redirect("/projects/$uid/tables/$table");
        }));
        Flight::route('POST /projects/@uid/tables/delete-all', fn (string $uid) => $this->action(function () use ($uid): void {
            $project = $this->projects->find($uid);
            $tables = $this->schema->tables($project);
            $dropped = 0;
            foreach ($tables as $table) {
                if (str_starts_with($table['name'], '_')) continue;
                $rows = $this->records->all($project, $table['name']);
                $this->webhooks->dispatch('table.deleted', $project, $table['name'], null);
                $this->schema->dropTable($project, $table['name']);
                $this->storage->deleteImagesFromRowsIfUnreferenced($project, $rows);
                $dropped++;
            }
            Http::flash('success', "$dropped tables deleted.");
            Flight::redirect("/projects/$uid");
        }));
        Flight::route('POST /projects/@uid/tables/@table/import', fn (string $uid, string $table) => $this->action(function () use ($uid, $table): void {
            $file = $_FILES['file'] ?? null;
            if (!$file || ($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
                throw new \InvalidArgumentException('Select a JSON or CSV file.');
            }
            $format = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
            $rows = $this->transfer->parse((string) file_get_contents($file['tmp_name']), $format);
            $result = $this->records->bulk($this->projects->find($uid), $table, $rows);
            Http::flash($result['failed'] ? 'warning' : 'success', "{$result['inserted']} records imported; {$result['failed']} failed.");
            Flight::redirect("/projects/$uid/tables/$table");
        }));
        Flight::route('GET /projects/@uid/tables/@table/export', fn (string $uid, string $table) => $this->page(fn () => $this->export($uid, $table)));
        Flight::route('GET /projects/@uid/tables/@table/@record/pdf', fn (string $uid, string $table, string $record) => $this->page(fn () => $this->pdfInvoice($uid, $table, $record)));
        Flight::route('POST /projects/@uid/backups', fn (string $uid) => $this->action(function () use ($uid): void {
            $this->backups->create($this->projects->find($uid));
            Http::flash('success', 'Backup created.');
            Flight::redirect("/projects/$uid?tab=backups");
        }));
        Flight::route('GET /projects/@uid/backups/@backup/download', fn (string $uid, string $backup) => $this->page(fn () => $this->downloadBackup($uid, $backup)));
        Flight::route('POST /projects/@uid/backups/@backup/restore', fn (string $uid, string $backup) => $this->action(function () use ($uid, $backup): void {
            $this->backups->restore($this->projects->find($uid), $backup);
            Http::flash('success', 'Backup restored. A safety backup was created first.');
            Flight::redirect("/projects/$uid?tab=backups");
        }));
        Flight::route('POST /projects/@uid/backups/@backup/delete', fn (string $uid, string $backup) => $this->action(function () use ($uid, $backup): void {
            $this->backups->delete($this->projects->find($uid), $backup);
            Http::flash('success', 'Backup deleted.');
            Flight::redirect("/projects/$uid?tab=backups");
        }));
        Flight::route('POST /projects/@uid/storage', fn (string $uid) => $this->action(function () use ($uid): void {
            $this->storage->upload($this->projects->find($uid), $_FILES['file'] ?? []);
            Http::flash('success', 'File uploaded.');
            Flight::redirect("/projects/$uid?tab=storage");
        }));
        Flight::route('POST /projects/@uid/storage/@file/delete', fn (string $uid, string $file) => $this->action(function () use ($uid, $file): void {
            $this->storage->delete($this->projects->find($uid), $file);
            Http::flash('success', 'File deleted.');
            Flight::redirect("/projects/$uid?tab=storage");
        }));
        Flight::route('POST /projects/@uid/webhooks', fn (string $uid) => $this->action(function (array $in) use ($uid): void {
            $this->webhooks->create($uid, (string) ($in['event'] ?? ''), (string) ($in['url'] ?? ''));
            Http::flash('success', 'Webhook created.');
            Flight::redirect("/projects/$uid?tab=webhooks");
        }));
        Flight::route('POST /projects/@uid/licenses', fn (string $uid) => $this->action(function (array $in) use ($uid): void {
            $project = $this->projects->find($uid);
            $in['project_url'] = $this->config['url'] . '/api/' . $project['uid'];
            $in['public_key'] = $project['public_key'];
            $in['secret_key'] = $project['secret_key'];
            $this->licenses->create($uid, $in);
            Http::flash('success', 'License created.');
            Flight::redirect("/projects/$uid?tab=licenses");
        }));
        Flight::route('POST /projects/@uid/licenses/@license/update', fn (string $uid, string $license) => $this->action(function (array $in) use ($uid, $license): void {
            $this->licenses->update($license, $in);
            Http::flash('success', 'License updated.');
            Flight::redirect("/projects/$uid?tab=licenses");
        }));
        Flight::route('POST /projects/@uid/licenses/@license/status', fn (string $uid, string $license) => $this->action(function (array $in) use ($uid, $license): void {
            $this->licenses->setStatus($license, (string) ($in['status'] ?? 'active'));
            Http::flash('success', 'License status changed.');
            Flight::redirect("/projects/$uid?tab=licenses");
        }));
        Flight::route('POST /projects/@uid/licenses/@license/reset-uses', fn (string $uid, string $license) => $this->action(function () use ($uid, $license): void {
            $this->licenses->resetUses($license);
            Http::flash('success', 'License usage counter reset.');
            Flight::redirect("/projects/$uid?tab=licenses");
        }));
        Flight::route('POST /projects/@uid/licenses/@license/delete', fn (string $uid, string $license) => $this->action(function () use ($uid, $license): void {
            $this->licenses->delete($license);
            Http::flash('success', 'License deleted.');
            Flight::redirect("/projects/$uid?tab=licenses");
        }));
        Flight::route('POST /projects/@uid/licenses/@license/authorize-device', fn (string $uid, string $license) => $this->action(function (array $in) use ($uid, $license): void {
            $this->licenses->authorizeDevice($license, (string) ($in['device_id'] ?? ''));
            Http::flash('success', 'Device authorized.');
            Flight::redirect("/projects/$uid?tab=licenses");
        }));
        Flight::route('POST /projects/@uid/licenses/@license/block-device', fn (string $uid, string $license) => $this->action(function (array $in) use ($uid, $license): void {
            $this->licenses->blockDevice($license, (string) ($in['device_id'] ?? ''));
            Http::flash('success', 'Device blocked.');
            Flight::redirect("/projects/$uid?tab=licenses");
        }));
        Flight::route('GET /licenses', fn () => $this->page(fn () => $this->licensesPage()));
        Flight::route('POST /licenses', fn () => $this->action(function (array $in): void {
            $projectUid = (string) ($in['project_uid'] ?? '');
            if (!$projectUid) throw new \InvalidArgumentException('Select a project.');
            $project = $this->projects->find($projectUid);
            $in['project_url'] = $this->config['url'] . '/api/' . $project['uid'];
            $in['public_key'] = $project['public_key'];
            $in['secret_key'] = $project['secret_key'];
            $this->licenses->create($projectUid, $in);
            Http::flash('success', 'License created.');
            Flight::redirect('/licenses');
        }));
        Flight::route('POST /licenses/@license/update', fn (string $license) => $this->action(function (array $in) use ($license): void {
            $this->licenses->update($license, $in);
            Http::flash('success', 'License updated.');
            Flight::redirect('/licenses');
        }));
        Flight::route('POST /licenses/@license/status', fn (string $license) => $this->action(function (array $in) use ($license): void {
            $this->licenses->setStatus($license, (string) ($in['status'] ?? 'active'));
            Http::flash('success', 'License status changed.');
            Flight::redirect('/licenses');
        }));
        Flight::route('POST /licenses/@license/reset-uses', fn (string $license) => $this->action(function () use ($license): void {
            $this->licenses->resetUses($license);
            Http::flash('success', 'Usage counter reset.');
            Flight::redirect('/licenses');
        }));
        Flight::route('POST /licenses/@license/delete', fn (string $license) => $this->action(function () use ($license): void {
            $this->licenses->delete($license);
            Http::flash('success', 'License deleted.');
            Flight::redirect('/licenses');
        }));
        Flight::route('POST /licenses/@license/authorize-device', fn (string $license) => $this->action(function (array $in) use ($license): void {
            $this->licenses->authorizeDevice($license, (string) ($in['device_id'] ?? ''));
            Http::flash('success', 'Device authorized.');
            Flight::redirect('/licenses');
        }));
        Flight::route('POST /licenses/@license/block-device', fn (string $license) => $this->action(function (array $in) use ($license): void {
            $this->licenses->blockDevice($license, (string) ($in['device_id'] ?? ''));
            Http::flash('success', 'Device blocked.');
            Flight::redirect('/licenses');
        }));
        Flight::route('POST /projects/@uid/foreign-keys', fn (string $uid) => $this->action(function (array $in) use ($uid): void {
            $project = $this->projects->find($uid);
            $this->schema->addForeignKey($project, (string) ($in['table'] ?? ''), (string) ($in['column'] ?? ''), (string) ($in['ref_table'] ?? ''), (string) ($in['ref_column'] ?? 'id'), (string) ($in['on_delete'] ?? 'CASCADE'));
            Http::flash('success', 'Foreign key added.');
            Flight::redirect("/projects/$uid?tab=diagram");
        }));
        Flight::route('POST /projects/@uid/foreign-keys/delete', fn (string $uid) => $this->action(function (array $in) use ($uid): void {
            $project = $this->projects->find($uid);
            $this->schema->dropForeignKey($project, (string) ($in['table'] ?? ''), (string) ($in['column'] ?? ''));
            Http::flash('success', 'Foreign key removed.');
            Flight::redirect("/projects/$uid?tab=diagram");
        }));
        Flight::route('POST /projects/@uid/functions', fn (string $uid) => $this->action(function (array $in) use ($uid): void {
            $this->functions->create($uid, (string) ($in['name'] ?? ''), (string) ($in['code'] ?? ''), (string) ($in['description'] ?? ''), (string) ($in['event'] ?? ''));
            Http::flash('success', 'Function created.');
            Flight::redirect("/projects/$uid?tab=functions");
        }));
        Flight::route('POST /projects/@uid/functions/@function/delete', fn (string $uid, string $function) => $this->action(function () use ($uid, $function): void {
            $this->functions->delete($function);
            Http::flash('success', 'Function deleted.');
            Flight::redirect("/projects/$uid?tab=functions");
        }));
        Flight::route('POST /projects/@uid/functions/@function/toggle', fn (string $uid, string $function) => $this->action(function () use ($uid, $function): void {
            $fn = $this->functions->find($function);
            $this->functions->update($function, $fn['name'], $fn['code'], $fn['description'] ?? '', $fn['event'] ?? '', !$fn['is_active']);
            Http::flash('success', 'Function toggled.');
            Flight::redirect("/projects/$uid?tab=functions");
        }));
        Flight::route('POST /projects/@uid/migrations/migrate', fn (string $uid) => $this->action(function () use ($uid): void {
            $output = $this->migrations->migrate($uid);
            Http::flash('success', implode("\n", $output));
            Flight::redirect("/projects/$uid?tab=migrations");
        }));
        Flight::route('POST /projects/@uid/migrations/rollback', fn (string $uid) => $this->action(function () use ($uid): void {
            $output = $this->migrations->rollback($uid);
            Http::flash('success', implode("\n", $output));
            Flight::redirect("/projects/$uid?tab=migrations");
        }));
    }

    private function home(): void
    {
        if (!$this->installer->installed()) {
            Flight::redirect('/install');
            return;
        }
        if (Auth::check()) {
            Flight::redirect('/dashboard');
            return;
        }
        View::render('auth', ['title' => 'Sign in', 'flashes' => Http::flashes()]);
    }

    private function installPage(): void
    {
        if ($this->installer->installed()) {
            Flight::redirect('/');
            return;
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        View::render('install', [
            'title' => 'Install TMPBase',
            'checks' => $this->installer->checks(),
            'ready' => $this->installer->ready(),
            'suggestedUrl' => $scheme . '://' . $host,
            'flashes' => Http::flashes(),
        ]);
    }

    private function apiDocs(): void
    {
        View::render('api-docs', [
            'title' => 'API Docs', 'baseUrl' => $this->config['url'],
            'flashes' => Http::flashes(),
        ]);
    }

    private function backupsPage(): void
    {
        View::render('backups', [
            'title' => 'All Backups', 'backups' => $this->backups->allGlobal(),
            'flashes' => Http::flashes(),
        ]);
    }

    private function storagePage(): void
    {
        $projects = $this->projects->all();
        $files = $this->storage->allGlobal($projects);
        View::render('storage', [
            'title' => 'All Storage', 'files' => $files,
            'flashes' => Http::flashes(),
        ]);
    }

    private function licensesPage(): void
    {
        $projects = $this->projects->all();
        $licenses = $this->licenses->allGlobal();
        View::render('licenses-global', [
            'title' => 'All Licenses', 'projects' => $projects, 'licenses' => $licenses,
            'flashes' => Http::flashes(),
        ]);
    }

    private function dashboard(): void
    {
        $projects = $this->projects->all();
        $tables = 0;
        $records = 0;
        foreach ($projects as $project) {
            foreach ($this->schema->tables($project) as $table) {
                $tables++;
                $records += $table['count'];
            }
        }
        View::render('dashboard', [
            'title' => 'Dashboard', 'projects' => $projects, 'projectCount' => count($projects),
            'tableCount' => $tables, 'recordCount' => $records, 'logs' => $this->logs->recent(null, 12),
            'flashes' => Http::flashes(),
        ]);
    }

    private function project(string $uid): void
    {
        $project = $this->projects->find($uid);
        $tab = (string) ($_GET['tab'] ?? 'tables');
        $connections = $this->databaseBridge->all($uid);
        $selectedConnection = (string) ($_GET['connection'] ?? ($connections[0]['uid'] ?? ''));
        $mysqlTables = [];
        $mysqlTablesError = null;
        if ($tab === 'database' && $selectedConnection !== '') {
            try {
                $mysqlTables = $this->databaseBridge->mysqlTables($uid, $selectedConnection);
            } catch (\Throwable $e) {
                $mysqlTablesError = $e->getMessage();
            }
        }
        $sqlEditor = $_SESSION['_sql_editor'] ?? ['query' => 'SELECT * FROM your_table LIMIT 100;', 'target' => 'sqlite', 'result' => null];
        unset($_SESSION['_sql_editor']);
        $allTables = $this->schema->tables($project);
        $relations = $this->schema->relations($project);
        $columnsByTable = [];
        foreach ($allTables as $t) {
            try { $columnsByTable[$t['name']] = $this->schema->columns($project, $t['name']); } catch (\Throwable) { $columnsByTable[$t['name']] = []; }
        }
        $foreignKeys = [];
        $fnOutput = [];
        foreach ($allTables as $t) {
            $fks = $this->schema->foreignKeys($project, $t['name']);
            $foreignKeys = array_merge($foreignKeys, $fks);
        }
        $summary = $this->metrics->summary($uid);
        $requestsTimeline = $this->metrics->timeline('api_request', $uid);
        $storageUsage = $this->metrics->storageUsage($this->config['storage']);
        $storageHuman = function($bytes) {
            if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 1) . ' GB';
            if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
            if ($bytes >= 1024) return number_format($bytes / 1024, 1) . ' KB';
            return $bytes . ' B';
        };
        preg_match('/^\d+\.\d+\.\d+/', file_get_contents($this->config['root'] . '/composer.json'), $m);

        View::render('project', [
            'title' => $project['name'], 'project' => $project, 'tables' => $allTables,
            'logs' => $this->logs->recent($uid, 100), 'backups' => $this->backups->all($uid),
            'files' => $this->storage->all($project), 'webhooks' => $this->webhooks->all($uid),
            'licenses' => $this->licenses->all($uid),
            'tab' => $tab, 'flashes' => Http::flashes(),
            'projectApiUrl' => $this->config['url'] . '/api/' . $project['uid'],
            'connections' => $connections, 'selectedConnection' => $selectedConnection,
            'mysqlTables' => $mysqlTables, 'mysqlTablesError' => $mysqlTablesError,
            'sqlEditor' => $sqlEditor,
            'foreignKeys' => $foreignKeys,
            'relations' => $relations,
            'columnsByTable' => $columnsByTable,
            'functions' => $this->functions->all($uid),
            'migrations' => $this->migrations->all(),
            'output' => [],
            'summary' => $summary,
            'storageUsage' => $storageUsage,
            'storageHuman' => $storageHuman($storageUsage['total']),
            'requestsTimeline' => $requestsTimeline,
            'fnCount' => count($this->functions->all($uid)),
        ]);
    }

    private function syncJson(string $uid, string $table): void
    {
        $project = $this->projects->find($uid);
        $from = (string) ($_GET['from'] ?? '');
        $to = $_GET['to'] ?? null;
        $data = $this->records->modified($project, $table, $from, $to);
        Flight::json(['data' => $data, 'server_time' => date('Y-m-d H:i:s')]);
    }

    private function table(string $uid, string $table): void
    {
        $project = $this->projects->find($uid);
        $columns = $this->schema->columns($project, $table);
        $result = $this->records->paginate($project, $table, $_GET);
        $files = $this->storage->all($project);
        $uploadedImages = [];
        foreach ($files as $file) {
            if (str_starts_with((string) ($file['mime_type'] ?? ''), 'image/')) {
                $uploadedImages[(string) $file['url']] = $file;
                $uploadedImages[(string) $file['uid']] = $file;
            }
        }
        $tableImages = [];
        if (($_GET['tab'] ?? 'data') === 'images') {
            $references = [];
            foreach ($this->records->all($project, $table) as $row) {
                foreach ($columns as $column) {
                    $value = (string) ($row[$column['name']] ?? '');
                    if ($value !== '') $references[$value][] = ['record_uid' => $row['uid'], 'column' => $column['name']];
                }
            }
            $directories = $table === 'productos' ? ['productos', 'products'] : [$table];
            foreach ($files as $file) {
                if (!str_starts_with((string) ($file['mime_type'] ?? ''), 'image/')) continue;
                $linked = array_merge($references[(string) $file['url']] ?? [], $references[(string) $file['uid']] ?? []);
                if ($linked || in_array(trim((string) ($file['directory'] ?? '/'), '/'), $directories, true)) {
                    $tableImages[] = ['file' => $file, 'references' => $linked];
                }
            }
        }
        View::render('table', [
            'title' => $table, 'project' => $project, 'table' => $table, 'columns' => $columns,
            'rows' => $result['data'], 'meta' => $result['meta'], 'tab' => $_GET['tab'] ?? 'data',
            'accessMode' => $this->schema->accessMode($project, $table), 'flashes' => Http::flashes(),
            'baseUrl' => $this->config['url'], 'uploadedImages' => $uploadedImages, 'tableImages' => $tableImages,
        ]);
    }

    private function export(string $uid, string $table): void
    {
        $project = $this->projects->find($uid);
        $format = ($_GET['format'] ?? 'json') === 'csv' ? 'csv' : 'json';
        $rows = $this->records->all($project, $table);
        header('Content-Type: ' . ($format === 'csv' ? 'text/csv' : 'application/json'));
        header('Content-Disposition: attachment; filename="' . $table . '.' . $format . '"');
        echo $this->transfer->export($rows, $format);
    }

    private function pdfInvoice(string $uid, string $table, string $recordUid): void
    {
        $project = $this->projects->find($uid);
        $record = $this->records->find($project, $table, $recordUid);
        $pdf = $this->pdf->invoice($project, $table, $record);

        if (str_starts_with($pdf, '%PDF')) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="factura_' . $recordUid . '.pdf"');
            echo $pdf;
        } else {
            header('Content-Type: text/html; charset=utf-8');
            echo $pdf;
        }
        exit;
    }

    private function downloadBackup(string $uid, string $backupUid): void
    {
        $backup = $this->backups->find($backupUid, $this->projects->find($uid)['uid']);
        header('Content-Type: application/vnd.sqlite3');
        header('Content-Disposition: attachment; filename="' . basename($backup['file_path']) . '"');
        header('Content-Length: ' . filesize($backup['file_path']));
        readfile($backup['file_path']);
    }

    private function page(callable $callback): void
    {
        if (!$this->installer->installed()) {
            Flight::redirect('/install');
            return;
        }
        if (!Auth::check()) {
            Http::flash('error', 'Sign in to continue.');
            Flight::redirect('/');
            return;
        }
        try {
            $callback();
        } catch (\Throwable $e) {
            Http::flash('error', $this->config['debug'] ? $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine() : $e->getMessage());
            Flight::redirect('/dashboard');
        }
    }

    private function action(callable $callback, bool $requiresAuth = true): void
    {
        try {
            if ($requiresAuth && !Auth::check()) {
                throw new \RuntimeException('Your session expired.');
            }
            $input = Http::input();
            Csrf::verify($input['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null));
            $callback($input);
        } catch (\Throwable $e) {
            Http::flash('error', $e->getMessage());
            Flight::redirect($_SERVER['HTTP_REFERER'] ?? '/');
        }
    }
}
