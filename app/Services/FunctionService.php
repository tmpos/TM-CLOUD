<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Support;
use PDO;

final class FunctionService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS _functions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uid TEXT UNIQUE NOT NULL,
            project_uid TEXT NOT NULL,
            name TEXT NOT NULL,
            code TEXT NOT NULL,
            description TEXT DEFAULT '',
            event TEXT DEFAULT '',
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )");
    }

    public function all(?string $projectUid = null): array
    {
        if ($projectUid) {
            $stmt = $this->db->prepare('SELECT * FROM _functions WHERE project_uid = ? ORDER BY name ASC');
            $stmt->execute([$projectUid]);
        } else {
            $stmt = $this->db->query('SELECT * FROM _functions ORDER BY name ASC');
        }
        return $stmt->fetchAll();
    }

    public function find(string $uid): array
    {
        $stmt = $this->db->prepare('SELECT * FROM _functions WHERE uid = ?');
        $stmt->execute([$uid]);
        return $stmt->fetch() ?: throw new \RuntimeException('Function not found.');
    }

    public function create(string $projectUid, string $name, string $code, string $description = '', string $event = ''): void
    {
        $stmt = $this->db->prepare('INSERT INTO _functions (uid, project_uid, name, code, description, event, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)');
        $now = Support::now();
        $stmt->execute([Support::uid('fn_'), $projectUid, $name, $code, $description, $event, $now, $now]);
    }

    public function update(string $uid, string $name, string $code, string $description = '', string $event = '', bool $isActive = true): void
    {
        $stmt = $this->db->prepare('UPDATE _functions SET name=?, code=?, description=?, event=?, is_active=?, updated_at=? WHERE uid=?');
        $stmt->execute([$name, $code, $description, $event, $isActive ? 1 : 0, Support::now(), $uid]);
    }

    public function delete(string $uid): void
    {
        $stmt = $this->db->prepare('DELETE FROM _functions WHERE uid = ?');
        $stmt->execute([$uid]);
    }

    public function execute(string $uid, array $payload = []): mixed
    {
        $fn = $this->find($uid);
        if (!$fn['is_active']) return null;

        $code = $fn['code'];
        $result = null;

        $function = function (array $data) use ($code, &$result): void {
            $result = eval($code);
        };

        try {
            $function($payload);
            return $result;
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
