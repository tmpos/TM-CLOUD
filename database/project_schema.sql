CREATE TABLE _system_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uid TEXT UNIQUE NOT NULL,
    event TEXT NOT NULL,
    payload TEXT,
    created_at TEXT NOT NULL
);

CREATE TABLE _system_files (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uid TEXT UNIQUE NOT NULL,
    original_name TEXT NOT NULL,
    stored_name TEXT NOT NULL,
    mime_type TEXT NOT NULL,
    size INTEGER NOT NULL,
    path TEXT NOT NULL,
    url TEXT NOT NULL,
    directory TEXT NOT NULL DEFAULT '/',
    created_at TEXT NOT NULL
);

CREATE TABLE _system_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key TEXT UNIQUE NOT NULL,
    value TEXT,
    updated_at TEXT NOT NULL
);

CREATE TABLE _system_table_settings (
    table_name TEXT PRIMARY KEY,
    access_mode TEXT NOT NULL DEFAULT 'private',
    visible_columns TEXT,
    editable_columns TEXT,
    soft_delete INTEGER NOT NULL DEFAULT 0,
    updated_at TEXT NOT NULL
);
