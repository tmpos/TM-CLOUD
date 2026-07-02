<?php

declare(strict_types=1);

namespace App\Services;

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
        return $this->db->query(
            'SELECT b.*, p.name AS project_name, p.slug AS project_slug
             FROM backups b
             JOIN projects p ON p.uid = b.project_uid
             ORDER BY b.id DESC'
        )->fetchAll();
    }

    public function create(array $project): array
    {
        $directory = $this->config['storage'] . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $project['uid'];
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create the backup directory.');
        }
        $uid = Support::uid('bak_');
        $path = $directory . DIRECTORY_SEPARATOR . gmdate('Ymd_His') . '_' . $uid . '.sqlite';
        $source = new PDO('sqlite:' . $project['database_path']);
        $escaped = str_replace("'", "''", $path);
        $source->exec("VACUUM INTO '$escaped'");
        $size = filesize($path) ?: 0;
        $stmt = $this->db->prepare('INSERT INTO backups (uid,project_uid,file_path,size,created_at) VALUES (?,?,?,?,?)');
        $stmt->execute([$uid, $project['uid'], $path, $size, Support::now()]);
        $this->logs->write('backup.created', $project['uid'], null, $uid);
        return ['uid' => $uid, 'project_uid' => $project['uid'], 'file_path' => $path, 'size' => $size];
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
        if (!is_file($backup['file_path'])) {
            throw new RuntimeException('Backup file is missing.');
        }
        $safety = $this->create($project);
        if (!copy($backup['file_path'], $project['database_path'])) {
            throw new RuntimeException('Could not restore the backup.');
        }
        $this->logs->write('backup.restored', $project['uid'], null, $uid, ['safety_backup' => $safety['uid']]);
    }

    public function delete(array $project, string $uid): void
    {
        $backup = $this->find($uid, $project['uid']);
        if (is_file($backup['file_path'])) {
            unlink($backup['file_path']);
        }
        $stmt = $this->db->prepare('DELETE FROM backups WHERE uid = ? AND project_uid = ?');
        $stmt->execute([$uid, $project['uid']]);
    }
}
