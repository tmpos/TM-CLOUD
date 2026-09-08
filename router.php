<?php

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . '/public' . $path;
if ($path !== '/' && is_file($file) && !preg_match('#^/system-apps/[^/]+/(?:index\.html|\.system-app\.json)$#i', $path)) {
    return false;
}
require __DIR__ . '/public/index.php';
