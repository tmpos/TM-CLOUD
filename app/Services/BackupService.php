<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Support;
use PDO;
use RuntimeException;

final class BackupService
{
    public function __construct(private PDO $db, private array $config, private LogService $logs)
    {
    }

    public function all(string $projectUid): array
    {
        $stmt = $this->db->prepare('SELECT * FROM backups WHERE project_uid = ? ORDER BY id DESC');
        $stmt->execute([$projectUid]);
        return $stmt->fetchAll();
    }

    public function allGlobal(): array
    {
        return $this->db->query('SELECT b.*, p.name AS project_name, p.slug AS project_slug FROM backups b JOIN projects p ON p.uid = b.project_uid ORDER BY b.id DESC')->fetchAll();
    }

    public function apiData(array $backup): array
    {
        return [
            'uid' => $backup['uid'], 'project_uid' => $backup['project_uid'],
            'size' => (int) $backup['size'], 'checksum' => $backup['checksum'] ?? null,
            'status' => $backup['status'] ?? 'valid', 'created_at' => $backup['created_at'],
        ];
    }

    public function create(array $project): array
    {
        $directory = $this->config['storage'] . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $project['uid'];
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) throw new RuntimeException('Could not create the backup directory.');
        $uid = Support::uid('bak_');
        $path = $directory . DIRECTORY_SEPARATOR . gmdate('Ymd_His') . '_' . $uid . '.sqlite';
        $source = Database::connect($project['database_path']);
        $source->exec('PRAGMA wal_checkpoint(FULL)');
        $source->exec("VACUUM INTO '" . str_replace("'", "''", $path) . "'");
        $size = filesize($path) ?: 0;
        $candidate = new PDO('sqlite:' . $path);
        if (strtolower((string) $candidate->query('PRAGMA integrity_check')->fetchColumn()) !== 'ok') {
            $candidate = null; @unlink($path); throw new RuntimeException('Backup integrity check failed.');
        }
        $candidate = null;
        $checksum = hash_file('sha256', $path) ?: null;
        $createdAt = Support::now();
        $this->db->prepare('INSERT INTO backups (uid,project_uid,file_path,size,checksum,status,created_at) VALUES (?,?,?,?,?,\'valid\',?)')
            ->execute([$uid, $project['uid'], $path, $size, $checksum, $createdAt]);
        $this->cleanup($project['uid']);
        $this->logs->write('backup.created', $project['uid'], null, $uid, null, ['checksum' => $checksum, 'size' => $size]);
        return ['uid' => $uid, 'project_uid' => $project['uid'], 'file_path' => $path, 'size' => $size, 'checksum' => $checksum, 'status' => 'valid', 'created_at' => $createdAt];
    }

    public function find(string $uid, string $projectUid): array
    {
        $stmt = $this->db->prepare('SELECT * FROM backups WHERE uid = ? AND project_uid = ? LIMIT 1');
        $stmt->execute([$uid, $projectUid]);
        return $stmt->fetch() ?: throw new RuntimeException('Backup not found.');
    }

    public function restore(array $project, string $uid): void
    {
        $backup = $this->find($uid, $project['uid']);
        if (!is_file($backup['file_path'])) throw new RuntimeException('Backup file is missing.');
        $actualChecksum = hash_file('sha256', $backup['file_path']) ?: '';
        if (!empty($backup['checksum']) && !hash_equals((string) $backup['checksum'], $actualChecksum)) throw new RuntimeException('Backup checksum does not match.');
        $safety = $this->create($project);
        $databasePath = $project['database_path'];
        $temporary = $databasePath . '.restore-' . bin2hex(random_bytes(6));
        $rollback = $databasePath . '.rollback-' . bin2hex(random_bytes(6));
        if (!copy($backup['file_path'], $temporary)) throw new RuntimeException('Could not prepare the backup restore.');
        try {
            $candidate = new PDO('sqlite:' . $temporary);
            if (strtolower((string) $candidate->query('PRAGMA integrity_check')->fetchColumn()) !== 'ok') throw new RuntimeException('Backup integrity check failed.');
            $candidate = null;
            $current = Database::connect($databasePath);
            $current->exec('PRAGMA wal_checkpoint(TRUNCATE)');
            $current = null;
            Database::disconnect($databasePath);
            foreach ([$databasePath . '-wal', $databasePath . '-shm'] as $sidecar) if (is_file($sidecar)) @unlink($sidecar);
            if (!rename($databasePath, $rollback)) throw new RuntimeException('Could not preserve the current database.');
            if (!rename($temporary, $databasePath)) {
                rename($rollback, $databasePath);
                throw new RuntimeException('Could not activate the restored database.');
            }
            $restored = Database::connect($databasePath);
            if (strtolower((string) $restored->query('PRAGMA integrity_check')->fetchColumn()) !== 'ok') {
                $restored = null; Database::disconnect($databasePath); @unlink($databasePath); rename($rollback, $databasePath);
                throw new RuntimeException('Restored database failed final integrity check.');
            }
            @unlink($rollback);
        } catch (\Throwable $e) {
            @unlink($temporary);
            if (!is_file($databasePath) && is_file($rollback)) rename($rollback, $databasePath);
            throw $e;
        }
        $this->logs->write('backup.restored', $project['uid'], null, $uid, ['safety_backup' => $safety['uid']]);
    }

    public function delete(array $project, string $uid): void
    {
        $backup = $this->find($uid, $project['uid']);
        if (is_file($backup['file_path'])) unlink($backup['file_path']);
        $this->db->prepare('DELETE FROM backups WHERE uid = ? AND project_uid = ?')->execute([$uid, $project['uid']]);
    }

    private function cleanup(string $projectUid): void
    {
        $limit = max(1, (int) ($this->config['backup_retention_count'] ?? 20));
        $stmt = $this->db->prepare('SELECT * FROM backups WHERE project_uid = ? ORDER BY created_at DESC, id DESC');
        $stmt->execute([$projectUid]);
        $backups = $stmt->fetchAll();
        $toDelete = array_slice($backups, $limit);
        $kept = array_slice($backups, 0, $limit);
        $retentionDays = max(1, (int) ($this->config['backup_retention_days'] ?? 90));
        $cutoff = time() - ($retentionDays * 86400);
        foreach (array_slice($kept, 1) as $candidate) {
            $created = strtotime((string) ($candidate['created_at'] ?? ''));
            if ($created !== false && $created < $cutoff) {
                $toDelete[] = $candidate;
                $kept = array_values(array_filter($kept, static fn (array $item): bool => $item['id'] !== $candidate['id']));
            }
        }
        $maxBytes = max(0, (int) ($this->config['backup_max_bytes_per_project'] ?? 0));
        $usedBytes = array_sum(array_map(static fn (array $backup): int => (int) $backup['size'], $kept));
        while ($maxBytes > 0 && $usedBytes > $maxBytes && count($kept) > 1) {
            $old = array_pop($kept);
            $usedBytes -= (int) $old['size'];
            $toDelete[] = $old;
        }
        $seen = [];
        foreach ($toDelete as $old) {
            if (isset($seen[$old['id']])) continue;
            $seen[$old['id']] = true;
            if (is_file($old['file_path'])) @unlink($old['file_path']);
            $this->db->prepare('DELETE FROM backups WHERE id = ?')->execute([$old['id']]);
        }
    }
}
