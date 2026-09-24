<?php
// Project OTP: same algorithm as TMPOS (electron/main.ts calculateVariableOtp),
// support sign-in with it and the dashboard OTP tab.
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/app/helpers.php';

use App\Services\ProjectOtpService;

function check(bool $ok, string $what): void
{
    if (!$ok) { fwrite(STDERR, "FAIL: $what\n"); exit(1); }
}

// Vectors computed in Node: createHmac('sha256', secret).update(String(floor(ms/1000/60))).digest().readUInt32BE(0) % 10000
foreach ([1789000000 => '7603', 1789000059 => '8616', 1789000060 => '8616', 1789003600 => '5265'] as $ts => $expected) {
    $got = ProjectOtpService::calculate('vector-secret', 60, $ts * 1000);
    check($got === $expected, "OTP en $ts = $expected (igual que TMPOS), obtuvo $got");
}

$now = date('Y-m-d H:i:s');
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$otp = new ProjectOtpService();

$status = $otp->status($db, $now);
check(preg_match('/^\d{4}$/', $status['code']) === 1 && $status['mode'] === 'variable', 'crea la fila y devuelve un codigo');
check($otp->validate($db, $status['code'], $now), 'acepta el codigo actual');
$row = $otp->row($db, $now);
$previous = ProjectOtpService::calculate((string) $row['secret'], 60, (int) round(microtime(true) * 1000) - 60000);
check($otp->validate($db, $previous, $now), 'acepta el intervalo anterior');
check(!$otp->validate($db, 'abcd', $now) && !$otp->validate($db, '', $now), 'rechaza entradas no numericas');

$otp->save($db, ['mode' => 'fixed', 'fixedCode' => '2468'], $now);
check($otp->status($db, $now)['code'] === '2468' && $otp->validate($db, '2468', $now), 'modo fijo usa el codigo fijo');
$otp->save($db, ['mode' => 'variable'], $now);

// Web sign-in (SystemRuntimeService::supportLogin) with the project OTP.
$runtime = (new ReflectionClass(App\Services\SystemRuntimeService::class))->newInstanceWithoutConstructor();
$tz = new ReflectionProperty($runtime, 'requestTimezone');
$tz->setValue($runtime, new DateTimeZone('America/Santo_Domingo'));
$login = new ReflectionMethod($runtime, 'supportLogin');
$db->exec("CREATE TABLE usuarios(id INTEGER PRIMARY KEY, usuario TEXT, nombre TEXT, pin TEXT, rol TEXT, nivel_seguridad TEXT, estado TEXT)");
$db->exec("INSERT INTO usuarios VALUES (1,'ana','ANA','1111','vendedor','Vendedor','ACTIVADO')");
$code = $otp->status($db, $now)['code'];

$session = $login->invoke($runtime, $db, 'pin', ['pin' => $code]);
check(($session['support_session'] ?? false) === true && $session['id'] === 0, 'OTP por PIN entra como soporte (sesion de soporte)');
check(($login->invoke($runtime, $db, 'credentials', ['usuario' => 'Soporte', 'password' => $code])['rol'] ?? '') === 'soporte', 'usuario soporte + OTP');
check($login->invoke($runtime, $db, 'credentials', ['usuario' => 'ana', 'password' => $code]) === null, 'otro usuario con el OTP no entra como soporte');
$wrong = $code === '0000' ? '0001' : '0000';
if (!$otp->validate($db, $wrong, $now)) check($login->invoke($runtime, $db, 'pin', ['pin' => $wrong]) === null, 'un codigo que no es el OTP no entra');
$db->exec("INSERT INTO usuarios VALUES (7,'tecnico','TECNICO','','soporte','Soporte','ACTIVADO')");
check((int) ($login->invoke($runtime, $db, 'pin', ['pin' => $code])['id'] ?? 0) === 7, 'usa el usuario soporte activo del proyecto');

// Dashboard tab renders in both modes.
$project = ['uid' => 'prj_test', 'slug' => 'empresa-demo'];
foreach ([['mode' => 'variable', 'fixedCode' => '0000', 'intervalSeconds' => 60, 'code' => '0421', 'secondsRemaining' => 33], ['mode' => 'fixed', 'fixedCode' => '2468', 'intervalSeconds' => 60, 'code' => '2468', 'secondsRemaining' => 0]] as $otpView) {
    $otp = $otpView;
    ob_start();
    include dirname(__DIR__) . '/app/Views/project_otp.php';
    $html = ob_get_clean();
    check(str_contains($html, $otpView['code']) && str_contains($html, '/projects/prj_test/otp'), "la pestaña OTP muestra el codigo ({$otpView['mode']})");
    check(($otpView['mode'] === 'fixed') === str_contains($html, 'Modo fijo'), "la pestaña indica el modo ({$otpView['mode']})");
}

echo "PASS: OTP del proyecto (igual que TMPOS), login de soporte y pestaña OTP\n";
