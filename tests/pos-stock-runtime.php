<?php
declare(strict_types=1);
namespace App\Services {
    final class WebhookService {
        public function dispatch(string $event, array $project, string $table, array $row): void {}
    }
}
namespace {
    $backend = $argv[1] ?? dirname(__DIR__);
    require_once $backend . '/app/Core/Support.php';
    require_once ($argv[2] ?? $backend . '/app/Services/SystemRuntimeService.php');
    function check(bool $ok, string $message): void { if (!$ok) throw new \RuntimeException($message); }
    $r = new \ReflectionClass(\App\Services\SystemRuntimeService::class);
    $service = $r->newInstanceWithoutConstructor();
    $r->getProperty('webhooks')->setValue($service, new \App\Services\WebhookService());
    $save = $r->getMethod('guardarVenta');
    $policy = $r->getMethod('permitsNegativeStock');
    $cases = [
        ['disabled', '0', 0, 1, 1, false],
        ['default', '', 0, 1, 1, false],
        ['enabled-zero', '1', 0, 2, 1, true],
        ['enabled-negative', '1', -2, 2, 1, true],
        ['enabled-excess', '1', 1, 2, 1, true],
        ['disabled-sufficient', '0', 4, 2, 1, true],
        ['wrong-warehouse', '1', 0, 1, 2, false],
        ['missing', '1', 0, 1, 99, false],
        ['zero-quantity', '1', 0, 0, 1, false],
        ['negative-quantity', '1', 0, -1, 1, false],
    ];
    foreach ($cases as [$name,$enabled,$stock,$quantity,$id,$success]) {
        $db = new \PDO('sqlite::memory:');
        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $db->exec("CREATE TABLE facturas(id INTEGER PRIMARY KEY,no_factura TEXT,almacen_uid TEXT,productos TEXT,uid TEXT);
            CREATE TABLE accesorios(id INTEGER PRIMARY KEY,nombre TEXT,cantidad REAL,almacen_uid TEXT,updated_at TEXT);
            CREATE TABLE piezas(id INTEGER PRIMARY KEY,nombre TEXT,cantidad REAL,almacen_uid TEXT,updated_at TEXT);
            CREATE TABLE configuracion(id INTEGER PRIMARY KEY,clave TEXT,valor TEXT,categoria TEXT,uid TEXT,created_at TEXT,updated_at TEXT);
            CREATE TABLE apariencia_almacen(almacen_uid TEXT,almacen_id INTEGER,modo_tienda TEXT);
            INSERT INTO piezas VALUES(1,'Pieza',10,'a','');
            INSERT INTO accesorios VALUES(2,'Otro almacen',0,'b','');");
        $db->prepare('INSERT INTO accesorios VALUES(1,?,?,?,?)')->execute(['Producto',$stock,'a','']);
        $db->prepare('INSERT INTO configuracion(clave,valor) VALUES(?,?)')->execute(['pos_allow_negative_stock',$enabled]);
        $payload = [
            'operation_uid'=>'stock-test-operation',
            // A client flag must never override the persisted setting.
            'allowNegativeStock'=>true,
            'factura'=>['no_factura'=>'F-STOCK-1','almacen_uid'=>'a','productos'=>[['tipo'=>'accesorio','accesorio_id'=>$id,'cantidad'=>$quantity]]],
            'inventario'=>[['tabla'=>'piezas','id'=>1,'cantidad'=>1],['tabla'=>'accesorios','id'=>$id,'cantidad'=>$quantity]],
        ];
        $error=null;
        try { $save->invoke($service,$db,['uid'=>'test'],$payload); } catch (\Throwable $e) { $error=$e; }
        check(($error===null)===$success,"$name result: ".($error?->getMessage()??''));
        check((int)$db->query('SELECT COUNT(*) FROM facturas')->fetchColumn()===($success?1:0),"$name invoice atomicity");
        check((float)$db->query('SELECT cantidad FROM piezas WHERE id=1')->fetchColumn()===($success?9.0:10.0),"$name rollback earlier stock");
        check((float)$db->query('SELECT cantidad FROM accesorios WHERE id=1')->fetchColumn()===($success?(float)($stock-$quantity):(float)$stock),"$name stock");
        if($success) {
            $repeat=$save->invoke($service,$db,['uid'=>'test'],$payload);
            check($repeat['data']['duplicate']===true,"$name idempotency");
            check((float)$db->query('SELECT cantidad FROM accesorios WHERE id=1')->fetchColumn()===(float)($stock-$quantity),"$name duplicate discount");
        }
        $db->exec("UPDATE configuracion SET valor='1'; INSERT INTO apariencia_almacen VALUES('a',1,'farmacia')");
        check($policy->invoke($service,$db,['almacen_uid'=>'a'])===false,'Pharmacy must require stock');
        $set=$r->getMethod('configSet');
        try {
            $set->invoke($service,$db,['clave'=>'pos_allow_negative_stock','valor'=>'0'],['rol'=>'cajero']);
            throw new \LogicException('Cashier changed stock policy');
        } catch (\RuntimeException $e) { check($e->getCode()===403,'Policy permissions'); }
        $set->invoke($service,$db,['clave'=>'pos_allow_negative_stock','valor'=>'0'],['rol'=>'administrador']);
        check($db->query("SELECT valor FROM configuracion WHERE clave='pos_allow_negative_stock'")->fetchColumn()==='0','Disable persisted');
    }
    echo "PASS: 10 stock policy scenarios, rollback, replay, pharmacy and permissions\n";
}
