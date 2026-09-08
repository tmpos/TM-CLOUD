<?php

declare(strict_types=1);

$root = dirname(__DIR__);
foreach ([
    'app/Core/Support.php',
    'app/Core/Database.php',
    'app/Core/Auth.php',
    'app/Core/Http.php',
    'app/Services/LogService.php',
    'app/Services/ProjectService.php',
    'app/Services/SchemaService.php',
    'app/Services/ProjectSqlApiService.php',
] as $file) {
    require_once $root . '/' . $file;
}

use App\Core\Database;
use App\Services\LogService;
use App\Services\ProjectService;
use App\Services\ProjectSqlApiService;
use App\Services\SchemaService;

function checkSqlApi(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function removeSqlApiDirectory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    foreach (array_diff(scandir($directory) ?: [], ['.', '..']) as $name) {
        $path = $directory . DIRECTORY_SEPARATOR . $name;
        is_dir($path) ? removeSqlApiDirectory($path) : unlink($path);
    }
    rmdir($directory);
}

$storage = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tmpbase-sql-api-' . bin2hex(random_bytes(6));
foreach ([$storage, "$storage/projects", "$storage/backups", "$storage/uploads"] as $directory) {
    mkdir($directory, 0775, true);
}
$database = $storage . DIRECTORY_SEPARATOR . 'central.sqlite';
$config = ['storage' => $storage, 'database' => $database, 'url' => 'https://api.example.test'];

try {
    $db = Database::connect($database);
    Database::migrate($db);
    $logs = new LogService($db);
    $projects = new ProjectService($db, $config, $logs);
    $schema = new SchemaService($projects, $logs);
    $service = new ProjectSqlApiService($schema, $logs);
    $project = $projects->create(['name' => 'SQL API smoke']);

    $schema->createTable($project, 'items', [
        ['name' => 'name', 'type' => 'TEXT', 'required' => true],
        ['name' => 'active', 'type' => 'BOOLEAN'],
        ['name' => 'balance', 'type' => 'REAL'],
    ]);

    $insert = $service->execute($project, [
        'operation' => 'run',
        'sql' => 'INSERT INTO items (name, active, balance) VALUES (?, ?, ?)',
        'params' => ['First', 1, 12.5],
    ]);
    checkSqlApi($insert['lastInsertRowid'] === 1, 'The first insert did not return its remote id.');

    $first = $service->execute($project, [
        'operation' => 'get',
        'sql' => 'SELECT * FROM items WHERE id = ?',
        'params' => [1],
    ]);
    checkSqlApi($first['name'] === 'First', 'The inserted row was not returned.');
    checkSqlApi(str_starts_with($first['uid'], 'rec_'), 'The technical uid was not generated.');

    $missing = $service->execute($project, [
        'operation' => 'get',
        'sql' => 'SELECT * FROM items WHERE id = ?',
        'params' => [999999],
    ]);
    checkSqlApi($missing === null, 'A missing row must return null without a PHP type error.');

    $service->execute($project, [
        'operation' => 'transaction',
        'queries' => [
            ['sql' => 'INSERT INTO items (name, active) VALUES (?, ?)', 'params' => ['Second', 1]],
            ['sql' => 'UPDATE items SET balance = balance + ? WHERE id = ?', 'params' => [7.5, 1]],
        ],
    ]);
    $count = $service->execute($project, ['operation' => 'get', 'sql' => 'SELECT COUNT(*) AS total FROM items']);
    checkSqlApi((int) $count['total'] === 2, 'The transaction was not committed.');

    $service->execute($project, [
        'operation' => 'run',
        'sql' => "INSERT INTO items (name, active) SELECT 'Conditional', 1 WHERE NOT EXISTS (SELECT 1 FROM items WHERE name = 'Conditional')",
    ]);
    $count = $service->execute($project, ['operation' => 'get', 'sql' => 'SELECT COUNT(*) AS total FROM items']);
    checkSqlApi((int) $count['total'] === 3, 'INSERT SELECT did not receive its technical fields.');

    $rolledBack = false;
    try {
        $service->execute($project, [
            'operation' => 'transaction',
            'queries' => [
                ['sql' => 'INSERT INTO items (name) VALUES (?)', 'params' => ['Must roll back']],
                ['sql' => 'INSERT INTO missing_table (name) VALUES (?)', 'params' => ['Error']],
            ],
        ]);
    } catch (Throwable) {
        $rolledBack = true;
    }
    checkSqlApi($rolledBack, 'The invalid transaction did not fail.');
    $count = $service->execute($project, ['operation' => 'get', 'sql' => 'SELECT COUNT(*) AS total FROM items']);
    checkSqlApi((int) $count['total'] === 3, 'The failed transaction left partial data.');

    $blocked = false;
    try {
        $service->execute($project, ['operation' => 'run', 'sql' => "ATTACH DATABASE 'other.sqlite' AS other"]);
    } catch (Throwable) {
        $blocked = true;
    }
    checkSqlApi($blocked, 'ATTACH was not blocked.');

    fwrite(STDOUT, "Project SQL API smoke test passed.\n");
} finally {
    unset($service, $schema, $projects, $logs, $db);
    removeSqlApiDirectory($storage);
}
