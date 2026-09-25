<?php
declare(strict_types=1);
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) require dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
});
use App\Core\Database;
use App\Services\{LogService, ProjectService, SchemaService, RecordService, StorefrontService, WebsiteOrdersService};
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$storage = sys_get_temp_dir() . '/website-orders-' . bin2hex(random_bytes(6));
mkdir($storage . '/projects', 0775, true);
$config = ['storage' => $storage, 'database' => $storage . '/central.sqlite', 'url' => 'https://example.test'];
$db = Database::connect($config['database']); Database::migrate($db);
$logs = new LogService($db); $projects = new ProjectService($db, $config, $logs);
$schema = new SchemaService($projects, $logs); $records = new RecordService($schema, $logs);
$stores = new StorefrontService($db, $config, $projects, $schema, $records);
$project = $projects->create(['name' => 'Orders test']);
$other = $projects->create(['name' => 'Other orders']);
$store = $stores->findForProject($project['uid']);
$foreign = $stores->findForProject($other['uid']);
$insert = $db->prepare('INSERT INTO storefront_orders (uid,order_number,project_uid,storefront_uid,customer_name,customer_phone,delivery_method,payment_provider,currency,total,source,provider_payload,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
foreach ([['one',$project,$store,'web'],['two',$project,$store,'web'],['pos',$project,$store,'pos'],['foreign',$other,$foreign,'web']] as [$uid,$owner,$shop,$source]) {
    $insert->execute([$uid,'WEB-'.$uid,$owner['uid'],$shop['uid'],'Cliente '.$uid,'8095550000','pickup','cash','DOP',150,$source,'private-provider-data','2026-09-24 15:00:00','2026-09-24 15:00:00']);
}
$db->exec("INSERT INTO storefront_order_items (uid,order_uid,product_uid,product_name,unit_price,quantity,line_total,created_at) VALUES ('item','one','product','Servicio',150,1,150,'2026-09-24')");
$service = new WebsiteOrdersService($db, $schema);
$db->prepare('UPDATE storefront_order_items SET metadata=? WHERE uid=?')->execute([json_encode(['source_table'=>'accesorios','warehouse'=>['uid'=>'warehouse','id'=>1,'name'=>'Principal'],'private'=>'hidden']), 'item']);
$list = $service->handle($project, 'list', ['limit'=>1]);
check($list['total'] === 2 && count($list['orders']) === 1 && $list['orders'][0]['uid'] === 'two', 'Pagination or source isolation failed');
check(!isset($list['orders'][0]['provider_payload']), 'Provider payload exposed');
$second = $service->handle($project, 'list', ['limit'=>1,'page'=>2,'project_uid'=>$other['uid']]);
check($second['orders'][0]['uid'] === 'one', 'Page or project isolation failed');
check($service->handle($project, 'list', ['search'=>'Cliente one'])['total'] === 1, 'Customer search failed');
check($service->handle($project, 'list', ['search'=>'%'])['total'] === 0, 'Literal wildcard search failed');
check($service->handle($project, 'list', ['search'=>"' OR 1=1 --"])['total'] === 0, 'Search injection');
check($service->handle($project, 'list', ['status'=>'cancelled'])['total'] === 0, 'Status filter failed');
check($service->handle($project, 'list', ['page'=>999,'limit'=>1])['page'] === 2, 'Out-of-range page');
$detail = $service->handle($project, 'detail', ['uid'=>'one'])['order'];
check(count($detail['items']) === 1 && $detail['items'][0]['product_name'] === 'Servicio', 'Items missing');
check(!isset($detail['provider_payload']), 'Private payment data exposed');
check($detail['items'][0]['source_table'] === 'accesorios' && $detail['items'][0]['warehouse']['uid'] === 'warehouse', 'POS inventory identity missing');
check(!isset($detail['items'][0]['metadata']), 'Unfiltered metadata exposed');
$schema->createTable($project, 'facturas', [['name'=>'no_factura','type'=>'TEXT'],['name'=>'operation_uid','type'=>'TEXT']]);
$records->create($project, 'facturas', ['uid'=>'web_invoice_one','no_factura'=>'F-ONE','operation_uid'=>'web_order_one']);
check($service->handle($project, 'detail', ['uid'=>'one'])['order']['invoice']['no_factura'] === 'F-ONE', 'Linked invoice missing');
foreach (['foreign','pos','missing'] as $uid) {
    try { $service->handle($project, 'detail', ['uid'=>$uid]); throw new LogicException('Unauthorized order exposed'); }
    catch (RuntimeException $e) { check($e->getCode() === 404, 'Wrong detail error'); }
}
try { $service->handle($project, 'list', ['status'=>'bad']); throw new LogicException('Invalid state accepted'); } catch (InvalidArgumentException) {}
check((int)$db->query('SELECT COUNT(*) FROM storefront_orders')->fetchColumn() === 4, 'Read changed orders');
echo "Website orders passed: tenant/source isolation, pagination, search, status filters, details and private payload exclusion.\n";
