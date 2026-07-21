<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;

final class PortalAuth
{
    public function __construct(private PDO $db)
    {
    }

    public function createForProject(string $projectUid, array $data): array
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $name = trim((string) ($data['name'] ?? ''));
        $role = strtolower(trim((string) ($data['role'] ?? 'viewer')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '') throw new \InvalidArgumentException('Name and valid email are required.');
        if (!in_array($role, ['owner', 'admin', 'accounting', 'viewer'], true)) throw new \InvalidArgumentException('Invalid portal role.');
        $find = $this->db->prepare('SELECT * FROM portal_users WHERE email = ? LIMIT 1');
        $find->execute([$email]);
        $user = $find->fetch();
        $temporaryPassword = null;
        $now = Support::now();
        $this->db->beginTransaction();
        try {
            if (!$user) {
                $temporaryPassword = trim((string) ($data['password'] ?? '')) ?: rtrim(strtr(base64_encode(random_bytes(15)), '+/', '-_'), '=');
                if (strlen($temporaryPassword) < 10) throw new \InvalidArgumentException('Password must contain at least 10 characters.');
                $uid = Support::uid('por_');
                $this->db->prepare('INSERT INTO portal_users (uid,name,email,password_hash,status,created_at,updated_at) VALUES (?,?,?,?,\'active\',?,?)')
                    ->execute([$uid, $name, $email, password_hash($temporaryPassword, PASSWORD_DEFAULT), $now, $now]);
                $user = ['uid' => $uid, 'name' => $name, 'email' => $email, 'status' => 'active'];
            }
            $this->db->prepare('INSERT INTO project_memberships (portal_user_uid,project_uid,role,created_at,updated_at) VALUES (?,?,?,?,?) ON CONFLICT(portal_user_uid,project_uid) DO UPDATE SET role=excluded.role, updated_at=excluded.updated_at')
                ->execute([$user['uid'], $projectUid, $role, $now, $now]);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
        return ['uid' => $user['uid'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $role, 'temporary_password' => $temporaryPassword];
    }

    public function attempt(string $email, string $password): bool
    {
        $stmt = $this->db->prepare("SELECT * FROM portal_users WHERE email = ? AND status = 'active' LIMIT 1");
        $stmt->execute([strtolower(trim($email))]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) return false;
        session_regenerate_id(true);
        $_SESSION['portal_user'] = ['uid' => $user['uid'], 'name' => $user['name'], 'email' => $user['email']];
        $this->db->prepare('UPDATE portal_users SET last_login_at = ?, updated_at = ? WHERE uid = ?')->execute([Support::now(), Support::now(), $user['uid']]);
        return true;
    }

    public static function check(): bool { return isset($_SESSION['portal_user']['uid']); }
    public static function user(): ?array { return $_SESSION['portal_user'] ?? null; }

    public static function logout(): void
    {
        unset($_SESSION['portal_user']);
        session_regenerate_id(true);
    }

    public function projects(string $userUid): array
    {
        $stmt = $this->db->prepare("SELECT p.*,m.role FROM project_memberships m JOIN projects p ON p.uid=m.project_uid WHERE m.portal_user_uid=? AND p.status='active' ORDER BY p.name");
        $stmt->execute([$userUid]);
        return $stmt->fetchAll();
    }

    public function membership(string $userUid, string $projectUid): array
    {
        $stmt = $this->db->prepare('SELECT * FROM project_memberships WHERE portal_user_uid = ? AND project_uid = ? LIMIT 1');
        $stmt->execute([$userUid, $projectUid]);
        return $stmt->fetch() ?: throw new RuntimeException('You do not have access to this project.', 403);
    }
}
