<?php

declare(strict_types=1);

// Covers SystemRuntimeService::guardarVenta: idempotency by operation uid,
// IMEI/serial availability, server-side NCF assignment and rollback.
// Run: php tests/pos-sale-atomic.php

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

use App\Services\SystemRuntimeService;
use App\Services\WebhookService;

function checkSale(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
}

function expectSaleError(callable $fn, string $needle): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        checkSale(str_contains($e->getMessage(), $needle), "se esperaba '$needle', llego '{$e->getMessage()}'");
        return;
    }
    throw new RuntimeException("FAIL: se esperaba un error con '$needle'");
}

$dbFile = tempnam(sys_get_temp_dir(), 'tmpos-sale-') . '.sqlite';
$db = new PDO('sqlite:' . $dbFile);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec('PRAGMA busy_timeout = 5000');
$db->exec('PRAGMA journal_mode = WAL');
$db->exec(<<<'SQL'
CREATE TABLE facturas(id INTEGER PRIMARY KEY AUTOINCREMENT, uid TEXT UNIQUE, no_factura TEXT, ncf TEXT, comprobante_id INTEGER, total REAL, otro TEXT, almacen_uid TEXT, created_at TEXT, updated_at TEXT);
CREATE TABLE cuentas_cobrar(id INTEGER PRIMARY KEY AUTOINCREMENT, uid TEXT UNIQUE, no_factura TEXT, total REAL, created_at TEXT, updated_at TEXT);
CREATE TABLE comprobantes_fiscales(id INTEGER PRIMARY KEY AUTOINCREMENT, tipo TEXT, nombre TEXT, prefijo TEXT, secuencia_actual INTEGER, secuencia_hasta INTEGER, updated_at TEXT);
INSERT INTO comprobantes_fiscales(id,tipo,nombre,prefijo,secuencia_actual,secuencia_hasta) VALUES (1,'B02','Consumo','B02',5,99999999),(2,'E32','Consumo e-CF','E32',1,9999999999),(3,'B01','Credito fiscal','B01',9,9),(4,'SIN','Sin comprobante','',1,99999999);
CREATE TABLE imei(id INTEGER PRIMARY KEY AUTOINCREMENT, uid TEXT UNIQUE, imei TEXT, estado TEXT, no_factura TEXT, updated_at TEXT);
INSERT INTO imei(id,uid,imei,estado) VALUES (1,'i1','350000000000001','DISPONIBLE'),(2,'i2','350000000000002','VENDIDO'),(3,'i3','350000000000003',NULL),(4,'i4','350000000000004','DISPONIBLE');
CREATE TABLE serial(id INTEGER PRIMARY KEY AUTOINCREMENT, uid TEXT UNIQUE, serial TEXT, estado TEXT, updated_at TEXT);
INSERT INTO serial(id,uid,serial,estado) VALUES (1,'s1','SN-1','RESERVADO');
CREATE TABLE accesorios(id INTEGER PRIMARY KEY AUTOINCREMENT, uid TEXT UNIQUE, nombre TEXT, cantidad REAL, updated_at TEXT);
INSERT INTO accesorios(id,uid,nombre,cantidad) VALUES (1,'a1','Cover',10);
CREATE TABLE bancos(id INTEGER PRIMARY KEY AUTOINCREMENT, uid TEXT UNIQUE, nombre TEXT, saldo REAL, fecha_transaccion TEXT, updated_at TEXT);
INSERT INTO bancos(id,uid,nombre,saldo) VALUES (1,'b1','Banco',1000);
SQL);

// Minimal service: guardarVenta only needs the project DB and webhooks.
$meta = new PDO('sqlite::memory:');
$meta->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$meta->exec('CREATE TABLE webhooks(id INTEGER PRIMARY KEY, project_uid TEXT, event TEXT, url TEXT, is_active INTEGER)');
$reflection = new ReflectionClass(SystemRuntimeService::class);
$service = $reflection->newInstanceWithoutConstructor();
$webhooks = $reflection->getProperty('webhooks');
$webhooks->setAccessible(true);
$webhooks->setValue($service, new WebhookService($meta));
$guardar = $reflection->getMethod('guardarVenta');
$guardar->setAccessible(true);
$project = ['uid' => 'prj_test', 'name' => 'Prueba'];
$sell = fn (array $payload): array => $guardar->invoke($service, $db, $project, $payload);
$count = fn (string $sql): int => (int) $db->query($sql)->fetchColumn();

