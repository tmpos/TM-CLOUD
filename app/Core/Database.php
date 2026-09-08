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
        // Ajustes de lectura seguros: conservan la durabilidad configurada y
        // reducen acceso a disco al construir snapshots grandes.
        $pdo->exec('PRAGMA cache_size = -65536');
        $pdo->exec('PRAGMA temp_store = MEMORY');
        $pdo->exec('PRAGMA mmap_size = 268435456');
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
    system_app TEXT NOT NULL DEFAULT 'default',
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
CREATE INDEX IF NOT EXISTS idx_logs_deleted_lookup ON project_logs(project_uid, action, table_name, record_uid);
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
CREATE TABLE IF NOT EXISTS mail_settings (
    id INTEGER PRIMARY KEY CHECK(id = 1),
    enabled INTEGER NOT NULL DEFAULT 0,
    host TEXT NOT NULL DEFAULT 'smtp.gmail.com',
    port INTEGER NOT NULL DEFAULT 587,
    encryption TEXT NOT NULL DEFAULT 'tls',
    username TEXT NOT NULL DEFAULT '',
    password_encrypted TEXT NOT NULL DEFAULT '',
    from_email TEXT NOT NULL DEFAULT '',
    from_name TEXT NOT NULL DEFAULT 'TMPBase',
    reply_to TEXT NOT NULL DEFAULT '',
    updated_at TEXT NOT NULL
);
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
CREATE TABLE IF NOT EXISTS storefronts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uid TEXT UNIQUE NOT NULL,
    project_uid TEXT UNIQUE NOT NULL REFERENCES projects(uid) ON DELETE CASCADE,
    slug TEXT UNIQUE NOT NULL,
    enabled INTEGER NOT NULL DEFAULT 1,
    store_name TEXT NOT NULL,
    business_type TEXT NOT NULL DEFAULT 'auto',
    tagline TEXT NOT NULL DEFAULT 'Compra fácil, rápido y seguro',
    hero_title TEXT NOT NULL DEFAULT 'Todo lo que buscas en un solo lugar',
    hero_text TEXT NOT NULL DEFAULT 'Explora nuestros productos y consulta tus facturas cuando lo necesites.',
    primary_color TEXT NOT NULL DEFAULT '#0f766e',
    accent_color TEXT NOT NULL DEFAULT '#f59e0b',
    logo_url TEXT,
    phone TEXT,
    whatsapp TEXT,
    email TEXT,
    address TEXT,
    currency TEXT NOT NULL DEFAULT 'DOP',
    catalog_table TEXT,
    background_color TEXT NOT NULL DEFAULT '#ffffff',
    surface_color TEXT NOT NULL DEFAULT '#f5f7fb',
    text_color TEXT NOT NULL DEFAULT '#14213d',
    muted_color TEXT NOT NULL DEFAULT '#65758b',
    header_color TEXT NOT NULL DEFAULT '#ffffff',
    footer_color TEXT NOT NULL DEFAULT '#0b1324',
    border_radius INTEGER NOT NULL DEFAULT 18,
    font_family TEXT NOT NULL DEFAULT 'inter',
    hero_style TEXT NOT NULL DEFAULT 'gradient',
    hero_background_mode TEXT NOT NULL DEFAULT 'gradient',
    hero_background_color TEXT NOT NULL DEFAULT '#0b1324',
    hero_images TEXT NOT NULL DEFAULT '[]',
    hero_overlay_opacity INTEGER NOT NULL DEFAULT 55,
    hero_carousel_interval INTEGER NOT NULL DEFAULT 6000,
    promo_enabled INTEGER NOT NULL DEFAULT 1,
    promo_title TEXT NOT NULL DEFAULT 'Encuentra el equipo ideal para ti',
    promo_text TEXT NOT NULL DEFAULT 'Productos seleccionados, inventario actualizado y atención personalizada.',
    promo_image TEXT,
    promo_link TEXT,
    show_featured INTEGER NOT NULL DEFAULT 1,
    show_new_arrivals INTEGER NOT NULL DEFAULT 1,
    show_brands INTEGER NOT NULL DEFAULT 1,
    card_style TEXT NOT NULL DEFAULT 'elevated',
    announcement_enabled INTEGER NOT NULL DEFAULT 0,
    announcement_text TEXT,
    show_stock INTEGER NOT NULL DEFAULT 1,
    show_sku INTEGER NOT NULL DEFAULT 1,
    footer_text TEXT,
    instagram_url TEXT,
    facebook_url TEXT,
    pickup_enabled INTEGER NOT NULL DEFAULT 1,
    delivery_enabled INTEGER NOT NULL DEFAULT 1,
    shipping_enabled INTEGER NOT NULL DEFAULT 0,
    flat_shipping_cost REAL NOT NULL DEFAULT 0,
    free_shipping_threshold REAL NOT NULL DEFAULT 0,
    checkout_terms_url TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_storefronts_enabled_slug ON storefronts(enabled, slug);
