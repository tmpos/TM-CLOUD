<?php
declare(strict_types=1);

require __DIR__ . '/../app/Core/Support.php';
require __DIR__ . '/../app/Core/Auth.php';
require __DIR__ . '/../app/Core/Http.php';
require __DIR__ . '/../app/Services/LogService.php';
require __DIR__ . '/../app/Services/CredentialCipher.php';
require __DIR__ . '/../app/Services/MailService.php';
require __DIR__ . '/../app/Services/EventRegistrationService.php';

use App\Services\EventRegistrationService;
use App\Services\LogService;
use App\Services\MailService;

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA foreign_keys=ON');
$db->exec('CREATE TABLE projects(uid TEXT PRIMARY KEY, slug TEXT NOT NULL)');
$db->exec("INSERT INTO projects(uid,slug) VALUES('project-a','a'),('project-b','b')");
$service = new EventRegistrationService($db, ['url' => 'https://example.test']);
$a = ['uid' => 'project-a', 'slug' => 'a'];
$b = ['uid' => 'project-b', 'slug' => 'b'];
$event = $service->save($a, [
    'title' => 'Charla ambiental', 'image' => 'fil_event123', 'speaker' => 'Ana', 'event_date' => date('Y-m-d', strtotime('+7 days')),
    'event_time' => '10:30', 'venue' => 'Hotel', 'room' => 'Sala 3',
    'map_url' => 'https://www.openstreetmap.org/?mlat=18.4861&mlon=-69.9312#map=15/18.4861/-69.9312',
    'capacity' => 1, 'published' => true, 'registration_open' => true,
]);
if ($event['capacity'] !== 1 || !str_contains($event['map_url'], 'openstreetmap.org') || count($service->list($a, true)) !== 1 || count($service->list($b)) !== 0) throw new RuntimeException('Evento, mapa o aislamiento incorrecto');
$service->page($a, ['title' => 'Eventos', 'introduction' => 'Agenda', 'color' => '#123456', 'enabled' => true,
    'eyebrow' => 'Encuentros especiales', 'hero_image' => 'fil_hero123', 'about_title' => 'Sobre nosotros',
    'about_text' => 'Aprende con expertos', 'highlights' => "Ponencias\nNetworking", 'cta_label' => 'Reserva tu lugar']);
if (!$service->page($a)['enabled'] || $service->page($b)['enabled']) throw new RuntimeException('Página no aislada');
$page = $service->page($a);
$events = $service->list($a, true);
$registered = false;
$error = '';
$config = ['url' => 'https://example.test'];
$website = [
    'store_name' => 'Organizador', 'primary_color' => '#3346a8', 'accent_color' => '#ef9b54',
    'background_color' => '#f9f8f5', 'surface_color' => '#ececfa', 'text_color' => '#20234a',
    'web_editor' => ['design' => ['preset' => 'events', 'hero' => 'center', 'width' => 1200]],
];
ob_start(); require __DIR__ . '/../app/Views/events-landing.php'; $html = ob_get_clean();
if (!str_contains($html, 'data-template="events"') || !str_contains($html, '--brand:#3346a8')
    || !str_contains($html, '<form method="post"') || !str_contains($html, 'Charla ambiental')
    || !str_contains($html, '/storage/fil_hero123') || !str_contains($html, '/storage/fil_event123')
    || !str_contains($html, 'Reserva tu lugar') || !str_contains($html, 'Networking')
    || !str_contains($html, 'www.openstreetmap.org/export/embed.html')) {
    throw new RuntimeException('La landing no aplica la plantilla o perdió el formulario de registro');
}
$website['hero_background_mode'] = 'image';
$website['hero_images'] = ['https://example.test/cover.jpg'];
ob_start(); require __DIR__ . '/../app/Views/events-landing.php'; $imageHtml = ob_get_clean();
if (!str_contains($imageHtml, 'class="hero-cover"') || !str_contains($imageHtml, 'https://example.test/cover.jpg')) throw new RuntimeException('La portada elegida no aparece en eventos');
$website['hero_background_mode'] = 'color';
ob_start(); require __DIR__ . '/../app/Views/events-landing.php'; $colorHtml = ob_get_clean();
if (str_contains($colorHtml, 'class="hero-cover"')) throw new RuntimeException('El modo color muestra una portada anterior');
$website['web_editor']['design']['preset'] = 'boutique';
$website['primary_color'] = '#7c3f58';
ob_start(); require __DIR__ . '/../app/Views/events-landing.php'; $html = ob_get_clean();
if (!str_contains($html, 'data-template="boutique"') || !str_contains($html, '--brand:#7c3f58')) {
    throw new RuntimeException('La landing no refleja el cambio de plantilla publicada');
}
$website['web_editor']['design']['preset'] = 'classic';
ob_start(); require __DIR__ . '/../app/Views/events-landing.php'; $html = ob_get_clean();
if (!str_contains($html, '--brand:#123456')) throw new RuntimeException('La plantilla clásica perdió el color de eventos existente');

