<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
use App\Core\Database;
use App\Services\{LogService,ProjectService,SchemaService,RecordService,WebhookService,CustomerRegistrationService};
function check(bool $ok): void { if (!$ok) throw new RuntimeException('Registration assertion failed'); }
$storage = sys_get_temp_dir() . '/registration-test-' . bin2hex(random_bytes(6));
mkdir($storage . '/projects', 0775, true);
$config = ['storage'=>$storage, 'database'=>$storage.'/central.sqlite', 'url'=>'https://example.test'];
$db = Database::connect($config['database']);
Database::migrate($db);
Database::migrate($db);
$logs = new LogService($db);
$projects = new ProjectService($db, $config, $logs);
$schema = new SchemaService($projects, $logs);
$project = $projects->create(['name'=>'QR test']);
$schema->createTable($project, 'clientes', [['name'=>'nombre','type'=>'TEXT'],['name'=>'telefono','type'=>'TEXT'],['name'=>'almacen_id','type'=>'INTEGER'],['name'=>'cedula','type'=>'TEXT'],['name'=>'rnc','type'=>'TEXT']]);
$service = new CustomerRegistrationService($db, $config, $logs, $schema, new RecordService($schema,$logs), new WebhookService($db));
$qr = $service->create($project, ['reusable'=>true,'almacen_id'=>7]);
check($qr['reusable'] && $qr['expires_at'] === null);
$token = basename($qr['url']);
$parent = $service->resolve($token);
try { $service->complete($parent,$project,[]); throw new LogicException('Parent accepted'); } catch (RuntimeException $e) { check($e->getCode() === 409); }
for ($i=0; $i<2; $i++) {
  $child = $service->create($project, ['almacen_id'=>$parent['almacen_id']], 'printed-qr', true);
  $request = $service->resolve(basename($child['url']));
  $customer = $service->complete($request,$project,['nombre'=>'Cliente '.$i,'telefono'=>'809555010'.$i,'documento'=>'001-1234567-8']);
  check($customer['cedula'] === '00112345678' && $customer['rnc'] === '00112345678');
  check((int)$customer['almacen_id'] === 7);
  try { $service->complete($request,$project,['nombre'=>'Repetido','telefono'=>'8095550199','documento'=>'00112345678']); throw new LogicException('Reuse accepted'); } catch (RuntimeException $e) { check($e->getCode() === 409); }
  check($service->resolve($token)['status'] === 'pending');
}
$personal = $service->create($project,['phone'=>'8095550188']);
check(!$personal['reusable'] && $personal['expires_at'] !== null);
$request = $service->resolve(basename($personal['url']));
$records = new RecordService($schema,$logs);
$before = count($records->all($project,'clientes'));
foreach ([[], ['documento'=>''], ['documento'=>'   '], ['documento'=>'---abc'], ['rnc'=>'']] as $documentInput) {
  try {
    $service->complete($request,$project,['nombre'=>'Cliente personal',...$documentInput]);
    throw new LogicException('Empty document accepted');
  } catch (InvalidArgumentException $e) {
    check($e->getMessage() === 'Indique la cédula o RNC del cliente.');
  }
  check($service->resolve(basename($personal['url']))['status'] === 'pending');
  check(count($records->all($project,'clientes')) === $before);
}
$customer = $service->complete($request,$project,['nombre'=>'Cliente personal','rnc'=>'131123456']);
check($customer['cedula'] === '131123456');
check($service->resolve(basename($personal['url']))['status'] === 'used');
echo "Customer registration smoke passed\n";
