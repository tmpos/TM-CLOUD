<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Env;

$root = dirname(__DIR__);
$requirements = [];
if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    $requirements[] = 'PHP 8.1 or newer is required. PHP 8.2 or 8.3 is recommended.';
}
foreach (['pdo', 'pdo_sqlite', 'fileinfo'] as $extension) {
    if (!extension_loaded($extension)) {
        $requirements[] = "The PHP extension \"$extension\" is required.";
    }
}
$storage = $root . '/storage';
if (!is_dir($storage) || !is_writable($storage)) {
    $requirements[] = 'The storage directory must exist and be writable by PHP.';
}
if ($requirements) {
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    $items = implode('', array_map(
        static fn (string $message): string => '<li>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</li>',
        $requirements
    ));
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>TMPBase requirements</title><style>body{background:#090d12;color:#cbd5e1;font:16px system-ui;margin:0;padding:40px}'
        . 'main{max-width:720px;margin:auto;background:#111820;border:1px solid #22303d;border-radius:18px;padding:28px}'
        . 'h1{color:#fff}li{margin:12px 0}code{color:#5eead4}</style></head><body><main><h1>TMPBase cannot start</h1>'
        . '<p>Resolve these server requirements and reload the page:</p><ul>' . $items . '</ul>'
        . '<p>On Hostinger, use PHP 8.2 or 8.3 and enable PDO SQLite and Fileinfo.</p></main></body></html>';
    exit;
}

if (is_file($root . '/vendor/autoload.php')) {
    require $root . '/vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class) use ($root): void {
        $prefix = 'App\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
        $file = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . $relative . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
    require $root . '/app/Core/FlightFallback.php';
}
require $root . '/app/helpers.php';

Env::load($root . '/.env');
$config = require $root . '/config/app.php';

ini_set('display_errors', $config['debug'] ? '1' : '0');
error_reporting(E_ALL);
date_default_timezone_set('UTC');

session_name('tmpbase_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => filter_var(getenv('SESSION_SECURE') ?: false, FILTER_VALIDATE_BOOL),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Max-Age: 86400');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

App::boot($config);
Flight::start();
