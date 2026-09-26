<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
use App\Core\Database;
use App\Services\{LogService,ProjectService,SchemaService,RecordService,WebhookService,CustomerRegistrationService,CustomerRegistrationSettingsService};
function verify(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$storage = sys_get_temp_dir() . '/registration-settings-' . bin2hex(random_bytes(6));
mkdir($storage . '/projects', 0775, true);
$config = ['storage'=>$storage, 'database'=>$storage.'/central.sqlite', 'url'=>'https://example.test'];
$db = Database::connect($config['database']); Database::migrate($db);
$logs = new LogService($db); $projects = new ProjectService($db,$config,$logs);
$schema = new SchemaService($projects,$logs); $records = new RecordService($schema,$logs);
$project = $projects->create(['name'=>'Formulario de prueba']);
$other = $projects->create(['name'=>'Otra empresa']);
$schema->createTable($project,'clientes',array_map(fn($name)=>['name'=>$name,'type'=>'TEXT'],['nombre','telefono','email','cedula','rnc','direccion','tipo_cliente']));
$service = new CustomerRegistrationService($db,$config,$logs,$schema,$records,new WebhookService($db));
$settingsService = new CustomerRegistrationSettingsService($schema);
$original = $settingsService->get($project);
verify($original['fields']['documento']['required'], 'Document required by default');
$link = $service->create($project,['phone'=>'8095550100']);
$request = $service->resolve(basename($link['url']));
$settings = $original;
foreach (['telefono','documento','direccion','tipo_cliente','producto_interes','necesidad'] as $key) $settings['fields'][$key] = ['visible'=>false,'required'=>true];
$settings['fields']['email']['required'] = true;
$settings['fields']['nombre'] = ['visible'=>false,'required'=>false];
$settings['show_logo'] = false;
$saved = $settingsService->save($project,$settings);
verify(!$saved['fields']['documento']['required'] && $saved['fields']['nombre']['required'], 'Hidden fields optional and name locked');
verify($settingsService->get($other) === $original, 'Project isolation');
verify((new CustomerRegistrationSettingsService($schema))->get($project) === $saved, 'Persistence');
try { $service->complete($request,$project,['nombre'=>'Cliente']); throw new LogicException('Required email accepted'); } catch (InvalidArgumentException) {}
verify($service->resolve(basename($link['url']))['status'] === 'pending', 'Validation must not consume token');
verify(count($records->all($project,'clientes')) === 0, 'Validation must not create customer');
$customer = $service->complete($request,$project,['nombre'=>'Cliente','email'=>'test@example.test','documento'=>'00112345678','telefono'=>'bad','direccion'=>'Injected','tipo_cliente'=>'FISCAL']);
verify(($customer['cedula'] ?? '') === '' && ($customer['telefono'] ?? '') === '' && ($customer['direccion'] ?? '') === '', 'Hidden fields discarded');
verify($customer['tipo_cliente'] === 'NORMAL', 'Hidden type defaults to normal');
verify($customer['email'] === 'test@example.test', 'Required email saved');
$settings = $original; $settings['fields']['documento']['required'] = false;
$settingsService->save($project,$settings);
$link2 = $service->create($project,['phone'=>'8095550101']);
$customer2 = $service->complete($service->resolve(basename($link2['url'])),$project,['nombre'=>'Sin documento']);
verify(($customer2['cedula'] ?? '') === '', 'Optional document accepted');
foreach ([['primary_color'=>'red;}</style><script>'],['show_logo'=>'false'],['title'=>''],['fields'=>['unknown'=>['visible'=>true,'required'=>true]]]] as $invalid) {
  try { $settingsService->save($project,$invalid); throw new LogicException('Invalid settings accepted'); } catch (InvalidArgumentException) {}
}
verify($settingsService->get($project) === $settings, 'Invalid settings leave previous config intact');
$schema->createTable($project,'empresa',[['name'=>'nombre','type'=>'TEXT'],['name'=>'logo','type'=>'TEXT']]);
$company = $records->create($project,'empresa',['nombre'=>'Empresa <segura>','logo'=>'javascript:alert(1)']);
verify($settingsService->branding($project)['logo_url'] === '', 'Unsafe logo rejected');
$records->update($project,'empresa',$company['uid'],['logo'=>'https://example.test/logo.png']);
verify($settingsService->branding($project)['logo_url'] === 'https://example.test/logo.png', 'Company logo');
$settings['title'] = '<script>alert(1)</script>';
$settings['fields']['documento']['visible'] = false;
$settings['fields']['email']['required'] = true;
$settings['show_logo'] = false;
$settingsService->save($project,$settings);
$presentation = $service->settings($project);
$request = ['status'=>'pending','phone'=>'']; $error = ''; $_POST = [];
ob_start(); require dirname(__DIR__) . '/app/Views/customer-registration.php'; $html = ob_get_clean();
verify(!str_contains($html,'name="documento"') && str_contains($html,'name="email"'), 'Field rendering');
verify(!str_contains($html,'<script>') && str_contains($html,'&lt;script&gt;'), 'Title escaped');
verify(!str_contains($html,'<img'), 'Logo disabled');
$presentation['settings']['show_logo'] = true;
ob_start(); require dirname(__DIR__) . '/app/Views/customer-registration.php'; $html = ob_get_clean();
verify(str_contains($html,'src="https://example.test/logo.png"'), 'Logo enabled');
// CRM respects optional and hidden fields, while keeping contact consent.
$schema->createTable($project,'usuarios',[['name'=>'nombre','type'=>'TEXT'],['name'=>'estado','type'=>'TEXT']]);
$records->create($project,'usuarios',['uid'=>'seller','nombre'=>'Ana','estado'=>'ACTIVADO']);
$settings = $original;
foreach (['telefono','documento','producto_interes','necesidad'] as $key) $settings['fields'][$key] = ['visible'=>false,'required'=>false];
$settingsService->save($project,$settings);
$crm = $service->create($project,['phone'=>'8095550102','crm'=>['vendedor_uid'=>'seller']]);
$request = $service->resolve(basename($crm['url']));
try { $service->complete($request,$project,['nombre'=>'CRM']); throw new LogicException('Consent bypassed'); } catch (InvalidArgumentException) {}
$service->complete($request,$project,['nombre'=>'CRM','consentimiento'=>'1','producto_interes'=>'Injected']);
$leads = $records->all($project,'crm_prospectos');
verify(count($leads) === 1 && $leads[0]['producto_interes'] === '' && $leads[0]['telefono'] === '', 'CRM hidden fields discarded');
echo "Customer registration settings passed: defaults, persistence, isolation, validation, hidden fields, existing links, logo, escaping and CRM.\n";
