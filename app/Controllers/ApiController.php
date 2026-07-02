<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http;
use App\Services\ApiKeyService;
use App\Services\BackupService;
use App\Services\ImportExportService;
use App\Services\LogService;
use App\Services\ProjectService;
use App\Services\RecordService;
use App\Services\SchemaService;
use App\Services\LicenseService;
use App\Services\StorageService;
use App\Services\WebhookService;
use Flight;

final class ApiController
{
    public function __construct(
        private array $config,
        private ProjectService $projects,
        private SchemaService $schema,
        private RecordService $records,
        private ImportExportService $transfer,
        private StorageService $storage,
        private ApiKeyService $keys,
        private WebhookService $webhooks,
        private LicenseService $licenses,
        private LogService $logs,
        private BackupService $backups,
    ) {
    }

    public function register(): void
    {
        Flight::route('POST /api/license/validate', function (): void {
            try {
                $input = Http::input();
                $key = trim((string) ($input['license_key'] ?? ''));
                $system = trim((string) ($input['system_name'] ?? ''));
                if ($key === '') {
                    Flight::json(['valid' => false, 'error' => 'license_key is required.'], 422);
                    return;
                }
                $result = $this->licenses->validate($key, $system ?: null);
                Flight::json($result, $result['valid'] ? 200 : 403);
            } catch (\Throwable $e) {
                Flight::json(['valid' => false, 'error' => $e->getMessage()], 400);
            }
        });
        Flight::route('POST /api/project/create', function (): void {
            try {
                $input = Http::input();
                $project = $this->projects->create($input);
                $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
                $this->licenses->create($project['uid'], [
                    'system_name' => (string) ($input['system_name'] ?? $project['name']),
                    'tipo' => 'trial',
                    'expires_at' => $expiresAt,
                    'max_uses' => 1,
                    'nombre' => (string) ($input['nombre'] ?? $input['name'] ?? ''),
                    'project_url' => $this->config['url'] . '/api/' . $project['uid'],
                    'public_key' => $project['public_key'],
                    'secret_key' => $project['secret_key'],
                ]);
                $licenses = $this->licenses->all($project['uid']);
                Flight::json(['data' => ['project' => $project, 'license' => $licenses[0] ?? null]], 201);
            } catch (\Throwable $e) {
                Flight::json(['error' => $e->getMessage()], $e instanceof \InvalidArgumentException ? 422 : 400);
            }
        });
        Flight::route('POST /api/@project/licenses', fn ($project) => $this->runProject($project, true, function ($p): void {
            $input = Http::input();
            $license = $this->licenses->create($p['uid'], $input);
            $this->webhooks->dispatch('license.created', $p, 'licenses', $license);
            Flight::json(['data' => $license]);
        }));
        Flight::route('PUT /api/@project/licenses/@uid', fn ($project, $uid) => $this->runProject($project, true, function ($p) use ($uid): void {
            $input = Http::input();
            $license = $this->licenses->update($uid, $input);
            $this->webhooks->dispatch('license.updated', $p, 'licenses', $license);
            Flight::json(['data' => $license]);
        }));
        Flight::route('GET /api/@project/licenses', fn ($project) => $this->runProject($project, false, function ($p): void {
            Flight::json(['data' => $this->licenses->all($p['uid'])]);
        }));
        Flight::route('GET /api/@project/licenses/@uid', fn ($project, $uid) => $this->runProject($project, false, function ($p) use ($uid): void {
            Flight::json(['data' => $this->licenses->find($uid)]);
        }));
        Flight::route('GET /api/@project/licenses/@uid/devices', fn ($project, $uid) => $this->runProject($project, false, function ($p) use ($uid): void {
            $license = $this->licenses->find($uid);
            $devices = $license['dispositivos'] ? (json_decode($license['dispositivos'], true) ?? []) : [];
            Flight::json(['data' => ['devices' => $devices, 'count' => count($devices), 'max_devices' => (int) $license['max_uses']]]);
        }));
        Flight::route('POST /api/@project/licenses/@uid/authorize-device', fn ($project, $uid) => $this->runProject($project, true, function ($p) use ($uid): void {
            $input = Http::input();
            $deviceId = (string) ($input['device_id'] ?? '');
            if ($deviceId === '') {
                throw new \InvalidArgumentException('device_id is required.');
            }
            $license = $this->licenses->authorizeDevice($uid, $deviceId);
            Flight::json(['data' => ['devices' => $license['dispositivos'] ? json_decode($license['dispositivos'], true) : []]]);
        }));
        Flight::route('POST /api/@project/licenses/@uid/status', fn ($project, $uid) => $this->runProject($project, true, function ($p) use ($uid): void {
            $input = Http::input();
            $status = (string) ($input['status'] ?? 'active');
            $license = $this->licenses->setStatus($uid, $status);
            $this->webhooks->dispatch('license.status_changed', $p, 'licenses', $license);
            Flight::json(['data' => $license]);
        }));
        Flight::route('DELETE /api/@project/licenses/@uid', fn ($project, $uid) => $this->runProject($project, true, function ($p) use ($uid): void {
            $license = $this->licenses->find($uid);
            $this->licenses->delete($uid);
            $this->webhooks->dispatch('license.deleted', $p, 'licenses', $license);
            Flight::json(['data' => ['deleted' => true]]);
        }));
        Flight::route('POST /api/license/connect', function (): void {
            $this->handleLicenseConnect();
        });
        Flight::route('GET /licenses/info', function (): void {
            $this->handleLicenseInfo();
        });
        Flight::route('POST /licenses/info', function (): void {
            $this->handleLicenseInfo();
        });
        Flight::route('GET /api/license/info', function (): void {
            $this->handleLicenseInfo();
        });
        Flight::route('POST /api/license/info', function (): void {
            $this->handleLicenseInfo();
        });
        Flight::route('POST /api/license/use', function (): void {
            try {
                $input = Http::input();
                $key = trim((string) ($input['license_key'] ?? ''));
                if ($key === '') {
                    Flight::json(['valid' => false, 'error' => 'license_key is required.'], 422);
                    return;
                }
                $result = $this->licenses->use($key);
                Flight::json($result, $result['valid'] ? 200 : 403);
            } catch (\Throwable $e) {
                Flight::json(['valid' => false, 'error' => $e->getMessage()], 400);
            }
        });
        Flight::route('GET /api/@project/sync', fn ($project) => $this->runProject($project, false, function ($p): void {
            $since = (string) ($_GET['since'] ?? '');
            if ($since === '') {
                throw new \InvalidArgumentException('since parameter is required (ISO datetime).');
            }
            $tables = $this->schema->tables($p);
            $changes = [];
            foreach ($tables as $table) {
                $name = $table['name'];
                $rows = $this->records->modified($p, $name, $since);
                if ($rows) {
                    $changes[$name] = ['updated' => $rows, 'deleted' => []];
                }
            }
            $deleted = $this->logs->deletedSince($p['uid'], $since);
            foreach ($deleted as $table => $items) {
                if (isset($changes[$table])) {
                    $changes[$table]['deleted'] = $items;
                } else {
                    $changes[$table] = ['updated' => [], 'deleted' => $items];
                }
            }
            Flight::json([
                'changes' => $changes,
                'server_time' => date('Y-m-d H:i:s'),
            ]);
        }));
        Flight::route('GET /api/@project/health', fn ($project) => $this->runProject($project, false, function ($p): void {
            Flight::json([
                'status' => 'ok',
                'data' => [
                    'project_uid' => $p['uid'],
                    'project_name' => $p['name'],
                    'tables' => count($this->schema->tables($p)),
                ],
            ]);
        }));
        Flight::route('DELETE /api/@project', fn ($project) => $this->runProject($project, true, function ($p): void {
            $this->projects->delete($p['uid']);
            Flight::json(['data' => ['deleted' => true, 'project_uid' => $p['uid']]]);
        }));
        Flight::route('POST /api/@project/backups', fn ($project) => $this->runProject($project, true, function ($p): void {
            $backup = $this->backups->create($p);
            Flight::json(['data' => $backup], 201);
        }));
        Flight::route('GET /api/@project/backups', fn ($project) => $this->runProject($project, false, function ($p): void {
            Flight::json(['data' => $this->backups->all($p['uid'])]);
        }));
        Flight::route('GET /api/@project/backups/@uid/download', fn ($project, $uid) => $this->runProject($project, true, function ($p) use ($uid): void {
            $backup = $this->backups->find($uid, $p['uid']);
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($backup['file_path']) . '"');
            header('Content-Length: ' . filesize($backup['file_path']));
            readfile($backup['file_path']);
            exit;
        }));
        Flight::route('POST /api/@project/backups/@uid/restore', fn ($project, $uid) => $this->runProject($project, true, function ($p) use ($uid): void {
            $this->backups->restore($p, $uid);
            Flight::json(['data' => ['restored' => true, 'uid' => $uid]]);
        }));
        Flight::route('DELETE /api/@project/backups/@uid', fn ($project, $uid) => $this->runProject($project, true, function ($p) use ($uid): void {
            $this->backups->delete($p, $uid);
            Flight::json(['data' => ['deleted' => true, 'uid' => $uid]]);
        }));
        Flight::route('GET /api/@project/schema', fn ($project) => $this->runProject($project, false, function ($p): void {
            $tables = $this->schema->tables($p);
            $result = [];
            foreach ($tables as $table) {
                $name = $table['name'];
                $result[$name] = [
                    'count' => $table['count'],
                    'columns' => $this->schema->columns($p, $name),
                ];
            }
            Flight::json(['data' => $result]);
        }));
        Flight::route('GET /api/@project/schema/tables', fn ($project) => $this->runProject($project, false, function ($p): void {
            Flight::json(['data' => $this->schema->tables($p)]);
        }));
        Flight::route('GET /api/@project/schema/tables/@table', fn ($project, $table) => $this->runProject($project, false, function ($p) use ($table): void {
            Flight::json(['data' => $this->schema->columns($p, $table)]);
        }));
        Flight::route('POST /api/@project/schema/tables/batch', fn ($project) => $this->runProject($project, true, function ($p): void {
            $input = Http::input();
            $tables = $input['tables'] ?? [];
            if (!is_array($tables) || count($tables) > 100) {
                throw new \InvalidArgumentException('Provide an array containing at most 100 tables.');
            }
            $existing = array_column($this->schema->tables($p), 'name');
            $created = 0;
            $skipped = 0;
            $columnsCreated = 0;
            $errors = [];
            foreach ($tables as $definition) {
                $name = (string) ($definition['name'] ?? '');
                try {
                    $fields = array_values(array_filter(
                        is_array($definition['columns'] ?? null) ? $definition['columns'] : [],
                        static fn (array $field): bool => !in_array($field['name'] ?? '', SchemaService::PROTECTED, true)
                    ));
                    if (in_array($name, $existing, true)) {
                        $remoteColumns = array_column($this->schema->columns($p, $name), 'name');
                        foreach ($fields as $field) {
                            $column = (string) ($field['name'] ?? '');
                            if ($column === '' || in_array($column, $remoteColumns, true)) {
                                continue;
                            }
                            $this->schema->addColumn($p, $name, $field);
                            $remoteColumns[] = $column;
                            $columnsCreated++;
                        }
                        $skipped++;
                        continue;
                    }
                    $this->schema->createTable($p, $name, $fields);
                    $existing[] = $name;
                    $created++;
                } catch (\Throwable $e) {
                    $errors[] = ['table' => $name, 'error' => $e->getMessage()];
                }
            }
            Flight::json(['data' => [
                'created' => $created,
                'skipped' => $skipped,
                'columns_created' => $columnsCreated,
                'failed' => count($errors),
                'errors' => $errors,
                'message' => "$created created, $skipped already existed, $columnsCreated columns added, " . count($errors) . ' failed.',
            ]], $errors ? 207 : 200);
        }));
        Flight::route('POST /api/@project/storage/upload', fn ($project) => $this->runStorage($project, function ($p): void {
            $directory = (string) ($_POST['directory'] ?? $_GET['directory'] ?? '/');
            Flight::json(['data' => $this->storage->upload($p, $_FILES['file'] ?? [], $directory)], 201);
        }));
        Flight::route('GET /api/@project/storage/files', fn ($project) => $this->runStorage($project, function ($p): void {
            $directory = $_GET['directory'] ?? null;
            Flight::json(['data' => $this->storage->all($p, $directory ? (string) $directory : null)]);
        }));
        Flight::route('GET /api/@project/storage/@uid', fn ($project, $uid) => $this->serveFile($project, $uid));
        Flight::route('DELETE /api/@project/storage/@uid', fn ($project, $uid) => $this->runStorage($project, function ($p) use ($uid): void {
            $this->storage->delete($p, $uid);
            Flight::json(['message' => 'File deleted.']);
        }));
        Flight::route('POST /api/@project/@table/upsert', fn ($project, $table) => $this->run($project, $table, fn ($p) => Flight::json(
            ['data' => $this->records->upsert($p, $table, Http::input()['rows'] ?? Http::input())]
        )));
        Flight::route('GET /api/@project/@table/export', fn ($project, $table) => $this->run($project, $table, function ($p) use ($table): void {
            $format = ($_GET['format'] ?? 'json') === 'csv' ? 'csv' : 'json';
            $rows = $this->records->all($p, $table);
            header('Content-Type: ' . ($format === 'csv' ? 'text/csv' : 'application/json'));
            header("Content-Disposition: attachment; filename=\"$table.$format\"");
            echo $this->transfer->export($rows, $format);
        }));
        Flight::route('GET /api/@project/@table/sync', fn ($project, $table) => $this->run($project, $table, fn ($p) => Flight::json([
            'data' => $this->records->modified($p, $table, (string) ($_GET['from'] ?? ''), $_GET['to'] ?? null),
        ])));
        Flight::route('GET /api/@project/@table/modified-since', fn ($project, $table) => $this->run($project, $table, fn ($p) => Flight::json([
            'data' => $this->records->modified($p, $table, (string) ($_GET['since'] ?? '')),
        ])));
        Flight::route('POST /api/@project/@table/bulk', fn ($project, $table) => $this->run($project, $table, fn ($p) => Flight::json(
            $this->records->bulk($p, $table, Http::input()), 201
        )));
        Flight::route('GET /api/@project/@table', fn ($project, $table) => $this->run($project, $table, fn ($p) => Flight::json(
            $this->records->paginate($p, $table, $_GET)
        )));
        Flight::route('POST /api/@project/@table', fn ($project, $table) => $this->run($project, $table, function ($p) use ($table): void {
            $record = $this->records->create($p, $table, Http::input());
            $this->webhooks->dispatch('record.created', $p, $table, $record);
            Flight::json(['data' => $record], 201);
        }));
        Flight::route('DELETE /api/@project/@table', fn ($project, $table) => $this->run($project, $table, function ($p) use ($table): void {
            $rows = $this->records->all($p, $table);
            $this->schema->truncate($p, $table);
            $imagesDeleted = $this->storage->deleteImagesFromRowsIfUnreferenced($p, $rows);
            $this->webhooks->dispatch('table.truncated', $p, $table, null);
            Flight::json(['message' => 'Table emptied.', 'images_deleted' => $imagesDeleted]);
        }));
        Flight::route('GET /api/@project/@table/@uid', fn ($project, $table, $uid) => $this->run($project, $table, fn ($p) => Flight::json([
            'data' => $this->records->find($p, $table, $uid),
        ])));
        Flight::route('PUT /api/@project/@table/@uid', fn ($project, $table, $uid) => $this->run($project, $table, function ($p) use ($table, $uid): void {
            $record = $this->records->update($p, $table, $uid, Http::input());
            $this->webhooks->dispatch('record.updated', $p, $table, $record);
            Flight::json(['data' => $record]);
        }));
        Flight::route('DELETE /api/@project/@table/@uid', fn ($project, $table, $uid) => $this->run($project, $table, function ($p) use ($table, $uid): void {
            $record = $this->records->find($p, $table, $uid);
            $this->records->delete($p, $table, $uid);
            $imagesDeleted = $this->storage->deleteImagesFromRowsIfUnreferenced($p, [$record]);
            $this->webhooks->dispatch('record.deleted', $p, $table, $record);
            Flight::json(['message' => 'Record deleted.', 'images_deleted' => $imagesDeleted]);
        }));
    }

    private function run(string $projectUid, string $table, callable $callback): void
    {
        try {
            $project = $this->projects->find($projectUid);
            $this->schema->columns($project, $table);
            $this->keys->authorize($project, $table, $_SERVER['REQUEST_METHOD'] ?? 'GET', $this->bearer());
            $callback($project);
        } catch (\Throwable $e) {
            $status = in_array($e->getCode(), [401, 403, 404, 429], true) ? $e->getCode() : ($e instanceof \InvalidArgumentException ? 422 : 400);
            Flight::json(['error' => $e->getMessage()], $status);
        }
    }

    private function runStorage(string $projectUid, callable $callback): void
    {
        try {
            $project = $this->projects->find($projectUid);
            $key = $this->bearer();
            if (!$key || !hash_equals($project['secret_key'], $key)) {
                throw new \RuntimeException('Secret key required.', 401);
            }
            $callback($project);
        } catch (\Throwable $e) {
            Flight::json(['error' => $e->getMessage()], $e->getCode() === 401 ? 401 : 400);
        }
    }

    private function runProject(string $projectUid, bool $secretOnly, callable $callback): void
    {
        try {
            $project = $this->projects->find($projectUid);
            $this->keys->authorizeProject($project, $this->bearer(), $secretOnly);
            $callback($project);
        } catch (\Throwable $e) {
            $status = in_array($e->getCode(), [401, 403, 429], true) ? $e->getCode() : ($e instanceof \InvalidArgumentException ? 422 : 400);
            Flight::json(['error' => $e->getMessage()], $status);
        }
    }

    private function serveFile(string $projectUid, string $uid): void
    {
        try {
            $project = $this->projects->find($projectUid);
            $file = $this->storage->find($project, $uid);
            header('Content-Type: ' . $file['mime_type']);
            header('Content-Length: ' . $file['size']);
            header('Content-Disposition: inline; filename="' . addslashes($file['original_name']) . '"');
            readfile($file['path']);
        } catch (\Throwable $e) {
            Flight::json(['error' => $e->getMessage()], 404);
        }
    }

    private function handleLicenseInfo(): void
    {
        try {
            $key = trim((string) (Http::input()['license_key'] ?? $_GET['license_key'] ?? ''));
            if ($key === '') {
                Flight::json(['error' => 'license_key is required.'], 422);
                return;
            }
            $license = $this->licenses->findByKey($key);
            Flight::json(['data' => $license]);
        } catch (\Throwable $e) {
            Flight::json(['error' => $e->getMessage()], 404);
        }
    }

    private function handleLicenseConnect(): void
    {
        try {
            $input = Http::input();
            $key = trim((string) ($input['license_key'] ?? ''));
            $device = trim((string) ($input['device_id'] ?? ''));
            if ($key === '' || $device === '') {
                Flight::json(['error' => 'license_key and device_id are required.'], 422);
                return;
            }
            $check = $this->licenses->validate($key);
            if (!$check['valid']) {
                Flight::json($check, 403);
                return;
            }
            $result = $this->licenses->registerDevice($key, $device);
            $license = $this->licenses->findByKey($key);
            Flight::json([
                'success' => $result['success'],
                'device_id' => $device,
                'device_registered' => $result['device_registered'] ?? false,
                'devices' => $license['dispositivos'] ? json_decode($license['dispositivos'], true) : [],
                'max_devices' => (int) $license['max_uses'],
                'data' => $license,
            ]);
        } catch (\Throwable $e) {
            Flight::json(['error' => $e->getMessage()], 400);
        }
    }

    private function bearer(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        return preg_match('/^Bearer\s+(.+)$/i', $header, $matches) ? trim($matches[1]) : null;
    }
}
