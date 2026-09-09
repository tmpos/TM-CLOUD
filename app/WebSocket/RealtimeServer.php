<?php

declare(strict_types=1);

namespace App\WebSocket;

use PDO;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use React\Socket\ConnectionInterface as ReactConnection;
use React\Socket\SocketServer;

final class RealtimeServer implements MessageComponentInterface
{
    private \SplObjectStorage $clients;
    private array $projects = [];
    private array $connectionProject = [];
    /** connId => ['client_id'=>, 'role'=>'station'|'admin', 'device_id'=>?, 'device_name'=>?, 'project'=>] */
    private array $connectionMeta = [];
    /** projectUid => deviceId => connId, so an admin can address a specific station. */
    private array $projectDevices = [];
    private ?PDO $db = null;
    private string $dbPath;

    public function __construct(string $dbPath)
    {
        $this->clients = new \SplObjectStorage();
        $this->dbPath = $dbPath;
    }

    private function db(): PDO
    {
        if ($this->db === null) {
            $this->db = new PDO('sqlite:' . $this->dbPath, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
        return $this->db;
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $data = json_decode((string) $msg, true);
        if (!$data || !isset($data['type'])) return;

        match ($data['type']) {
            'subscribe' => $this->subscribe($from, $data),
            'unsubscribe' => $this->unsubscribe($from, $data),
            'signal' => $this->relaySignal($from, $data),
            'ping' => $from->send(json_encode(['type' => 'pong'])),
            default => null,
        };
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $this->clients->detach($conn);
        $connId = spl_object_id($conn);
        $meta = $this->connectionMeta[$connId] ?? null;

        if ($meta && $meta['role'] === 'station' && $meta['device_id'] !== null) {
            $projectUid = $meta['project'];
            if (($this->projectDevices[$projectUid][$meta['device_id']] ?? null) === $connId) {
                unset($this->projectDevices[$projectUid][$meta['device_id']]);
            }
            $this->broadcastPresence($projectUid, 'offline', $meta['device_id'], $meta['device_name']);
        }

        if (isset($this->connectionProject[$connId])) {
            $project = $this->connectionProject[$connId];
            unset($this->projects[$project][$connId]);
            if (empty($this->projects[$project])) {
                unset($this->projects[$project]);
            }
            unset($this->connectionProject[$connId]);
        }
        unset($this->connectionMeta[$connId]);
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        $conn->close();
    }

    private function subscribe(ConnectionInterface $conn, array $data): void
    {
        $projectUid = (string) ($data['project'] ?? '');
        $token = (string) ($data['token'] ?? '');
        $role = (string) ($data['role'] ?? 'station');
        if (!in_array($role, ['station', 'admin'], true)) $role = 'station';

        if ($projectUid === '' || $token === '') {
            $conn->send(json_encode(['type' => 'error', 'message' => 'Project UID and token are required.']));
            return;
        }

        try {
            $stmt = $this->db()->prepare('SELECT public_key, secret_key FROM projects WHERE uid = ?');
            $stmt->execute([$projectUid]);
            $project = $stmt->fetch();

            if (!$project) {
                $conn->send(json_encode(['type' => 'error', 'message' => 'Project not found.']));
                return;
            }

            if ($role === 'admin') {
                if (!$this->consumeSupportToken($projectUid, $token)) {
                    $conn->send(json_encode(['type' => 'error', 'message' => 'Invalid or expired support token.']));
                    return;
                }
            } else {
                $authorized = hash_equals($project['public_key'], $token) || hash_equals($project['secret_key'], $token);
                if (!$authorized) {
                    $conn->send(json_encode(['type' => 'error', 'message' => 'Invalid token.']));
                    return;
                }
            }
        } catch (\Throwable $e) {
            $conn->send(json_encode(['type' => 'error', 'message' => 'Authentication failed.']));
            return;
        }

        $connId = spl_object_id($conn);
        $this->projects[$projectUid][$connId] = $conn;
        $this->connectionProject[$connId] = $projectUid;

        $clientId = bin2hex(random_bytes(12));
        $deviceId = $role === 'station' ? trim((string) ($data['device_id'] ?? '')) : null;
        $deviceId = ($deviceId === '' ? null : $deviceId);
        $deviceName = $role === 'station' ? trim((string) ($data['device_name'] ?? '')) : null;
        $deviceName = ($deviceName === '' ? null : $deviceName);

        $this->connectionMeta[$connId] = [
            'client_id' => $clientId, 'role' => $role, 'project' => $projectUid,
            'device_id' => $deviceId, 'device_name' => $deviceName,
        ];

        if ($role === 'station' && $deviceId !== null) {
            $this->projectDevices[$projectUid][$deviceId] = $connId;
            $this->broadcastPresence($projectUid, 'online', $deviceId, $deviceName);
        }

        $conn->send(json_encode(['type' => 'subscribed', 'project' => $projectUid, 'client_id' => $clientId, 'role' => $role]));

        if ($role === 'admin') {
            $conn->send(json_encode(['type' => 'presence_snapshot', 'stations' => $this->stationsSnapshot($projectUid)]));
        }
    }

    private function stationsSnapshot(string $projectUid): array
    {
        $stations = [];
        foreach ($this->projectDevices[$projectUid] ?? [] as $deviceId => $connId) {
            $meta = $this->connectionMeta[$connId] ?? null;
            if ($meta) $stations[] = ['device_id' => $deviceId, 'device_name' => $meta['device_name']];
        }
        return $stations;
    }

    private function broadcastPresence(string $projectUid, string $action, string $deviceId, ?string $deviceName): void
    {
        if (!isset($this->projects[$projectUid])) return;
        $payload = json_encode(['type' => 'presence', 'action' => $action, 'device_id' => $deviceId, 'device_name' => $deviceName]);
        foreach ($this->projects[$projectUid] as $connId => $conn) {
            $meta = $this->connectionMeta[$connId] ?? null;
            if ($meta && $meta['role'] === 'admin') $conn->send($payload);
        }
    }

    /** Signaling is a dumb relay: the server only checks that sender/target share a project and that
     * an admin<->station pairing is respected, never inspects the WebRTC payload itself. */
    private function relaySignal(ConnectionInterface $from, array $data): void
    {
        $connId = spl_object_id($from);
        $meta = $this->connectionMeta[$connId] ?? null;
        if (!$meta) return;

        $projectUid = $meta['project'];
        $sessionId = (string) ($data['session_id'] ?? '');
        $payload = $data['payload'] ?? null;
        if ($sessionId === '' || $payload === null) return;

        $targetConn = null;
        if ($meta['role'] === 'admin') {
            $toDeviceId = (string) ($data['to_device_id'] ?? '');
            $targetConnId = $this->projectDevices[$projectUid][$toDeviceId] ?? null;
            if ($targetConnId !== null) $targetConn = $this->projects[$projectUid][$targetConnId] ?? null;
        } else {
            $toClientId = (string) ($data['to_client_id'] ?? '');
            foreach ($this->projects[$projectUid] ?? [] as $cid => $conn) {
                $candidateMeta = $this->connectionMeta[$cid] ?? null;
                if ($candidateMeta && $candidateMeta['role'] === 'admin' && $candidateMeta['client_id'] === $toClientId) {
                    $targetConn = $conn;
                    break;
                }
            }
        }
        if (!$targetConn) return;

        $targetConn->send(json_encode([
            'type' => 'signal',
            'session_id' => $sessionId,
            'from_client_id' => $meta['client_id'],
            'from_device_id' => $meta['device_id'],
            'from_device_name' => $meta['device_name'],
            'payload' => $payload,
        ]));

        $this->logSupportEvent($projectUid, $sessionId, is_array($payload) ? (string) ($payload['kind'] ?? '') : '', $meta);
    }

    private function logSupportEvent(string $projectUid, string $sessionId, string $kind, array $fromMeta): void
    {
        if (!in_array($kind, ['request', 'accept', 'deny', 'end'], true)) return;
        try {
            $this->db()->prepare(
                'INSERT INTO project_logs (uid, project_uid, action, table_name, record_uid, created_at) VALUES (?,?,?,?,?,?)'
            )->execute([
                bin2hex(random_bytes(12)), $projectUid, 'support.' . $kind, $fromMeta['device_id'] ?? null, $sessionId, gmdate('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
        }
    }

    private function consumeSupportToken(string $projectUid, string $token): bool
    {
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $this->db()->prepare(
            'SELECT id FROM _support_tokens WHERE uid = ? AND project_uid = ? AND used_at IS NULL AND expires_at > ? LIMIT 1'
        );
        $stmt->execute([$token, $projectUid, $now]);
        $row = $stmt->fetch();
        if (!$row) return false;
        $this->db()->prepare('UPDATE _support_tokens SET used_at = ? WHERE id = ?')->execute([$now, $row['id']]);
        return true;
    }

    private function unsubscribe(ConnectionInterface $conn, array $data): void
    {
        $projectUid = (string) ($data['project'] ?? '');
        $connId = spl_object_id($conn);

        if (isset($this->projects[$projectUid][$connId])) {
            unset($this->projects[$projectUid][$connId]);
            if (empty($this->projects[$projectUid])) {
                unset($this->projects[$projectUid]);
            }
        }
        if (isset($this->connectionProject[$connId]) && $this->connectionProject[$connId] === $projectUid) {
            unset($this->connectionProject[$connId]);
        }
    }

    public function broadcast(string $projectUid, string $event, ?string $table, mixed $record): void
    {
        if (!isset($this->projects[$projectUid])) return;

        $payload = json_encode([
            'type' => 'event',
            'event' => $event,
            'project_uid' => $projectUid,
            'table' => $table,
            'record' => $record,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        foreach ($this->projects[$projectUid] as $conn) {
            $conn->send($payload);
        }
    }

    public static function start(string $dbPath, int $wsPort = 8080, int $eventPort = 8081): void
    {
        $loop = Loop::get();
        $server = new self($dbPath);

        $ws = new WsServer($server);
        $httpWs = new HttpServer($ws);
        $wsSocket = new SocketServer("0.0.0.0:$wsPort", [], $loop);
        new IoServer($httpWs, $wsSocket, $loop);

        $tcpServer = new SocketServer("0.0.0.0:$eventPort", [], $loop);
        $tcpServer->on('connection', function (ReactConnection $conn) use ($server): void {
            $buffer = '';
            $conn->on('data', function (string $data) use ($server, $conn, &$buffer): void {
                $buffer .= $data;
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);
                    $body = json_decode($line, true);
                    if ($body && isset($body['project_uid'], $body['event'])) {
                        $server->broadcast(
                            $body['project_uid'],
                            $body['event'],
                            $body['table'] ?? null,
                            $body['record'] ?? null,
                        );
                    }
                }
            });
            $conn->on('close', function () use ($conn): void {});
        });

        echo "[Realtime] Server started on ws://0.0.0.0:$wsPort and tcp://0.0.0.0:$eventPort\n";

        $loop->run();
    }
}
