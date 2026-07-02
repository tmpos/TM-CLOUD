<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\ApiController;
use App\Controllers\WebController;
use App\Services\ApiKeyService;
use App\Services\BackupService;
use App\Services\ImportExportService;
use App\Services\InstallerService;
use App\Services\LogService;
use App\Services\ProjectService;
use App\Services\RecordService;
use App\Services\SchemaService;
use App\Services\StorageService;
use App\Services\RealtimeService;
use App\Services\WebhookService;
use App\Services\LicenseService;
use App\Services\DatabaseBridgeService;
use App\Services\FunctionService;
use App\Services\MetricsService;
use App\Services\MigrationService;
use App\Services\PdfService;

final class App
{
    public static function boot(array $config): void
    {
        self::ensureStorage($config['storage']);
        $db = Database::connect($config['database']);
        Database::migrate($db);

        $logs = new LogService($db);
        $projects = new ProjectService($db, $config, $logs);
        $schema = new SchemaService($projects, $logs);
        $records = new RecordService($schema, $logs);
        $transfer = new ImportExportService($records);
        $backups = new BackupService($db, $config, $logs);
        $storage = new StorageService($config, $schema, $logs);
        $realtime = new RealtimeService($config['realtime'] ?? []);
        $functions = new FunctionService($db);
        $webhooks = new WebhookService($db, $realtime, $functions);
        $keys = new ApiKeyService($db, $schema, $config['rate_limit']);
        $auth = new Auth($db);
        $installer = new InstallerService($config, $auth);
        $licenses = new LicenseService($db, $logs);
        $databaseBridge = new DatabaseBridgeService($db, $schema, $logs, $config);
        $pdf = new PdfService();
        $metrics = new MetricsService($db);
        $migrations = new MigrationService($db, $config['storage']);

        (new WebController($config, $auth, $installer, $db, $projects, $schema, $records, $transfer, $logs, $backups, $storage, $webhooks, $licenses, $databaseBridge, $pdf, $functions, $metrics, $migrations))->register();
        (new ApiController($config, $projects, $schema, $records, $transfer, $storage, $keys, $webhooks, $licenses, $logs, $backups, $metrics))->register();
    }

    private static function ensureStorage(string $storage): void
    {
        foreach ([$storage, "$storage/projects", "$storage/backups", "$storage/uploads", "$storage/functions", "$storage/migrations"] as $directory) {
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new \RuntimeException("Cannot create storage directory: $directory");
            }
        }
    }
}
