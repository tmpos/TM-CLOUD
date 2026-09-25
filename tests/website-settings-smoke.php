<?php
declare(strict_types=1);
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) require dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
});
use App\Core\Database;
use App\Services\{LogService, ProjectService, SchemaService, RecordService, StorefrontService, StorefrontSettingsService};
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$storage = sys_get_temp_dir() . '/website-settings-' . bin2hex(random_bytes(6));
mkdir($storage . '/projects', 0775, true);
$config = ['storage' => $storage, 'database' => $storage . '/central.sqlite', 'url' => 'https://example.test'];
$db = Database::connect($config['database']); Database::migrate($db);
$logs = new LogService($db); $projects = new ProjectService($db, $config, $logs);
$schema = new SchemaService($projects, $logs); $records = new RecordService($schema, $logs);
$stores = new StorefrontService($db, $config, $projects, $schema, $records);
$settings = new StorefrontSettingsService($stores);
$project = $projects->create(['name' => 'Website test']);
$other = $projects->create(['name' => 'Other website']);
$original = $settings->handle($project, 'get');
$foreign = $settings->handle($other, 'get');
check(isset($original['settings']['slug'], $original['warehouses'], $original['tables']), 'Incomplete settings');
$saved = $settings->handle($project, 'save', ['enabled' => false, 'show_stock' => false, 'show_prices' => false, 'hero_title' => 'Nuevo título', 'hero_images' => "https://example.test/a.jpg\nhttps://example.test/b.jpg", 'project_uid' => $other['uid']]);
check(!(bool)$saved['settings']['enabled'] && !(bool)$saved['settings']['show_stock'] && !(bool)$saved['settings']['show_prices'], 'False checkboxes must remain off');
check(count($saved['settings']['hero_images']) === 2, 'Carousel lost');
check($saved['settings']['hero_title'] === 'Nuevo título', 'Title not saved');
check($saved['settings']['store_name'] === $original['settings']['store_name'], 'Omitted fields reset');
check($settings->handle($other, 'get')['settings']['hero_title'] === $foreign['settings']['hero_title'], 'Cross-project write');
$again = $settings->handle($project, 'save', ['enabled' => true, 'show_prices' => true, 'primary_color' => '#123456']);
check((bool)$again['settings']['enabled'] && (bool)$again['settings']['show_prices'], 'Checkboxes cannot be re-enabled');
check(!(bool)$again['settings']['show_stock'] && $again['settings']['hero_title'] === 'Nuevo título', 'Partial update resets settings');
check($stores->findForProject($project['uid'])['primary_color'] === '#123456', 'Dashboard uses different settings');
try { $settings->handle($project, 'save', ['slug' => $foreign['settings']['slug']]); throw new LogicException('Duplicate slug accepted'); }
catch (InvalidArgumentException) {}
$pageText = "Nuestra historia\n<script>alert('unsafe')</script>";
$saved = $settings->handle($project, 'save', ['page_about' => $pageText, 'page_terms' => 'Condiciones propias', 'contact_hours' => 'Lunes a viernes']);
check($saved['settings']['page_about'] === $pageText, 'Page content not saved');
$saved = $settings->handle($project, 'save', ['page_contact' => 'Escríbenos']);
check($saved['settings']['page_about'] === $pageText && $saved['settings']['page_terms'] === 'Condiciones propias', 'Partial update erased pages');
check($settings->handle($other, 'get')['settings']['page_about'] === '', 'Pages leaked across tenants');
try { $settings->handle($project, 'save', ['page_terms' => str_repeat('a', 16001)]); throw new LogicException('Oversized page accepted'); } catch (InvalidArgumentException) {}
try { $settings->handle($project, 'save', ['page_terms' => ['invalid']]); throw new LogicException('Invalid page accepted'); } catch (InvalidArgumentException) {}
$store = $saved['settings'];
$mapUrl = 'https://www.google.com/maps/embed?pb=!1m18!2m3';
$saved = $settings->handle($project, 'save', ['map_embed_url' => '<iframe src="' . $mapUrl . '" onload="alert(1)"></iframe>']);
check($saved['settings']['map_embed_url'] === $mapUrl, 'Map URL not normalized');
$saved = $settings->handle($project, 'save', ['page_contact' => 'Visítanos']);
check($saved['settings']['map_embed_url'] === $mapUrl, 'Partial update erased map');
check($settings->handle($other, 'get')['settings']['map_embed_url'] === '', 'Map leaked across tenants');
foreach (['javascript:alert(1)', 'https://www.google.com.evil.test/maps/embed?pb=x', 'https://maps.app.goo.gl/test', 'https://www.google.com/maps/embed', 'https://user@www.google.com/maps/embed?pb=x'] as $invalidMap) {
    try { $settings->handle($project, 'save', ['map_embed_url' => $invalidMap]); throw new LogicException('Unsafe map accepted'); } catch (InvalidArgumentException) {}
}
$store = $saved['settings'];
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
foreach (\App\Services\StorefrontPagesService::PAGES as $page => $definition) {
    ob_start(); require dirname(__DIR__).'/app/Views/storefront-information.php'; $html = ob_get_clean();
    check(str_contains($html, '/pages/contacto') && str_contains($html, '<h1>'), 'Incomplete public page');
    check(!str_contains($html, "<script>alert('unsafe')</script>"), 'Unescaped page content');
    if ($page === 'nosotros') check(str_contains($html, '&lt;script&gt;'), 'Content not rendered');
    check(str_contains($html, 'title="Ubicación de ') === ($page === 'contacto'), 'Map must render only on Contacto');
}
$clearedMap = $settings->handle($project, 'save', ['map_embed_url' => '']);
check($clearedMap['settings']['map_embed_url'] === '', 'Cannot clear map');
$saved = $settings->handle($project, 'save', ['page_about' => '']);
check($saved['settings']['page_about'] === '', 'Cannot clear page');
echo "Website settings passed: settings, pages, partial updates, tenant isolation, validation and safe rendering.\n";

