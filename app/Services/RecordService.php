<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Support;
use PDO;
use RuntimeException;

final class RecordService
{
    public function __construct(private SchemaService $schema, private LogService $logs)
    {
    }

    public function paginate(array $project, string $table, array $query): array
    {
        $db = $this->schema->connection($project);
        $columns = $this->columnNames($project, $table);
        $tableSql = Support::quoteIdentifier($table);
        $page = max(1, (int) ($query['page'] ?? 1));
        $limit = min(100, max(1, (int) ($query['limit'] ?? 25)));
        $where = [];
        $params = [];
        $search = trim((string) ($query['search'] ?? ''));
        if ($search !== '') {
            $searchable = array_values(array_diff($columns, ['id']));
            $where[] = '(' . implode(' OR ', array_map(fn ($c) => 'CAST(' . Support::quoteIdentifier($c) . ' AS TEXT) LIKE ?', $searchable)) . ')';
            $params = array_fill(0, count($searchable), '%' . $search . '%');
        }
        foreach ($query as $key => $value) {
            if (!str_starts_with((string) $key, 'filter_')) {
                continue;
            }
            $column = substr((string) $key, 7);
            if (in_array($column, $columns, true)) {
                $where[] = Support::quoteIdentifier($column) . ' = ?';
                $params[] = $value;
            }
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $orderBy = in_array($query['order_by'] ?? '', $columns, true) ? $query['order_by'] : 'id';
        $direction = strtoupper((string) ($query['order_dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $count = $db->prepare("SELECT COUNT(*) FROM $tableSql$whereSql");
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $sql = "SELECT * FROM $tableSql$whereSql ORDER BY " . Support::quoteIdentifier($orderBy) . " $direction LIMIT ? OFFSET ?";
        $stmt = $db->prepare($sql);
        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value);
        }
        $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, ($page - 1) * $limit, PDO::PARAM_INT);
        $stmt->execute();
        return ['data' => $stmt->fetchAll(), 'meta' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'pages' => (int) ceil($total / $limit)]];
    }

    public function find(array $project, string $table, string $uid): array
    {
        $stmt = $this->schema->connection($project)->prepare(
            'SELECT * FROM ' . Support::quoteIdentifier($table) . ' WHERE uid = ? LIMIT 1'
        );
        $stmt->execute([$uid]);
        return $stmt->fetch() ?: throw new RuntimeException('Record not found.');
    }

    public function all(array $project, string $table): array
    {
        $table = Support::identifier($table, 'table name');
        $this->schema->columns($project, $table);
        return $this->schema->connection($project)
            ->query('SELECT * FROM ' . Support::quoteIdentifier($table) . ' ORDER BY id ASC')
            ->fetchAll();
    }

    public function create(array $project, string $table, array $data): array
    {
        $db = $this->schema->connection($project);
        $data = $this->sanitize($project, $table, $data, true);
        $now = Support::now();
        $data['uid'] = trim((string) ($data['uid'] ?? '')) ?: Support::uid('rec_');
        $data['created_at'] = trim((string) ($data['created_at'] ?? '')) ?: $now;
        $data['updated_at'] = trim((string) ($data['updated_at'] ?? '')) ?: $now;
        $columns = array_keys($data);
        $sql = 'INSERT INTO ' . Support::quoteIdentifier($table) . ' (' .
            implode(',', array_map([Support::class, 'quoteIdentifier'], $columns)) . ') VALUES (' .
            implode(',', array_fill(0, count($columns), '?')) . ')';
        $db->prepare($sql)->execute(array_values($data));
        $record = $this->find($project, $table, $data['uid']);
        $this->logs->write('record.created', $project['uid'], $table, $data['uid'], null, $record);
        return $record;
    }

    public function update(array $project, string $table, string $uid, array $data): array
    {
        $old = $this->find($project, $table, $uid);
        $data = $this->sanitize($project, $table, $data, false);
        unset($data['uid'], $data['created_at']);
        $data['updated_at'] = trim((string) ($data['updated_at'] ?? '')) ?: Support::now();
        $assignments = implode(',', array_map(fn ($c) => Support::quoteIdentifier($c) . ' = ?', array_keys($data)));
        $values = [...array_values($data), $uid];
        $this->schema->connection($project)->prepare(
            'UPDATE ' . Support::quoteIdentifier($table) . " SET $assignments WHERE uid = ?"
        )->execute($values);
        $record = $this->find($project, $table, $uid);
        $this->logs->write('record.updated', $project['uid'], $table, $uid, $old, $record);
        return $record;
    }

