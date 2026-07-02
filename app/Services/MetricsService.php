<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class MetricsService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS _metrics (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            project_uid TEXT,
            metric TEXT NOT NULL,
            value INTEGER NOT NULL DEFAULT 1,
            recorded_at TEXT NOT NULL
        )");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_metrics_project ON _metrics(project_uid, metric, recorded_at)");
    }

    public function track(string $metric, ?string $projectUid = null, int $value = 1): void
    {
        $stmt = $this->db->prepare('INSERT INTO _metrics (project_uid, metric, value, recorded_at) VALUES (?,?,?,?)');
        $stmt->execute([$projectUid, $metric, $value, gmdate('Y-m-d H:i:s')]);
    }

    public function summary(?string $projectUid = null, string $period = '24h'): array
    {
        $since = match ($period) {
            '1h' => gmdate('Y-m-d H:i:s', time() - 3600),
            '24h' => gmdate('Y-m-d H:i:s', time() - 86400),
            '7d' => gmdate('Y-m-d H:i:s', time() - 604800),
            '30d' => gmdate('Y-m-d H:i:s', time() - 2592000),
            default => gmdate('Y-m-d H:i:s', time() - 86400),
        };

        $where = 'recorded_at >= ?';
        $params = [$since];
        if ($projectUid) {
            $where .= ' AND project_uid = ?';
            $params[] = $projectUid;
        }

        $stmt = $this->db->prepare("SELECT metric, SUM(value) as total FROM _metrics WHERE {$where} GROUP BY metric ORDER BY total DESC");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function timeline(string $metric, ?string $projectUid = null, string $period = '24h'): array
    {
        $since = match ($period) {
            '1h' => gmdate('Y-m-d H:i:s', time() - 3600),
            '24h' => gmdate('Y-m-d H:i:s', time() - 86400),
            '7d' => gmdate('Y-m-d H:i:s', time() - 604800),
            '30d' => gmdate('Y-m-d H:i:s', time() - 2592000),
            default => gmdate('Y-m-d H:i:s', time() - 86400),
        };
        $fmt = $period === '1h' ? '%H:%i' : ($period === '24h' ? '%H:00' : '%Y-%m-%d');

        $where = 'metric = ? AND recorded_at >= ?';
        $params = [$metric, $since];
        if ($projectUid) {
            $where .= ' AND project_uid = ?';
            $params[] = $projectUid;
        }

        $stmt = $this->db->prepare("SELECT strftime('{$fmt}', recorded_at) as bucket, SUM(value) as total FROM _metrics WHERE {$where} GROUP BY bucket ORDER BY bucket ASC");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function storageUsage(string $storage): array
    {
        $total = 0;
        $byDir = [];
        foreach (['backups', 'projects', 'uploads', 'functions'] as $dir) {
            $path = $storage . '/' . $dir;
            $size = $this->dirSize($path);
            $byDir[$dir] = $size;
            $total += $size;
        }
        return ['total' => $total, 'details' => $byDir];
    }

    private function dirSize(string $path): int
    {
        $size = 0;
        if (!is_dir($path)) return 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $f) {
            $size += $f->getSize();
        }
        return $size;
    }

    public function globalSummary(): array
    {
        $projects = (int) $this->db->query('SELECT COUNT(*) FROM projects')->fetchColumn();
        $stmt = $this->db->query('SELECT SUM(value) FROM _metrics WHERE metric = ?');
        $apiCalls = (int) $stmt->execute(['api_request']) ? (int) $stmt->fetchColumn() : 0;
        return [
            'projects' => $projects,
            'api_calls' => $apiCalls,
        ];
    }
}
