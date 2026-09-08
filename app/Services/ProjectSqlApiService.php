<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Executes parameterized SQL inside one already-authorized project database.
 *
 * The caller never supplies a database path. ApiController resolves the active
 * project and requires its secret key before this service is reached.
 */
final class ProjectSqlApiService
{
    private const MAX_SQL_BYTES = 100000;
    private const MAX_PARAMS = 1000;
    private const MAX_TRANSACTION_ITEMS = 100;
    private const READ_OPERATIONS = ['SELECT', 'EXPLAIN'];
    private const WRITE_OPERATIONS = ['CREATE', 'ALTER', 'INSERT', 'UPDATE', 'DELETE', 'DROP', 'REPLACE'];
    private const READ_PRAGMAS = ['table_info', 'foreign_key_check', 'index_list', 'index_info'];

    public function __construct(private SchemaService $schema, private LogService $logs)
    {
    }

    public function execute(array $project, array $input): array|null
    {
        $operation = strtolower(trim((string) ($input['operation'] ?? '')));
        return match ($operation) {
            'query' => $this->query($project, $input, false),
            'get' => $this->query($project, $input, true),
            'run' => $this->run($project, $input),
            'transaction' => $this->transaction($project, $input),
            default => throw new InvalidArgumentException('operation must be query, get, run, or transaction.'),
        };
    }

    private function query(array $project, array $input, bool $first): array|null
    {
        $sql = $this->validateSql((string) ($input['sql'] ?? ''), false);
        $params = $this->params($input['params'] ?? []);
        $statement = $this->schema->connection($project)->prepare($sql);
        $this->executeStatement($statement, $params);
        return $first ? ($statement->fetch() ?: null) : $statement->fetchAll();
    }

    private function run(array $project, array $input): array
    {
        $sql = $this->validateSql((string) ($input['sql'] ?? ''), true);
        $params = $this->params($input['params'] ?? []);
        $db = $this->schema->connection($project);
        [$sql, $params] = $this->completeTechnicalInsertFields($db, $sql, $params);
        $statement = $db->prepare($sql);
        $this->executeStatement($statement, $params);
        $result = $this->writeResult($db, $statement, $sql);
        $this->audit($project, 'run', [$sql], $result['changes']);
        return $result;
    }

