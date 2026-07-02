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
            default => null,
        };
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $this->clients->detach($conn);
        $connId = spl_object_id($conn);

        if (isset($this->connectionProject[$connId])) {
            $project = $this->connectionProject[$connId];
            unset($this->projects[$project][$connId]);
            if (empty($this->projects[$project])) {
                unset($this->projects[$project]);
            }
            unset($this->connectionProject[$connId]);
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        $conn->close();
    }

    private function subscribe(ConnectionInterface $conn, array $data): void
    {
        $projectUid = (string) ($data['project'] ?? '');
        $token = (string) ($data['token'] ?? '');

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

            $authorized = hash_equals($project['public_key'], $token) || hash_equals($project['secret_key'], $token);
            if (!$authorized) {
                $conn->send(json_encode(['type' => 'error', 'message' => 'Invalid token.']));
                return;
            }
        } catch (\Throwable $e) {
            $conn->send(json_encode(['type' => 'error', 'message' => 'Authentication failed.']));
            return;
        }

        $connId = spl_object_id($conn);
        $this->projects[$projectUid][$connId] = $conn;
        $this->connectionProject[$connId] = $projectUid;

        $conn->send(json_encode(['type' => 'subscribed', 'project' => $projectUid]));
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
