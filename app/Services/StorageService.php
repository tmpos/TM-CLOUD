<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Support;
use RuntimeException;

final class StorageService
{
    public function __construct(private array $config, private SchemaService $schema, private LogService $logs)
    {
    }

    public function apiData(array $file): array
    {
        return array_intersect_key($file, array_flip([
            'uid', 'original_name', 'mime_type', 'size', 'url', 'directory', 'created_at',
        ]));
    }

    private function ensureDirectoryColumn(array $project): void
    {
        $db = $this->schema->connection($project);
        try {
            $db->exec("ALTER TABLE _system_files ADD COLUMN directory TEXT NOT NULL DEFAULT '/'");
        } catch (\Throwable) {
        }
    }

    public function all(array $project, ?string $directory = null): array
    {
        $this->ensureDirectoryColumn($project);
        $db = $this->schema->connection($project);
        if ($directory !== null) {
            $stmt = $db->prepare('SELECT * FROM _system_files WHERE directory = ? ORDER BY id DESC');
            $stmt->execute([$directory]);
        } else {
            $stmt = $db->query('SELECT * FROM _system_files ORDER BY id DESC');
        }
        return $stmt->fetchAll();
    }

    public function allGlobal(array $projects): array
    {
        $files = [];
        foreach ($projects as $project) {
            try {
                $this->ensureDirectoryColumn($project);
                $projectFiles = $this->schema->connection($project)->query(
                    'SELECT f.*, ' . $this->schema->connection($project)->quote($project['name']) . ' AS project_name,
                     ' . $this->schema->connection($project)->quote($project['uid']) . ' AS project_uid
                     FROM _system_files f ORDER BY f.id DESC'
                )->fetchAll();
                $files = array_merge($files, $projectFiles);
            } catch (\Throwable) {
            }
        }
        usort($files, fn ($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        return $files;
    }

    public function upload(array $project, array $file, string $directory = '/'): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new \InvalidArgumentException('Select a valid file.');
        }
        if ((int) $file['size'] > $this->config['max_upload_bytes']) {
            throw new \InvalidArgumentException('The file exceeds the configured upload limit.');
        }
        $this->ensureDirectoryColumn($project);
        $usedBytes = (int) $this->schema->connection($project)->query('SELECT COALESCE(SUM(size), 0) FROM _system_files')->fetchColumn();
        $projectLimit = max((int) $this->config['max_upload_bytes'], (int) ($this->config['project_storage_max_bytes'] ?? 0));
        if ($projectLimit > 0 && $usedBytes + (int) $file['size'] > $projectLimit) {
            throw new RuntimeException('Project storage quota exceeded.', 413);
        }
        $uid = Support::uid('fil_');
        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $allowedTypes = [
            'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
            'webp' => ['image/webp'], 'gif' => ['image/gif'], 'pdf' => ['application/pdf'],
            'txt' => ['text/plain'], 'csv' => ['text/plain', 'text/csv', 'application/csv'],
            'json' => ['application/json', 'text/plain'], 'zip' => ['application/zip', 'application/x-zip-compressed'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        ];
        if ($extension === '' || !isset($allowedTypes[$extension])) {
            throw new \InvalidArgumentException('This file type is not allowed.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']) ?: 'application/octet-stream';
        if (!in_array(strtolower($mime), $allowedTypes[$extension], true)) {
            throw new \InvalidArgumentException('This file content type is not allowed.');
        }
        $storedName = $uid . ($extension !== '' ? '.' . preg_replace('/[^a-z0-9]/', '', $extension) : '');
        $directory = $this->normalizeDirectory($directory);
        $projectRoot = $this->config['storage'] . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $project['uid'];
        if (!is_dir($projectRoot) && !mkdir($projectRoot, 0775, true) && !is_dir($projectRoot)) {
            throw new RuntimeException('Could not create the project upload directory.');
        }
        $uploadDir = $projectRoot . ($directory === '/' ? '' : DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $directory));
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('Could not create the upload directory.');
        }
        $rootReal = realpath($projectRoot);
        $uploadReal = realpath($uploadDir);
        if (!$rootReal || !$uploadReal || ($uploadReal !== $rootReal && !str_starts_with($uploadReal, $rootReal . DIRECTORY_SEPARATOR))) {
            throw new RuntimeException('Invalid upload directory.');
        }
        $path = $uploadDir . DIRECTORY_SEPARATOR . $storedName;
        if (!move_uploaded_file($file['tmp_name'], $path)) {
            throw new RuntimeException('Could not store the uploaded file.');
        }
        $url = $this->config['url'] . '/api/' . $project['uid'] . '/storage/' . $uid;
        $stmt = $this->schema->connection($project)->prepare(
            'INSERT INTO _system_files (uid,original_name,stored_name,mime_type,size,path,url,directory,created_at) VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([$uid, basename((string) $file['name']), $storedName, $mime, filesize($path), $path, $url, $directory, Support::now()]);
        $this->logs->write('file.uploaded', $project['uid'], '_system_files', $uid);
        return $this->find($project, $uid);
    }