$migrated = new EventRegistrationService($db, $config);
if ($migrated->page($a)['hero_image'] !== 'fil_hero123' || $migrated->list($a)[0]['image'] !== 'fil_event123') throw new RuntimeException('La migración alteró las imágenes');
try { $service->page($a, ['title' => 'X', 'color' => '#123456', 'hero_image' => 'https://evil.test/x']); throw new RuntimeException('Aceptó imagen ajena'); }
catch (InvalidArgumentException) { /* Solo se admiten imágenes del almacenamiento del proyecto. */ }

$first = $service->register($a, ['event_uid' => $event['uid'], 'name' => 'Participante', 'email' => 'uno@example.test', 'consent' => '1'], true);
if ($first['email_status'] !== 'unavailable' || $first['event_title'] !== 'Charla ambiental' || $first['map_url'] !== $event['map_url']) throw new RuntimeException('Respuesta de inscripción incompleta');
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
$removable = $service->save($a, [
    'title' => 'Evento temporal', 'speaker' => 'Ana', 'event_date' => date('Y-m-d', strtotime('+8 days')),
    'event_time' => '10:30', 'venue' => 'Hotel', 'capacity' => 3, 'published' => true,
]);
$attendee = $service->register($a, ['event_uid' => $removable['uid'], 'name' => 'Temporal', 'email' => 'temporal@example.test'], false);
$service->attendance($a, $attendee['uid'], 'CANCELADO');
try { $service->delete($b, $removable['uid']); throw new RuntimeException('Se eliminó un evento de otro proyecto'); }
catch (RuntimeException $error) { if ($error->getCode() !== 404) throw $error; }
if (count($service->list($a, true)) !== 1) throw new RuntimeException('La eliminación cruzada alteró el evento');
$removed = $service->handle($a, 'archive', ['uid' => $removable['uid'], 'permanent' => true]);
if (!$removed['deleted'] || $removed['participants_deleted'] !== 1 || count($service->list($a, true)) !== 0) throw new RuntimeException('La eliminación no borró el evento y sus participantes');
try { $service->participants($a, $removable['uid']); throw new RuntimeException('Quedaron participantes de un evento eliminado'); }
catch (RuntimeException $error) { if ($error->getCode() !== 404) throw $error; }

try {
    $service->save($a, ['title' => 'Mapa inválido', 'speaker' => 'Ana', 'event_date' => date('Y-m-d', strtotime('+10 days')),
        'event_time' => '10:30', 'venue' => 'Hotel', 'map_url' => 'https://evil.example/maps']);
    throw new RuntimeException('Aceptó un mapa ajeno');
} catch (InvalidArgumentException) { /* Solo enlaces aprobados. */ }

