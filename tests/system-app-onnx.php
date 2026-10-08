<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/Services/SystemAppService.php';

use App\Services\SystemAppService;

if (!class_exists(ZipArchive::class)) throw new RuntimeException('Se requiere la extensión Zip.');
$root = sys_get_temp_dir() . '/tmpbase-system-onnx-' . bin2hex(random_bytes(8));
mkdir($root, 0700, true);
$service = new SystemAppService(['root' => $root]);
$extract = new ReflectionMethod($service, 'extractStaticFiles');
$cases = [
    'onnx' => ['assets/sface_2021dec-DPn6NY-s.onnx', null],
    'uppercase' => ['assets/model.ONNX', null],
    'php' => ['assets/model.onnx.php', 'tipo de archivo no permitido'],
    'exe' => ['assets/program.exe', 'tipo de archivo no permitido'],
    'traversal' => ['../escape.onnx', 'ruta no permitida'],
];
try {
    foreach ($cases as $name => [$entry, $expected]) {
        $file = "$root/$name.zip";
        $zip = new ZipArchive();
        if ($zip->open($file, ZipArchive::CREATE) !== true) throw new RuntimeException('No se pudo crear ZIP.');
        $zip->addFromString('index.html', '<!doctype html><title>TM-GYM</title>');
        $zip->addFromString($entry, "\x08\x08modelo de prueba");
        $zip->close();
        $zip->open($file);
        mkdir("$root/$name");
        try {
            $extract->invoke($service, $zip, "$root/$name");
            if ($expected !== null) throw new RuntimeException("No rechazó $name");
            if (file_get_contents("$root/$name/$entry") !== "\x08\x08modelo de prueba") throw new RuntimeException('Contenido alterado');
        } catch (InvalidArgumentException $error) {
            if ($expected === null || !str_contains($error->getMessage(), $expected)) throw $error;
        } finally { $zip->close(); }
        echo "OK: $name\n";
    }
    if (isset($argv[1])) {
        $zip = new ZipArchive();
        if ($zip->open($argv[1]) !== true) throw new RuntimeException('No se pudo abrir el ZIP compilado.');
        mkdir("$root/build");
        try {
            $extract->invoke($service, $zip, "$root/build");
            $findRoot = new ReflectionMethod($service, 'findAppRoot');
            $findRoot->invoke($service, "$root/build");
            echo "OK: ZIP compilado ({$zip->numFiles} archivos)\n";
        } finally { $zip->close(); }
    }
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    rmdir($root);
}
