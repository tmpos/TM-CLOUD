<?php
declare(strict_types=1);
namespace App\Services;

final class DocumentSettingsService
{
    public function __construct(private SchemaService $schema) {}
    public static function defaults(): array
    {
        return ['version' => 1, 'primary_color' => '#176b9c', 'heading_color' => '#102a43', 'show_logo' => true, 'logo_width' => 150, 'logo_height' => 90, 'quote_validity_days' => 30];
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
        $settings = $this->normalize($input);
        $db = $this->schema->connection($project);
        $db->exec('CREATE TABLE IF NOT EXISTS _document_settings (id INTEGER PRIMARY KEY CHECK(id=1), settings TEXT NOT NULL)');
        $db->prepare('INSERT INTO _document_settings VALUES(1,?) ON CONFLICT(id) DO UPDATE SET settings=excluded.settings')->execute([json_encode($settings, JSON_THROW_ON_ERROR)]);
        return $settings;
    }
}
