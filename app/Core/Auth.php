<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;

final class Auth
{
    /** Sections a "limited" admin-panel user can be granted access to. Keys used by WebController::componentForPath(). */
    public const COMPONENTS = [
        'projects' => 'Proyectos',
        'sistemas_web' => 'Sistemas web',
        'api_docs' => 'API Docs',
        'backups' => 'Backups',
        'storage' => 'Storage',
        'space_usage' => 'Uso de espacio',
        'apk_files' => 'Archivos APK',
        'mail_settings' => 'Correo OTP',
        'licenses' => 'Licenses',
        'onboarding_links' => 'Enlaces de registro',
    ];

    public function __construct(private PDO $db)
    {
    }

    public function installed(): bool
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
    }

    public function install(string $name, string $email, string $password): void
    {
        if ($this->installed()) {
            throw new RuntimeException('TMPBase is already installed.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
            throw new \InvalidArgumentException('Use a valid email and a password of at least 10 characters.');
        }
        $now = Support::now();
        $stmt = $this->db->prepare(
            'INSERT INTO users (uid,name,email,password_hash,role,permissions,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([Support::uid('usr_'), trim($name), strtolower($email), password_hash($password, PASSWORD_DEFAULT), 'admin', '[]', $now, $now]);
    }

    public function attempt(string $email, string $password): bool
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([strtolower(trim($email))]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'uid' => $user['uid'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role'],
            'permissions' => json_decode((string) ($user['permissions'] ?? '[]'), true) ?: [],
        ];
        return true;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']['uid']);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    /** Full access regardless of the granted component list. */
    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? '') === 'admin';
    }

    public static function hasComponent(string $component): bool
    {
        if (self::isAdmin()) {
            return true;
        }
        return in_array($component, (array) (self::user()['permissions'] ?? []), true);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public function all(): array
    {
        return $this->db->query('SELECT uid,name,email,role,permissions,invited_by,created_at,updated_at FROM users ORDER BY id ASC')->fetchAll();
    }

    public function find(string $uid): array
    {
        $stmt = $this->db->prepare('SELECT uid,name,email,role,permissions,invited_by,created_at,updated_at FROM users WHERE uid = ? LIMIT 1');
        $stmt->execute([$uid]);
        return $stmt->fetch() ?: throw new RuntimeException('User not found.');
    }

    public function create(string $name, string $email, string $password, string $role, array $permissions, string $invitedBy): array
    {
        $name = trim($name);
        $email = strtolower(trim($email));
        if ($name === '' || strlen($name) > 100) {
            throw new \InvalidArgumentException('Indique un nombre (maximo 100 caracteres).');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Correo invalido.');
        }
        if (strlen($password) < 10) {
            throw new \InvalidArgumentException('La contrasena debe tener al menos 10 caracteres.');
        }
        $exists = $this->db->prepare('SELECT 1 FROM users WHERE email = ?');
        $exists->execute([$email]);
        if ($exists->fetchColumn()) {
            throw new \InvalidArgumentException('Ya existe un usuario con ese correo.');
        }
        $role = $role === 'admin' ? 'admin' : 'limited';
        $permissions = $role === 'admin' ? [] : array_values(array_intersect($permissions, array_keys(self::COMPONENTS)));
        $now = Support::now();
        $uid = Support::uid('usr_');
        $this->db->prepare('INSERT INTO users (uid,name,email,password_hash,role,permissions,invited_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$uid, $name, $email, password_hash($password, PASSWORD_DEFAULT), $role, Support::json($permissions), $invitedBy, $now, $now]);
        return $this->find($uid);
    }

    public function updateAccess(string $uid, string $role, array $permissions): void
    {
        $role = $role === 'admin' ? 'admin' : 'limited';
        if ($role === 'limited' && $this->countAdmins() <= 1 && $this->find($uid)['role'] === 'admin') {
            throw new RuntimeException('Debe quedar al menos un administrador.');
        }
        $permissions = $role === 'admin' ? [] : array_values(array_intersect($permissions, array_keys(self::COMPONENTS)));
        $this->db->prepare('UPDATE users SET role = ?, permissions = ?, updated_at = ? WHERE uid = ?')
            ->execute([$role, Support::json($permissions), Support::now(), $uid]);
    }

    public function resetPassword(string $uid, string $password): void
    {
        if (strlen($password) < 10) {
            throw new \InvalidArgumentException('La contrasena debe tener al menos 10 caracteres.');
        }
        $this->db->prepare('UPDATE users SET password_hash = ?, updated_at = ? WHERE uid = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), Support::now(), $uid]);
    }

    public function delete(string $uid): void
    {
        $user = $this->find($uid);
        if ($user['role'] === 'admin' && $this->countAdmins() <= 1) {
            throw new RuntimeException('Debe quedar al menos un administrador.');
        }
        $this->db->prepare('DELETE FROM users WHERE uid = ?')->execute([$uid]);
    }

    private function countAdmins(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    }
}
