<?php

declare(strict_types=1);

namespace App\Services;

final class RealtimeService
{
    private string $host;
    private int $port;
    private bool $enabled;

    public function __construct(array $config)
    {
        $this->enabled = (bool) ($config['enabled'] ?? true);
        $this->host = (string) ($config['server_host'] ?? '127.0.0.1');
        $this->port = (int) ($config['event_port'] ?? 8081);
    }

    public function broadcast(string $event, array $project, ?string $table, mixed $record): void
    {
        if (!$this->enabled) return;

        try {
            $socket = @fsockopen('tcp://' . $this->host, $this->port, $errno, $errstr, 1);
            if (!$socket) return;

            $payload = json_encode([
                'event' => $event,
                'project_uid' => $project['uid'],
                'table' => $table,
                'record' => $record,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);

            fwrite($socket, $payload . "\n");
            fclose($socket);
        } catch (\Throwable) {
        }
    }
}
