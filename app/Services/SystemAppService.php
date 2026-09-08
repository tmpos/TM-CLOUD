<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Support;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

final class SystemAppService
{
    private string $directory;

    public function __construct(private array $config)
    {
        $this->directory = $config['root'] . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'system-apps';
    }

    public function all(): array
    {
        $apps = [];
        $legacy = $this->legacyPath();
        if (is_file($legacy . DIRECTORY_SEPARATOR . 'index.html')) {
            $apps[] = [
                'slug' => 'default',
                'name' => 'Sistema TMPOS (predeterminado)',
                'description' => 'Interfaz actual ubicada en public/sistema/app.',
                'path' => $legacy,
                'url' => '/sistema/app/',
                'is_default' => true,
            ];
        }

        $this->ensureDirectory();
        foreach (new \DirectoryIterator($this->directory) as $item) {
            if (!$item->isDir() || $item->isDot()) continue;
            $path = $item->getPathname();
            if (!is_file($path . DIRECTORY_SEPARATOR . 'index.html')) continue;
            $meta = [];
            $metaPath = $path . DIRECTORY_SEPARATOR . '.system-app.json';
            if (is_file($metaPath)) {
                $decoded = json_decode((string) file_get_contents($metaPath), true);
                if (is_array($decoded)) $meta = $decoded;
            }
            $slug = $item->getFilename();
            $apps[] = [
                'slug' => $slug,
                'name' => (string) ($meta['name'] ?? $slug),
                'description' => (string) ($meta['description'] ?? ''),
                'path' => $path,
                'url' => '/system-apps/' . rawurlencode($slug) . '/',
                'is_default' => false,
                'uploaded_at' => (string) ($meta['uploaded_at'] ?? gmdate('Y-m-d H:i:s', (int) filemtime($path))),
            ];
        }
        usort($apps, fn (array $a, array $b): int => ($b['is_default'] <=> $a['is_default']) ?: strcasecmp($a['name'], $b['name']));
        return $apps;
    }

    public function find(string $slug): array
    {
        $slug = $this->normalizeSlug($slug);
        foreach ($this->all() as $app) {
            if ($app['slug'] === $slug) return $app;
        }
        throw new RuntimeException('La ubicacion del sistema seleccionada no existe.', 404);
    }

