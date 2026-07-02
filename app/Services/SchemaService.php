<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Support;
use PDO;
use RuntimeException;

final class SchemaService
{
    public const PROTECTED = ['id', 'uid', 'created_at', 'updated_at'];
    public const TYPES = ['TEXT', 'INTEGER', 'REAL', 'BOOLEAN', 'DATE', 'DATETIME', 'JSON', 'EMAIL', 'PHONE', 'URL', 'FILE', 'IMAGE'];

    public function __construct(private ProjectService $projects, private LogService $logs)
    {
    }

    public function connection(array $project): PDO
    {
        return Database::connect($project['database_path']);
    }

    public function tables(array $project): array
    {
        $db = $this->connection($project);
        $rows = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name NOT LIKE '\\_%' ESCAPE '\\' ORDER BY name")->fetchAll();
        foreach ($rows as &$row) {
            $table = Support::quoteIdentifier($row['name']);
            $row['count'] = (int) $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        }
        return $rows;
    }

    public function columns(array $project, string $table): array
    {
        $table = Support::identifier($table, 'table name');
        $db = $this->connection($project);
        $columns = $db->query('PRAGMA table_info(' . Support::quoteIdentifier($table) . ')')->fetchAll();
        if (!$columns) {
            throw new RuntimeException('Table not found.');
        }
        $indexes = $db->query('PRAGMA index_list(' . Support::quoteIdentifier($table) . ')')->fetchAll();
        $indexed = [];
        foreach ($indexes as $index) {
            foreach ($db->query('PRAGMA index_info(' . Support::quoteIdentifier($index['name']) . ')')->fetchAll() as $column) {
                $indexed[$column['name']] = ['unique' => (bool) $index['unique']];
            }
        }
        return array_map(function (array $column) use ($indexed): array {
            $column['indexed'] = isset($indexed[$column['name']]);
            $column['unique'] = $indexed[$column['name']]['unique'] ?? false;
            $column['protected'] = in_array($column['name'], self::PROTECTED, true);
            return $column;
        }, $columns);
    }

