<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Auth
{
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
            throw new \RuntimeException('TMPBase is already installed.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
            throw new \InvalidArgumentException('Use a valid email and a password of at least 10 characters.');
        }
        $now = Support::now();
        $stmt = $this->db->prepare(
            'INSERT INTO users (uid,name,email,password_hash,role,created_at,updated_at) VALUES (?,?,?,?,?,?,?)'
        );
        $stmt->execute([Support::uid('usr_'), trim($name), strtolower($email), password_hash($password, PASSWORD_DEFAULT), 'admin', $now, $now]);
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
        $_SESSION['user'] = ['uid' => $user['uid'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role']];
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

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}
