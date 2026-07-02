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
    'rate_limit' => (int) (getenv('RATE_LIMIT_PER_MINUTE') ?: 120),
];
