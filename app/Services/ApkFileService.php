<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

final class ApkFileService
{
    private string $directory;

    public function __construct(private array $config)
    {
        $this->directory = $config['storage'] . DIRECTORY_SEPARATOR . 'apk-files';
    }

    public function all(): array
    {
        $this->ensureDirectory();
        $files = [];
        foreach (new \DirectoryIterator($this->directory) as $item) {
            if (!$item->isFile() || strtolower($item->getExtension()) !== 'apk') {
                continue;
            }
            $files[] = $this->fileData($item->getFilename());
        }
        usort($files, fn (array $a, array $b): int => $b['modified_timestamp'] <=> $a['modified_timestamp']);
        return $files;
    }

    public function find(string $name): array
    {
        $this->ensureDirectory();
        $safeName = $this->safeRequestedName($name);
        $path = $this->directory . DIRECTORY_SEPARATOR . $safeName;
        if (!is_file($path)) {
            throw new RuntimeException('Archivo APK no encontrado.', 404);
        }
        return $this->fileData($safeName);
    }

    public function latest(): array
    {
        $files = $this->all();
        if (!$files) {
            throw new RuntimeException('No hay archivos APK disponibles.', 404);
        }
        return $files[0];
    }

    public function upload(array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
            throw new InvalidArgumentException($this->uploadError($error));
        }

        $size = (int) ($file['size'] ?? 0);
        $maxBytes = (int) ($this->config['apk_max_upload_bytes'] ?? 262144000);
        if ($size <= 0 || $size > $maxBytes) {
            throw new InvalidArgumentException('El APK supera el limite permitido de ' . round($maxBytes / 1048576) . ' MB.');
        }

        $originalName = basename((string) ($file['name'] ?? 'aplicacion.apk'));
        if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'apk') {
            throw new InvalidArgumentException('Solo se permiten archivos con extension .apk.');
        }

        $handle = fopen((string) $file['tmp_name'], 'rb');
        $signature = $handle ? fread($handle, 4) : false;
        if (is_resource($handle)) {
            fclose($handle);
        }
        if (!is_string($signature) || !str_starts_with($signature, 'PK')) {
            throw new InvalidArgumentException('El archivo no contiene una estructura APK valida.');
        }

        $this->ensureDirectory();
        $name = $this->availableName($this->sanitizeName($originalName));
        $path = $this->directory . DIRECTORY_SEPARATOR . $name;
        if (!move_uploaded_file((string) $file['tmp_name'], $path)) {
            throw new RuntimeException('No se pudo guardar el archivo APK.');
        }
        return $this->fileData($name);
    }

    public function delete(string $name): void
    {
        $file = $this->find($name);
        if (!unlink((string) $file['file_path'])) {
            throw new RuntimeException('No se pudo eliminar el archivo APK.');
        }
    }

    public function apiData(array $file): array
    {
        return [
            'name' => $file['name'],
            'size' => $file['size'],
            'download_url' => $file['download_url'],
            'modified_at' => $file['modified_at'],
        ];
    }

    private function fileData(string $name): array
    {
        $path = $this->directory . DIRECTORY_SEPARATOR . $name;
        $modified = (int) (filemtime($path) ?: 0);
        return [
            'name' => $name,
            'original_name' => $name,
            'file_path' => $path,
            'size' => (int) filesize($path),
            'download_url' => rtrim((string) $this->config['url'], '/') . '/downloads/apk/' . rawurlencode($name),
            'modified_timestamp' => $modified,
            'modified_at' => gmdate('Y-m-d H:i:s', $modified),
        ];
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new RuntimeException('No se pudo crear el directorio para APK.');
        }
    }

    private function safeRequestedName(string $name): string
    {
        $decoded = rawurldecode($name);
        if ($decoded === '' || basename($decoded) !== $decoded || strtolower(pathinfo($decoded, PATHINFO_EXTENSION)) !== 'apk') {
            throw new InvalidArgumentException('Nombre de archivo APK invalido.');
        }
        return $decoded;
    }

    private function sanitizeName(string $name): string
    {
        $base = pathinfo($name, PATHINFO_FILENAME);
        $base = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $base), '.-_');
        return ($base !== '' ? $base : 'aplicacion') . '.apk';
    }

    private function availableName(string $name): string
    {
        if (!is_file($this->directory . DIRECTORY_SEPARATOR . $name)) {
            return $name;
        }
        $base = pathinfo($name, PATHINFO_FILENAME);
        $index = 2;
        do {
            $candidate = $base . '-' . $index++ . '.apk';
        } while (is_file($this->directory . DIRECTORY_SEPARATOR . $candidate));
        return $candidate;
    }

    private function uploadError(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El APK supera el limite permitido por el servidor.',
            UPLOAD_ERR_PARTIAL => 'La subida del APK quedo incompleta.',
            UPLOAD_ERR_NO_FILE => 'Seleccione un archivo APK.',
            default => 'No se pudo recibir el archivo APK.',
        };
    }
}
