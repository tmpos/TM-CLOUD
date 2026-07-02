<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Support;
use PDO;

final class MigrationService
{
    private PDO $db;
    private string $storage;

    public function __construct(PDO $db, string $storage)
    {
        $this->db = $db;
        $this->storage = $storage;
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS _migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            version TEXT UNIQUE NOT NULL,
            name TEXT NOT NULL,
            applied_at TEXT NOT NULL,
            batch INTEGER NOT NULL
        )");
    }

    public function all(): array
    {
        $dir = $this->storage . '/migrations';
        if (!is_dir($dir)) return [];

        $files = glob($dir . '/*_*.up.sql');
        $migrations = [];

        foreach ($files as $file) {
            $basename = basename($file);
            if (!preg_match('/^(\d+)_(.+)\.up\.sql$/', $basename, $m)) continue;
            $version = $m[1];
            $name = $m[2];
            $applied = $this->db->prepare('SELECT applied_at, batch FROM _migrations WHERE version = ?');
            $applied->execute([$version]);
            $row = $applied->fetch();
            $migrations[] = [
                'version' => $version,
                'name' => $name,
                'applied' => $row !== false,
                'applied_at' => $row['applied_at'] ?? null,
                'batch' => $row ? (int) $row['batch'] : null,
                'up_file' => $file,
                'down_file' => str_replace('.up.sql', '.down.sql', $file),
            ];
        }

        usort($migrations, fn($a, $b) => $a['version'] <=> $b['version']);
        return $migrations;
    }

    public function migrate(string $projectUid): array
    {
        $output = [];
        $migrations = array_filter($this->all(), fn($m) => !$m['applied']);
        $batch = $this->nextBatch();

        $projectDb = $this->getProjectDb($projectUid);

        foreach ($migrations as $m) {
            $sql = file_get_contents($m['up_file']);
            if ($sql === false) {
                $output[] = "ERROR: Cannot read {$m['up_file']}";
                break;
            }
            try {
                $projectDb->exec($sql);
                $stmt = $this->db->prepare('INSERT INTO _migrations (version, name, applied_at, batch) VALUES (?,?,?,?)');
                $stmt->execute([$m['version'], $m['name'], Support::now(), $batch]);
                $output[] = "OK: {$m['version']}_{$m['name']}";
            } catch (\Throwable $e) {
                $output[] = "ERROR: {$m['version']}_{$m['name']}: {$e->getMessage()}";
                break;
            }
        }

        return $output;
    }

    public function rollback(string $projectUid, int $batch = 0): array
    {
        $output = [];
        $lastBatch = $batch ?: $this->lastBatch();
        if (!$lastBatch) return ['Nothing to rollback.'];

        $stmt = $this->db->prepare('SELECT version, name FROM _migrations WHERE batch = ? ORDER BY id DESC');
        $stmt->execute([$lastBatch]);
        $applied = $stmt->fetchAll();

        $projectDb = $this->getProjectDb($projectUid);

        foreach ($applied as $m) {
            $downFile = $this->storage . '/migrations/' . $m['version'] . '_' . $m['name'] . '.down.sql';
            if (!file_exists($downFile)) {
                $output[] = "SKIP: {$m['version']}_{$m['name']} (no down file)";
                continue;
            }
            $sql = file_get_contents($downFile);
            if ($sql === false) {
                $output[] = "ERROR: Cannot read $downFile";
                break;
            }
            try {
                $projectDb->exec($sql);
                $stmt = $this->db->prepare('DELETE FROM _migrations WHERE version = ?');
                $stmt->execute([$m['version']]);
                $output[] = "ROLLBACK: {$m['version']}_{$m['name']}";
            } catch (\Throwable $e) {
                $output[] = "ERROR: {$m['version']}_{$m['name']}: {$e->getMessage()}";
                break;
            }
        }

        return $output;
    }

    private function nextBatch(): int
    {
        return ((int) $this->db->query('SELECT COALESCE(MAX(batch),0) FROM _migrations')->fetchColumn()) + 1;
    }

    private function lastBatch(): int
    {
        return (int) $this->db->query('SELECT COALESCE(MAX(batch),0) FROM _migrations')->fetchColumn();
    }

    private function getProjectDb(string $projectUid): PDO
    {
        $stmt = $this->db->prepare('SELECT database_path FROM projects WHERE uid = ?');
        $stmt->execute([$projectUid]);
        $project = $stmt->fetch();
        if (!$project) throw new \RuntimeException('Project not found');
        return new PDO('sqlite:' . $project['database_path']);
    }
}