    private function transaction(array $project, array $input): array
    {
        $items = $input['queries'] ?? null;
        if (!is_array($items) || !$items || count($items) > self::MAX_TRANSACTION_ITEMS) {
            throw new InvalidArgumentException('queries must contain between 1 and ' . self::MAX_TRANSACTION_ITEMS . ' items.');
        }

        $db = $this->schema->connection($project);
        $prepared = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('Every transaction item must be an object.');
            }
            $sql = $this->validateSql((string) ($item['sql'] ?? ''), true);
            $params = $this->params($item['params'] ?? []);
            [$sql, $params] = $this->completeTechnicalInsertFields($db, $sql, $params);
            $prepared[] = ['sql' => $sql, 'params' => $params];
        }

        $db->beginTransaction();
        try {
            $results = [];
            foreach ($prepared as $item) {
                $statement = $db->prepare($item['sql']);
                $this->executeStatement($statement, $item['params']);
                if ($statement->columnCount() > 0) {
                    $results[] = ['data' => $statement->fetchAll()];
                } else {
                    $results[] = $this->writeResult($db, $statement, $item['sql']);
                }
            }
            $db->commit();
            $changes = array_sum(array_map(static fn (array $result): int => (int) ($result['changes'] ?? 0), $results));
            $this->audit($project, 'transaction', array_column($prepared, 'sql'), $changes);
            return $results;
        } catch (\Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    private function validateSql(string $sql, bool $allowWrite): string
    {
        $sql = trim($sql);
        if ($sql === '' || strlen($sql) > self::MAX_SQL_BYTES) {
            throw new InvalidArgumentException('SQL must contain between 1 and ' . self::MAX_SQL_BYTES . ' bytes.');
        }
        if (str_contains($sql, "\0") || preg_match('/(?:--|\/\*|\*\/)/', $sql)) {
            throw new InvalidArgumentException('SQL comments and null bytes are not allowed.');
        }
        $withoutTrailingSemicolon = (string) preg_replace('/;\s*$/', '', $sql);
        if (str_contains($withoutTrailingSemicolon, ';')) {
            throw new InvalidArgumentException('Only one SQL statement is allowed per item.');
        }
        $sql = trim($withoutTrailingSemicolon);
        if (preg_match('/\b(?:ATTACH|DETACH|VACUUM|REINDEX|ANALYZE|LOAD_EXTENSION)\b/i', $sql)) {
            throw new InvalidArgumentException('That SQL operation is not allowed.');
        }

        preg_match('/^([A-Za-z]+)/', ltrim($sql), $match);
        $verb = strtoupper((string) ($match[1] ?? ''));
        if ($verb === 'PRAGMA') {
            if ($allowWrite && preg_match('/^PRAGMA\s+foreign_keys\s*=\s*(?:ON|OFF|0|1)$/i', $sql)) {
                return $sql;
            }
            if (preg_match('/^PRAGMA\s+([A-Za-z_]+)/i', $sql, $pragma) && in_array(strtolower($pragma[1]), self::READ_PRAGMAS, true)) {
                return $sql;
            }
            throw new InvalidArgumentException('That PRAGMA is not allowed.');
        }
        if (in_array($verb, self::READ_OPERATIONS, true)) {
            return $sql;
        }
        if ($allowWrite && in_array($verb, self::WRITE_OPERATIONS, true)) {
            return $sql;
        }
        throw new RuntimeException($allowWrite ? 'SQL operation is not supported.' : 'Only read-only SQL is allowed.', 403);
    }

    private function params(mixed $params): array
    {
        if (!is_array($params) || !array_is_list($params) || count($params) > self::MAX_PARAMS) {
            throw new InvalidArgumentException('params must be a list containing at most ' . self::MAX_PARAMS . ' values.');
        }
        return array_map(static function (mixed $value): mixed {
            if (is_array($value) || is_object($value)) {
                return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            }
            if (is_bool($value)) {
                return $value ? 1 : 0;
            }
            if ($value === null || is_int($value) || is_float($value) || is_string($value)) {
                return $value;
            }
            throw new InvalidArgumentException('Unsupported SQL parameter type.');
        }, $params);
    }

    private function executeStatement(\PDOStatement $statement, array $params): void
    {
        foreach ($params as $index => $value) {
            $type = match (true) {
                $value === null => PDO::PARAM_NULL,
                is_int($value) => PDO::PARAM_INT,
                default => PDO::PARAM_STR,
            };
            $statement->bindValue($index + 1, $value, $type);
        }
        $statement->execute();
    }

    /**
     * Generic API tables own uid/created_at/updated_at. Legacy applications do
     * not know those columns, so fill them server-side for a simple VALUES
     * insert while keeping the original parameterized statement intact.
     */
    private function completeTechnicalInsertFields(PDO $db, string $sql, array $params): array
    {
        $identifier = '(?:"[^"]+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)';
        $insertPrefix = '\s*INSERT\s+(?:OR\s+(?:ROLLBACK|ABORT|REPLACE|FAIL|IGNORE)\s+)?INTO\s+';
        $valuesPattern = '/^(' . $insertPrefix . '(' . $identifier . ')\s*\()([^)]*)(\)\s*VALUES\s*\()([^)]*)(\)\s*)$/is';
        $selectPattern = '/^(' . $insertPrefix . '(' . $identifier . ')\s*\()([^)]*)(\)\s*SELECT\s+)(.*?)(\s+WHERE\s+.*)$/is';
        if (preg_match($valuesPattern, $sql, $match) !== 1 && preg_match($selectPattern, $sql, $match) !== 1) {
            return [$sql, $params];
        }

        $table = trim($match[2], "\"`[]");
        $columns = array_map(
            static fn (string $column): string => strtolower(trim(trim($column), "\"`[]")),
            explode(',', $match[3])
        );
        $columnRows = $db->query('PRAGMA table_info(' . $this->quoteIdentifier($table) . ')')->fetchAll();
        $available = array_map(static fn (array $column): string => strtolower((string) $column['name']), $columnRows);

        $extraColumns = [];
        $extraValues = [];
        $now = gmdate('Y-m-d H:i:s');
        foreach ([
            'uid' => 'rec_' . bin2hex(random_bytes(16)),
            'created_at' => $now,
            'updated_at' => $now,
        ] as $column => $value) {
            if (in_array($column, $available, true) && !in_array($column, $columns, true)) {
                $extraColumns[] = $this->quoteIdentifier($column);
                $extraValues[] = $value;
            }
        }
        if (!$extraColumns) {
            return [$sql, $params];
        }

        $placeholders = implode(', ', array_fill(0, count($extraValues), '?'));
        $sql = $match[1] . $match[3] . ', ' . implode(', ', $extraColumns)
            . $match[4] . $match[5] . ', ' . $placeholders . $match[6];
        return [$sql, [...$params, ...$extraValues]];
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    private function writeResult(PDO $db, \PDOStatement $statement, string $sql): array
    {
        $insert = preg_match('/^\s*(?:INSERT|REPLACE)\b/i', $sql) === 1;
        return [
            'changes' => $statement->rowCount(),
            'lastInsertRowid' => $insert ? (int) $db->lastInsertId() : 0,
        ];
    }

    private function audit(array $project, string $operation, array $statements, int $changes): void
    {
        $this->logs->write('api.sql.' . $operation, $project['uid'], null, null, null, [
            'statement_count' => count($statements),
            'verbs' => array_map(static fn (string $sql): string => strtoupper((string) strtok(ltrim($sql), " \t\r\n")), $statements),
            'sql_hashes' => array_map(static fn (string $sql): string => hash('sha256', $sql), $statements),
            'changes' => $changes,
        ]);
    }
}
