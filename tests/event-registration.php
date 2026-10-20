<?php
declare(strict_types=1);

require __DIR__ . '/../app/Core/Support.php';
require __DIR__ . '/../app/Services/EventRegistrationService.php';

use App\Services\EventRegistrationService;

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA foreign_keys=ON');
$db->exec('CREATE TABLE projects(uid TEXT PRIMARY KEY, slug TEXT NOT NULL)');
$db->exec("INSERT INTO projects(uid,slug) VALUES('project-a','a'),('project-b','b')");
$service = new EventRegistrationService($db, ['url' => 'https://example.test']);
$a = ['uid' => 'project-a', 'slug' => 'a'];
$b = ['uid' => 'project-b', 'slug' => 'b'];
$event = $service->save($a, [
    'title' => 'Charla ambiental', 'speaker' => 'Ana', 'event_date' => date('Y-m-d', strtotime('+7 days')),
    'event_time' => '10:30', 'venue' => 'Hotel', 'room' => 'Sala 3',
    'capacity' => 1, 'published' => true, 'registration_open' => true,
]);
if ($event['capacity'] !== 1 || count($service->list($a, true)) !== 1 || count($service->list($b)) !== 0) throw new RuntimeException('Evento o aislamiento incorrecto');
$service->page($a, ['title' => 'Eventos', 'introduction' => 'Agenda', 'color' => '#123456', 'enabled' => true]);
if (!$service->page($a)['enabled'] || $service->page($b)['enabled']) throw new RuntimeException('Página no aislada');
$page = $service->page($a);
$events = $service->list($a, true);
$registered = false;
$error = '';
$website = [
    'store_name' => 'Organizador', 'primary_color' => '#3346a8', 'accent_color' => '#ef9b54',
    'background_color' => '#f9f8f5', 'surface_color' => '#ececfa', 'text_color' => '#20234a',
    'web_editor' => ['design' => ['preset' => 'events', 'hero' => 'center', 'width' => 1200]],
];
ob_start(); require __DIR__ . '/../app/Views/events-landing.php'; $html = ob_get_clean();
if (!str_contains($html, 'data-template="events"') || !str_contains($html, '--brand:#3346a8')
    || !str_contains($html, '<form method="post"') || !str_contains($html, 'Charla ambiental')) {
    throw new RuntimeException('La landing no aplica la plantilla o perdió el formulario de registro');
}
$website['web_editor']['design']['preset'] = 'boutique';
$website['primary_color'] = '#7c3f58';
ob_start(); require __DIR__ . '/../app/Views/events-landing.php'; $html = ob_get_clean();
if (!str_contains($html, 'data-template="boutique"') || !str_contains($html, '--brand:#7c3f58')) {
    throw new RuntimeException('La landing no refleja el cambio de plantilla publicada');
}
$website['web_editor']['design']['preset'] = 'classic';
ob_start(); require __DIR__ . '/../app/Views/events-landing.php'; $html = ob_get_clean();
if (!str_contains($html, '--brand:#123456')) throw new RuntimeException('La plantilla clásica perdió el color de eventos existente');

$first = $service->register($a, ['event_uid' => $event['uid'], 'name' => 'Participante', 'email' => 'uno@example.test', 'consent' => '1'], true);
if ($service->list($a)[0]['remaining'] !== 0) throw new RuntimeException('Cupo no reservado');
foreach (['uno@example.test', 'dos@example.test'] as $email) {
    try {
        $service->register($a, ['event_uid' => $event['uid'], 'name' => 'Otro', 'email' => $email, 'consent' => '1'], true);
        throw new RuntimeException('Se permitió duplicar o superar cupos');
    } catch (RuntimeException $error) {
        if (!in_array($error->getCode(), [409], true)) throw $error;
    }
}
$service->attendance($a, $first['uid'], 'CANCELADO');
$second = $service->register($a, ['event_uid' => $event['uid'], 'name' => 'Otra', 'email' => 'dos@example.test', 'consent' => '1'], true);
if ($service->list($a)[0]['registered'] !== 1) throw new RuntimeException('Cancelación no liberó el cupo');
$service->attendance($a, $second['uid'], 'ASISTIO');
if ($service->participants($a, $event['uid'])[0]['status'] !== 'ASISTIO') throw new RuntimeException('Asistencia no guardada');
try { $service->participants($b, $event['uid']); throw new RuntimeException('Se filtraron participantes de otro proyecto'); }
catch (RuntimeException $error) { if ($error->getCode() !== 404) throw $error; }
$service->archive($a, $event['uid']);
if ($service->list($a, true) !== []) throw new RuntimeException('Evento archivado visible');
echo "event-registration: ok\n";
