<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Support;
use PDO;

final class SupportService
{
    public function __construct(private PDO $db, private array $config)
    {
    }

    public function issueToken(string $projectUid, ?string $adminUserUid): array
    {
        $this->db->prepare("DELETE FROM _support_tokens WHERE expires_at < ?")
            ->execute([gmdate('Y-m-d H:i:s', time() - 3600)]);

        $uid = Support::uid('spt_');
        $expiresAt = gmdate('Y-m-d H:i:s', time() + 60);
        $now = Support::now();
        $this->db->prepare(
            'INSERT INTO _support_tokens (uid, project_uid, admin_user_uid, expires_at, created_at) VALUES (?,?,?,?,?)'
        )->execute([$uid, $projectUid, $adminUserUid, $expiresAt, $now]);

        return [
            'token' => $uid,
            'expires_at' => $expiresAt,
            'ws_url' => !empty($this->config['realtime']['enabled']) ? ($this->config['realtime']['ws_url'] ?? null) : null,
        ];
    }

    /** Nombre asignado por el admin a una estacion (ej. "Caja 1"), independiente
     * del nombre que la propia estacion reporte al conectarse. Vacio borra la
     * etiqueta y vuelve a dejar que mande el nombre reportado por la estacion. */
    public function setLabel(string $projectUid, string $deviceId, string $label): void
    {
        $deviceId = trim($deviceId);
        $label = trim($label);
        if ($deviceId === '') return;
        if ($label === '') {
            $this->db->prepare('DELETE FROM _station_labels WHERE project_uid = ? AND device_id = ?')
                ->execute([$projectUid, $deviceId]);
            return;
        }
        $this->db->prepare(
            'INSERT INTO _station_labels (project_uid, device_id, label, updated_at) VALUES (?,?,?,?)
             ON CONFLICT(project_uid, device_id) DO UPDATE SET label = excluded.label, updated_at = excluded.updated_at'
        )->execute([$projectUid, $deviceId, $label, Support::now()]);
    }

    /** device_id => label para todas las estaciones etiquetadas de un proyecto. */
    public function getLabels(string $projectUid): array
    {
        $stmt = $this->db->prepare('SELECT device_id, label FROM _station_labels WHERE project_uid = ?');
        $stmt->execute([$projectUid]);
        $labels = [];
        foreach ($stmt->fetchAll() as $row) {
            $labels[$row['device_id']] = $row['label'];
        }
        return $labels;
    }
}