function salePayload(string $no, array $extra = []): array
{
    return array_replace_recursive([
        'factura' => ['no_factura' => $no, 'total' => 100, 'ncf' => '', 'comprobante_id' => 0, 'almacen_uid' => 'alm', 'otro' => '{}'],
        'cuenta_cobrar' => null,
        'comprobante_id' => 0,
        'inventario' => [],
        'bancos' => [],
    ], $extra);
}

// 1. NCF assigned by the server from secuencia_actual (B-series, 8 digits).
$r1 = $sell(salePayload('F-1', ['operation_uid' => 'op-00000001', 'comprobante_id' => 1, 'factura' => ['comprobante_id' => 1], 'inventario' => [['tabla' => 'imei', 'id' => 1, 'cambios' => ['estado' => 'VENDIDO', 'no_factura' => 'F-1']]], 'bancos' => [['id' => 1, 'monto' => 100]]]));
checkSale($r1['success'] === true && $r1['data']['ncf'] === 'B0200000005', 'NCF B02 asignado por el servidor: ' . json_encode($r1));
checkSale((int) $db->query('SELECT secuencia_actual FROM comprobantes_fiscales WHERE id=1')->fetchColumn() === 6, 'secuencia avanza a 6');
checkSale($db->query('SELECT estado FROM imei WHERE id=1')->fetchColumn() === 'VENDIDO', 'IMEI vendido');
checkSale((float) $db->query('SELECT saldo FROM bancos WHERE id=1')->fetchColumn() === 1100.0, 'banco acreditado');
checkSale($db->query("SELECT operation_uid FROM facturas WHERE no_factura='F-1'")->fetchColumn() === 'op-00000001', 'operation_uid guardado');

// 2. Idempotent replay: same operation uid returns the same factura, no side effects.
$r2 = $sell(salePayload('F-1', ['operation_uid' => 'op-00000001', 'comprobante_id' => 1, 'inventario' => [['tabla' => 'imei', 'id' => 1]], 'bancos' => [['id' => 1, 'monto' => 100]]]));
checkSale($r2['success'] === true && $r2['data']['duplicate'] === true && $r2['data']['id'] === $r1['data']['id'] && $r2['data']['ncf'] === 'B0200000005', 'reenvio idempotente');
checkSale($count('SELECT COUNT(*) FROM facturas') === 1, 'sin factura duplicada');
checkSale((float) $db->query('SELECT saldo FROM bancos WHERE id=1')->fetchColumn() === 1100.0, 'banco sin doble credito');
checkSale((int) $db->query('SELECT secuencia_actual FROM comprobantes_fiscales WHERE id=1')->fetchColumn() === 6, 'secuencia sin doble consumo');

// Operation uid taken from factura.otro.offline_uid (offline queue format).
$r3 = $sell(salePayload('F-2', ['factura' => ['otro' => json_encode(['offline_uid' => 'op-00000002'])]]));
$r3b = $sell(salePayload('F-2', ['factura' => ['otro' => json_encode(['offline_uid' => 'op-00000002'])]]));
checkSale($r3b['data']['duplicate'] === true && $r3b['data']['id'] === $r3['data']['id'], 'idempotencia desde otro.offline_uid');

// 3. IMEI already sold: the whole sale rolls back.
$facturasAntes = $count('SELECT COUNT(*) FROM facturas');
expectSaleError(fn () => $sell(salePayload('F-3', ['operation_uid' => 'op-00000003', 'comprobante_id' => 1, 'inventario' => [
    ['tabla' => 'imei', 'id' => 4, 'cambios' => ['estado' => 'VENDIDO']],
    ['tabla' => 'imei', 'id' => 2, 'cambios' => ['estado' => 'VENDIDO']],
    ['tabla' => 'accesorios', 'id' => 1, 'cantidad' => 2],
], 'bancos' => [['id' => 1, 'monto' => 50]]])), 'El IMEI 350000000000002 ya fue vendido');
checkSale($count('SELECT COUNT(*) FROM facturas') === $facturasAntes, 'rollback: sin factura');
checkSale($db->query('SELECT estado FROM imei WHERE id=4')->fetchColumn() === 'DISPONIBLE', 'rollback: IMEI previo sigue disponible');
checkSale((float) $db->query('SELECT cantidad FROM accesorios WHERE id=1')->fetchColumn() === 10.0, 'rollback: accesorio intacto');
checkSale((float) $db->query('SELECT saldo FROM bancos WHERE id=1')->fetchColumn() === 1100.0, 'rollback: banco intacto');
checkSale((int) $db->query('SELECT secuencia_actual FROM comprobantes_fiscales WHERE id=1')->fetchColumn() === 6, 'rollback: secuencia intacta');
checkSale(!$db->inTransaction(), 'sin transaccion abierta');

