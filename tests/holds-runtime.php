<?php
require_once $argv[1] ?? dirname(__DIR__).'/app/Services/HoldRuntimeService.php';
use App\Services\HoldRuntimeService as Holds;
function check($ok, $message) { if (!$ok) throw new RuntimeException($message); }
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$seller = ['rol'=>'vendedor','permisos'=>['vender']];
$editor = ['rol'=>'vendedor','permisos'=>['vender','ventas-espera-editar']];
$scope = ['warehouse'=>['id'=>1,'uid'=>'a']];
$create = $scope + ['operation'=>'create','data'=>['codigo'=>'HOLD-test','datos'=>'{"cart":[{"nombre":"Producto","cantidad":1}]}']];
$row = Holds::handle($db,$create,$seller,false)['data'];
check(Holds::handle($db,$create,$seller,false)['data']['id']===$row['id'],'Retry duplicated hold');
check(count(Holds::handle($db,$scope+['operation'=>'list'],$editor,false)['data'])===1,'Other user cannot read hold');
check(count(Holds::handle($db,['warehouse'=>['uid'=>'b'],'operation'=>'list'],$editor,false)['data'])===1,'Other warehouse cannot see hold');
check(count(Holds::handle($db,['operation'=>'list'],$editor,false)['data'])===1,'Listing requires a warehouse');
$update = $scope+['operation'=>'update','id'=>$row['id'],'revision'=>0,'data'=>['datos'=>'{"cart":[{"cantidad":2}]}']];
try { Holds::handle($db,$update,$seller,false); throw new Exception('Missing permission accepted'); } catch (RuntimeException $e) { check($e->getCode()===403,'Wrong permission error'); }
$changed = Holds::handle($db,array_replace($update,['warehouse'=>['uid'=>'b']]),$editor,false)['data'];
check($changed['almacen_uid']==='a','Cross-warehouse edit moved original warehouse');
check($changed['revision']===1,'Revision not advanced');
try { Holds::handle($db,$update,$editor,false); throw new Exception('Stale edit accepted'); } catch (RuntimeException $e) { check($e->getCode()===409,'Wrong conflict error'); }
$finish=$scope+['operation'=>'update','id'=>$row['id'],'revision'=>1,'data'=>['estado'=>'RECUPERADA']];
try { Holds::handle($db,$finish,$editor,false); throw new Exception('Seller closed hold'); } catch (RuntimeException $e) { check($e->getCode()===403,'Wrong close permission error'); }
Holds::handle($db,$finish,['rol'=>'cajero'],false);
check(Holds::handle($db,$scope+['operation'=>'list'],$editor,false)['data'][0]['estado']==='RECUPERADA','Recovered hold hidden');
$db->exec("INSERT INTO ventas_pausadas (codigo,estado,datos) VALUES ('old-cancelled','CANCELADA','{broken'),('old-empty',NULL,'{}'),('old-custom','EN ESPERA','{}')");
check(count(Holds::handle($db,['operation'=>'list'],$editor,false)['data'])===4,'Some states or malformed rows hidden');
try { Holds::handle($db,array_replace($finish,['revision'=>2]),['rol'=>'cajero'],false); throw new Exception('Closed hold modified'); } catch (RuntimeException $e) { check($e->getCode()===409,'Wrong closed-hold error'); }
$otherProject = new PDO('sqlite::memory:');
check(!Holds::handle($otherProject,['operation'=>'list'],$editor,false)['data'],'Project isolation failed');
$viewer = ['rol'=>'vendedor','permisos'=>['ventas']];
check(count(Holds::handle($db,['operation'=>'list'],$viewer,false)['data'])===4,'Sales viewer cannot read management list');
$delete = ['operation'=>'delete','id'=>$row['id'],'revision'=>2];
foreach ([$seller, $editor, $viewer] as $unauthorized) {
    try { Holds::handle($db,$delete,$unauthorized,false); throw new Exception('Seller deleted hold'); } catch (RuntimeException $e) { check($e->getCode()===403,'Wrong deletion permission error'); }
}
try { Holds::handle($db,array_replace($delete,['revision'=>0]),['rol'=>'cajero'],false); throw new Exception('Stale delete accepted'); } catch (RuntimeException $e) { check($e->getCode()===409,'Wrong deletion conflict error'); }
check(Holds::handle($db,$delete,['rol'=>'cajero'],false)['data']['estado']==='ELIMINADA','Recovered hold not removed');
check(count(Holds::handle($db,['operation'=>'list'],$viewer,false)['data'])===3,'Deleted hold still listed');
check(Holds::handle($db,$create+['import'=>true],$seller,false)['data']['estado']==='ELIMINADA','Local migration resurrected deleted hold');
try { Holds::handle($db,$create,$seller,false); throw new Exception('Retry recreated deleted hold'); } catch (RuntimeException $e) { check($e->getCode()===409,'Wrong deleted-create error'); }
foreach (Holds::handle($db,['operation'=>'list'],$viewer,false)['data'] as $remaining) {
    Holds::handle($db,['operation'=>'delete','id'=>$remaining['id'],'revision'=>$remaining['revision']],[],true);
}
check(!Holds::handle($db,['operation'=>'list'],$viewer,false)['data'],'Cancelled or legacy rows cannot be deleted');
check((int)$db->query('SELECT count(*) FROM ventas_pausadas')->fetchColumn()===4,'Deletion lost migration tombstones');
echo "PASS: global reads, permissions, conflicts, deletion of every state and migration without resurrection\n";