    public function delete(array $project, string $table, string $uid): void
    {
        $old = $this->find($project, $table, $uid);
        $stmt = $this->schema->connection($project)->prepare('DELETE FROM ' . Support::quoteIdentifier($table) . ' WHERE uid = ?');
        $stmt->execute([$uid]);
        $this->logs->write('record.deleted', $project['uid'], $table, $uid, $old);
    }

    public function deleteBulk(array $project, string $table, array $uids): array
    {
        if (count($uids) > 1000) {
            throw new \InvalidArgumentException('Cannot delete more than 1000 records at once.');
        }
        $db = $this->schema->connection($project);
        $deleted = 0;
        $errors = [];
        $db->beginTransaction();
        foreach ($uids as $uid) {
            try {
                $this->delete($project, $table, (string) $uid);
                $deleted++;
            } catch (\Throwable $e) {
                $errors[] = ['uid' => $uid, 'error' => $e->getMessage()];
            }
        }
        $db->commit();
        return ['deleted' => $deleted, 'failed' => count($errors), 'errors' => $errors];
    }

    public function bulk(array $project, string $table, array $rows): array
    {
        if (count($rows) > 1000) {
            throw new \InvalidArgumentException('A bulk request may contain at most 1000 rows.');
        }
        $db = $this->schema->connection($project);
        $inserted = 0;
        $errors = [];
        $db->beginTransaction();
        foreach ($rows as $index => $row) {
            try {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('Row must be an object.');
                }
                $this->create($project, $table, $row);
                $inserted++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $index, 'error' => $e->getMessage()];
            }
        }
        $db->commit();
        return ['inserted' => $inserted, 'failed' => count($errors), 'errors' => $errors];
    }

    public function upsert(array $project, string $table, array $rows): array
    {
        if (count($rows) > 1000) {
            throw new \InvalidArgumentException('An upsert request may contain at most 1000 rows.');
        }
        $db = $this->schema->connection($project);
        $inserted = 0;
        $updated = 0;
        $errors = [];
        $db->beginTransaction();
        foreach ($rows as $index => $row) {
            try {
                if (!is_array($row) || empty($row['uid'])) {
                    throw new \InvalidArgumentException('Each row must contain a uid.');
                }
                try {
                    $this->find($project, $table, (string) $row['uid']);
                    $uid = (string) $row['uid'];
                    unset($row['uid'], $row['created_at']);
                    $this->update($project, $table, $uid, $row);
                    $updated++;
                } catch (RuntimeException) {
                    $this->create($project, $table, $row);
                    $inserted++;
                }
            } catch (\Throwable $e) {
                $errors[] = ['row' => $index, 'error' => $e->getMessage()];
            }
        }
        $db->commit();
        return ['inserted' => $inserted, 'updated' => $updated, 'failed' => count($errors), 'errors' => $errors];
    }

    public function modified(array $project, string $table, string $from, ?string $to = null): array
    {
        $sql = 'SELECT * FROM ' . Support::quoteIdentifier($table) . ' WHERE updated_at >= ?';
        $params = [$from];
        if ($to !== null) {
            $sql .= ' AND updated_at <= ?';
            $params[] = $to;
        }
        $sql .= ' ORDER BY updated_at ASC';
        $stmt = $this->schema->connection($project)->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function sanitize(array $project, string $table, array $data, bool $creating): array
    {
        $columns = $this->columnNames($project, $table);
        $allowed = $creating
            ? array_diff($columns, ['id'])
            : array_diff($columns, ['id', 'uid', 'created_at']);
        $unknown = array_diff(array_keys($data), $allowed);
        if ($unknown) {
            throw new \InvalidArgumentException('Unknown fields: ' . implode(', ', $unknown));
        }
        foreach ($data as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $data[$key] = Support::json($value);
            }
        }
        return $data;
    }

    private function columnNames(array $project, string $table): array
    {
        return array_column($this->schema->columns($project, Support::identifier($table, 'table name')), 'name');
    }
}
