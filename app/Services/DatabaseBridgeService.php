<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Support;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class DatabaseBridgeService
{
    public function __construct(
        private PDO $db,
        private SchemaService $schema,
        private LogService $logs,
        private array $config,
    ) {
    }

    public function all(string $projectUid): array
    {
        $stmt = $this->db->prepare(
            'SELECT uid,project_uid,name,driver,host,port,database_name,username,charset,created_at,updated_at
             FROM external_connections WHERE project_uid = ? ORDER BY name'
        );
        $stmt->execute([$projectUid]);
        return $stmt->fetchAll();
    }

    public function create(string $projectUid, array $input): array
    {
        if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
            throw new RuntimeException('The PDO MySQL extension is not installed on this server.');
        }

        $connection = $this->validateConnection($input);
        $this->connectMysql($connection);
        $now = Support::now();
        $uid = Support::uid('dbc_');
        $stmt = $this->db->prepare(
            'INSERT INTO external_connections
             (uid,project_uid,name,driver,host,port,database_name,username,password_encrypted,charset,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $uid, $projectUid, $connection['name'], 'mysql', $connection['host'], $connection['port'],
            $connection['database_name'], $connection['username'], $this->encrypt($connection['password']),
            $connection['charset'], $now, $now,
        ]);
        $this->logs->write('database.connection.created', $projectUid, null, $uid, null, [
            'name' => $connection['name'], 'host' => $connection['host'], 'database' => $connection['database_name'],
        ]);
        return $this->find($projectUid, $uid, false);
    }

    public function test(string $projectUid, string $uid): void
    {
        $connection = $this->find($projectUid, $uid);
        $pdo = $this->connectMysql($connection);
        $pdo->query('SELECT 1')->fetchColumn();
    }

    public function delete(string $projectUid, string $uid): void
    {
        $connection = $this->find($projectUid, $uid, false);
        $stmt = $this->db->prepare('DELETE FROM external_connections WHERE project_uid = ? AND uid = ?');
        $stmt->execute([$projectUid, $uid]);
        $this->logs->write('database.connection.deleted', $projectUid, null, $uid, $connection, null);
    }

    public function mysqlTables(string $projectUid, string $uid): array
    {
        $pdo = $this->connectMysql($this->find($projectUid, $uid));
        $stmt = $pdo->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME');
        return array_map(static fn (array $row): string => (string) $row['TABLE_NAME'], $stmt->fetchAll());
    }

    public function transfer(array $project, string $connectionUid, array $input): array
    {
        $direction = (string) ($input['direction'] ?? 'sqlite_to_mysql');
        if (!in_array($direction, ['sqlite_to_mysql', 'mysql_to_sqlite'], true)) {
            throw new InvalidArgumentException('Invalid transfer direction.');
        }
        $mode = (string) ($input['mode'] ?? 'upsert');
        if (!in_array($mode, ['upsert', 'append', 'replace'], true)) {
            throw new InvalidArgumentException('Invalid transfer mode.');
        }

        $sourceTable = Support::identifier(trim((string) ($input['source_table'] ?? '')), 'source table');
        $targetTable = Support::identifier(trim((string) ($input['target_table'] ?? $sourceTable)) ?: $sourceTable, 'target table');
        $limit = min(10000, max(1, (int) ($input['limit'] ?? 1000)));
        $sqlite = $this->schema->connection($project);
        $mysql = $this->connectMysql($this->find($project['uid'], $connectionUid));
        $source = $direction === 'sqlite_to_mysql' ? $sqlite : $mysql;
        $target = $direction === 'sqlite_to_mysql' ? $mysql : $sqlite;
        $sourceDriver = $direction === 'sqlite_to_mysql' ? 'sqlite' : 'mysql';
        $targetDriver = $direction === 'sqlite_to_mysql' ? 'mysql' : 'sqlite';

        if (!$this->tableExists($source, $sourceDriver, $sourceTable)) {
            throw new RuntimeException("Source table '$sourceTable' does not exist.");
        }
        $sourceColumns = $this->columns($source, $sourceDriver, $sourceTable);
        if (!$this->tableExists($target, $targetDriver, $targetTable)) {
            $this->createTargetTable($target, $targetDriver, $targetTable, $sourceColumns);
        }
        $targetColumns = $this->columns($target, $targetDriver, $targetTable);

        $sourceSql = 'SELECT * FROM ' . $this->quote($sourceTable, $sourceDriver) . ' LIMIT ' . $limit;
        $rows = $source->query($sourceSql)->fetchAll();
        $sourceNames = array_column($sourceColumns, 'name');
        $targetNames = array_column($targetColumns, 'name');
        $common = array_values(array_intersect($sourceNames, $targetNames));
        foreach (['uid', 'created_at', 'updated_at'] as $systemColumn) {
            if (in_array($systemColumn, $targetNames, true) && !in_array($systemColumn, $common, true)) {
                $common[] = $systemColumn;
            }
        }
        $key = in_array('uid', $sourceNames, true) && in_array('uid', $targetNames, true)
            ? 'uid'
            : $this->commonPrimaryKey($sourceColumns, $targetNames);
        if ($key !== 'id') {
            $common = array_values(array_diff($common, ['id']));
        }
        if (!$common) {
            throw new RuntimeException('The source and target tables have no compatible columns.');
        }

        $inserted = 0;
        $updated = 0;
        $failed = 0;
        $errors = [];
        $target->beginTransaction();
        try {
            if ($mode === 'replace') {
                $target->exec('DELETE FROM ' . $this->quote($targetTable, $targetDriver));
            }
            foreach ($rows as $index => $row) {
                $values = [];
                foreach ($common as $column) {
                    if (array_key_exists($column, $row)) {
                        $values[$column] = $row[$column];
                    } elseif ($column === 'uid') {
                        $values[$column] = Support::uid('rec_');
                    } elseif ($column === 'created_at' || $column === 'updated_at') {
                        $values[$column] = Support::now();
                    }
                }
                try {
                    if ($mode === 'upsert' && $key !== null && isset($values[$key]) && $this->rowExists($target, $targetDriver, $targetTable, $key, $values[$key])) {
                        $updates = array_diff_key($values, [$key => true, 'id' => true]);
                        if ($updates) {
                            $this->updateRow($target, $targetDriver, $targetTable, $key, $values[$key], $updates);
                        }
                        $updated++;
                    } else {
                        $this->insertRow($target, $targetDriver, $targetTable, $values);
                        $inserted++;
                    }
                } catch (Throwable $e) {
                    $failed++;
                    if (count($errors) < 5) {
                        $errors[] = 'Row ' . ($index + 1) . ': ' . $e->getMessage();
                    }
                }
            }
            if ($mode === 'replace' && $failed > 0) {
                throw new RuntimeException('Replace transfer aborted because one or more rows could not be copied. The destination was rolled back. ' . implode(' | ', $errors));
            }
            $target->commit();
        } catch (Throwable $e) {
            if ($target->inTransaction()) {
                $target->rollBack();
            }
            throw $e;
        }

        $result = [
            'direction' => $direction, 'source_table' => $sourceTable, 'target_table' => $targetTable,
            'read' => count($rows), 'inserted' => $inserted, 'updated' => $updated, 'failed' => $failed,
            'errors' => $errors,
        ];
        $this->logs->write('database.transfer', $project['uid'], $targetTable, null, null, $result);
        return $result;
    }

    public function executeSql(array $project, string $target, string $sql, bool $allowWrite): array
    {
        $sql = trim($sql);
        if ($sql === '') {
            throw new InvalidArgumentException('Enter a SQL statement.');
        }
        if (strlen($sql) > 100000) {
            throw new InvalidArgumentException('SQL statements are limited to 100 KB.');
        }

        if ($target === 'sqlite') {
            $pdo = $this->schema->connection($project);
            $targetLabel = 'SQLite';
        } else {
            $connection = $this->find($project['uid'], $target);
            $pdo = $this->connectMysql($connection);
            $targetLabel = 'MySQL: ' . $connection['name'];
        }
        $readOnly = $this->isReadOnlySql($sql);
        if (!$readOnly && !$allowWrite) {
            throw new RuntimeException('This statement may modify data. Enable the write confirmation before running it.');
        }

        $started = microtime(true);
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $columns = [];
        $rows = [];
        $truncated = false;
        if ($stmt->columnCount() > 0) {
            for ($i = 0; $i < $stmt->columnCount(); $i++) {
                $meta = $stmt->getColumnMeta($i);
                $columns[] = (string) ($meta['name'] ?? "column_$i");
            }
            while (($row = $stmt->fetch()) !== false) {
                if (count($rows) === 200) {
                    $truncated = true;
                    break;
                }
                $rows[] = array_map(static function (mixed $value): mixed {
                    return is_string($value) && strlen($value) > 10000 ? substr($value, 0, 10000) . '…' : $value;
                }, $row);
            }
        }
        $result = [
            'target' => $targetLabel, 'columns' => $columns, 'rows' => $rows,
            'affected' => $stmt->rowCount(), 'truncated' => $truncated,
            'duration_ms' => round((microtime(true) - $started) * 1000, 2),
        ];
        $this->logs->write('database.sql.executed', $project['uid'], null, null, null, [
            'target' => $targetLabel, 'read_only' => $readOnly, 'affected' => $result['affected'],
            'statement' => strtoupper((string) strtok(ltrim($sql), " \t\r\n")),
            'sql_hash' => hash('sha256', $sql),
        ]);
        return $result;
    }

    private function find(string $projectUid, string $uid, bool $withPassword = true): array
    {
        $stmt = $this->db->prepare('SELECT * FROM external_connections WHERE project_uid = ? AND uid = ? LIMIT 1');
        $stmt->execute([$projectUid, $uid]);
        $connection = $stmt->fetch();
        if (!$connection) {
            throw new RuntimeException('Database connection not found.');
        }
        if ($withPassword) {
            $connection['password'] = $this->decrypt((string) $connection['password_encrypted']);
        }
        unset($connection['password_encrypted']);
        return $connection;
    }

    private function validateConnection(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $host = trim((string) ($input['host'] ?? ''));
        $database = trim((string) ($input['database_name'] ?? ''));
        $username = trim((string) ($input['username'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $port = (int) ($input['port'] ?? 3306);
        $charset = strtolower(trim((string) ($input['charset'] ?? 'utf8mb4')));
        if ($name === '' || strlen($name) > 100 || $host === '' || strlen($host) > 255) {
            throw new InvalidArgumentException('Connection name and host are required.');
        }
        if ($database === '' || $username === '' || strlen($database) > 128 || strlen($username) > 128) {
            throw new InvalidArgumentException('Database name and username are required.');
        }
        if (preg_match('/[;\x00-\x1F]/', $host . $database)) {
            throw new InvalidArgumentException('Host and database name contain invalid characters.');
        }
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('Invalid MySQL port.');
        }
        if (!in_array($charset, ['utf8mb4', 'utf8'], true)) {
            throw new InvalidArgumentException('Only utf8mb4 and utf8 charsets are supported.');
        }
        return compact('name', 'host', 'port', 'database', 'username', 'password', 'charset') + ['database_name' => $database];
    }

    private function connectMysql(array $connection): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $connection['host'], (int) $connection['port'], $connection['database_name'], $connection['charset'] ?? 'utf8mb4'
        );
        return new PDO($dsn, $connection['username'], $connection['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 8,
        ]);
    }

    private function tableExists(PDO $pdo, string $driver, string $table): bool
    {
        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?");
        } else {
            $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        }
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }

    private function columns(PDO $pdo, string $driver, string $table): array
    {
        if ($driver === 'sqlite') {
            $rows = $pdo->query('PRAGMA table_info(' . $this->quote($table, $driver) . ')')->fetchAll();
            return array_map(static fn (array $row): array => [
                'name' => $row['name'], 'type' => strtoupper((string) $row['type']),
                'primary' => (int) $row['pk'] > 0, 'auto' => $row['name'] === 'id',
            ], $rows);
        }
        $stmt = $pdo->prepare(
            'SELECT COLUMN_NAME,DATA_TYPE,COLUMN_KEY,EXTRA FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION'
        );
        $stmt->execute([$table]);
        return array_map(static fn (array $row): array => [
            'name' => $row['COLUMN_NAME'], 'type' => strtoupper((string) $row['DATA_TYPE']),
            'primary' => $row['COLUMN_KEY'] === 'PRI', 'auto' => str_contains((string) $row['EXTRA'], 'auto_increment'),
        ], $stmt->fetchAll());
    }

    private function createTargetTable(PDO $pdo, string $driver, string $table, array $sourceColumns): void
    {
        $sourceNames = array_column($sourceColumns, 'name');
        $definitions = [];
        if (!in_array('id', $sourceNames, true)) {
            $definitions[] = $driver === 'mysql' ? '`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : '"id" INTEGER PRIMARY KEY AUTOINCREMENT';
        }
        foreach ($sourceColumns as $column) {
            $name = Support::identifier((string) $column['name'], 'column name');
            if ($name === 'id') {
                $definitions[] = $driver === 'mysql' ? '`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : '"id" INTEGER PRIMARY KEY AUTOINCREMENT';
            } elseif ($name === 'uid') {
                $definitions[] = $this->quote($name, $driver) . ($driver === 'mysql' ? ' VARCHAR(191) NOT NULL UNIQUE' : ' TEXT NOT NULL UNIQUE');
            } elseif ($driver === 'mysql' && ($name === 'created_at' || $name === 'updated_at')) {
                $definitions[] = $this->quote($name, $driver) . ' DATETIME NOT NULL';
            } else {
                $definitions[] = $this->quote($name, $driver) . ' ' . $this->mapType((string) $column['type'], $driver);
            }
        }
        foreach (['uid', 'created_at', 'updated_at'] as $required) {
            if (!in_array($required, $sourceNames, true)) {
                $type = $driver === 'mysql' && $required !== 'uid' ? 'DATETIME NOT NULL' : ($driver === 'mysql' ? 'VARCHAR(191) NOT NULL UNIQUE' : 'TEXT NOT NULL' . ($required === 'uid' ? ' UNIQUE' : ''));
                $definitions[] = $this->quote($required, $driver) . ' ' . $type;
            }
        }
        $sql = 'CREATE TABLE ' . $this->quote($table, $driver) . ' (' . implode(',', $definitions) . ')';
        if ($driver === 'mysql') {
            $sql .= ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        }
        $pdo->exec($sql);
    }

    private function mapType(string $type, string $targetDriver): string
    {
        $type = strtoupper($type);
        if ($targetDriver === 'sqlite') {
            return match (true) {
                str_contains($type, 'INT'), str_contains($type, 'BOOL') => 'INTEGER',
                str_contains($type, 'REAL'), str_contains($type, 'FLOA'), str_contains($type, 'DOUB'), str_contains($type, 'DEC') => 'REAL',
                str_contains($type, 'BLOB'), str_contains($type, 'BINARY') => 'BLOB',
                default => 'TEXT',
            };
        }
        return match (true) {
            str_contains($type, 'INT') => 'BIGINT NULL',
            str_contains($type, 'BOOL') => 'TINYINT(1) NULL',
            str_contains($type, 'REAL'), str_contains($type, 'FLOA'), str_contains($type, 'DOUB') => 'DOUBLE NULL',
            str_contains($type, 'DEC') => 'DECIMAL(30,10) NULL',
            str_contains($type, 'BLOB'), str_contains($type, 'BINARY') => 'LONGBLOB NULL',
            str_contains($type, 'JSON') => 'JSON NULL',
            str_contains($type, 'DATETIME'), str_contains($type, 'TIMESTAMP') => 'DATETIME NULL',
            $type === 'DATE' => 'DATE NULL',
            default => 'LONGTEXT NULL',
        };
    }

    private function commonPrimaryKey(array $sourceColumns, array $targetNames): ?string
    {
        foreach ($sourceColumns as $column) {
            if ($column['primary'] && in_array($column['name'], $targetNames, true)) {
                return (string) $column['name'];
            }
        }
        return null;
    }

    private function rowExists(PDO $pdo, string $driver, string $table, string $key, mixed $value): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM ' . $this->quote($table, $driver) . ' WHERE ' . $this->quote($key, $driver) . ' = ? LIMIT 1');
        $stmt->execute([$value]);
        return (bool) $stmt->fetchColumn();
    }

    private function insertRow(PDO $pdo, string $driver, string $table, array $values): void
    {
        $columns = array_keys($values);
        $sql = 'INSERT INTO ' . $this->quote($table, $driver) . ' (' . implode(',', array_map(fn (string $c): string => $this->quote($c, $driver), $columns)) .
            ') VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')';
        $pdo->prepare($sql)->execute(array_values($values));
    }

    private function updateRow(PDO $pdo, string $driver, string $table, string $key, mixed $keyValue, array $values): void
    {
        $assignments = implode(',', array_map(fn (string $c): string => $this->quote($c, $driver) . ' = ?', array_keys($values)));
        $sql = 'UPDATE ' . $this->quote($table, $driver) . ' SET ' . $assignments . ' WHERE ' . $this->quote($key, $driver) . ' = ?';
        $pdo->prepare($sql)->execute([...array_values($values), $keyValue]);
    }

    private function quote(string $identifier, string $driver): string
    {
        $identifier = Support::identifier($identifier);
        return $driver === 'mysql' ? '`' . $identifier . '`' : '"' . $identifier . '"';
    }

    private function isReadOnlySql(string $sql): bool
    {
        $clean = preg_replace('/\A(?:\s|--[^\r\n]*(?:\r?\n|\z)|\/\*.*?\*\/)+/s', '', $sql) ?? $sql;
        if (preg_match('/\A(?:SELECT|SHOW|DESCRIBE|DESC|EXPLAIN|PRAGMA)\b/i', $clean)) {
            return true;
        }
        return preg_match('/\AWITH\b/i', $clean) === 1
            && preg_match('/\b(?:INSERT|UPDATE|DELETE|REPLACE|CREATE|ALTER|DROP|TRUNCATE)\b/i', $clean) !== 1;
    }

    private function encrypt(string $plain): string
    {
        $key = $this->credentialKey();
        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            return 's1:' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $key));
        }
        if (!function_exists('openssl_encrypt')) {
            throw new RuntimeException('Sodium or OpenSSL is required to encrypt database credentials.');
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new RuntimeException('Could not encrypt database credentials.');
        }
        return 'o1:' . base64_encode($iv . $tag . $cipher);
    }

    private function decrypt(string $encrypted): string
    {
        [$version, $payload] = array_pad(explode(':', $encrypted, 2), 2, '');
        $data = base64_decode($payload, true);
        if ($data === false) {
            throw new RuntimeException('Stored database credentials are invalid.');
        }
        $key = $this->credentialKey(false);
        if ($version === 's1' && function_exists('sodium_crypto_secretbox_open')) {
            $nonceSize = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
            $plain = sodium_crypto_secretbox_open(substr($data, $nonceSize), substr($data, 0, $nonceSize), $key);
            if ($plain !== false) return $plain;
        }
        if ($version === 'o1' && function_exists('openssl_decrypt')) {
            $plain = openssl_decrypt(substr($data, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($data, 0, 12), substr($data, 12, 16));
            if ($plain !== false) return $plain;
        }
        throw new RuntimeException('Could not decrypt the stored database password.');
    }

    private function credentialKey(bool $create = true): string
    {
        $path = $this->config['storage'] . DIRECTORY_SEPARATOR . '.credential-key';
        if (!is_file($path)) {
            if (!$create) {
                throw new RuntimeException('The database credential encryption key is missing.');
            }
            $encoded = base64_encode(random_bytes(32));
            $handle = @fopen($path, 'x');
            if (is_resource($handle)) {
                $written = fwrite($handle, $encoded);
                fclose($handle);
                if ($written !== strlen($encoded)) {
                    @unlink($path);
                    throw new RuntimeException('Could not create the database credential encryption key.');
                }
                @chmod($path, 0600);
            } elseif (!is_file($path)) {
                throw new RuntimeException('Could not create the database credential encryption key.');
            }
        }
        $key = base64_decode(trim((string) file_get_contents($path)), true);
        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException('The database credential encryption key is invalid.');
        }
        return $key;
    }
}
