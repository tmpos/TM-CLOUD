<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Http;
use App\Core\Support;
use PDO;

final class LogService
{
    public function __construct(private PDO $db)
    {
    }

    public function write(string $action, ?string $projectUid = null, ?string $table = null, ?string $recordUid = null, mixed $old = null, mixed $new = null): void
    {
        $old = $this->sanitize($old);
        $new = $this->sanitize($new);
        $stmt = $this->db->prepare(
            'INSERT INTO project_logs (uid,project_uid,user_uid,action,table_name,record_uid,old_data,new_data,ip_address,user_agent,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            Support::uid('log_'), $projectUid, Auth::user()['uid'] ?? null, $action, $table, $recordUid,
            $old === null ? null : Support::json($old), $new === null ? null : Support::json($new),
            Http::clientIp(), substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500), Support::now(),
        ]);
    }

    private function sanitize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $sanitized = [];
        foreach ($value as $key => $item) {
            $name = strtolower((string) $key);
            if (preg_match('/(?:password|passwd|secret|token|api[_-]?key|public[_-]?key|private[_-]?key|authorization|credential|smtp[_-]?pass)/i', $name)) {
                $sanitized[$key] = '[REDACTED]';
                continue;
            }
            $sanitized[$key] = $this->sanitize($item);
        }
        return $sanitized;
    }

    public function deletedSince(string $projectUid, string $since): array
    {
        $stmt = $this->db->prepare(
            'SELECT table_name, record_uid, old_data, created_at FROM project_logs
             WHERE project_uid = ? AND action = ? AND created_at >= ?
             ORDER BY created_at ASC'
        );
        $stmt->execute([$projectUid, 'record.deleted', $since]);
        $rows = $stmt->fetchAll();
        $grouped = [];
        foreach ($rows as $row) {
            $table = $row['table_name'];
            if (!isset($grouped[$table])) {
                $grouped[$table] = [];
            }
            $grouped[$table][] = [
                'uid' => $row['record_uid'],
                'data' => $row['old_data'] ? json_decode($row['old_data'], true) : null,
                'deleted_at' => $row['created_at'],
            ];
        }
        return $grouped;
    }

    public function recent(?string $projectUid = null, int $limit = 50): array
    {
        if ($projectUid) {
            $stmt = $this->db->prepare('SELECT * FROM project_logs WHERE project_uid = ? ORDER BY id DESC LIMIT ?');
            $stmt->bindValue(1, $projectUid);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        }
        $stmt = $this->db->prepare('SELECT * FROM project_logs ORDER BY id DESC LIMIT ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
