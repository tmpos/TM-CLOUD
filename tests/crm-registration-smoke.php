<?php
declare(strict_types=1);
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) require dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
});
use App\Core\Database;
use App\Services\{LogService,ProjectService,SchemaService,RecordService,WebhookService,CustomerRegistrationService};
function check(bool $ok): void { if (!$ok) throw new RuntimeException('CRM registration assertion failed'); }
$storage = sys_get_temp_dir() . '/crm-registration-test-' . bin2hex(random_bytes(6));
mkdir($storage . '/projects', 0775, true);
$config = ['storage'=>$storage, 'database'=>$storage.'/central.sqlite', 'url'=>'https://example.test'];
$db = Database::connect($config['database']); Database::migrate($db); Database::migrate($db);
$logs = new LogService($db); $projects = new ProjectService($db,$config,$logs);
$schema = new SchemaService($projects,$logs); $records = new RecordService($schema,$logs);
$project = $projects->create(['name'=>'CRM test']);
$schema->createTable($project,'usuarios',[['name'=>'nombre','type'=>'TEXT'],['name'=>'estado','type'=>'TEXT']]);
$records->create($project,'usuarios',['uid'=>'seller','nombre'=>'Ana','estado'=>'ACTIVADO']);
$service = new CustomerRegistrationService($db,$config,$logs,$schema,$records,new WebhookService($db));
$qr = $service->create($project,['reusable'=>true,'crm'=>['vendedor_uid'=>'seller']]);
check($qr['crm'] && $qr['reusable']);
$parent = $service->resolve(basename($qr['url']));
$child = $service->create($project,['crm'=>json_decode($parent['crm_json'],true)],'printed-qr',true);
$request = $service->resolve(basename($child['url']));
$input = ['nombre'=>'Cliente CRM','telefono'=>'8095550188','producto_interes'=>'iPhone y cargador','necesidad'=>'Para trabajar','consentimiento'=>'1','vendedor_uid'=>'intruder'];
try { $service->complete($request,$project,[...$input,'consentimiento'=>'']); throw new LogicException('Consent ignored'); } catch (InvalidArgumentException) {}
check(count($records->all($project,'clientes')) === 0);
$customer = $service->complete($request,$project,$input);
$leads = $records->all($project,'crm_prospectos');
check(count($leads) === 1 && $leads[0]['cliente_uid'] === $customer['uid']);
check($leads[0]['vendedor_uid'] === 'seller' && $leads[0]['necesidad'] === 'Para trabajar');
try { $service->complete($request,$project,$input); throw new LogicException('Duplicate accepted'); } catch (RuntimeException $e) { check($e->getCode() === 409); }
check(count($records->all($project,'crm_prospectos')) === 1);
// A failed write rolls back the customer too, and the same token can be retried.
$child2 = $service->create($project,['crm'=>['vendedor_uid'=>'seller']],'printed-qr',true);
$request2 = $service->resolve(basename($child2['url']));
$projectDb = $schema->connection($project);
$projectDb->exec("CREATE TRIGGER fail_lead BEFORE INSERT ON crm_prospectos BEGIN SELECT RAISE(ABORT,'test failure'); END");
try { $service->complete($request2,$project,$input); throw new LogicException('Write failure ignored'); } catch (PDOException) {}
check(count($records->all($project,'clientes')) === 1);
check($service->resolve(basename($child2['url']))['status'] === 'pending');
$projectDb->exec('DROP TRIGGER fail_lead');
$service->complete($request2,$project,$input);
check(count($records->all($project,'crm_prospectos')) === 2);
$records->update($project,'usuarios','seller',['estado'=>'DESACTIVADO']);
try { $service->create($project,['reusable'=>true,'crm'=>['vendedor_uid'=>'seller']]); throw new LogicException('Inactive seller accepted'); } catch (InvalidArgumentException) {}
echo "CRM registration smoke passed: capture, assignment, consent, duplicate protection, rollback and retry.\n";
