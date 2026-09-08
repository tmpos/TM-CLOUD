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

    // GET /schema combina tables()+columns() para cada tabla: sin cache, cada
    // llamada dispara un COUNT(*) y varios PRAGMA por tabla. Los clientes lo
    // piden en cada ciclo de sincronizacion (cada ~60s en cada dispositivo
    // conectado), asi que con muchas tablas esto se vuelve lento y bloquea el
    // resto del ciclo de sync (incluidas las eliminaciones pendientes).
    // Se cachea el resultado combinado por proyecto con un TTL corto: el conteo
    // es solo informativo (se usa para un mensaje de progreso en el cliente) y
    // la estructura de columnas cambia con muy poca frecuencia.
    private const SCHEMA_CACHE_TTL = 30;

    public function fullSchema(array $project): array
    {
        $cached = $this->readSchemaCache($project);
        if ($cached !== null) {
            return $cached;
        }
        $result = [];
        foreach ($this->tables($project) as $table) {
            $name = $table['name'];
            $result[$name] = [
                'count' => $table['count'],
                'columns' => $this->columns($project, $name),
            ];
        }
        $this->writeSchemaCache($project, $result);
        return $result;
    }

    private function schemaCachePath(array $project): string
    {
        return dirname($project['database_path']) . '/schema_cache.json';
    }

    private function readSchemaCache(array $project): ?array
    {
        $path = $this->schemaCachePath($project);
        if (!is_file($path) || (time() - filemtime($path)) > self::SCHEMA_CACHE_TTL) {
            return null;
        }
        $contents = @file_get_contents($path);
        if ($contents === false) {
            return null;
        }
        $decoded = json_decode($contents, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function writeSchemaCache(array $project, array $result): void
    {
        try {
            @file_put_contents($this->schemaCachePath($project), Support::json($result), LOCK_EX);
        } catch (\Throwable) {
            // El cache es una optimizacion; si no se puede escribir, se ignora.
        }
    }

    private function invalidateSchemaCache(array $project): void
    {
        $path = $this->schemaCachePath($project);
        if (is_file($path)) {
            @unlink($path);
        }
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
        $this->invalidateSchemaCache($project);
    }

    public function addColumn(array $project, string $table, array $field): void
    {
        $table = Support::identifier($table, 'table name');
        $db = $this->connection($project);
        $definition = $this->columnDefinition($field);
        $db->exec('ALTER TABLE ' . Support::quoteIdentifier($table) . ' ADD COLUMN ' . $definition);
        $this->createRequestedIndexes($db, $table, [$field]);
        $this->logs->write('field.created', $project['uid'], $table, null, null, $field);
        $this->invalidateSchemaCache($project);
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
        $this->invalidateSchemaCache($project);
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
        $this->invalidateSchemaCache($project);
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

    // Traduce las columnas reales de una tabla (PRAGMA table_info) al formato
    // de "field" que espera createTable(), para poder recrear la misma
    // estructura en otro proyecto durante una importacion de tablas.
    public function importableFields(array $project, string $table): array
    {
        $fields = [];
        foreach ($this->columns($project, $table) as $column) {
            if (in_array($column['name'], self::PROTECTED, true)) {
                continue;
            }
            $type = in_array($column['type'], ['INTEGER', 'REAL'], true) ? $column['type'] : 'TEXT';
            $field = [
                'name' => $column['name'],
                'type' => $type,
                'required' => (bool) $column['notnull'],
                'indexed' => (bool) $column['indexed'],
                'unique' => (bool) $column['unique'],
            ];
            if ($column['dflt_value'] !== null) {
                $field['default'] = $this->unquoteDefault($type, (string) $column['dflt_value']);
            }
            $fields[] = $field;
        }
        return $fields;
    }

    private function unquoteDefault(string $type, string $raw): string
    {
        if ($type === 'TEXT' && strlen($raw) >= 2 && $raw[0] === "'" && substr($raw, -1) === "'") {
            return str_replace("''", "'", substr($raw, 1, -1));
        }
        return $raw;
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

    public function ensureFkSystemTable(array $project): void
    {
        $db = $this->connection($project);
        $db->exec("CREATE TABLE IF NOT EXISTS _system_foreign_keys (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            table_name TEXT NOT NULL,
            column_name TEXT NOT NULL,
            ref_table TEXT NOT NULL,
            ref_column TEXT NOT NULL,
            on_delete TEXT NOT NULL DEFAULT 'CASCADE',
            created_at TEXT NOT NULL
        )");
    }

    public function foreignKeys(array $project, string $table): array
    {
        $this->ensureFkSystemTable($project);
        $db = $this->connection($project);
        $stmt = $db->prepare('SELECT * FROM _system_foreign_keys WHERE table_name = ? ORDER BY id');
        $stmt->execute([$table]);
        return $stmt->fetchAll();
    }

    public function addForeignKey(array $project, string $table, string $column, string $refTable, string $refColumn = 'id', string $onDelete = 'CASCADE'): void
    {
        $this->ensureFkSystemTable($project);
        $db = $this->connection($project);
        $stmt = $db->prepare('INSERT INTO _system_foreign_keys (table_name, column_name, ref_table, ref_column, on_delete, created_at) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$table, $column, $refTable, $refColumn, $onDelete, Support::now()]);
        $this->logs->write('field.foreign_key_added', $project['uid'], $table, null, null, ['column' => $column, 'references' => "$refTable($refColumn)"]);
    }

    public function dropForeignKey(array $project, string $table, string $column): void
    {
        $this->ensureFkSystemTable($project);
        $db = $this->connection($project);
        $stmt = $db->prepare('DELETE FROM _system_foreign_keys WHERE table_name = ? AND column_name = ?');
        $stmt->execute([$table, $column]);
        $this->logs->write('field.foreign_key_removed', $project['uid'], $table);
    }

    public function relations(array $project): array
    {
        $this->ensureFkSystemTable($project);
        $db = $this->connection($project);
        return $db->query('SELECT * FROM _system_foreign_keys ORDER BY table_name, id')->fetchAll();
    }
}
