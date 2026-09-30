<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
use App\Core\Database;
use App\Services\{LogService,ProjectService,SchemaService};
function verifyRuntime(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$root = dirname(__DIR__);
$storage = sys_get_temp_dir() . '/runtime-secret-' . bin2hex(random_bytes(6));
mkdir($storage . '/projects', 0775, true);
$config = require $root . '/config/app.php';
$config['storage'] = $storage; $config['database'] = $storage . '/central.sqlite';
$config['url'] = 'https://example.test'; $config['realtime']['enabled'] = false;
$db = Database::connect($config['database']); Database::migrate($db);
$logs = new LogService($db); $projects = new ProjectService($db,$config,$logs); $schema = new SchemaService($projects,$logs);
$project = $projects->create(['name'=>'HTTP test']);
$pdo = $schema->connection($project);
$pdo->exec("CREATE TABLE empresa(id INTEGER PRIMARY KEY,uid TEXT,almacen_uid TEXT); INSERT INTO empresa VALUES(1,'warehouse','warehouse'); CREATE TABLE productos(id INTEGER PRIMARY KEY,almacen_uid TEXT); INSERT INTO productos VALUES(1,'old')");
$router = $storage . '/router.php';
file_put_contents($router, '<?php $_SERVER["SCRIPT_NAME"]="/index.php"; $_SERVER["PHP_SELF"]="/index.php"; require ' . var_export($root . '/vendor/autoload.php',true) . '; App\\Core\\App::boot(' . var_export($config,true) . '); Flight::start();');
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error); verifyRuntime((bool)$socket,'No test socket');
$address = stream_socket_get_name($socket,false); fclose($socket);
$process = proc_open([PHP_BINARY,'-S',$address,$router],[0=>['pipe','r'],1=>['file',$storage.'/http.log','a'],2=>['file',$storage.'/http.log','a']],$pipes,$root);
verifyRuntime(is_resource($process),'No test server');
try {
    $ready = false;
    for ($i=0;$i<50;$i++) { $connection=@stream_socket_client('tcp://'.$address,$errno,$error,.1); if ($connection) { fclose($connection);$ready=true;break; } usleep(100000); }
    verifyRuntime($ready,'Test server did not start');
    $request = function (?string $key, string $channel, array $args) use ($address,$project): array {
        $body=json_encode(['action'=>'invoke','data'=>['channel'=>$channel,'args'=>[$args],'authentication'=>'project-secret']]);
        $context=stream_context_create(['http'=>['method'=>'POST','ignore_errors'=>true,'timeout'=>15,'header'=>"Content-Type: application/json\r\n".($key ? "Authorization: Bearer $key\r\n" : ''),'content'=>$body]]);
        $response=file_get_contents('http://'.$address.'/api/'.$project['uid'].'/runtime',false,$context);
        return json_decode($response ?: '{}',true) ?: ['error'=>'Non-JSON response: '.strip_tags(substr($response ?: '',0,300))];
    };
    $args=['almacen_id'=>1,'almacen_uid'=>'warehouse','authentication'=>'project-secret','rol'=>'Administrador'];
    foreach ([null,$project['public_key'],'invalid-test-key'] as $key) {
        $result=$request($key,'almacen:asignarTodosLosDatos',$args);
        verifyRuntime(($result['success']??false)!==true,'Untrusted key granted authority');
        verifyRuntime($pdo->query('SELECT almacen_uid FROM productos')->fetchColumn()==='old','Untrusted request changed data');
    }
    $result=$request($project['secret_key'],'almacen:asignarTodosLosDatos',$args);
    verifyRuntime(($result['success']??false)===true && ($result['data']['registros']??0)===1,'Secret runtime assignment rejected: '.($result['error']??json_encode($result)));
    verifyRuntime($pdo->query('SELECT almacen_uid FROM productos')->fetchColumn()==='warehouse','Assignment not saved');
    $result=$request($project['secret_key'],'documentos:crearEnlaceFirmaRepresentante',['representative_name'=>'Test']);
    verifyRuntime(($result['success']??false)===true && ($result['data']['request']['status']??'')==='pending','Secret signature request rejected');
    $pdo->exec("CREATE TABLE facturas(id INTEGER PRIMARY KEY,uid TEXT,estado_factura TEXT,metodo_pago TEXT,total REAL,efectivo REAL,tarjeta REAL,transferencia REAL,otro TEXT,updated_at TEXT)");
    $pdo->exec("INSERT INTO facturas VALUES(1,'cash-test','PENDIENTE','',720,0,0,0,'{}',NULL)");
    $pdo->exec("UPDATE facturas SET otro='{}'");
    $pdo->prepare('UPDATE facturas SET otro=?')->execute([json_encode(['fiscal_preserved'=>true])]);
    $cashArgs=['factura_id'=>1,'metodo_pago'=>'EFECTIVO','efectivo'=>720,'transferencia'=>0,'tarjeta'=>0,'efectivo_recibido'=>700];
    $result=$request($project['secret_key'],'ventas:cobrarPendiente',$cashArgs);
    verifyRuntime(($result['success']??false)!==true,'Insufficient cash accepted');
    verifyRuntime($pdo->query('SELECT estado_factura FROM facturas WHERE id=1')->fetchColumn()==='PENDIENTE','Rejected collection changed invoice');
    $cashArgs['efectivo_recibido']=1000;
    $result=$request($project['secret_key'],'ventas:cobrarPendiente',$cashArgs);
    verifyRuntime(($result['success']??false)===true,'Cash collection failed: '.json_encode($result));
    $paid=$pdo->query('SELECT * FROM facturas WHERE id=1')->fetch(PDO::FETCH_ASSOC);
    $other=json_decode($paid['otro'],true);
    verifyRuntime((float)$paid['efectivo']===720.0 && (float)$paid['total']===720.0,'Tender inflated revenue');
    verifyRuntime(($other['cobro_caja']['efectivo_recibido']??0)==1000 && ($other['cobro_caja']['cambio']??0)==280 && $other['fiscal_preserved']===true,'Tender or metadata not preserved');
    $result=$request($project['secret_key'],'ventas:cobrarPendiente',$cashArgs);
    verifyRuntime(($result['success']??false)!==true,'Duplicate collection accepted');
    echo "RUNTIME_SECRET_HTTP=OK real endpoint, Secret accepted, Public/missing/invalid denied, payload spoofing denied\n";
} finally { proc_terminate($process); proc_close($process); }