    public function upload(array $file, array $data): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('El servidor necesita la extension PHP Zip para subir sistemas.');
        }
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
            throw new InvalidArgumentException($this->uploadError($error));
        }
        $max = (int) ($this->config['system_app_max_upload_bytes'] ?? 536870912);
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > $max) {
            throw new InvalidArgumentException('El ZIP supera el limite permitido de ' . round($max / 1048576) . ' MB.');
        }
        if (strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION)) !== 'zip') {
            throw new InvalidArgumentException('Suba el proyecto web compilado en un archivo ZIP.');
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || strlen($name) > 100) throw new InvalidArgumentException('Indique un nombre de hasta 100 caracteres.');
        $slug = $this->normalizeSlug((string) ($data['slug'] ?? $name));
        if ($slug === 'default') throw new InvalidArgumentException('La ubicacion "default" esta reservada.');

        $this->ensureDirectory();
        $target = $this->directory . DIRECTORY_SEPARATOR . $slug;
        if (is_dir($target) || is_file($target)) throw new InvalidArgumentException('Ya existe una carpeta con esa ubicacion.');
        $staging = $this->directory . DIRECTORY_SEPARATOR . '.upload-' . bin2hex(random_bytes(8));
        if (!mkdir($staging, 0775, true) && !is_dir($staging)) throw new RuntimeException('No se pudo preparar la carpeta de subida.');

        $zip = new ZipArchive();
        $zipOpen = false;
        try {
            if ($zip->open((string) $file['tmp_name']) !== true) throw new InvalidArgumentException('El archivo ZIP no es valido.');
            $zipOpen = true;
            $this->extractStaticFiles($zip, $staging);
            $zip->close();
            $zipOpen = false;
            $source = $this->findAppRoot($staging);
            if (!rename($source, $target)) throw new RuntimeException('No se pudo publicar la carpeta del sistema.');
            if (is_dir($staging)) $this->removeDirectory($staging);
            $metadata = json_encode([
                'name' => $name,
                'description' => trim((string) ($data['description'] ?? '')),
                'uploaded_at' => gmdate('Y-m-d H:i:s'),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (file_put_contents($target . DIRECTORY_SEPARATOR . '.system-app.json', $metadata, LOCK_EX) === false) {
                throw new RuntimeException('No se pudieron guardar los datos de la carpeta.');
            }
        } catch (\Throwable $e) {
            if ($zipOpen) $zip->close();
            if (is_dir($staging)) $this->removeDirectory($staging);
            if (is_dir($target)) $this->removeDirectory($target);
            throw $e;
        }
        return $this->find($slug);
    }

    /** Reemplaza por completo la interfaz por defecto (public/sistema/app) con
     * el contenido de un ZIP (el build de /dist comprimido). Extrae primero a
     * una carpeta temporal y valida que tenga un index.html antes de tocar la
     * carpeta en vivo; si el intercambio falla a medio camino, se restaura la
     * version anterior en vez de dejar la carpeta vacia o a medio subir.
     */
    public function replaceDefault(array $file): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('El servidor necesita la extension PHP Zip para subir sistemas.');
        }
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
            throw new InvalidArgumentException($this->uploadError($error));
        }
        $max = (int) ($this->config['system_app_max_upload_bytes'] ?? 536870912);
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > $max) {
            throw new InvalidArgumentException('El ZIP supera el limite permitido de ' . round($max / 1048576) . ' MB.');
        }
        if (strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION)) !== 'zip') {
            throw new InvalidArgumentException('Suba el proyecto web compilado en un archivo ZIP.');
        }

        $target = $this->legacyPath();
        $parent = dirname($target);
        if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
            throw new RuntimeException('No se pudo preparar la carpeta del sistema.');
        }
        $staging = $parent . DIRECTORY_SEPARATOR . '.replace-' . bin2hex(random_bytes(8));
        if (!mkdir($staging, 0775, true) && !is_dir($staging)) throw new RuntimeException('No se pudo preparar la carpeta de subida.');

        $zip = new ZipArchive();
        $zipOpen = false;
        $backup = $parent . DIRECTORY_SEPARATOR . '.backup-' . bin2hex(random_bytes(8));
        $backedUp = false;
        try {
            if ($zip->open((string) $file['tmp_name']) !== true) throw new InvalidArgumentException('El archivo ZIP no es valido.');
            $zipOpen = true;
            $this->extractStaticFiles($zip, $staging);
            $zip->close();
            $zipOpen = false;
            $source = $this->findAppRoot($staging);

            if (is_dir($target)) {
                if (!rename($target, $backup)) throw new RuntimeException('No se pudo retirar la version anterior.');
                $backedUp = true;
            }
            if (!rename($source, $target)) {
                if ($backedUp) rename($backup, $target);
                throw new RuntimeException('No se pudo publicar la nueva version.');
            }
            if (is_dir($staging)) $this->removeDirectory($staging);
            if ($backedUp && is_dir($backup)) $this->removeDirectory($backup);
        } catch (\Throwable $e) {
            if ($zipOpen) $zip->close();
            if (is_dir($staging)) $this->removeDirectory($staging);
            if ($backedUp && is_dir($backup) && !is_dir($target)) rename($backup, $target);
            throw $e;
        }
    }

    public function delete(string $slug): void
    {
        $slug = $this->normalizeSlug($slug);
        if ($slug === 'default') throw new InvalidArgumentException('El sistema predeterminado no se puede eliminar.');
        $app = $this->find($slug);
        $root = realpath($this->directory);
        $path = realpath((string) $app['path']);
        if (!$root || !$path || !str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('La carpeta indicada no es valida.');
        }
        $this->removeDirectory($path);
    }

    private function extractStaticFiles(ZipArchive $zip, string $staging): void
    {
        $allowed = ['html','htm','css','js','mjs','cjs','json','map','png','jpg','jpeg','gif','webp','svg','ico','avif','woff','woff2','ttf','otf','eot','txt','xml','webmanifest','wasm','mp3','mp4','webm','wav','ogg','pdf'];
        $total = 0;
        if ($zip->numFiles < 1 || $zip->numFiles > 10000) throw new InvalidArgumentException('El ZIP esta vacio o contiene demasiados archivos.');
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $entry = str_replace('\\', '/', (string) ($stat['name'] ?? ''));
            if ($entry === '' || str_starts_with($entry, '/') || preg_match('#(^|/)\.\.(/|$)#', $entry)) {
                throw new InvalidArgumentException('El ZIP contiene una ruta no permitida.');
            }
            $parts = array_values(array_filter(explode('/', $entry), fn (string $part): bool => $part !== '' && $part !== '.'));
            if (!$parts || in_array('.htaccess', $parts, true)) continue;
            $relative = implode(DIRECTORY_SEPARATOR, $parts);
            $isDirectory = str_ends_with($entry, '/');
            $destination = $staging . DIRECTORY_SEPARATOR . $relative;
            if ($isDirectory) {
                if (!is_dir($destination) && !mkdir($destination, 0775, true) && !is_dir($destination)) throw new RuntimeException('No se pudo crear una subcarpeta.');
                continue;
            }
            $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
            if (!in_array($extension, $allowed, true)) throw new InvalidArgumentException('El ZIP contiene un tipo de archivo no permitido: ' . basename($relative));
            $total += (int) ($stat['size'] ?? 0);
            if ($total > 1073741824) throw new InvalidArgumentException('El contenido descomprimido supera 1 GB.');
            $parent = dirname($destination);
            if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) throw new RuntimeException('No se pudo crear una subcarpeta.');
            $input = $zip->getStream((string) $stat['name']);
            if (!is_resource($input)) throw new RuntimeException('No se pudo leer un archivo del ZIP.');
            $output = fopen($destination, 'wb');
            if (!is_resource($output)) {
                fclose($input);
                throw new RuntimeException('No se pudo extraer un archivo del ZIP.');
            }
            $copied = stream_copy_to_stream($input, $output);
            fclose($input);
            fclose($output);
            if ($copied === false) throw new RuntimeException('La extraccion del ZIP quedo incompleta.');
        }
    }

    private function findAppRoot(string $staging): string
    {
        if (is_file($staging . DIRECTORY_SEPARATOR . 'index.html')) return $staging;
        $matches = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($staging, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            if ($item->isFile() && strtolower($item->getFilename()) === 'index.html') $matches[] = $item->getPath();
        }
        if (count($matches) !== 1) throw new InvalidArgumentException('El ZIP debe contener un unico archivo index.html en la raiz del proyecto compilado.');
        return $matches[0];
    }

    private function normalizeSlug(string $slug): string
    {
        $slug = Support::slug($slug);
        if ($slug === '' || strlen($slug) > 80) throw new InvalidArgumentException('La ubicacion debe tener entre 1 y 80 caracteres.');
        return $slug;
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new RuntimeException('No se pudo crear el directorio de sistemas.');
        }
    }

    private function legacyPath(): string
    {
        return $this->config['root'] . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'sistema' . DIRECTORY_SEPARATOR . 'app';
    }

    private function removeDirectory(string $directory): void
    {
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        rmdir($directory);
    }

    private function uploadError(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El ZIP supera el limite permitido por el servidor.',
            UPLOAD_ERR_PARTIAL => 'La subida del ZIP quedo incompleta.',
            UPLOAD_ERR_NO_FILE => 'Seleccione un archivo ZIP.',
            default => 'No se pudo recibir el archivo ZIP.',
        };
    }
}
