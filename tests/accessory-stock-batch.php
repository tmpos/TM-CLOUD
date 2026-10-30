<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec('CREATE TABLE accesorios (id INTEGER PRIMARY KEY, uid TEXT NOT NULL, cantidad REAL NOT NULL, updated_at TEXT)');
$db->exec("INSERT INTO accesorios (id, uid, cantidad) VALUES (1, 'a', 5), (2, 'b', 7)");

$reflection = new ReflectionClass(\App\Services\SystemRuntimeService::class);
$runtime = $reflection->newInstanceWithoutConstructor();
$batch = $reflection->getMethod('setAccessoryStock');
try {
    $batch->invoke($runtime, $db, ['uid' => 'test'], [
        'entries' => [
            ['id' => 1, 'uid' => 'a', 'expectedQuantity' => 5, 'quantity' => 9],
            ['id' => 2, 'uid' => 'b', 'expectedQuantity' => 8, 'quantity' => 0],
        ],
    ], ['email' => 'test@example.invalid']);
    throw new RuntimeException('Expected stock conflict');
} catch (RuntimeException $error) {
    if ($error->getCode() !== 409) throw $error;
}

$rows = $db->query('SELECT id, cantidad FROM accesorios ORDER BY id')->fetchAll();
if ((float) $rows[0]['cantidad'] !== 5.0 || (float) $rows[1]['cantidad'] !== 7.0 || $db->inTransaction()) {
    throw new RuntimeException('Batch conflict did not roll back every stock change');
}
if (!$runtime->isWrite('db/setAccessoryStock', [])) throw new RuntimeException('Batch write not classified as write');
echo "accessory stock batch: ok\n";
