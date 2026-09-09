<?php

declare(strict_types=1);

namespace App\Services;

final class RealtimeService
{
    private int $port;
    private bool $enabled;

    public function __construct(array $config)
    {
        $this->enabled = (bool) ($config['enabled'] ?? true);
        $this->port = (int) ($config['event_port'] ?? 8081);
    }

    public function broadcast(string $event, array $project, ?string $table, mixed $record): void
    {
        if (!$this->enabled) return;

        try {
            // El servidor de realtime siempre corre en el mismo contenedor y
            // escucha en 0.0.0.0 (todas las interfaces) para aceptar conexiones;
            // eso no significa que se pueda usar 0.0.0.0 como destino al
            // conectar como cliente. Este handoff es siempre local, por eso
            // se usa 127.0.0.1 sin depender de config/env (una config de
            // "server_host" incorrecta en el entorno rompia esta conexion en
            // silencio: la escritura quedaba guardada pero el evento nunca salia).
            $socket = @fsockopen('tcp://127.0.0.1', $this->port, $errno, $errstr, 1);
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
