PRAGMA foreign_keys = ON;

CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uid TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'admin',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE projects (
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
    blocked_reason TEXT,
    blocked_at TEXT,
    archived_at TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE project_logs (
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

CREATE TABLE backups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uid TEXT UNIQUE NOT NULL,
    project_uid TEXT NOT NULL,
    file_path TEXT NOT NULL,
    size INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL
);

CREATE TABLE webhooks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uid TEXT UNIQUE NOT NULL,
    project_uid TEXT NOT NULL,
    event TEXT NOT NULL,
    url TEXT NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE rate_limits (
    api_key_hash TEXT NOT NULL,
    bucket TEXT NOT NULL,
    hits INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY(api_key_hash, bucket)
);

CREATE TABLE external_connections (
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

CREATE INDEX idx_logs_project_created ON project_logs(project_uid, created_at DESC);
CREATE INDEX idx_external_connections_project ON external_connections(project_uid);

CREATE TABLE mail_settings (
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

CREATE TABLE storefronts (
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
    logo_url TEXT,
    phone TEXT,
    whatsapp TEXT,
    email TEXT,
    address TEXT,
    currency TEXT NOT NULL DEFAULT 'DOP',
    catalog_table TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX idx_storefronts_enabled_slug ON storefronts(enabled, slug);

CREATE TABLE storefront_payment_methods (
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

CREATE TABLE storefront_orders (
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
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE storefront_customer_verifications (
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

CREATE INDEX idx_storefront_customer_verifications_email ON storefront_customer_verifications(storefront_uid,email);

CREATE TABLE storefront_order_items (
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

CREATE TABLE storefront_admin_users (
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

CREATE TABLE storefront_admin_memberships (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uid TEXT UNIQUE NOT NULL,
    user_uid TEXT NOT NULL REFERENCES storefront_admin_users(uid) ON DELETE CASCADE,
    storefront_uid TEXT NOT NULL REFERENCES storefronts(uid) ON DELETE CASCADE,
    role TEXT NOT NULL DEFAULT 'cashier' CHECK(role IN ('owner','manager','cashier','kitchen')),
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE(user_uid,storefront_uid)
);

CREATE INDEX idx_storefront_admin_memberships_store ON storefront_admin_memberships(storefront_uid,role);
