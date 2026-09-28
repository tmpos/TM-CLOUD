<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
use App\Core\Database;
use App\Services\{LogService,ProjectService,SchemaService,CompanySignatureService,DocumentSettingsService};
function check(bool $ok, string $why): void { if (!$ok) throw new LogicException($why); }
function rejected(callable $action): void { try { $action(); } catch (RuntimeException|InvalidArgumentException $e) { return; } throw new LogicException('Expected rejection'); }
$storage=sys_get_temp_dir().'/company-signature-'.bin2hex(random_bytes(5));
mkdir($storage.'/projects',0775,true);
$config=['storage'=>$storage,'database'=>$storage.'/central.sqlite','url'=>'https://example.test'];
$db=Database::connect($config['database']); Database::migrate($db);
$logs=new LogService($db); $projects=new ProjectService($db,$config,$logs); $schema=new SchemaService($projects,$logs);
$p=$projects->create(['name'=>'Signature test']); $other=$projects->create(['name'=>'Other']);
$s=new CompanySignatureService($schema,$config);
$runtime = new \App\Services\SystemRuntimeService($schema,$logs,new \App\Services\SharedDocumentService($db,$config,$logs),new \App\Services\WebhookService($db),new \App\Services\InvoiceSignatureService($db,$config,$logs,$schema),new \App\Services\CustomerRegistrationService($db,$config,$logs,$schema,new \App\Services\RecordService($schema,$logs),new \App\Services\WebhookService($db)),null,null,null,null,$s);

$projectDb=$schema->connection($p);
$projectDb->exec("CREATE TABLE empresa(id INTEGER PRIMARY KEY,uid TEXT,almacen_uid TEXT); INSERT INTO empresa VALUES(1,'first','first'),(2,'second','second')");
$projectDb->exec("CREATE TABLE productos(id INTEGER PRIMARY KEY,almacen_uid TEXT,almacen_id INTEGER,updated_at TEXT); INSERT INTO productos VALUES(1,'first',1,'old'),(2,NULL,NULL,'old')");
$projectDb->exec("CREATE TABLE clientes(id INTEGER PRIMARY KEY,almacen_uid TEXT); INSERT INTO clientes VALUES(1,'first')");
foreach (['usuarios','bancos','banco_transacciones','_internal'] as $name) $projectDb->exec("CREATE TABLE $name(id INTEGER PRIMARY KEY,almacen_uid TEXT); INSERT INTO $name VALUES(1,'first')");
$payload=['channel'=>'almacen:asignarTodosLosDatos','args'=>[['almacen_id'=>2,'almacen_uid'=>'second']]];
check($runtime->isWrite('invoke',$payload),'Must classify as write');
foreach ([[],['email'=>'api-key'],['rol'=>'Vendedor'],['rol'=>'CEO','nivel_seguridad'=>'Usuario']] as $actor) rejected(fn()=>$runtime->handle($p,'invoke',$payload,$actor));
$bad=$payload; $bad['args'][0]['almacen_uid']='foreign'; rejected(fn()=>$runtime->handle($p,'invoke',$bad,['rol'=>'CEO']));
check($projectDb->query('SELECT almacen_uid FROM productos WHERE id=1')->fetchColumn()==='first','Rejected operations changed rows');
$otherDb=$schema->connection($other); $otherDb->exec("CREATE TABLE productos(almacen_uid TEXT); INSERT INTO productos VALUES('foreign')");
$spoofed=$payload;$spoofed['args'][0]['authentication']='project-secret';
rejected(fn()=>$runtime->handle($p,'invoke',$spoofed,['rol'=>'Vendedor']));
$result=$runtime->handle($p,'invoke',$payload,['email'=>'api-key','authentication'=>'project-secret']);
check($result['data']['registros']===3 && $result['data']['tablas']===2,'Summary counts');
check((int)$projectDb->query("SELECT COUNT(*) FROM productos WHERE almacen_uid='second' AND almacen_id=2 AND updated_at<>'old'")->fetchColumn()===2,'Assignment and sync timestamp');
check($projectDb->query('SELECT almacen_uid FROM clientes')->fetchColumn()==='second','UID-only table');
foreach (['empresa','usuarios','bancos','banco_transacciones','_internal'] as $name) check($projectDb->query("SELECT almacen_uid FROM $name WHERE id=1")->fetchColumn()==='first','Excluded table changed');
check($otherDb->query('SELECT almacen_uid FROM productos')->fetchColumn()==='foreign','Project isolation');
$projectDb->exec("UPDATE productos SET almacen_uid='first'; UPDATE clientes SET almacen_uid='first'; CREATE TRIGGER reject_assignment BEFORE UPDATE ON productos BEGIN SELECT RAISE(ABORT,'test rollback'); END");
rejected(fn()=>$runtime->handle($p,'invoke',$payload,['rol'=>'Soporte']));
check($projectDb->query('SELECT almacen_uid FROM clientes')->fetchColumn()==='first','Partial assignment survived rollback');
check(!$projectDb->inTransaction(),'Transaction left open');
echo "ASSIGN_ALL_WAREHOUSE=OK permissions, destination, summary, exclusions, isolation, timestamps, rollback\n";