    public function createTable(array $project, string $name, array $fields = []): void
    {
        $name = Support::identifier($name, 'table name');
        if (str_starts_with($name, '_')) {
            throw new \InvalidArgumentException('Table names beginning with an underscore are reserved.');
        }
        $db = $this->connection($project);
        $definitions = [
            '"id" INTEGER PRIMARY KEY AUTOINCREMENT',
            '"uid" TEXT UNIQUE NOT NULL',
        ];
        foreach ($fields as $field) {
            $definitions[] = $this->columnDefinition($field);
        }
        $definitions[] = '"created_at" TEXT NOT NULL';
        $definitions[] = '"updated_at" TEXT NOT NULL';
        $db->beginTransaction();
        try {
            $db->exec('CREATE TABLE ' . Support::quoteIdentifier($name) . ' (' . implode(',', $definitions) . ')');
            $this->createRequestedIndexes($db, $name, $fields);
            $stmt = $db->prepare('INSERT INTO _system_table_settings (table_name,access_mode,updated_at) VALUES (?,?,?)');
            $stmt->execute([$name, 'private', Support::now()]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        $this->logs->write('table.created', $project['uid'], $name);
    }

    public function addColumn(array $project, string $table, array $field): void
    {
        $table = Support::identifier($table, 'table name');
        $db = $this->connection($project);
        $definition = $this->columnDefinition($field);
        $db->exec('ALTER TABLE ' . Support::quoteIdentifier($table) . ' ADD COLUMN ' . $definition);
        $this->createRequestedIndexes($db, $table, [$field]);
        $this->logs->write('field.created', $project['uid'], $table, null, null, $field);
    }

    public function dropColumn(array $project, string $table, string $column): void
    {
        $table = Support::identifier($table, 'table name');
        $column = Support::identifier($column, 'column name');
        if (in_array($column, self::PROTECTED, true)) {
            throw new \InvalidArgumentException('Protected columns cannot be removed.');
        }
        $db = $this->connection($project);
        $db->exec('ALTER TABLE ' . Support::quoteIdentifier($table) . ' DROP COLUMN ' . Support::quoteIdentifier($column));
        $this->logs->write('field.deleted', $project['uid'], $table, null, ['name' => $column]);
    }

    public function truncate(array $project, string $table): void
    {
        $table = Support::identifier($table, 'table name');
        $db = $this->connection($project);
        $quoted = Support::quoteIdentifier($table);
        $db->beginTransaction();
        $db->exec("DELETE FROM $quoted");
        $stmt = $db->prepare('DELETE FROM sqlite_sequence WHERE name = ?');
        $stmt->execute([$table]);
        $db->commit();
        $this->logs->write('table.truncated', $project['uid'], $table);
    }

    public function dropTable(array $project, string $table): void
    {
        $table = Support::identifier($table, 'table name');
        if (str_starts_with($table, '_')) {
            throw new \InvalidArgumentException('System tables cannot be removed.');
        }
        $db = $this->connection($project);
        $db->exec('DROP TABLE ' . Support::quoteIdentifier($table));
        $stmt = $db->prepare('DELETE FROM _system_table_settings WHERE table_name = ?');
        $stmt->execute([$table]);
        $this->logs->write('table.deleted', $project['uid'], $table);
    }

    public function accessMode(array $project, string $table): string
    {
        $stmt = $this->connection($project)->prepare('SELECT access_mode FROM _system_table_settings WHERE table_name = ?');
        $stmt->execute([$table]);
        return $stmt->fetchColumn() ?: 'private';
    }

    public function setAccessMode(array $project, string $table, string $mode): void
    {
        $allowed = ['public_read', 'private', 'secret_only', 'blocked'];
        if (!in_array($mode, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid access mode.');
        }
        $stmt = $this->connection($project)->prepare('UPDATE _system_table_settings SET access_mode = ?, updated_at = ? WHERE table_name = ?');
        $stmt->execute([$mode, Support::now(), $table]);
    }

    private function columnDefinition(array $field): string
    {
        $name = Support::identifier((string) ($field['name'] ?? ''), 'column name');
        if (in_array($name, self::PROTECTED, true) || str_starts_with($name, '_')) {
            throw new \InvalidArgumentException('That column name is reserved.');
        }
        $type = strtoupper((string) ($field['type'] ?? 'TEXT'));
        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException('Unsupported column type.');
        }
        $sqlType = in_array($type, ['INTEGER', 'BOOLEAN'], true) ? 'INTEGER' : ($type === 'REAL' ? 'REAL' : 'TEXT');
        $definition = Support::quoteIdentifier($name) . ' ' . $sqlType;
        if (!empty($field['required'])) {
            $definition .= ' NOT NULL';
        }
        if (array_key_exists('default', $field) && $field['default'] !== '') {
            $default = $field['default'];
            $definition .= is_numeric($default) && $sqlType !== 'TEXT'
                ? ' DEFAULT ' . $default
                : ' DEFAULT ' . $this->connectionLiteral((string) $default);
        }
        return $definition;
    }

    private function connectionLiteral(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    private function createRequestedIndexes(PDO $db, string $table, array $fields): void
    {
        foreach ($fields as $field) {
            if (empty($field['indexed']) && empty($field['unique'])) {
                continue;
            }
            $column = Support::identifier((string) $field['name'], 'column name');
            $index = Support::identifier('idx_' . $table . '_' . $column, 'index name');
            $unique = !empty($field['unique']) ? 'UNIQUE ' : '';
            $db->exec("CREATE {$unique}INDEX " . Support::quoteIdentifier($index) . ' ON ' . Support::quoteIdentifier($table) . ' (' . Support::quoteIdentifier($column) . ')');
        }
    }
}
