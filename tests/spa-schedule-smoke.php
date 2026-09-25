<?php
declare(strict_types=1);
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) require dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
});
use App\Core\Database;
use App\Services\{LogService, ProjectService, SchemaService, RecordService, WebhookService, SpaScheduleService, SpaAppointmentService, SpaLandingService};

function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function rejected(callable $action, string $message): void {
    try { $action(); } catch (InvalidArgumentException|PDOException|RuntimeException $e) { return; }
    throw new LogicException($message);
}

// Separate processes contend for the same last slot, through different connections.
if (($argv[1] ?? '') === 'race') {
    $db = Database::connect($argv[2]);
    usleep(100000);
    try {
        $db->prepare("INSERT INTO citas_spa(uid,fecha,hora,estado) VALUES(?,?,?,'PENDIENTE')")
            ->execute(['race-' . $argv[3], $argv[4], '09:00']);
        echo 'reserved';
    } catch (PDOException) { echo 'full'; }
    exit;
}

$storage = sys_get_temp_dir() . '/spa-schedule-test-' . bin2hex(random_bytes(6));
mkdir($storage . '/projects', 0775, true);
$config = ['storage' => $storage, 'database' => $storage . '/central.sqlite', 'url' => 'https://example.test'];
$db = Database::connect($config['database']); Database::migrate($db);
$logs = new LogService($db); $projects = new ProjectService($db, $config, $logs);
$schema = new SchemaService($projects, $logs); $records = new RecordService($schema, $logs);
$project = $projects->create(['name' => 'Spa test']);
$schedule = new SpaScheduleService();
$settings = $schedule->get($project);
check(!$settings['enabled'] && $settings['shifts'][0]['capacity'] === 2 && $settings['shifts'][1]['capacity'] === 3, 'Defaults');
$settings['enabled'] = true; $settings['days'] = [0,1,2,3,4,5,6];
$schedule->save($project, $settings); $schedule->save($project, $settings);
$service = new SpaAppointmentService($db, $config, $logs, $schema, $records, new WebhookService($db));
$landing = new SpaLandingService($db, $config, $records);
$schema->createTable($project, 'servicios', [
    ['name' => 'nombre', 'type' => 'TEXT'], ['name' => 'estado', 'type' => 'TEXT'],
    ['name' => 'almacen_uid', 'type' => 'TEXT'], ['name' => 'costo', 'type' => 'REAL'],
]);
$catalogService = $records->create($project, 'servicios', ['nombre' => 'Masaje', 'estado' => 'ACTIVO', 'costo' => 500]);
$inactiveService = $records->create($project, 'servicios', ['nombre' => 'Inactivo', 'estado' => 'INACTIVO']);
$otherService = $records->create($project, 'servicios', ['nombre' => 'Otra sucursal', 'estado' => 'ACTIVO', 'almacen_uid' => 'other']);
$date = (new DateTimeImmutable('tomorrow', new DateTimeZone($settings['timezone'])))->format('Y-m-d');
$input = ['nombre' => 'Cliente SPA', 'telefono' => '8095550111', 'fecha' => $date, 'turno' => 'turno_1', 'servicio' => 'Masaje', 'servicio_uid' => $catalogService['uid']];
$link = $service->create($project, ['reusable' => true]);
$request = $service->resolve(basename($link['url']));
$publicCatalog = $service->publicServices(['almacen_uid' => 'main'], $project);
check(count($publicCatalog) === 1 && !isset($publicCatalog[0]['costo']), 'Catalog must hide inactive, other warehouse and private fields');
rejected(fn () => $service->complete($request, $project, [...$input, 'servicio_uid' => '']), 'Free text service accepted');
rejected(fn () => $service->complete($request, $project, [...$input, 'servicio_uid' => $inactiveService['uid']]), 'Inactive service accepted');
$first = $service->complete($request, $project, [...$input, 'hora' => '20:00']);
check($first['servicio'] === 'MASAJE', 'Service name must come from catalog');
check($first['hora'] === '09:00', 'Server must derive hour from shift');
$second = $landing->book($project, $input);
check($schedule->availability($project, $date)['shifts'][0]['remaining'] === 0, 'Both public entry points share capacity');
$single = $service->create($project, ['phone' => '8095550111']);
$singleRequest = $service->resolve(basename($single['url']));
rejected(fn () => $service->complete($singleRequest, $project, $input), 'Third morning booking accepted');
check($service->resolve(basename($single['url']))['status'] === 'pending', 'Rejected booking consumes token');
rejected(fn () => $landing->book($project, [...$input, 'turno' => '', 'hora' => '09:00']), 'Forged free hour accepted');
rejected(fn () => $landing->book($project, [...$input, 'fecha' => '2027-02-30']), 'Impossible date accepted');
$records->update($project, 'citas_spa', $first['uid'], ['estado' => 'COMPLETADA']);
check(!$schedule->availability($project, $date)['shifts'][0]['available'], 'Completed appointment freed slot');
$records->update($project, 'citas_spa', $second['uid'], ['estado' => 'CANCELADA']);
$third = $service->complete($singleRequest, $project, $input);
check($service->resolve(basename($single['url']))['status'] === 'used', 'Successful request not consumed');
rejected(fn () => $service->complete($singleRequest, $project, $input), 'Single-use replay accepted');
rejected(fn () => $records->update($project, 'citas_spa', $second['uid'], ['estado' => 'CONFIRMADA']), 'Reactivation overbooks');
for ($i = 0; $i < 3; $i++) $landing->book($project, [...$input, 'turno' => 'turno_2']);
rejected(fn () => $landing->book($project, [...$input, 'turno' => 'turno_2']), 'Fourth afternoon booking accepted');
rejected(fn () => $records->create($project, 'citas_spa', ['fecha' => $date, 'hora' => '09:30', 'estado' => 'PENDIENTE']), 'Generic insert bypasses capacity');
$smaller = $settings; $smaller['shifts'][0]['capacity'] = 1;
rejected(fn () => $schedule->save($project, $smaller), 'Capacity reduced below existing reservations');
check($schedule->get($project)['shifts'][0]['capacity'] === 2, 'Failed settings save not rolled back');
$closed = $settings; $closed['days'] = [(int) (new DateTimeImmutable($date))->modify('+1 day')->format('w')];
rejected(fn () => $schedule->save($project, $closed), 'Existing appointments stranded by closed day');
$bad = $settings; $bad['shifts'][0]['end'] = '15:00';
rejected(fn () => $schedule->save($project, $bad), 'Overlapping shifts accepted');
$other = $projects->create(['name' => 'Other spa']);
$schedule->save($other, $settings);
check($schedule->availability($other, $date)['shifts'][0]['remaining'] === 2, 'Capacity leaked between projects');
$onlyOneDay = $settings; $onlyOneDay['days'] = [(int) (new DateTimeImmutable($date))->modify('+1 day')->format('w')];
$schedule->save($other, $onlyOneDay);
check(!$schedule->availability($other, $date)['shifts'][0]['available'], 'Closed day available');
rejected(fn () => $schedule->bookingTime($other, $input), 'Closed date accepted');
check(!$schedule->availability($project, '2020-01-01')['shifts'][0]['available'], 'Past date available');