$db->exec('CREATE TABLE project_logs (uid TEXT, project_uid TEXT, user_uid TEXT, action TEXT, table_name TEXT, record_uid TEXT, old_data TEXT, new_data TEXT, ip_address TEXT, user_agent TEXT, created_at TEXT)');
$db->exec("CREATE TABLE mail_queue (id INTEGER PRIMARY KEY AUTOINCREMENT, uid TEXT UNIQUE NOT NULL, project_uid TEXT NOT NULL, template TEXT NOT NULL, recipient TEXT NOT NULL, payload TEXT, status TEXT NOT NULL DEFAULT 'pending', attempts INTEGER NOT NULL DEFAULT 0, max_attempts INTEGER NOT NULL DEFAULT 5, message_id TEXT, last_error TEXT, next_attempt_at TEXT, sent_at TEXT, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)");
$db->exec("CREATE TABLE mail_settings (id INTEGER PRIMARY KEY, enabled INTEGER NOT NULL DEFAULT 0, host TEXT NOT NULL DEFAULT '', port INTEGER NOT NULL DEFAULT 587, encryption TEXT NOT NULL DEFAULT 'tls', username TEXT NOT NULL DEFAULT '', password_encrypted TEXT NOT NULL DEFAULT '', from_email TEXT NOT NULL DEFAULT '', from_name TEXT NOT NULL DEFAULT '', reply_to TEXT NOT NULL DEFAULT '', updated_at TEXT NOT NULL DEFAULT '')");
$mail = new MailService($db, ['enabled' => false], new LogService($db), sys_get_temp_dir());
$serviceWithMail = new EventRegistrationService($db, $config, null, $mail);
$mailEvent = $serviceWithMail->save($a, [
    'title' => 'Encuentro con mapa', 'speaker' => 'Lucía', 'event_date' => date('Y-m-d', strtotime('+11 days')),
    'event_time' => '14:00', 'venue' => 'Centro Cultural', 'room' => 'Sala A',
    'map_url' => 'https://www.openstreetmap.org/?mlat=18.4861&mlon=-69.9312',
    'capacity' => 5, 'published' => true,
]);
$confirmed = $serviceWithMail->register($a, ['event_uid' => $mailEvent['uid'], 'name' => 'Lucía <script>', 'email' => 'lucia@example.test', 'consent' => '1'], true);
if ($confirmed['status'] !== 'CONFIRMADO' || $confirmed['email_status'] !== 'failed') throw new RuntimeException('Fallo SMTP revirtió inscripción o no informó estado');
$registration = ['name' => 'Lucía <script>', 'email' => 'lucia@example.test'] + $confirmed;
$registered = true;
$events = $serviceWithMail->list($a, true);
ob_start(); require __DIR__ . '/../app/Views/events-landing.php'; $thanksHtml = ob_get_clean();
if (!str_contains($thanksHtml, '¡Gracias por inscribirte') || !str_contains($thanksHtml, 'Centro Cultural')
    || !str_contains($thanksHtml, 'confirmación por correo aún no pudo enviarse') || str_contains($thanksHtml, '<script>')) {
    throw new RuntimeException('El agradecimiento no muestra la inscripción o no escapa los datos');
}
$mailJob = $db->query("SELECT template,recipient,payload,status FROM mail_queue LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$payload = json_decode($mailJob['payload'], true);
if ($mailJob['template'] !== 'event_registration' || $mailJob['recipient'] !== 'lucia@example.test' || $payload['map_url'] !== $mailEvent['map_url']) throw new RuntimeException('Correo de evento incompleto');
try {
    $serviceWithMail->register($a, ['event_uid' => $mailEvent['uid'], 'name' => 'Duplicada', 'email' => 'lucia@example.test', 'consent' => '1'], true);
    throw new RuntimeException('Duplicó la inscripción');
} catch (RuntimeException $error) { if ($error->getCode() !== 409) throw $error; }
if ((int) $db->query('SELECT COUNT(*) FROM mail_queue')->fetchColumn() !== 1) throw new RuntimeException('Duplicó el correo');
$render = new ReflectionMethod(MailService::class, 'render');
[, $mailHtml, $mailText] = $render->invoke($mail, 'event_registration', $payload);
if (!str_contains($mailHtml, 'Centro Cultural') || !str_contains($mailHtml, 'Ver ubicación en el mapa') || !str_contains($mailText, $mailEvent['map_url']) || str_contains($mailHtml, '<script>')) throw new RuntimeException('Plantilla del correo incompleta o insegura');
echo "event-registration: ok\n";
