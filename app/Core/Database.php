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

    public static function disconnect(string $path): void
    {
        unset(self::$connections[$path]);
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
    checksum TEXT,
    status TEXT NOT NULL DEFAULT 'valid',
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
CREATE TABLE IF NOT EXISTS license_devices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uid TEXT UNIQUE NOT NULL,
    license_uid TEXT NOT NULL REFERENCES licenses(uid) ON DELETE CASCADE,
    device_id TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','authorized','blocked','revoked')),
    requested_at TEXT NOT NULL,
    authorized_at TEXT,
    revoked_at TEXT,
    last_seen_at TEXT,
    ip_address TEXT,
    app_version TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE(license_uid, device_id)
);
CREATE INDEX IF NOT EXISTS idx_license_devices_status ON license_devices(license_uid, status);
CREATE TABLE IF NOT EXISTS mail_queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uid TEXT UNIQUE NOT NULL,
    project_uid TEXT NOT NULL REFERENCES projects(uid) ON DELETE CASCADE,
    template TEXT NOT NULL,
    recipient TEXT NOT NULL,
    payload TEXT,
    status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','sending','sent','failed')),
    attempts INTEGER NOT NULL DEFAULT 0,
    max_attempts INTEGER NOT NULL DEFAULT 5,
    message_id TEXT,
    last_error TEXT,
    next_attempt_at TEXT,
    sent_at TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_mail_queue_pending ON mail_queue(status, next_attempt_at, created_at);
CREATE TABLE IF NOT EXISTS shared_documents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uid TEXT UNIQUE NOT NULL,
    token_hash TEXT UNIQUE NOT NULL,
    project_uid TEXT NOT NULL REFERENCES projects(uid) ON DELETE CASCADE,
    document_type TEXT NOT NULL,
    table_name TEXT NOT NULL,
    record_uid TEXT NOT NULL,
    expires_at TEXT,
    revoked_at TEXT,
    access_count INTEGER NOT NULL DEFAULT 0,
    last_access_at TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_shared_documents_record ON shared_documents(project_uid, table_name, record_uid);
CREATE TABLE IF NOT EXISTS portal_users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uid TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active','blocked')),
    email_verified_at TEXT,
    last_login_at TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS project_memberships (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    portal_user_uid TEXT NOT NULL REFERENCES portal_users(uid) ON DELETE CASCADE,
    project_uid TEXT NOT NULL REFERENCES projects(uid) ON DELETE CASCADE,
    role TEXT NOT NULL DEFAULT 'viewer' CHECK(role IN ('owner','admin','accounting','viewer')),
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE(portal_user_uid, project_uid)
);
CREATE INDEX IF NOT EXISTS idx_memberships_project ON project_memberships(project_uid, role);
SQL);
        foreach (['project_url','public_key','secret_key','almacen','nombre','link','token','tipo','dispositivos','ultimopago','proximopago','precio','encargado','telefono','email','direccion','rnc','usuario','identificadordb','role_key','equipos_no_autorizados'] as $col) {
            try { $db->exec("ALTER TABLE licenses ADD COLUMN $col TEXT"); } catch (\Throwable) {}
        }
        foreach (['ALTER TABLE backups ADD COLUMN checksum TEXT', "ALTER TABLE backups ADD COLUMN status TEXT NOT NULL DEFAULT 'valid'"] as $migration) {
            try { $db->exec($migration); } catch (\Throwable) {}
        }
        // Las claves API pertenecen al proyecto. Se eliminan las copias históricas de licencias.
        $db->exec("UPDATE licenses SET public_key = '', secret_key = '' WHERE COALESCE(public_key, '') <> '' OR COALESCE(secret_key, '') <> ''");
        $licenses = $db->query('SELECT uid, dispositivos, equipos_no_autorizados FROM licenses')->fetchAll();
        $insertDevice = $db->prepare('INSERT OR IGNORE INTO license_devices (uid,license_uid,device_id,status,requested_at,authorized_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)');
        foreach ($licenses as $license) {
            $now = Support::now();
            $authorized = $license['dispositivos'] ? (json_decode((string) $license['dispositivos'], true) ?? []) : [];
            $pending = $license['equipos_no_autorizados'] ? (json_decode((string) $license['equipos_no_autorizados'], true) ?? []) : [];
            foreach (array_unique(array_filter(array_map('strval', $authorized))) as $deviceId) {
                $insertDevice->execute([Support::uid('dev_'), $license['uid'], $deviceId, 'authorized', $now, $now, $now, $now]);
            }
            foreach (array_unique(array_filter(array_map('strval', $pending))) as $deviceId) {
                $insertDevice->execute([Support::uid('dev_'), $license['uid'], $deviceId, 'pending', $now, null, $now, $now]);
            }
        }
    }
}
