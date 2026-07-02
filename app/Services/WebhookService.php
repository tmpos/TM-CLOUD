<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Support;
use PDO;

final class WebhookService
{
    public function __construct(private PDO $db, private ?RealtimeService $realtime = null)
    {
    }

    public function all(string $projectUid): array
    {
        $stmt = $this->db->prepare('SELECT * FROM webhooks WHERE project_uid = ? ORDER BY id DESC');
        $stmt->execute([$projectUid]);
        return $stmt->fetchAll();
    }

    public function create(string $projectUid, string $event, string $url): void
    {
        $events = ['record.created', 'record.updated', 'record.deleted', 'table.created', 'table.updated', 'table.truncated', 'table.deleted'];
        if (!in_array($event, $events, true) || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Invalid webhook event or URL.');
        }
        $parts = parse_url($url);
        if (!in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Webhook URLs must use HTTP or HTTPS.');
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        if ($host === 'localhost' || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new \InvalidArgumentException('Webhook URLs cannot target local or private network addresses.');
        }
        $now = Support::now();
        $stmt = $this->db->prepare('INSERT INTO webhooks (uid,project_uid,event,url,is_active,created_at,updated_at) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([Support::uid('whk_'), $projectUid, $event, $url, 1, $now, $now]);
    }

    public function dispatch(string $event, array $project, ?string $table, mixed $record): void
    {
        $stmt = $this->db->prepare('SELECT url FROM webhooks WHERE project_uid = ? AND event = ? AND is_active = 1');
        $stmt->execute([$project['uid'], $event]);
        $payload = Support::json([
            'event' => $event, 'project_uid' => $project['uid'], 'table' => $table,
            'record' => $record, 'created_at' => Support::now(),
        ]);
        foreach ($stmt->fetchAll() as $webhook) {
            $context = stream_context_create(['http' => [
                'method' => 'POST', 'header' => "Content-Type: application/json\r\n",
                'content' => $payload, 'timeout' => 3, 'ignore_errors' => true,
            ]]);
            @file_get_contents($webhook['url'], false, $context);
        }

        $this->realtime?->broadcast($event, $project, $table, $record);
    }
}
