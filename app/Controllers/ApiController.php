<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http;
use App\Services\ApiKeyService;
use App\Services\BackupService;
use App\Services\ImportExportService;
use App\Services\MetricsService;
use App\Services\MailService;
use App\Services\SharedDocumentService;
use App\Services\PdfService;
use App\Core\PortalAuth;
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
        private MetricsService $metrics,
        private MailService $mail,
        private SharedDocumentService $sharedDocuments,
        private PdfService $pdf,
        private PortalAuth $portalAuth,
    ) {
    }

    public function register(): void
    {
        Flight::route('POST /api/license/validate', function (): void {
            try {
                $input = Http::input();
                $key = trim((string) ($input['license_key'] ?? ''));
                $this->keys->rateLimitPublic('license-validate:' . hash('sha256', $key), 30);
                $system = trim((string) ($input['system_name'] ?? ''));
                if ($key === '') {
                    Http::error('license_key is required.', 422, extra: ['valid' => false]);
                    return;
                }
                $result = $this->licenses->validate($key, $system ?: null);
                Flight::json($result, $result['valid'] ? 200 : 403);
            } catch (\Throwable $e) {
                Http::error($e, 400, extra: ['valid' => false]);
            }
        });
        Flight::route('POST /api/project/create', function (): void {
            try {
                $this->keys->rateLimitPublic('project-create', 3);
                $this->keys->rateLimitPublic('project-create-daily', 20, 86400);
                $input = Http::input();
                $project = $this->projects->create($input);
                $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
                $this->licenses->create($project['uid'], [
                    'system_name' => (string) ($input['system_name'] ?? $project['name']),
                    'tipo' => 'trial',
                    'expires_at' => $expiresAt,
                    'max_uses' => 1,
                    'nombre' => (string) ($input['nombre'] ?? $input['name'] ?? ''),
                    'rnc' => (string) ($input['rnc'] ?? ''),
                    'telefono' => (string) ($input['telefono'] ?? $input['phone'] ?? ''),
                    'email' => (string) ($input['email'] ?? ''),
                    'direccion' => (string) ($input['direccion'] ?? $input['address'] ?? ''),
                    'project_url' => $this->config['url'] . '/api/' . $project['uid'],
                    'public_key' => $project['public_key'],
                    'secret_key' => $project['secret_key'],
                ]);
                $licenses = $this->licenses->all($project['uid']);
                Flight::json(['data' => ['project' => $project, 'license' => isset($licenses[0]) ? $this->licenses->apiData($licenses[0], true) : null]], 201);
            } catch (\Throwable $e) {
                Http::error($e, $e instanceof \InvalidArgumentException ? 422 : 400);
            }
        });
        Flight::route('POST /api/@project/licenses', fn ($project) => $this->runProject($project, true, function ($p): void {
            $input = Http::input();
            $license = $this->licenses->create($p['uid'], $input);
            $this->webhooks->dispatch('license.created', $p, 'licenses', $license);
            Flight::json(['data' => $this->licenses->apiData($license, true)]);
        }));
        Flight::route('PUT /api/@project/licenses/@uid', fn ($project, $uid) => $this->runProject($project, true, function ($p) use ($uid): void {
            $input = Http::input();
            $this->licenses->findForProject($uid, $p['uid']);
            $license = $this->licenses->update($uid, $input);
            $this->webhooks->dispatch('license.updated', $p, 'licenses', $license);
            Flight::json(['data' => $this->licenses->apiData($license, true)]);
        }));
        Flight::route('GET /api/@project/licenses', fn ($project) => $this->runProject($project, false, function ($p): void {
            Flight::json(['data' => array_map(fn (array $license): array => $this->licenses->apiData($license, true), $this->licenses->all($p['uid']))]);
        }));
        Flight::route('GET /api/@project/licenses/@uid', fn ($project, $uid) => $this->runProject($project, false, function ($p) use ($uid): void {
            Flight::json(['data' => $this->licenses->apiData($this->licenses->findForProject($uid, $p['uid']), true)]);
        }));
        Flight::route('GET /api/@project/licenses/@uid/devices', fn ($project, $uid) => $this->runProject($project, false, function ($p) use ($uid): void {
            $license = $this->licenses->findForProject($uid, $p['uid']);
            $groups = $this->licenses->devicesForLicense($license['uid']);
            Flight::json(['data' => [
                ...$groups,
                'devices' => $groups['authorized_devices'],
                'unauthorized_devices' => $groups['pending_devices'],
                'count' => count($groups['authorized_devices']),
                'pending_count' => count($groups['pending_devices']),
                'max_devices' => (int) $license['max_uses'],
            ]]);
        }));
        Flight::route('POST /api/@project/licenses/@uid/block-device', fn ($project, $uid) => $this->runProject($project, true, function ($p) use ($uid): void {
            $deviceId = trim((string) (Http::input()['device_id'] ?? ''));
            if ($deviceId === '') throw new \InvalidArgumentException('device_id is required.');
            $this->licenses->findForProject($uid, $p['uid']);
            $license = $this->licenses->blockDevice($uid, $deviceId);
            Flight::json(['data' => $this->licenses->apiData($license, true)]);
        }));
        Flight::route('POST /api/@project/licenses/@uid/revoke-device', fn ($project, $uid) => $this->runProject($project, true, function ($p) use ($uid): void {
            $deviceId = trim((string) (Http::input()['device_id'] ?? ''));
            if ($deviceId === '') throw new \InvalidArgumentException('device_id is required.');
            $this->licenses->findForProject($uid, $p['uid']);
            $license = $this->licenses->revokeDevice($uid, $deviceId);
            Flight::json(['data' => $this->licenses->apiData($license, true)]);
        }));
        Flight::route('POST /api/@project/licenses/@uid/authorize-device', fn ($project, $uid) => $this->runProject($project, true, function ($p) use ($uid): void {
            $input = Http::input();
            $deviceId = (string) ($input['device_id'] ?? '');
            if ($deviceId === '') {
                throw new \InvalidArgumentException('device_id is required.');
            }
            $this->licenses->findForProject($uid, $p['uid']);
            $license = $this->licenses->authorizeDevice($uid, $deviceId);
            Flight::json(['data' => ['devices' => $license['dispositivos'] ? json_decode($license['dispositivos'], true) : []]]);
        }));
        Flight::route('POST /api/@project/licenses/@uid/status', fn ($project, $uid) => $this->runProject($project, true, function ($p) use ($uid): void {
            $input = Http::input();
            $status = (string) ($input['status'] ?? 'active');
            $this->licenses->findForProject($uid, $p['uid']);
            $license = $this->licenses->setStatus($uid, $status);
            $this->webhooks->dispatch('license.status_changed', $p, 'licenses', $license);
            Flight::json(['data' => $license]);
        }));
        Flight::route('DELETE /api/@project/licenses/@uid', fn ($project, $uid) => $this->runProject($project, true, function ($p) use ($uid): void {
            $license = $this->licenses->findForProject($uid, $p['uid']);
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
                $this->keys->rateLimitPublic('license-use:' . hash('sha256', $key), 20);
                if ($key === '') {
                    Http::error('license_key is required.', 422, extra: ['valid' => false]);
                    return;
                }
                $result = $this->licenses->use($key);
                Flight::json($result, $result['valid'] ? 200 : 403);
            } catch (\Throwable $e) {
                Http::error($e, 400, extra: ['valid' => false]);
            }
        });
        Flight::route('POST /api/license/mail/send', function (): void {
            try {
                $input = Http::input();
                $licenseKey = trim((string) ($input['license_key'] ?? ''));
                $deviceId = trim((string) ($input['device_id'] ?? ''));
                $this->keys->rateLimitPublic('license-mail:' . hash('sha256', $licenseKey . ':' . $deviceId), 15);
                if ($licenseKey === '' || $deviceId === '') throw new \InvalidArgumentException('license_key and device_id are required.');
                $license = $this->licenses->findByKey($licenseKey);
                if ($license['status'] !== 'active' || ($license['expires_at'] && $license['expires_at'] < date('Y-m-d H:i:s'))) throw new \RuntimeException('License is not active.', 403);
                if (!$this->licenses->isDeviceAuthorized($license['uid'], $deviceId)) throw new \RuntimeException('Device is not authorized.', 403);
                $project = $this->projects->findActive($license['project_uid']);
                $template = strtolower(trim((string) ($input['template'] ?? '')));
                $recipient = (string) ($input['to'] ?? '');
                $data = is_array($input['data'] ?? null) ? $input['data'] : [];
                $share = null;
                if ($template === 'invoice') {
                    $recordUid = trim((string) ($input['record_uid'] ?? $data['record_uid'] ?? ''));
                    $invoice = $this->records->find($project, 'facturas', $recordUid);
                    $share = $this->sharedDocuments->create($project['uid'], 'invoice', 'facturas', $recordUid, gmdate('Y-m-d H:i:s', time() + 2592000));
                    $data = [
                        'company_name' => $project['name'], 'invoice_number' => $invoice['numero'] ?? $recordUid,
                        'total' => $invoice['total'] ?? null, 'status' => $invoice['estado'] ?? null,
                        'share_url' => $share['url'],
                    ];
                }
                $job = $this->mail->queue($project['uid'], $template, $recipient, $data);
                Flight::json(['data' => ['mail' => $job, 'share' => $share]], 202);
            } catch (\Throwable $e) {
                $status = in_array($e->getCode(), [403, 429], true) ? $e->getCode() : ($e instanceof \InvalidArgumentException ? 422 : 400);
                Http::error($e, $status);
            }
        });
        Flight::route('POST /api/license/invoice/share', function (): void {
            try {
                $input = Http::input();
                $licenseKey = trim((string) ($input['license_key'] ?? ''));
                $deviceId = trim((string) ($input['device_id'] ?? ''));
                $recordUid = trim((string) ($input['record_uid'] ?? ''));
                $this->keys->rateLimitPublic('license-share:' . hash('sha256', $licenseKey . ':' . $deviceId), 30);
                if ($licenseKey === '' || $deviceId === '' || $recordUid === '') {
                    throw new \InvalidArgumentException('license_key, device_id and record_uid are required.');
                }
                $license = $this->licenses->findByKey($licenseKey);
                if ($license['status'] !== 'active' || ($license['expires_at'] && $license['expires_at'] < date('Y-m-d H:i:s'))) {
                    throw new \RuntimeException('License is not active.', 403);
                }
                if (!$this->licenses->isDeviceAuthorized($license['uid'], $deviceId)) {
                    throw new \RuntimeException('Device is not authorized.', 403);
                }
                $project = $this->projects->findActive($license['project_uid']);
                $this->records->find($project, 'facturas', $recordUid);
                $share = $this->sharedDocuments->create(
                    $project['uid'],
                    'invoice',
                    'facturas',
                    $recordUid,
                    gmdate('Y-m-d H:i:s', time() + 2592000)
                );
                Flight::json(['data' => ['share' => $share]], 201);
            } catch (\Throwable $e) {
                $status = in_array($e->getCode(), [403, 429], true) ? $e->getCode() : ($e instanceof \InvalidArgumentException ? 422 : 400);
                Http::error($e, $status);
            }
        });
        Flight::route('POST /api/@project/mail/send', fn ($project) => $this->runProject($project, true, function ($p): void {
            $input = Http::input();
            $job = $this->mail->queue(
                $p['uid'],
                (string) ($input['template'] ?? ''),
                (string) ($input['to'] ?? ''),
                is_array($input['data'] ?? null) ? $input['data'] : []
            );
            Flight::json(['data' => $job], 202);
        }));
        Flight::route('GET /api/@project/mail/@uid', fn ($project, $uid) => $this->runProject($project, true, function ($p) use ($uid): void {
            Flight::json(['data' => $this->mail->status($p['uid'], $uid)]);
        }));
        Flight::route('POST /api/@project/portal/users', fn ($project) => $this->runProject($project, true, function ($p): void {
            Flight::json(['data' => $this->portalAuth->createForProject($p['uid'], Http::input())], 201);
        }));
        Flight::route('POST /api/@project/invoices/@uid/share', fn ($project, $uid) => $this->runProject($project, true, function ($p) use ($uid): void {
            $this->records->find($p, 'facturas', $uid);
            $input = Http::input();
            $expiresAt = trim((string) ($input['expires_at'] ?? '')) ?: null;
            Flight::json(['data' => $this->sharedDocuments->create($p['uid'], 'invoice', 'facturas', $uid, $expiresAt)], 201);
        }));
        Flight::route('POST /api/@project/invoices/@uid/email', fn ($project, $uid) => $this->runProject($project, true, function ($p) use ($uid): void {
            $invoice = $this->records->find($p, 'facturas', $uid);
            $input = Http::input();
            $share = $this->sharedDocuments->create($p['uid'], 'invoice', 'facturas', $uid, gmdate('Y-m-d H:i:s', time() + 2592000));
            $job = $this->mail->queue($p['uid'], 'invoice', (string) ($input['to'] ?? ''), [
                'company_name' => $p['name'], 'invoice_number' => $invoice['numero'] ?? $uid,
                'total' => $invoice['total'] ?? null, 'share_url' => $share['url'],
            ]);
            Flight::json(['data' => ['mail' => $job, 'share' => $share]], 202);
        }));
        Flight::route('DELETE /api/@project/shared-documents/@uid', fn ($project, $uid) => $this->runProject($project, true, function ($p) use ($uid): void {
            $this->sharedDocuments->revoke($p['uid'], $uid);
            Flight::json(['data' => ['revoked' => true]]);
        }));
        Flight::route('GET /share/invoice/@token', function ($token): void {
            $rawToken = (string) $token;
            $asPdf = str_ends_with($rawToken, '.pdf');
            if ($asPdf) $rawToken = substr($rawToken, 0, -4);
            $this->serveSharedInvoice($rawToken, $asPdf);
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
            Flight::json(['data' => $this->backups->apiData($backup)], 201);
        }));
        Flight::route('POST /api/@project/keys/rotate', fn ($project) => $this->runProject($project, true, function ($p): void {
            $result = $this->projects->rotateKey($p['uid'], strtolower(trim((string) (Http::input()['type'] ?? ''))));
            Flight::json(['data' => ['type' => $result['type'], 'key' => $result['key']]]);
        }));
        Flight::route('GET /api/@project/backups', fn ($project) => $this->runProject($project, false, function ($p): void {
            Flight::json(['data' => array_map(fn (array $backup): array => $this->backups->apiData($backup), $this->backups->all($p['uid']))]);
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
            Flight::json(['data' => $this->storage->apiData($this->storage->upload($p, $_FILES['file'] ?? [], $directory))], 201);
        }));
        Flight::route('GET /api/@project/storage/files', fn ($project) => $this->runStorage($project, function ($p): void {
            $directory = $_GET['directory'] ?? null;
            Flight::json(['data' => array_map(
                fn (array $file): array => $this->storage->apiData($file),
                $this->storage->all($p, $directory ? (string) $directory : null)
            )]);
        }));
        Flight::route('POST /api/@project/storage/cleanup', fn ($project) => $this->runStorage($project, function ($p): void {
            Flight::json(['data' => $this->storage->cleanupOrphans($p)]);
        }));
        Flight::route('GET /api/@project/storage/@uid', fn ($project, $uid) => $this->serveFile($project, $uid));
        Flight::route('DELETE /api/@project/storage/@uid', fn ($project, $uid) => $this->runStorage($project, function ($p) use ($uid): void {
            $this->storage->delete($p, $uid);
            Flight::json(['message' => 'File deleted.']);
        }));
        Flight::route('POST /api/@project/@table/upsert', fn ($project, $table) => $this->run($project, $table, function ($p) use ($table): void {
            $input = Http::input();
            $rows = isset($input['rows']) && is_array($input['rows'])
                ? $input['rows']
                : (array_is_list($input) ? $input : [$input]);
            Flight::json(['data' => $this->records->upsert($p, $table, $rows, filter_var($input['atomic'] ?? false, FILTER_VALIDATE_BOOL))]);
        }));
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
        Flight::route('POST /api/@project/@table/bulk', fn ($project, $table) => $this->run($project, $table, function ($p) use ($table): void {
            $input = Http::input();
            $rows = isset($input['rows']) && is_array($input['rows'])
                ? $input['rows']
                : (array_is_list($input) ? $input : [$input]);
            Flight::json($this->records->bulk($p, $table, $rows, filter_var($input['atomic'] ?? false, FILTER_VALIDATE_BOOL)), 201);
        }));
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
            $project = $this->projects->findActive($projectUid);
            $this->schema->columns($project, $table);
            $this->keys->authorize($project, $table, $_SERVER['REQUEST_METHOD'] ?? 'GET', $this->bearer());
            $this->metrics->track('api_request', $projectUid);
            $callback($project);
        } catch (\Throwable $e) {
            $status = in_array($e->getCode(), [401, 403, 404, 413, 429], true) ? $e->getCode() : ($e instanceof \InvalidArgumentException ? 422 : 400);
            Http::error($e, $status);
        }
    }

    private function runStorage(string $projectUid, callable $callback): void
    {
        try {
            $project = $this->projects->findActive($projectUid);
            $key = $this->bearer();
            if (!$key || !hash_equals($project['secret_key'], $key)) {
                throw new \RuntimeException('Secret key required.', 401);
            }
            $this->metrics->track('api_request', $projectUid);
            $callback($project);
        } catch (\Throwable $e) {
            Http::error($e, in_array($e->getCode(), [401, 403, 413, 429], true) ? $e->getCode() : 400);
        }
    }

    private function runProject(string $projectUid, bool $secretOnly, callable $callback): void
    {
        try {
            $project = $this->projects->findActive($projectUid);
            $this->keys->authorizeProject($project, $this->bearer(), $secretOnly);
            $this->metrics->track('api_request', $projectUid);
            $callback($project);
        } catch (\Throwable $e) {
            $status = in_array($e->getCode(), [401, 403, 404, 413, 429], true) ? $e->getCode() : ($e instanceof \InvalidArgumentException ? 422 : 400);
            Http::error($e, $status);
        }
    }

    private function serveFile(string $projectUid, string $uid): void
    {
        try {
            $project = $this->projects->findActive($projectUid);
            $file = $this->storage->find($project, $uid);
            header('Content-Type: ' . $file['mime_type']);
            header('Content-Length: ' . $file['size']);
            $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string) $file['original_name'])) ?: 'file';
            header('X-Content-Type-Options: nosniff');
            header('Content-Disposition: inline; filename="' . $safeName . '"');
            readfile($file['path']);
        } catch (\Throwable $e) {
            Http::error($e, 404);
        }
    }

    private function handleLicenseInfo(): void
    {
        try {
            $key = trim((string) (Http::input()['license_key'] ?? $_GET['license_key'] ?? ''));
            $this->keys->rateLimitPublic('license-info:' . hash('sha256', $key), 30);
            if ($key === '') {
                Http::error('license_key is required.', 422);
                return;
            }
            $license = $this->licenses->findByKey($key);
            Flight::json(['data' => $this->licenses->apiData($license)]);
        } catch (\Throwable $e) {
            Http::error($e, 404);
        }
    }

    private function handleLicenseConnect(): void
    {
        try {
            $input = Http::input();
            $key = trim((string) ($input['license_key'] ?? ''));
            $device = trim((string) ($input['device_id'] ?? ''));
            $this->keys->rateLimitPublic('license-connect:' . hash('sha256', $key), 20);
            if ($key === '' || $device === '') {
                Http::error('license_key and device_id are required.', 422);
                return;
            }
            $check = $this->licenses->validate($key);
            $usageLimitReached = !$check['valid'] && (($check['error'] ?? '') === 'License usage limit reached.');
            if (!$check['valid'] && !$usageLimitReached) {
                Flight::json($check, 403);
                return;
            }
            $result = $this->licenses->registerDevice($key, $device, [
            'ip_address' => Http::clientIp(),
                'app_version' => trim((string) ($input['app_version'] ?? ($_SERVER['HTTP_X_APP_VERSION'] ?? ''))) ?: null,
            ]);
            $license = $this->licenses->findByKey($key);
            Flight::json([
                'success' => $result['success'],
                'device_id' => $device,
                'device_registered' => $result['device_registered'] ?? false,
                'authorized' => $result['authorized'] ?? false,
                'pending_authorization' => $result['pending_authorization'] ?? true,
                'devices' => $license['dispositivos'] ? json_decode($license['dispositivos'], true) : [],
                'unauthorized_devices' => $license['equipos_no_autorizados'] ? json_decode($license['equipos_no_autorizados'], true) : [],
                'max_devices' => (int) $license['max_uses'],
                'data' => $this->licenses->apiData($license),
            ]);
        } catch (\Throwable $e) {
            Http::error($e, 400);
        }
    }

    private function bearer(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        return preg_match('/^Bearer\s+(.+)$/i', $header, $matches) ? trim($matches[1]) : null;
    }

    private function serveSharedInvoice(string $token, bool $asPdf): void
    {
        try {
            $this->keys->rateLimitPublic('shared-invoice:' . hash('sha256', $token), 60);
            $share = $this->sharedDocuments->resolve($token, 'invoice');
            $project = $this->projects->findActive($share['project_uid']);
            $invoice = $this->records->find($project, $share['table_name'], $share['record_uid']);
            $content = $asPdf
                ? $this->pdf->invoice($project, $share['table_name'], $invoice)
                : $this->pdf->invoiceHtml($project, $invoice);
            $etag = '"' . hash('sha256', $content) . '"';
            if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
                http_response_code(304);
                return;
            }
            $this->sharedDocuments->accessed($share['uid']);
            header('ETag: ' . $etag);
            header('Cache-Control: private, no-cache, must-revalidate');
            header('X-Robots-Tag: noindex, nofollow');
            header('Content-Type: ' . ($asPdf ? 'application/pdf' : 'text/html; charset=UTF-8'));
            if ($asPdf) header('Content-Disposition: inline; filename="factura.pdf"');
            echo $content;
        } catch (\Throwable) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Documento no disponible.';
        }
    }
}