    private function normalizeDirectory(string $directory): string
    {
        if (str_contains($directory, "\0") || str_contains($directory, '\\')) {
            throw new \InvalidArgumentException('Invalid directory.');
        }
        $trimmed = trim($directory, '/');
        if ($trimmed === '') return '/';
        $segments = explode('/', $trimmed);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || !preg_match('/^[A-Za-z0-9._-]+$/', $segment)) {
                throw new \InvalidArgumentException('Invalid directory.');
            }
        }
        return implode('/', $segments);
    }

    public function find(array $project, string $uid): array
    {
        $stmt = $this->schema->connection($project)->prepare('SELECT * FROM _system_files WHERE uid = ? LIMIT 1');
        $stmt->execute([$uid]);
        return $stmt->fetch() ?: throw new RuntimeException('File not found.');
    }

    public function delete(array $project, string $uid): void
    {
        $file = $this->find($project, $uid);
        if (is_file($file['path'])) {
            unlink($file['path']);
        }
        $stmt = $this->schema->connection($project)->prepare('DELETE FROM _system_files WHERE uid = ?');
        $stmt->execute([$uid]);
        $this->logs->write('file.deleted', $project['uid'], '_system_files', $uid);
    }

    public function deleteAndClearReferences(array $project, string $uid): int
    {
        $file = $this->find($project, $uid);
        $db = $this->schema->connection($project);
        $cleared = 0;
        foreach ($this->schema->tables($project) as $tableInfo) {
            $table = (string) $tableInfo['name'];
            foreach ($this->schema->columns($project, $table) as $columnInfo) {
                $column = (string) $columnInfo['name'];
                try {
                    $sql = 'UPDATE ' . Support::quoteIdentifier($table) . ' SET ' . Support::quoteIdentifier($column) .
                        ' = NULL WHERE CAST(' . Support::quoteIdentifier($column) . ' AS TEXT) IN (?, ?)';
                    $stmt = $db->prepare($sql);
                    $stmt->execute([(string) $file['uid'], (string) $file['url']]);
                    $cleared += $stmt->rowCount();
                } catch (\Throwable) {
                }
            }
        }
        $this->delete($project, $uid);
        return $cleared;
    }

    public function deleteImagesFromRowsIfUnreferenced(array $project, array $rows): int
    {
        $values = [];
        foreach ($rows as $row) {
            foreach ((array) $row as $value) {
                if (is_scalar($value) && trim((string) $value) !== '') $values[(string) $value] = true;
            }
        }
        if (!$values) return 0;

        $candidates = [];
        foreach ($this->all($project) as $file) {
            if (!str_starts_with((string) ($file['mime_type'] ?? ''), 'image/')) continue;
            if (isset($values[(string) $file['uid']]) || isset($values[(string) $file['url']])) $candidates[] = $file;
        }

        $deleted = 0;
        foreach ($candidates as $file) {
            if ($this->isReferenced($project, (string) $file['uid'], (string) $file['url'])) continue;
            $this->delete($project, (string) $file['uid']);
            $deleted++;
        }
        return $deleted;
    }

    public function cleanupOrphans(array $project): array
    {
        $this->ensureDirectoryColumn($project);
        $db = $this->schema->connection($project);
        $rows = $db->query('SELECT id, path FROM _system_files')->fetchAll();
        $known = [];
        $recordsRemoved = 0;
        foreach ($rows as $row) {
            $path = (string) ($row['path'] ?? '');
            if ($path !== '' && is_file($path)) {
                $resolved = realpath($path);
                if ($resolved) $known[strtolower($resolved)] = true;
                continue;
            }
            $db->prepare('DELETE FROM _system_files WHERE id = ?')->execute([$row['id']]);
            $recordsRemoved++;
        }

        $projectRoot = $this->config['storage'] . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $project['uid'];
        $filesRemoved = 0;
        $rootReal = is_dir($projectRoot) ? realpath($projectRoot) : false;
        if ($rootReal) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($rootReal, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            $cutoff = time() - 86400;
            foreach ($iterator as $item) {
                $resolved = $item->getRealPath();
                if (!$resolved || ($resolved !== $rootReal && !str_starts_with($resolved, $rootReal . DIRECTORY_SEPARATOR))) continue;
                if ($item->isDir()) {
                    $entries = @scandir($resolved);
                    if (is_array($entries) && count($entries) === 2) @rmdir($resolved);
                    continue;
                }
                if (!preg_match('/^fil_[A-Za-z0-9_-]+\.[A-Za-z0-9]+$/', $item->getFilename())) continue;
                if (!isset($known[strtolower($resolved)]) && $item->getMTime() < $cutoff && @unlink($resolved)) $filesRemoved++;
            }
        }
        if ($recordsRemoved || $filesRemoved) {
            $this->logs->write('storage.cleaned', $project['uid'], '_system_files', null, null, [
                'records_removed' => $recordsRemoved, 'files_removed' => $filesRemoved,
            ]);
        }
        return ['records_removed' => $recordsRemoved, 'files_removed' => $filesRemoved];
    }

    private function isReferenced(array $project, string $uid, string $url): bool
    {
        $db = $this->schema->connection($project);
        foreach ($this->schema->tables($project) as $tableInfo) {
            $table = (string) $tableInfo['name'];
            foreach ($this->schema->columns($project, $table) as $columnInfo) {
                $column = (string) $columnInfo['name'];
                $sql = 'SELECT 1 FROM ' . Support::quoteIdentifier($table) .
                    ' WHERE CAST(' . Support::quoteIdentifier($column) . ' AS TEXT) IN (?, ?) LIMIT 1';
                $stmt = $db->prepare($sql);
                $stmt->execute([$uid, $url]);
                if ($stmt->fetchColumn()) return true;
            }
        }
        return false;
    }
}
