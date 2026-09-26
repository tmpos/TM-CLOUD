<?php
declare(strict_types=1);
namespace App\Services;

use InvalidArgumentException;

final class CustomerRegistrationSettingsService
{
    public function __construct(private SchemaService $schema) {}

    public static function defaults(): array
    {
        $fields = [];
        foreach (['nombre', 'telefono', 'documento', 'email', 'direccion', 'tipo_cliente', 'producto_interes', 'necesidad'] as $key) {
            $fields[$key] = ['visible' => true, 'required' => in_array($key, ['nombre', 'telefono', 'documento', 'producto_interes', 'necesidad'], true)];
        }
        return ['version' => 1, 'show_logo' => true, 'title' => 'Registro de cliente', 'description' => 'Complete sus datos para que podamos brindarle una mejor atención.', 'primary_color' => '#0f766e', 'fields' => $fields];
    }

    public function normalize(array $input): array
    {
        $result = self::defaults();
        if (isset($input['show_logo'])) {
            if (!is_bool($input['show_logo'])) throw new InvalidArgumentException('La opción de logo debe ser booleana.');
            $result['show_logo'] = $input['show_logo'];
        }
        foreach (['title' => 100, 'description' => 400] as $key => $max) {
            if (!array_key_exists($key, $input)) continue;
            if (!is_string($input[$key]) || mb_strlen(trim($input[$key])) > $max) throw new InvalidArgumentException('El texto del formulario no es válido.');
            $result[$key] = trim($input[$key]);
        }
        if ($result['title'] === '') throw new InvalidArgumentException('Indique un título para el formulario.');
        if (isset($input['primary_color'])) {
            if (!is_string($input['primary_color']) || !preg_match('/^#[0-9a-f]{6}$/iD', $input['primary_color'])) throw new InvalidArgumentException('Seleccione un color válido.');
            $result['primary_color'] = strtolower($input['primary_color']);
        }
        if (isset($input['fields']) && !is_array($input['fields'])) throw new InvalidArgumentException('Los campos no son válidos.');
        foreach (($input['fields'] ?? []) as $key => $field) {
            if (!isset($result['fields'][$key]) || !is_array($field)) throw new InvalidArgumentException('Campo de registro no válido.');
            foreach (['visible', 'required'] as $flag) {
                if (!isset($field[$flag]) || !is_bool($field[$flag])) throw new InvalidArgumentException('Indique visibilidad y obligatoriedad de cada campo.');
            }
            $result['fields'][$key] = ['visible' => $field['visible'], 'required' => $field['visible'] && $field['required']];
        }
        $result['fields']['nombre'] = ['visible' => true, 'required' => true];
        return $result;
    }

    public function get(array $project): array
    {
        $db = $this->schema->connection($project);
        if (!$db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='_customer_registration_settings'")->fetchColumn()) return self::defaults();
        $json = $db->query('SELECT settings FROM _customer_registration_settings WHERE id=1')->fetchColumn();
        return $json ? $this->normalize(json_decode($json, true, 512, JSON_THROW_ON_ERROR)) : self::defaults();
    }

    public function save(array $project, array $input): array
    {
        $settings = $this->normalize($input);
        $db = $this->schema->connection($project);
        $db->exec('CREATE TABLE IF NOT EXISTS _customer_registration_settings (id INTEGER PRIMARY KEY CHECK(id=1), settings TEXT NOT NULL)');
        $db->prepare('INSERT INTO _customer_registration_settings(id,settings) VALUES(1,?) ON CONFLICT(id) DO UPDATE SET settings=excluded.settings')->execute([json_encode($settings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
        return $settings;
    }

    public function branding(array $project, array $request = []): array
    {
        $db = $this->schema->connection($project);
        $company = [];
        if ($db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='empresa'")->fetchColumn()) {
            $companies = $db->query('SELECT * FROM empresa ORDER BY id ASC')->fetchAll();
            $company = $companies[0] ?? [];
            foreach ($companies as $row) {
                if ((!empty($request['almacen_uid']) && in_array($request['almacen_uid'], [$row['almacen_uid'] ?? '', $row['uid'] ?? ''], true))
                    || (!empty($request['almacen_id']) && (int) $request['almacen_id'] === (int) ($row['almacen_id'] ?? $row['id'] ?? 0))) { $company = $row; break; }
            }
        }
        $logo = trim((string) ($company['logo'] ?? ''));
        if (preg_match('/^fil_[A-Za-z0-9_-]+$/D', $logo)) $logo = '/api/' . rawurlencode($project['uid']) . '/storage/' . rawurlencode($logo);
        elseif (!preg_match('#^/(?:api|storage)/[A-Za-z0-9_./-]+$#D', $logo)
            && !(filter_var($logo, FILTER_VALIDATE_URL) && strtolower((string) parse_url($logo, PHP_URL_SCHEME)) === 'https')
            && !preg_match('#^data:image/(?:png|jpeg|webp);base64,[A-Za-z0-9+/=]+$#D', $logo)) $logo = '';
        return ['name' => trim((string) ($company['nombre'] ?? '')) ?: (string) ($project['name'] ?? 'Empresa'), 'logo_url' => $logo];
    }
}