// Contact forms follow the selected warehouse's real system mode.
$fields = static fn(array $names): array => array_map(static fn($name) => ['name' => $name, 'type' => 'TEXT'], $names);
$schema->createTable($project, 'empresa', $fields(['nombre', 'almacen_id']));
$main = $records->create($project, 'empresa', ['nombre' => 'Spa', 'almacen_id' => '1']);
$branch = $records->create($project, 'empresa', ['nombre' => 'Tienda', 'almacen_id' => '2']);
$schema->createTable($project, 'apariencia_almacen', $fields(['modo_tienda', 'almacen_uid', 'almacen_id']));
$records->create($project, 'apariencia_almacen', ['modo_tienda' => 'spa', 'almacen_uid' => $main['uid'], 'almacen_id' => '1']);
$records->create($project, 'apariencia_almacen', ['modo_tienda' => 'general', 'almacen_uid' => $branch['uid'], 'almacen_id' => '2']);
$store = $stores->findForProject($project['uid']);
check($store['is_spa'], 'Default warehouse must detect spa');
check(!$stores->isSpa([...$store, 'warehouse_uid' => $branch['uid']]), 'Mode leaked to another warehouse');
check(!$stores->isSpa([...$store, 'warehouse_uid' => 'missing']), 'Missing warehouse detected as spa');
$scheduler = new \App\Services\SpaScheduleService();
$schedule = $scheduler->defaults(); $schedule['enabled'] = true; $schedule['days'] = [0,1,2,3,4,5,6];
$schedule['shifts'] = [['id' => 'one', 'start' => '09:00', 'end' => '10:00', 'capacity' => 1]];
$schedule = $scheduler->save($project, $schedule);
$schema->createTable($project, 'servicios', $fields(['nombre', 'estado', 'almacen_uid']));
$service = $records->create($project, 'servicios', ['nombre' => 'Masaje', 'estado' => 'ACTIVO', 'almacen_uid' => $main['uid']]);
$foreignService = $records->create($project, 'servicios', ['nombre' => 'Otro', 'estado' => 'ACTIVO', 'almacen_uid' => $branch['uid']]);
$appointments = new \App\Services\SpaAppointmentService($db, $config, $logs, $schema, $records, new \App\Services\WebhookService($db));
$request = ['uid' => 'store:' . $store['uid'], 'status' => 'pending', 'reusable' => true, 'almacen_uid' => $main['uid'], 'almacen_id' => 1];
$services = $appointments->publicServices($request, $project);
check(array_column($services, 'uid') === [$service['uid']], 'Contact exposes foreign services');
$base = '/store/' . $store['slug']; $bookingToken = 'test-token';
ob_start(); require dirname(__DIR__).'/app/Views/storefront-contact-form.php'; $html = ob_get_clean();
check(str_contains($html, 'name="servicio_uid"') && str_contains($html, 'id="spa-turno"') && !str_contains($html, 'name="cedula"'), 'Spa form missing service or shift');
$input = ['nombre' => 'Cliente prueba', 'telefono' => '8095550123', 'servicio_uid' => $service['uid'], 'fecha' => date('Y-m-d', strtotime('+2 days')), 'turno' => 'one'];
try { $appointments->complete($request, $project, [...$input, 'servicio_uid' => $foreignService['uid']]); throw new LogicException('Foreign service accepted'); } catch (InvalidArgumentException) {}
$appointment = $appointments->complete($request, $project, $input);
check($appointment['hora'] === '09:00' && $appointment['servicio'] === 'MASAJE' && $appointment['estado'] === 'PENDIENTE', 'Contact appointment wrong');
check(!$scheduler->availability($project, $input['fecha'])['shifts'][0]['available'], 'Contact does not consume shared capacity');
try { $appointments->complete($request, $project, $input); throw new LogicException('Full slot accepted'); } catch (RuntimeException) {}
$store['is_spa'] = false;
ob_start(); require dirname(__DIR__).'/app/Views/storefront-contact-form.php'; $html = ob_get_clean();
check(str_contains($html, 'name="cedula"') && str_contains($html, 'name="email"') && !str_contains($html, 'name="servicio_uid"'), 'Customer form wrong');
echo "Contact forms passed: warehouse mode, public services, booking, shared capacity and customer registration fields.\n";
