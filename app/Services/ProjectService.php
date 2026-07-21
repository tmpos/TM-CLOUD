<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Support;
use PDO;
use RuntimeException;

final class ProjectService
{
    public function __construct(private PDO $db, private array $config, private LogService $logs)
    {
    }

    public function all(): array
    {
        return $this->db->query('SELECT * FROM projects ORDER BY id DESC')->fetchAll();
    }

    public function find(string $uid): array
    {
        $stmt = $this->db->prepare('SELECT * FROM projects WHERE uid = ? LIMIT 1');
        $stmt->execute([$uid]);
        return $stmt->fetch() ?: throw new RuntimeException('Project not found.');
    }

    public function findActive(string $uid): array
    {
        $project = $this->find($uid);
        if (($project['status'] ?? '') !== 'active') {
            throw new RuntimeException('Project is not active.', 403);
        }
        return $project;
    }

    public function create(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || strlen($name) > 100) {
            throw new \InvalidArgumentException('Project name is required and must not exceed 100 characters.');
        }
        $baseSlug = Support::slug((string) ($data['slug'] ?? $name));
        $slug = $baseSlug;
        $suffix = 1;
        $check = $this->db->prepare('SELECT COUNT(*) FROM projects WHERE slug = ?');
        while (true) {
            $check->execute([$slug]);
            if ((int) $check->fetchColumn() === 0) {
                break;
            }
            $slug = $baseSlug . '-' . ++$suffix;
        }

        $uid = Support::uid('prj_');
        $directory = $this->config['storage'] . DIRECTORY_SEPARATOR . 'projects' . DIRECTORY_SEPARATOR . $uid;
        if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create the project directory.');
        }
        $path = $directory . DIRECTORY_SEPARATOR . 'database.sqlite';
        $projectDb = Database::connect($path);
        $this->initializeProjectDatabase($projectDb);
        $now = Support::now();
        $project = [
            'uid' => $uid, 'name' => $name, 'slug' => $slug,
            'description' => trim((string) ($data['description'] ?? '')), 'database_path' => $path,
            'public_key' => Support::apiKey('public'), 'secret_key' => Support::apiKey('secret'),
            'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
        ];
        $stmt = $this->db->prepare(
            'INSERT INTO projects (uid,name,slug,description,database_path,public_key,secret_key,status,created_at,updated_at)
             VALUES (:uid,:name,:slug,:description,:database_path,:public_key,:secret_key,:status,:created_at,:updated_at)'
        );
        $stmt->execute($project);
        $this->logs->write('project.created', $uid, null, null, null, ['name' => $name, 'slug' => $slug]);
        return $this->find($uid);
    }

    public function regenerateKeys(string $uid): array
    {
        $this->find($uid);
        $public = Support::apiKey('public');
        $secret = Support::apiKey('secret');
        $stmt = $this->db->prepare('UPDATE projects SET public_key = ?, secret_key = ?, updated_at = ? WHERE uid = ?');
        $stmt->execute([$public, $secret, Support::now(), $uid]);
        $this->logs->write('project.keys_regenerated', $uid);
        return $this->find($uid);
    }

    public function rotateKey(string $uid, string $type): array
    {
        $this->find($uid);
        if (!in_array($type, ['public', 'secret'], true)) {
            throw new \InvalidArgumentException('Key type must be public or secret.');
        }
        $column = $type . '_key';
        $key = Support::apiKey($type);
        $stmt = $this->db->prepare("UPDATE projects SET $column = ?, updated_at = ? WHERE uid = ?");
        $stmt->execute([$key, Support::now(), $uid]);
        $this->logs->write('project.' . $type . '_key_rotated', $uid);
        return ['type' => $type, 'key' => $key, 'project' => $this->find($uid)];
    }

    public function delete(string $uid): void
    {
        $project = $this->find($uid);
        $storageRoot = realpath($this->config['storage']);
        $projectDirectory = realpath(dirname($project['database_path']));
        if (!$storageRoot || !$projectDirectory || !str_starts_with($projectDirectory, $storageRoot . DIRECTORY_SEPARATOR . 'projects' . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Refusing to remove a project outside the storage directory.');
        }
        $this->logs->write('project.deleted', $uid, null, null, [
            'uid' => $project['uid'],
            'name' => $project['name'],
            'slug' => $project['slug'],
            'status' => $project['status'],
        ]);
        $this->db->beginTransaction();
        try {
            foreach (['webhooks', 'backups', 'project_logs'] as $table) {
                $stmt = $this->db->prepare("DELETE FROM $table WHERE project_uid = ?");
                $stmt->execute([$uid]);
            }
            $stmt = $this->db->prepare('DELETE FROM projects WHERE uid = ?');
            $stmt->execute([$uid]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        $this->removeDirectory($projectDirectory);
        foreach (['backups', 'uploads'] as $area) {
            $path = $this->config['storage'] . DIRECTORY_SEPARATOR . $area . DIRECTORY_SEPARATOR . $uid;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            }
        }
    }

    private function initializeProjectDatabase(PDO $db): void
    {
        $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS _system_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT, uid TEXT UNIQUE NOT NULL, event TEXT NOT NULL,
    payload TEXT, created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS _system_files (
    id INTEGER PRIMARY KEY AUTOINCREMENT, uid TEXT UNIQUE NOT NULL, original_name TEXT NOT NULL,
    stored_name TEXT NOT NULL, mime_type TEXT NOT NULL, size INTEGER NOT NULL, path TEXT NOT NULL,
    url TEXT NOT NULL, directory TEXT NOT NULL DEFAULT '/', created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS _system_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT, key TEXT UNIQUE NOT NULL, value TEXT, updated_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS _system_table_settings (
    table_name TEXT PRIMARY KEY, access_mode TEXT NOT NULL DEFAULT 'private',
    visible_columns TEXT, editable_columns TEXT, soft_delete INTEGER NOT NULL DEFAULT 0,
    updated_at TEXT NOT NULL
);
SQL);
    }

    private function removeDirectory(string $directory): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($directory);
    }
}
