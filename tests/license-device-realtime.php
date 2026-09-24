<?php
// Authorizing/blocking a license device publishes license.device_status on
// realtime, so TMPOS's license screen can continue on its own.
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Database;
use App\Services\LicenseService;
use App\Services\LogService;
use App\Services\RealtimeService;

function check(bool $ok, string $what): void
{
    if (!$ok) { fwrite(STDERR, "FAIL: $what\n"); exit(1); }
}

$dir = sys_get_temp_dir() . '/tmpbase-license-rt-' . bin2hex(random_bytes(4));
mkdir($dir);
$db = Database::connect($dir . '/central.sqlite');
Database::migrate($db);

// Fake realtime event port: captures what RealtimeService sends.
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
check($server !== false, "abrir puerto de prueba: $errstr");
$port = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);
$readEvent = function () use ($server): ?array {
    $conn = @stream_socket_accept($server, 2);
    if (!$conn) return null;
    $line = fgets($conn);
    fclose($conn);
    return $line ? json_decode($line, true) : null;
};

$projectUid = 'prj_' . bin2hex(random_bytes(6));
$db->prepare('INSERT INTO projects (uid, name, slug, database_path, public_key, secret_key, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)')
    ->execute([$projectUid, 'Demo', 'demo-' . substr($projectUid, 4), $dir . '/project.sqlite', 'pk', 'sk', gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s')]);

$licenses = new LicenseService($db, new LogService($db), new RealtimeService(['enabled' => true, 'event_port' => $port]));
$license = $licenses->create($projectUid, ['max_uses' => 5, 'system_name' => 'TMPOS', 'nombre' => 'DEMO']);
$licenses->registerDevice((string) $license['license_key'], 'TMPOS-E8A5-001B-9A83-CB58');

$licenses->authorizeDevice((string) $license['uid'], 'tmpos-e8a5-001b-9a83-cb58');
$event = $readEvent();
check($event !== null, 'autorizar publica un evento realtime');
check(($event['event'] ?? '') === 'license.device_status' && ($event['table'] ?? '') === 'license_devices', 'evento y tabla correctos');
check(($event['project_uid'] ?? '') === $projectUid, 'va al proyecto de la licencia');
check(($event['record']['device_id'] ?? '') === 'TMPOS-E8A5-001B-9A83-CB58' && ($event['record']['status'] ?? '') === 'authorized', 'incluye el equipo (normalizado) y el estado');
check($licenses->isDeviceAuthorized((string) $license['uid'], 'TMPOS-E8A5-001B-9A83-CB58'), 'el equipo queda autorizado');

$licenses->blockDevice((string) $license['uid'], 'TMPOS-E8A5-001B-9A83-CB58');
check(($readEvent()['record']['status'] ?? '') === 'blocked', 'bloquear tambien avisa');

// Without a realtime server listening the change still commits.
fclose($server);
$licenses->authorizeDevice((string) $license['uid'], 'TMPOS-E8A5-001B-9A83-CB58');
check($licenses->isDeviceAuthorized((string) $license['uid'], 'TMPOS-E8A5-001B-9A83-CB58'), 'sin realtime la autorizacion se guarda igual');

// Without RealtimeService (old wiring) nothing breaks either.
(new LicenseService($db, new LogService($db)))->revokeDevice((string) $license['uid'], 'TMPOS-E8A5-001B-9A83-CB58');

Database::disconnect($dir . '/central.sqlite');
echo "PASS: autorizar/bloquear equipo publica license.device_status por realtime\n";
