<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;

final class Database
{
    /** @var array<string, PDO> */
    private static array $connections = [];

    public static function connect(string $path): PDO
    {
        if (isset(self::$connections[$path])) {
            return self::$connections[$path];
        }

        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Cannot create database directory: $directory");
        }

        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA journal_mode = WAL');
        self::$connections[$path] = $pdo;

        return $pdo;
    }

    public static function migrate(PDO $db): void
    {
        $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uid TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'admin',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uid TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    slug TEXT UNIQUE NOT NULL,
    description TEXT,
    database_path TEXT NOT NULL,
    public_key TEXT UNIQUE NOT NULL,
    secret_key TEXT UNIQUE NOT NULL,
    status TEXT NOT NULL DEFAULT 'active',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS project_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uid TEXT UNIQUE NOT NULL,
    project_uid TEXT,
    user_uid TEXT,
    action TEXT NOT NULL,
    table_name TEXT,
    record_uid TEXT,
    old_data TEXT,
    new_data TEXT,
    ip_address TEXT,
    user_agent TEXT,
    created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_logs_project_created ON project_logs(project_uid, created_at DESC);
CREATE TABLE IF NOT EXISTS backups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uid TEXT UNIQUE NOT NULL,
    project_uid TEXT NOT NULL,
    file_path TEXT NOT NULL,
    size INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS webhooks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uid TEXT UNIQUE NOT NULL,
    project_uid TEXT NOT NULL,
    event TEXT NOT NULL,
    url TEXT NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS rate_limits (
    api_key_hash TEXT NOT NULL,
    bucket TEXT NOT NULL,
    hits INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY(api_key_hash, bucket)
);
CREATE TABLE IF NOT EXISTS external_connections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uid TEXT UNIQUE NOT NULL,
    project_uid TEXT NOT NULL REFERENCES projects(uid) ON DELETE CASCADE,
    name TEXT NOT NULL,
    driver TEXT NOT NULL DEFAULT 'mysql',
    host TEXT NOT NULL,
    port INTEGER NOT NULL DEFAULT 3306,
    database_name TEXT NOT NULL,
    username TEXT NOT NULL,
    password_encrypted TEXT NOT NULL,
    charset TEXT NOT NULL DEFAULT 'utf8mb4',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_external_connections_project ON external_connections(project_uid);
CREATE TABLE IF NOT EXISTS licenses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uid TEXT UNIQUE NOT NULL,
    project_uid TEXT NOT NULL REFERENCES projects(uid) ON DELETE CASCADE,
    system_name TEXT NOT NULL,
    license_key TEXT UNIQUE NOT NULL,
    status TEXT NOT NULL DEFAULT 'active',
    max_uses INTEGER NOT NULL DEFAULT 0,
    current_uses INTEGER NOT NULL DEFAULT 0,
    expires_at TEXT,
    metadata TEXT,
    project_url TEXT,
    public_key TEXT,
    secret_key TEXT,
    almacen TEXT,
    nombre TEXT,
    link TEXT,
    token TEXT,
    tipo TEXT,
    dispositivos TEXT,
    ultimopago TEXT,
    proximopago TEXT,
    precio TEXT,
    encargado TEXT,
    telefono TEXT,
    email TEXT,
    direccion TEXT,
    usuario TEXT,
    identificadordb TEXT,
    role_key TEXT,
    equipos_no_autorizados TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_licenses_project ON licenses(project_uid);
CREATE INDEX IF NOT EXISTS idx_licenses_key ON licenses(license_key);
SQL);
        foreach (['project_url','public_key','secret_key','almacen','nombre','link','token','tipo','dispositivos','ultimopago','proximopago','precio','encargado','telefono','email','direccion','usuario','identificadordb','role_key','equipos_no_autorizados'] as $col) {
            try { $db->exec("ALTER TABLE licenses ADD COLUMN $col TEXT"); } catch (\Throwable) {}
        }
    }
}