// Serial not available and NULL estado treated as DISPONIBLE.
expectSaleError(fn () => $sell(salePayload('F-4', ['inventario' => [['tabla' => 'serial', 'id' => 1, 'cambios' => ['estado' => 'VENDIDO']]]])), 'El serial SN-1 no esta disponible (RESERVADO)');
$r5 = $sell(salePayload('F-5', ['inventario' => [['tabla' => 'imei', 'id' => 3, 'cambios' => ['estado' => 'VENDIDO']]]]));
checkSale($r5['success'] && $db->query('SELECT estado FROM imei WHERE id=3')->fetchColumn() === 'VENDIDO', 'estado NULL se considera disponible');

// 4. Client NCF: kept when unique, reassigned when duplicated.
$r6 = $sell(salePayload('F-6', ['comprobante_id' => 1, 'factura' => ['ncf' => 'B0200000006']]));
checkSale($r6['data']['ncf'] === 'B0200000006', 'NCF de cliente unico se conserva');
checkSale((int) $db->query('SELECT secuencia_actual FROM comprobantes_fiscales WHERE id=1')->fetchColumn() === 7, 'secuencia 7 tras NCF de cliente');
$r7 = $sell(salePayload('F-7', ['comprobante_id' => 1, 'factura' => ['ncf' => 'B0200000005']]));
checkSale($r7['data']['ncf'] === 'B0200000007', 'NCF de cliente duplicado se reasigna: ' . $r7['data']['ncf']);
// Sequence behind an NCF already used by someone else skips it.
$db->exec("INSERT INTO facturas(no_factura,ncf) VALUES ('EXT-1','B0200000008')");
$r8 = $sell(salePayload('F-8', ['comprobante_id' => 1]));
checkSale($r8['data']['ncf'] === 'B0200000009', 'salta NCF ocupado: ' . $r8['data']['ncf']);
checkSale($count("SELECT COUNT(*) FROM (SELECT ncf FROM facturas WHERE ncf<>'' GROUP BY ncf HAVING COUNT(*)>1)") === 0, 'NCF unicos');

// E-series uses 10 digits; SIN does not consume a sequence.
$r9 = $sell(salePayload('F-9', ['comprobante_id' => 2]));
checkSale($r9['data']['ncf'] === 'E320000000001', 'e-CF con 10 digitos: ' . $r9['data']['ncf']);
$r10 = $sell(salePayload('F-10', ['comprobante_id' => 4]));
checkSale($r10['data']['ncf'] === '' && (int) $db->query('SELECT secuencia_actual FROM comprobantes_fiscales WHERE id=4')->fetchColumn() === 1, 'SIN no consume secuencia');

// Exhausted sequence fails the sale.
$r11 = $sell(salePayload('F-11', ['comprobante_id' => 3]));
checkSale($r11['data']['ncf'] === 'B0100000009', 'ultimo NCF del rango: ' . $r11['data']['ncf']);
expectSaleError(fn () => $sell(salePayload('F-12', ['comprobante_id' => 3])), 'La secuencia fiscal B01 esta agotada');
checkSale($count("SELECT COUNT(*) FROM facturas WHERE no_factura='F-12'") === 0, 'secuencia agotada no guarda la venta');

// 5. Duplicate invoice number with a different operation is a business error.
expectSaleError(fn () => $sell(salePayload('F-1', ['operation_uid' => 'op-00000099'])), 'La factura F-1 ya existe');

// Invalid operation uid is rejected.
expectSaleError(fn () => $sell(salePayload('F-13', ['operation_uid' => "bad uid'"])), 'Identificador de operacion');

@unlink($dbFile);
echo "PASS: venta atomica (idempotencia, IMEI/serial, NCF en servidor, rollback)\n";