CREATE TABLE IF NOT EXISTS storefront_payment_methods (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uid TEXT UNIQUE NOT NULL,
    project_uid TEXT NOT NULL REFERENCES projects(uid) ON DELETE CASCADE,
    provider TEXT NOT NULL,
    display_name TEXT NOT NULL,
    enabled INTEGER NOT NULL DEFAULT 0,
    test_mode INTEGER NOT NULL DEFAULT 1,
    public_config TEXT,
    credentials_encrypted TEXT,
    instructions TEXT,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE(project_uid, provider)
);
CREATE INDEX IF NOT EXISTS idx_storefront_payment_methods_project ON storefront_payment_methods(project_uid, enabled, sort_order);
CREATE TABLE IF NOT EXISTS storefront_orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uid TEXT UNIQUE NOT NULL,
    order_number TEXT UNIQUE NOT NULL,
    project_uid TEXT NOT NULL REFERENCES projects(uid) ON DELETE CASCADE,
    storefront_uid TEXT NOT NULL REFERENCES storefronts(uid) ON DELETE CASCADE,
    customer_name TEXT NOT NULL,
    customer_email TEXT,
    customer_phone TEXT NOT NULL,
    customer_document TEXT,
    delivery_method TEXT NOT NULL,
    delivery_address TEXT,
    delivery_city TEXT,
    customer_notes TEXT,
    payment_provider TEXT NOT NULL,
    currency TEXT NOT NULL,
    subtotal REAL NOT NULL DEFAULT 0,
    shipping_total REAL NOT NULL DEFAULT 0,
    total REAL NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'pending',
    payment_status TEXT NOT NULL DEFAULT 'pending',
    provider_reference TEXT,
    provider_payload TEXT,
    paid_at TEXT,
    source TEXT NOT NULL DEFAULT 'web',
    admin_user_uid TEXT,
    discount_total REAL NOT NULL DEFAULT 0,
    tax_total REAL NOT NULL DEFAULT 0,
    table_reference TEXT,
    inventory_status TEXT NOT NULL DEFAULT 'pending',
    inventory_committed_at TEXT,
    inventory_released_at TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_storefront_orders_project_created ON storefront_orders(project_uid, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_storefront_orders_payment ON storefront_orders(payment_provider, provider_reference);
CREATE TABLE IF NOT EXISTS storefront_customer_verifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    storefront_uid TEXT NOT NULL REFERENCES storefronts(uid) ON DELETE CASCADE,
    customer_uid TEXT NOT NULL,
    email TEXT NOT NULL,
    otp_hash TEXT NOT NULL,
    attempts INTEGER NOT NULL DEFAULT 0,
    expires_at TEXT NOT NULL,
    verified_at TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE(storefront_uid, customer_uid)
);
CREATE INDEX IF NOT EXISTS idx_storefront_customer_verifications_email ON storefront_customer_verifications(storefront_uid,email);
CREATE TABLE IF NOT EXISTS storefront_order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uid TEXT UNIQUE NOT NULL,
    order_uid TEXT NOT NULL REFERENCES storefront_orders(uid) ON DELETE CASCADE,
    product_uid TEXT NOT NULL,
    product_name TEXT NOT NULL,
    product_sku TEXT,
    product_image TEXT,
    unit_price REAL NOT NULL DEFAULT 0,
    quantity INTEGER NOT NULL,
    line_total REAL NOT NULL DEFAULT 0,
    metadata TEXT,
    created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_storefront_order_items_order ON storefront_order_items(order_uid);
CREATE TABLE IF NOT EXISTS storefront_order_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uid TEXT UNIQUE NOT NULL,
    order_uid TEXT NOT NULL REFERENCES storefront_orders(uid) ON DELETE CASCADE,
    storefront_uid TEXT NOT NULL REFERENCES storefronts(uid) ON DELETE CASCADE,
    user_uid TEXT,
    event_type TEXT NOT NULL,
    from_status TEXT,
    to_status TEXT,
    note TEXT,
    metadata TEXT,
    created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_storefront_order_events_order ON storefront_order_events(order_uid,created_at);