$raceDate = (new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d');
$records->create($project, 'citas_spa', ['fecha' => $raceDate, 'hora' => '09:00', 'estado' => 'CONFIRMADA']);
$workers = [];
for ($i = 0; $i < 4; $i++) {
    $process = proc_open([PHP_BINARY, __FILE__, 'race', $project['database_path'], (string) $i, $raceDate], [1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
    $workers[] = [$process, $pipes];
}
$reserved = 0;
foreach ($workers as [$process, $pipes]) {
    $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    check(proc_close($process) === 0 && $errors === '', 'Race worker failed: ' . $errors);
    check(in_array($output, ['reserved','full'], true), 'Unexpected worker response');
    if ($output === 'reserved') $reserved++;
}
check($reserved === 1, 'Concurrent writers overbooked the last slot');
check($schedule->availability($project, $raceDate)['shifts'][0]['remaining'] === 0, 'Race count inconsistent');
$webhooks = new WebhookService($db);
$manualDate = (new DateTimeImmutable($date))->modify('+2 days')->format('Y-m-d');
$manual = $schedule->handle($project, 'appointment-save', ['nombre_cliente' => 'Cliente manual', 'fecha' => $manualDate, 'turno' => 'turno_1', 'estado' => 'PENDIENTE'], $records, $webhooks);
check($manual['hora'] === '09:00' && $manual['origen'] === 'MANUAL', 'Manual booking did not use shift');
$schedule->handle($project, 'appointment-save', ['uid' => $manual['uid'], 'estado' => 'CONFIRMADA'], $records, $webhooks);
$landing->book($project, [...$input, 'fecha' => $manualDate]);
rejected(fn () => $schedule->handle($project, 'appointment-save', ['nombre_cliente' => 'Tercero', 'fecha' => $manualDate, 'turno' => 'turno_1'], $records, $webhooks), 'Panel overbooked public capacity');
check($schedule->availability($project, $manualDate, $manual['uid'])['shifts'][0]['remaining'] === 1, 'Editing counts its own slot');
$schedule->handle($project, 'appointment-save', ['uid' => $manual['uid'], 'nota' => 'Actualizada', 'turno' => 'turno_1'], $records, $webhooks);
check($records->find($project, 'citas_spa', $manual['uid'])['nota'] === 'Actualizada', 'Full shift prevents editing existing booking');
rejected(fn () => $schedule->handle($project, 'appointment-save', ['uid' => $manual['uid'], 'fecha' => $date, 'turno' => 'turno_2'], $records, $webhooks), 'Move accepted into full shift');
check($records->find($project, 'citas_spa', $manual['uid'])['fecha'] === $manualDate, 'Failed move changed appointment');
$schedule->handle($project, 'appointment-delete', ['uid' => $manual['uid']], $records, $webhooks);
check($schedule->availability($project, $manualDate)['shifts'][0]['remaining'] === 1, 'Deleting appointment did not release slot');
// Current-day turn cutoff follows configured timezone, never UTC server time.
$today = (new DateTimeImmutable('now', new DateTimeZone($settings['timezone'])))->format('Y-m-d');
$night = $settings; $night['shifts'][0]['start'] = '00:00'; $night['shifts'][0]['end'] = '00:01';
$schedule->save($other, $night);
check($schedule->availability($other, $today)['shifts'][0]['reason'] === 'Turno iniciado', 'Started turn is offered');
// Legacy hours remain available only when the operator disables shifts.
$night['enabled'] = false; $schedule->save($other, $night);
check($schedule->bookingTime($other, ['fecha' => $date, 'hora' => '10:30']) === '10:30', 'Disabled schedule breaks legacy hours');
$retryLink = $service->create($project, ['phone' => '8095550111']);
$retryRequest = $service->resolve(basename($retryLink['url']));
$retryInput = [...$input, 'fecha' => (new DateTimeImmutable($date))->modify('+3 days')->format('Y-m-d')];
$projectDb = $schema->connection($project);
$projectDb->exec("CREATE TRIGGER fail_spa_test BEFORE INSERT ON citas_spa BEGIN SELECT RAISE(ABORT,'test insert failure'); END");
rejected(fn () => $service->complete($retryRequest, $project, $retryInput), 'Failed database write reported success');
check($service->resolve(basename($retryLink['url']))['status'] === 'pending', 'Database failure consumed link');
$projectDb->exec('DROP TRIGGER fail_spa_test');
$service->complete($retryRequest, $project, $retryInput);
check($service->resolve(basename($retryLink['url']))['status'] === 'used', 'Retry after failed write failed');
// Both server-rendered entry points contain the shared picker and remove the free-time input.
$scheduleSettings = $schedule->get($project); $error = ''; $appointment = null;
$services = $service->publicServices($request, $project);
ob_start(); require dirname(__DIR__) . '/app/Views/spa-registration.php'; $html = ob_get_clean();
check(str_contains($html, 'name="servicio_uid" required') && str_contains($html, '>Masaje</option>') && !str_contains($html, 'name="servicio"'), 'Link must offer catalog selection instead of free text');
check(str_contains($html, 'name="turno"') && !str_contains($html, 'name="hora"'), 'Link view does not use shifts');
$settings = $landing->get($project); $services = []; $booked = false;
ob_start(); require dirname(__DIR__) . '/app/Views/spa-landing.php'; $html = ob_get_clean();
check(str_contains($html, 'name="turno"') && !str_contains($html, 'name="hora"'), 'Landing view does not use shifts');
$flexProject = $projects->create(['name' => 'Flexible turns']);
$flex = $schedule->defaults(); $flex['enabled'] = true; $flex['days'] = [0,1,2,3,4,5,6];
$flex['shifts'] = [
    ['id'=>'custom_c','start'=>'16:00','end'=>'17:00','capacity'=>1],
    ['id'=>'custom_a','start'=>'08:00','end'=>'09:00','capacity'=>1],
    ['id'=>'custom_b','start'=>'09:00','end'=>'10:00','capacity'=>2],
];
$saved = $schedule->save($flexProject, $flex);
check(array_column($saved['shifts'],'id') === ['custom_a','custom_b','custom_c'], 'Custom turns not sorted');
check($saved['shifts'][0]['label'] === '08:00–09:00', 'Custom turn still uses fixed category');
$customBooking = $landing->book($flexProject, [...$input, 'turno'=>'custom_b']);
check($customBooking['hora'] === '09:00', 'Booking did not use custom turn');
check($schedule->availability($flexProject,$date)['shifts'][1]['remaining'] === 1, 'Custom capacity not counted');
$removed = $saved; array_splice($removed['shifts'], 1, 1);
rejected(fn () => $schedule->save($flexProject,$removed), 'Removed turn with future bookings');
$records->update($flexProject,'citas_spa',$customBooking['uid'],['estado'=>'CANCELADA']);
$schedule->save($flexProject,$removed);
rejected(fn () => $landing->book($flexProject,[...$input,'turno'=>'custom_b']), 'Stale removed turn accepted');
$one = $saved; $one['shifts'] = [$saved['shifts'][0]];
check(count($schedule->save($flexProject,$one)['shifts']) === 1, 'Single custom turn rejected');
$empty = $one; $empty['shifts'] = [];
rejected(fn () => $schedule->save($flexProject,$empty), 'Empty schedule accepted');
$overlap = $saved; $overlap['shifts'][2]['start'] = '09:30';
rejected(fn () => $schedule->save($flexProject,$overlap), 'Custom overlap accepted');
$duplicate = $saved; $duplicate['shifts'][2]['id'] = 'custom_a';
rejected(fn () => $schedule->save($flexProject,$duplicate), 'Duplicate turn ids accepted');
// Existing identifiers remain valid after deployment; labels are time ranges.
$legacy = $one; $legacy['shifts'][0]['id'] = 'manana'; $legacy['shifts'][0]['label'] = 'Mañana';
$schedule->save($flexProject,$legacy);
check($schedule->get($flexProject)['shifts'][0]['label'] === '08:00–09:00', 'Legacy label not normalized');
$landing->book($flexProject,[...$input,'turno'=>'manana']);
$historyProject = $projects->create(['name'=>'Past appointments must not block activation']);
$historySettings = $schedule->defaults(); $historySettings['days'] = [0,1,2,3,4,5,6];
$schedule->save($historyProject,$historySettings);
$localNow = new DateTimeImmutable('now', new DateTimeZone($historySettings['timezone']));
if ($localNow->format('H:i') > '00:00') {
    $history = $records->create($historyProject,'citas_spa',['fecha'=>$localNow->format('Y-m-d'),'hora'=>'00:00','estado'=>'PENDIENTE']);
    $historySettings['enabled'] = true;
    check($schedule->save($historyProject,$historySettings)['enabled'], 'Earlier appointment today blocks activation');
    check($records->find($historyProject,'citas_spa',$history['uid'])['hora'] === '00:00', 'Activation modified historical appointment');
}
echo "Spa schedule smoke passed: flexible turns, shared limits, cancellation, replay, tampering, dates, rollback, tenant isolation and concurrent last-slot reservations.\n";
