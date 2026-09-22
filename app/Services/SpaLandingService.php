<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Support;
use InvalidArgumentException;
use PDO;

final class SpaLandingService
{
    private const FIELDS = [
        'nombre', 'tagline', 'descripcion', 'telefono', 'whatsapp', 'email',
        'direccion', 'horario', 'color_primario', 'imagen_portada', 'instagram', 'facebook', 'tiktok',
    ];
    private const MAX_LENGTHS = [
        'nombre' => 160, 'tagline' => 200, 'descripcion' => 4000, 'telefono' => 30, 'whatsapp' => 30,
        'email' => 160, 'direccion' => 300, 'horario' => 500, 'color_primario' => 20, 'imagen_portada' => 1000,
        'instagram' => 200, 'facebook' => 200, 'tiktok' => 200,
    ];
    private const MAX_GALLERY_IMAGES = 12;

    public function __construct(
        private PDO $db,
        private array $config,
        private RecordService $records,
    ) {}

    private function baseUrl(): string
    {
        return rtrim((string) ($this->config['url'] ?? ''), '/');
    }

    public function landingUrl(array $project): string
    {
        return $this->baseUrl() . '/spa/' . $project['slug'];
    }

    private function row(array $project): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM spa_landing_settings WHERE project_uid = ? LIMIT 1');
        $stmt->execute([$project['uid']]);
        return $stmt->fetch() ?: null;
    }

    private function present(array $project, ?array $row): array
    {
        $data = ['enabled' => false];
        foreach (self::FIELDS as $field) $data[$field] = '';
        $data['color_primario'] = '#8347d9';
        $data['galeria'] = [];
        if ($row) {
            $data['enabled'] = (bool) $row['enabled'];
            foreach (self::FIELDS as $field) $data[$field] = (string) ($row[$field] ?? '');
            $decoded = json_decode((string) ($row['galeria'] ?? '[]'), true);
            $data['galeria'] = is_array($decoded) ? array_values(array_filter(array_map('strval', $decoded))) : [];
        }
        $data['landing_url'] = $this->landingUrl($project);
        return $data;
    }

    public function get(array $project): array
    {
        return $this->present($project, $this->row($project));
    }

    public function save(array $project, array $input): array
    {
        $data = [];
        foreach (self::FIELDS as $field) {
            $value = trim((string) ($input[$field] ?? ''));
            $max = self::MAX_LENGTHS[$field];
            if (mb_strlen($value) > $max) throw new InvalidArgumentException("El campo \"$field\" excede el largo permitido.");
            $data[$field] = $value;
        }
        if ($data['color_primario'] !== '' && !preg_match('/^#[0-9a-fA-F]{3,8}$/', $data['color_primario'])) {
            throw new InvalidArgumentException('El color primario debe ser un valor hexadecimal, ej. #8347d9.');
        }
        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('El correo electronico no es valido.');
        }
        foreach (['imagen_portada'] as $urlField) {
            if ($data[$urlField] !== '' && !filter_var($data[$urlField], FILTER_VALIDATE_URL)) {
                throw new InvalidArgumentException("El campo \"$urlField\" debe ser una URL valida.");
            }
        }
        $galeria = is_array($input['galeria'] ?? null) ? $input['galeria'] : [];
        $galeria = array_values(array_filter(array_map(static fn ($url) => trim((string) $url), $galeria), static fn ($url) => $url !== ''));
        if (count($galeria) > self::MAX_GALLERY_IMAGES) throw new InvalidArgumentException('Maximo ' . self::MAX_GALLERY_IMAGES . ' imagenes en la galeria.');
        foreach ($galeria as $url) {
            if (!filter_var($url, FILTER_VALIDATE_URL)) throw new InvalidArgumentException('Una de las imagenes de la galeria no es una URL valida.');
        }
        $enabled = ($input['enabled'] ?? false) === true;
        if ($enabled && $data['nombre'] === '') throw new InvalidArgumentException('Indique el nombre del spa antes de activar la pagina.');

        $now = Support::now();
        $existing = $this->row($project);
        if ($existing) {
            $sql = 'UPDATE spa_landing_settings SET enabled=?, ' . implode('=?, ', self::FIELDS) . '=?, galeria=?, updated_at=? WHERE project_uid=?';
            $params = [(int) $enabled, ...array_map(fn ($field) => $data[$field], self::FIELDS), Support::json($galeria), $now, $project['uid']];
            $this->db->prepare($sql)->execute($params);
        } else {
            $columns = array_merge(['uid', 'project_uid', 'enabled'], self::FIELDS, ['galeria', 'created_at', 'updated_at']);
            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            $params = array_merge(
                [Support::uid('spl_'), $project['uid'], (int) $enabled],
                array_map(fn ($field) => $data[$field], self::FIELDS),
                [Support::json($galeria), $now, $now],
            );
            $this->db->prepare('INSERT INTO spa_landing_settings (' . implode(',', $columns) . ') VALUES (' . $placeholders . ')')->execute($params);
        }
        return $this->get($project);
    }

    public function publicServices(array $project): array
    {
        try {
            $rows = $this->records->all($project, 'servicios');
        } catch (\Throwable) {
            return [];
        }
        $services = [];
        foreach ($rows as $row) {
            if (strtoupper((string) ($row['estado'] ?? 'ACTIVO')) !== 'ACTIVO') continue;
            $services[] = [
                'uid' => (string) ($row['uid'] ?? ''),
                'nombre' => (string) ($row['nombre'] ?? ''),
                'descripcion' => (string) ($row['descripcion'] ?? ''),
                'precio_venta' => (float) ($row['precio_venta'] ?? 0),
                'duracion_minutos' => (int) ($row['duracion_minutos'] ?? 0),
                'imagen' => (string) ($row['imagen'] ?? ''),
            ];
        }
        return $services;
    }

    public function book(array $project, array $input): array
    {
        $name = mb_strtoupper(trim((string) ($input['nombre'] ?? '')), 'UTF-8');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 160) throw new InvalidArgumentException('Indique un nombre valido (2 a 160 caracteres).');
        $phone = self::phone((string) ($input['telefono'] ?? ''));
        if ($phone === '') throw new InvalidArgumentException('Indique un telefono valido.');
        $fecha = trim((string) ($input['fecha'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) throw new InvalidArgumentException('Indique una fecha valida.');
        if ($fecha < gmdate('Y-m-d')) throw new InvalidArgumentException('La fecha debe ser hoy o una fecha futura.');
        $hora = trim((string) ($input['hora'] ?? ''));
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora)) throw new InvalidArgumentException('Indique una hora valida.');
        $servicio = mb_strtoupper(trim((string) ($input['servicio'] ?? '')), 'UTF-8');
        if (mb_strlen($servicio) > 160) throw new InvalidArgumentException('El servicio solicitado es demasiado largo.');
        $nota = trim((string) ($input['nota'] ?? ''));
        if (mb_strlen($nota) > 500) throw new InvalidArgumentException('La nota es demasiado larga.');

        return $this->records->create($project, 'citas_spa', [
            'nombre_cliente' => $name,
            'telefono' => $phone,
            'fecha' => $fecha,
            'hora' => $hora,
            'servicio' => $servicio,
            'nota' => $nota,
            'estado' => 'PENDIENTE',
            'origen' => 'LANDING',
        ]);
    }

    private static function phone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?: '';
        if (strlen($digits) === 10) $digits = '1' . $digits;
        return strlen($digits) >= 10 && strlen($digits) <= 15 ? $digits : '';
    }
}
