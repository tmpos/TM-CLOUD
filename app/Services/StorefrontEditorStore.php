<?php
declare(strict_types=1);
namespace App\Services;
use PDO;

final class StorefrontEditorStore
{
    public function __construct(private PDO $db)
    {
        $db->exec('CREATE TABLE IF NOT EXISTS storefront_editor_drafts (store_uid TEXT PRIMARY KEY, revision INTEGER NOT NULL DEFAULT 0, draft_json TEXT, saved_at TEXT)');
    }
    public static function editable(array $store): array
    {
        foreach (['uid', 'project_uid', 'created_at', 'updated_at', 'url', 'project_name', 'project_status', 'page_content', 'resolved_business_type', 'is_spa', '_preview'] as $field) unset($store[$field]);
        return $store;
    }
    public static function fingerprint(array $store): string
    {
        $fields = self::editable($store); ksort($fields);
        return hash('sha256', json_encode($fields, JSON_THROW_ON_ERROR));
    }
    public function state(array $store): array
    {
        $stmt = $this->db->prepare('SELECT * FROM storefront_editor_drafts WHERE store_uid=?'); $stmt->execute([$store['uid']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return ['version' => 1, 'revision' => (int) ($row['revision'] ?? 0), 'published_hash' => self::fingerprint($store), 'draft' => !empty($row['draft_json']) ? json_decode($row['draft_json'], true, 512, JSON_THROW_ON_ERROR) : null, 'saved_at' => $row['saved_at'] ?? null];
    }
    public function mutate(string $action, array $request, callable $current, callable $prepare, callable $publish): array
    {
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $store = $current(); $state = $this->state($store);
            if (!isset($request['revision'], $request['published_hash']) || $request['revision'] !== $state['revision'] || !hash_equals($state['published_hash'], (string) $request['published_hash'])) throw new \RuntimeException('La web o el borrador cambió en otra sesión. Actualiza la configuración antes de guardar.', 409);
            $draft = null;
            if ($action !== 'discard') {
                $settings = $request['settings'] ?? null;
                if (!is_array($settings)) throw new \InvalidArgumentException('Falta la configuración del borrador.');
                $draft = self::editable($prepare($settings));
                if ($action === 'publish') { $store = $publish($draft); $draft = null; }
            }
            $this->db->prepare('INSERT INTO storefront_editor_drafts(store_uid,revision,draft_json,saved_at) VALUES(?,?,?,?) ON CONFLICT(store_uid) DO UPDATE SET revision=excluded.revision,draft_json=excluded.draft_json,saved_at=excluded.saved_at')->execute([$store['uid'], $state['revision'] + 1, $draft === null ? null : json_encode($draft, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), gmdate('c')]);
            $result = $this->state($store);
            $this->db->exec('COMMIT');
            return $result;
        } catch (\Throwable $error) { $this->db->exec('ROLLBACK'); throw $error; }
    }
}
