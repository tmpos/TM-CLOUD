<?php

declare(strict_types=1);

$root = dirname(__DIR__);

return [
    'name' => getenv('APP_NAME') ?: 'TMPBase',
    'url' => rtrim(getenv('APP_URL') ?: 'http://localhost:8000', '/'),
    'env' => getenv('APP_ENV') ?: 'production',
    'debug' => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOL),
    'root' => $root,
    'storage' => $root . DIRECTORY_SEPARATOR . 'storage',
    'database' => $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'tmpbase.sqlite',
    'max_upload_bytes' => ((int) (getenv('MAX_UPLOAD_MB') ?: 10)) * 1024 * 1024,
    'project_storage_max_bytes' => ((int) (getenv('PROJECT_STORAGE_MAX_MB') ?: 1024)) * 1024 * 1024,
    'cors_allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) (getenv('CORS_ALLOWED_ORIGINS') ?: ''))))),
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) (getenv('TRUSTED_PROXIES') ?: ''))))),
    'rate_limit' => (int) (getenv('RATE_LIMIT_PER_MINUTE') ?: 120),
    'backup_retention_count' => max(1, (int) (getenv('BACKUP_RETENTION_COUNT') ?: 20)),
    'backup_retention_days' => max(1, (int) (getenv('BACKUP_RETENTION_DAYS') ?: 90)),
    'backup_max_bytes_per_project' => ((int) (getenv('BACKUP_MAX_MB_PER_PROJECT') ?: 5120)) * 1024 * 1024,
    'mail' => [
        'enabled' => filter_var(getenv('MAIL_ENABLED') ?: false, FILTER_VALIDATE_BOOL),
        'host' => getenv('MAIL_HOST') ?: '',
        'port' => (int) (getenv('MAIL_PORT') ?: 587),
        'encryption' => strtolower((string) (getenv('MAIL_ENCRYPTION') ?: 'tls')),
        'username' => getenv('MAIL_USERNAME') ?: '',
        'password' => getenv('MAIL_PASSWORD') ?: '',
        'from_email' => getenv('MAIL_FROM_ADDRESS') ?: '',
        'from_name' => getenv('MAIL_FROM_NAME') ?: (getenv('APP_NAME') ?: 'TMPBase'),
        'reply_to' => getenv('MAIL_REPLY_TO') ?: '',
        'max_attempts' => max(1, (int) (getenv('MAIL_MAX_ATTEMPTS') ?: 5)),
    ],
    'realtime' => [
        'enabled' => filter_var(getenv('REALTIME_ENABLED') ?: true, FILTER_VALIDATE_BOOL),
        'ws_url' => getenv('REALTIME_WS_URL') ?: 'ws://127.0.0.1:8080',
        'server_host' => getenv('REALTIME_SERVER_HOST') ?: '127.0.0.1',
        'ws_port' => (int) (getenv('REALTIME_WS_PORT') ?: 8080),
        'event_port' => (int) (getenv('REALTIME_EVENT_PORT') ?: 8081),
    ],
];
