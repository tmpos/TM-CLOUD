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
}
