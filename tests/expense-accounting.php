<?php
require dirname(__DIR__) . '/app/Services/ExpenseAccountingService.php';
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec(<<<'SQL'
CREATE TABLE gastos(id INTEGER PRIMARY KEY,uid TEXT,cantidad REAL,fecha TEXT,hora TEXT,comentario TEXT,metodo_pago TEXT,efectivo REAL DEFAULT 0,transferencia REAL DEFAULT 0,banco_id INTEGER,almacen_id INTEGER,almacen_uid TEXT,created_at TEXT,updated_at TEXT);
 CREATE TABLE cuentas_pagar(id INTEGER PRIMARY KEY,uid TEXT,total REAL,abonado REAL,saldo REAL,estado TEXT,pagos TEXT,fecha_compra TEXT,fecha_vencimiento TEXT,nombre_proveedor TEXT,no_factura TEXT,created_at TEXT,updated_at TEXT);
 CREATE TABLE proveedores(id INTEGER PRIMARY KEY,uid TEXT,nombre TEXT,rnc TEXT); INSERT INTO proveedores VALUES(1,'provider','Proveedor','101000001');
 CREATE TABLE bancos(id INTEGER PRIMARY KEY,uid TEXT,nombre TEXT,saldo REAL,updated_at TEXT); INSERT INTO bancos VALUES(1,'bank','Banco',5000,'');
 CREATE TABLE caja_turnos(id INTEGER PRIMARY KEY,estado TEXT,almacen_uid TEXT,created_at TEXT); INSERT INTO caja_turnos VALUES(1,'abierto','warehouse','2026-09-01');
 CREATE TABLE caja_movimientos(id INTEGER PRIMARY KEY,uid TEXT,turno_id INTEGER,tipo TEXT,monto REAL,descripcion TEXT,created_at TEXT,updated_at TEXT,almacen_uid TEXT);
SQL);
$svc=new App\Services\ExpenseAccountingService();
$actor=['usuario'=>'prueba','estado'=>'ACTIVO','rol'=>'admin'];
$p=['operacion_uid'=>'smoke-operation-001','proveedor_id'=>1,'cuenta_contable_codigo'=>'5201','cantidad'=>1180,'monto_bienes'=>0,'monto_servicios'=>1000,'itbis'=>180,'itbis_retenido'=>180,'isr_retenido'=>100,'tipo_retencion_isr'=>'02','tipo_bienes_servicios'=>'02','rnc'=>'101000001','ncf'=>'B0100000001','fecha'=>'2026-09-01','metodo_pago'=>'TRANSFERENCIA','banco_id'=>1,'almacen_uid'=>'warehouse','condicion_pago'=>'CONTADO'];
$r=$svc->handle($db,'gastos:guardarContable',$p,$actor);
if(!$r['success'])throw new RuntimeException(json_encode($r));
$r2=$svc->handle($db,'gastos:guardarContable',$p,$actor);
if(!$r2['success']||$r2['data']['id']!==$r['data']['id'])throw new RuntimeException('Idempotencia');
if((float)$db->query('SELECT saldo FROM bancos')->fetchColumn()!==4100.0)throw new RuntimeException('Saldo incorrecto');
$d=$svc->handle($db,'gastos:consultarContabilidad',['id'=>$r['data']['id']],$actor);
if(!$d['success']||count($d['data']['asientos'])!==2)throw new RuntimeException('Asientos');
foreach($d['data']['asientos'] as $a){$balance=0;foreach($a['lineas'] as $l)$balance+=$l['debito']-$l['credito'];if(abs($balance)>0.005)throw new RuntimeException('Desbalance');}
$p['operacion_uid']='smoke-operation-002';$p['ncf']='B0100000002';$p['cantidad']=118000;$p['monto_servicios']=100000;$p['itbis']=18000;$p['itbis_retenido']=0;$p['isr_retenido']=0;
$fail=$svc->handle($db,'gastos:guardarContable',$p,$actor);
if($fail['success']||(int)$db->query('SELECT COUNT(*) FROM gastos')->fetchColumn()!==1)throw new RuntimeException('Rollback');
echo "PASS: PHP/Node/PDO registro, asientos, idempotencia y rollback\n";