CREATE TABLE IF NOT EXISTS storefront_inventory_movements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uid TEXT UNIQUE NOT NULL,
    order_uid TEXT NOT NULL REFERENCES storefront_orders(uid) ON DELETE CASCADE,
    project_uid TEXT NOT NULL,
    item_uid TEXT,
    movement_type TEXT NOT NULL,
    table_name TEXT NOT NULL,
    stock_column TEXT,
    key_column TEXT,
    row_key TEXT,
    product_uid TEXT,
    product_name TEXT,
    imei_uid TEXT,
    imei_value TEXT,
    quantity REAL NOT NULL DEFAULT 0,
    before_value TEXT,
    after_value TEXT,
    created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_storefront_inventory_movements_order ON storefront_inventory_movements(order_uid,id);
CREATE TABLE IF NOT EXISTS storefront_admin_users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uid TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active','blocked')),
    last_login_at TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS storefront_admin_memberships (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uid TEXT UNIQUE NOT NULL,
    user_uid TEXT NOT NULL REFERENCES storefront_admin_users(uid) ON DELETE CASCADE,
    storefront_uid TEXT NOT NULL REFERENCES storefronts(uid) ON DELETE CASCADE,
    role TEXT NOT NULL DEFAULT 'cashier' CHECK(role IN ('owner','manager','cashier','kitchen')),
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE(user_uid,storefront_uid)
);
CREATE INDEX IF NOT EXISTS idx_storefront_admin_memberships_store ON storefront_admin_memberships(storefront_uid,role);
SQL);
        $now = Support::now();
        $projects = $db->query('SELECT uid,name,slug FROM projects')->fetchAll();
        $insertStorefront = $db->prepare(
            'INSERT OR IGNORE INTO storefronts
             (uid,project_uid,slug,enabled,store_name,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?)'
        );
        foreach ($projects as $project) {
            $insertStorefront->execute([
                Support::uid('sto_'),
                $project['uid'],
                $project['slug'],
                1,
                $project['name'],
                $now,
                $now,
            ]);
        }
        $storefrontMigrations = [
            "ALTER TABLE storefronts ADD COLUMN background_color TEXT NOT NULL DEFAULT '#ffffff'",
            "ALTER TABLE storefronts ADD COLUMN business_type TEXT NOT NULL DEFAULT 'auto'",
            "ALTER TABLE storefronts ADD COLUMN surface_color TEXT NOT NULL DEFAULT '#f5f7fb'",
            "ALTER TABLE storefronts ADD COLUMN text_color TEXT NOT NULL DEFAULT '#14213d'",
            "ALTER TABLE storefronts ADD COLUMN muted_color TEXT NOT NULL DEFAULT '#65758b'",
            "ALTER TABLE storefronts ADD COLUMN header_color TEXT NOT NULL DEFAULT '#ffffff'",
            "ALTER TABLE storefronts ADD COLUMN footer_color TEXT NOT NULL DEFAULT '#0b1324'",
            "ALTER TABLE storefronts ADD COLUMN border_radius INTEGER NOT NULL DEFAULT 18",
            "ALTER TABLE storefronts ADD COLUMN font_family TEXT NOT NULL DEFAULT 'inter'",
            "ALTER TABLE storefronts ADD COLUMN hero_style TEXT NOT NULL DEFAULT 'gradient'",
            "ALTER TABLE storefronts ADD COLUMN hero_background_mode TEXT NOT NULL DEFAULT 'gradient'",
            "ALTER TABLE storefronts ADD COLUMN hero_background_color TEXT NOT NULL DEFAULT '#0b1324'",
            "ALTER TABLE storefronts ADD COLUMN hero_images TEXT NOT NULL DEFAULT '[]'",
            "ALTER TABLE storefronts ADD COLUMN hero_overlay_opacity INTEGER NOT NULL DEFAULT 55",
            "ALTER TABLE storefronts ADD COLUMN hero_carousel_interval INTEGER NOT NULL DEFAULT 6000",
            "ALTER TABLE storefronts ADD COLUMN promo_enabled INTEGER NOT NULL DEFAULT 1",
            "ALTER TABLE storefronts ADD COLUMN promo_title TEXT NOT NULL DEFAULT 'Encuentra el equipo ideal para ti'",
            "ALTER TABLE storefronts ADD COLUMN promo_text TEXT NOT NULL DEFAULT 'Productos seleccionados, inventario actualizado y atención personalizada.'",
            "ALTER TABLE storefronts ADD COLUMN promo_image TEXT",
            "ALTER TABLE storefronts ADD COLUMN promo_link TEXT",
            "ALTER TABLE storefronts ADD COLUMN show_featured INTEGER NOT NULL DEFAULT 1",
            "ALTER TABLE storefronts ADD COLUMN show_new_arrivals INTEGER NOT NULL DEFAULT 1",
            "ALTER TABLE storefronts ADD COLUMN show_brands INTEGER NOT NULL DEFAULT 1",
            "ALTER TABLE storefronts ADD COLUMN card_style TEXT NOT NULL DEFAULT 'elevated'",
            "ALTER TABLE storefronts ADD COLUMN announcement_enabled INTEGER NOT NULL DEFAULT 0",
            "ALTER TABLE storefronts ADD COLUMN announcement_text TEXT",
            "ALTER TABLE storefronts ADD COLUMN show_stock INTEGER NOT NULL DEFAULT 1",
            "ALTER TABLE storefronts ADD COLUMN show_sku INTEGER NOT NULL DEFAULT 1",
            "ALTER TABLE storefronts ADD COLUMN footer_text TEXT",
            "ALTER TABLE storefronts ADD COLUMN instagram_url TEXT",
            "ALTER TABLE storefronts ADD COLUMN facebook_url TEXT",
            "ALTER TABLE storefronts ADD COLUMN pickup_enabled INTEGER NOT NULL DEFAULT 1",
            "ALTER TABLE storefronts ADD COLUMN delivery_enabled INTEGER NOT NULL DEFAULT 1",
            "ALTER TABLE storefronts ADD COLUMN shipping_enabled INTEGER NOT NULL DEFAULT 0",
            "ALTER TABLE storefronts ADD COLUMN flat_shipping_cost REAL NOT NULL DEFAULT 0",
            "ALTER TABLE storefronts ADD COLUMN free_shipping_threshold REAL NOT NULL DEFAULT 0",
            "ALTER TABLE storefronts ADD COLUMN checkout_terms_url TEXT",
        ];
        foreach ($storefrontMigrations as $migration) {
            try { $db->exec($migration); } catch (\Throwable) {}
        }
        foreach ([
            "ALTER TABLE storefront_orders ADD COLUMN source TEXT NOT NULL DEFAULT 'web'",
            "ALTER TABLE storefront_orders ADD COLUMN admin_user_uid TEXT",
            "ALTER TABLE storefront_orders ADD COLUMN discount_total REAL NOT NULL DEFAULT 0",
            "ALTER TABLE storefront_orders ADD COLUMN tax_total REAL NOT NULL DEFAULT 0",
            "ALTER TABLE storefront_orders ADD COLUMN table_reference TEXT",
            "ALTER TABLE storefront_orders ADD COLUMN inventory_status TEXT NOT NULL DEFAULT 'pending'",
            "ALTER TABLE storefront_orders ADD COLUMN inventory_committed_at TEXT",
            "ALTER TABLE storefront_orders ADD COLUMN inventory_released_at TEXT",
            "ALTER TABLE storefront_order_items ADD COLUMN metadata TEXT",
        ] as $migration) {
            try { $db->exec($migration); } catch (\Throwable) {}
        }
        foreach (['project_url','public_key','secret_key','almacen','nombre','link','token','tipo','dispositivos','ultimopago','proximopago','precio','encargado','telefono','email','direccion','rnc','usuario','identificadordb','role_key','equipos_no_autorizados'] as $col) {
            try { $db->exec("ALTER TABLE licenses ADD COLUMN $col TEXT"); } catch (\Throwable) {}
        }
        foreach (['ALTER TABLE backups ADD COLUMN checksum TEXT', "ALTER TABLE backups ADD COLUMN status TEXT NOT NULL DEFAULT 'valid'"] as $migration) {
            try { $db->exec($migration); } catch (\Throwable) {}
        }
        try { $db->exec("ALTER TABLE projects ADD COLUMN system_app TEXT NOT NULL DEFAULT 'default'"); } catch (\Throwable) {}
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
