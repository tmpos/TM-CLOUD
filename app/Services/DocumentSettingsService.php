<?php
declare(strict_types=1);
namespace App\Services;

final class DocumentSettingsService
{
    public function __construct(private SchemaService $schema) {}
    public static function defaults(): array
    {
        return ['version' => 1, 'primary_color' => '#176b9c', 'heading_color' => '#102a43', 'show_logo' => true, 'logo_width' => 150, 'logo_height' => 90, 'quote_validity_days' => 30, 'show_company_signature' => false, 'representative_name' => '', 'representative_signature' => ''];
    }
    public function normalize(array $input): array
    {
        $result = self::defaults();
        foreach (['primary_color', 'heading_color'] as $key) {
            if (!array_key_exists($key, $input)) continue;
            if (!is_string($input[$key]) || !preg_match('/^#[0-9a-f]{6}$/iD', $input[$key])) throw new \InvalidArgumentException('Color no valido.');
            $result[$key] = strtolower($input[$key]);
        }
        if (array_key_exists('show_logo', $input)) {
            if (!is_bool($input['show_logo'])) throw new \InvalidArgumentException('Logo no valido.');
            $result['show_logo'] = $input['show_logo'];
        }
        foreach (['logo_width' => [30, 250], 'logo_height' => [20, 150], 'quote_validity_days' => [1, 365]] as $key => [$min, $max]) {
            if (!array_key_exists($key, $input)) continue;
            if (!is_int($input[$key]) || $input[$key] < $min || $input[$key] > $max) throw new \InvalidArgumentException('Medida o plazo no valido.');
            $result[$key] = $input[$key];
        }
        if (array_key_exists('show_company_signature', $input)) {
            if (!is_bool($input['show_company_signature'])) throw new \InvalidArgumentException('Opción de firma no válida.');
            $result['show_company_signature'] = $input['show_company_signature'];
        }
        if (array_key_exists('representative_name', $input)) {
            if (!is_string($input['representative_name']) || mb_strlen($input['representative_name']) > 100) throw new \InvalidArgumentException('Nombre del representante no válido.');
            $result['representative_name'] = trim($input['representative_name']);
        }
        $signature = $input['representative_signature'] ?? '';
        if (!is_string($signature) || strlen($signature) > 700000) throw new \InvalidArgumentException('Firma demasiado grande.');
        if ($signature !== '') {
            if (!preg_match('#^data:image/(png|jpeg);base64,([A-Za-z0-9+/]+={0,2})$#D', $signature, $matches)) throw new \InvalidArgumentException('La firma debe ser una imagen PNG o JPG.');
            $bytes = base64_decode($matches[2], true);
            $size = $bytes === false ? false : @getimagesizefromstring($bytes);
            if (!$size || $size['mime'] !== 'image/' . $matches[1] || $size[0] > 2000 || $size[1] > 2000 || $size[0] * $size[1] > 2000000) throw new \InvalidArgumentException('Imagen de firma no válida o demasiado grande.');
            $result['representative_signature'] = $signature;
        }
        if ($result['show_company_signature'] && ($result['representative_signature'] === '' || $result['representative_name'] === '')) throw new \InvalidArgumentException('Carga la firma e indica el representante antes de activarla.');
        return $result;
    }
    public function get(array $project): array
    {
        $db = $this->schema->connection($project);
        if (!$db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='_document_settings'")->fetchColumn()) return self::defaults();
        $json = $db->query('SELECT settings FROM _document_settings WHERE id=1')->fetchColumn();
        return $json ? $this->normalize(json_decode($json, true, 512, JSON_THROW_ON_ERROR)) : self::defaults();
    }
    public function save(array $project, array $input): array
    {
        $settings = $this->normalize(array_replace($this->get($project), $input));
        $db = $this->schema->connection($project);
        $db->exec('CREATE TABLE IF NOT EXISTS _document_settings (id INTEGER PRIMARY KEY CHECK(id=1), settings TEXT NOT NULL)');
        $db->prepare('INSERT INTO _document_settings VALUES(1,?) ON CONFLICT(id) DO UPDATE SET settings=excluded.settings')->execute([json_encode($settings, JSON_THROW_ON_ERROR)]);
        return $settings;
    }
}
