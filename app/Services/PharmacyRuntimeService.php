<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use RuntimeException;

/** Pharmacy stock allocations share the existing invoice transaction and accessory inventory. */
final class PharmacyRuntimeService
{
    private static function rows(PDO $db,string $sql,array $args=[]): array { $q=$db->prepare($sql);$q->execute($args);return $q->fetchAll(PDO::FETCH_ASSOC); }
    private static function fail(string $message): never { throw new RuntimeException($message); }
    public static function authorizeSale(PDO $db,array $payload,array $actor,bool $admin): void {
        if($admin)return;
        if(!self::rows($db,"SELECT 1 FROM sqlite_master WHERE type='table' AND name='apariencia_almacen'"))return;
        $invoice=$payload['factura']??[];
        if(!self::rows($db,"SELECT 1 FROM apariencia_almacen WHERE almacen_uid=? AND modo_tienda='farmacia'",[(string)($invoice['almacen_uid']??'')]))return;
        $permissions=$actor['permisos']??[];if(is_string($permissions))$permissions=json_decode($permissions,true)?:[];
        if(!in_array('farmacia',(array)$permissions,true)&&empty($permissions['farmacia']))throw new RuntimeException('Se requiere el permiso Farmacia para dispensar.',403);
    }
    /** Reject non-atomic sales/returns before generic invoice insertion has financial side effects. */
    public static function guardInsert(PDO $db,string $table,array $data): void {
        if($table!=='facturas'||!self::rows($db,"SELECT 1 FROM sqlite_master WHERE type='table' AND name='apariencia_almacen'"))return;
        $warehouse=(string)($data['almacen_uid']??'');$pharmacy=(bool)self::rows($db,"SELECT 1 FROM apariencia_almacen WHERE almacen_uid=? AND modo_tienda='farmacia'",[$warehouse]);
        if(($data['tipo_factura']??'FACTURA_VENTA')==='COTIZACION')return;
        if($pharmacy&&($data['tipo_factura']??'FACTURA_VENTA')==='FACTURA_VENTA')self::fail('Las ventas de farmacia deben pasar por la dispensacion atomica del POS.');
        $return=($data['tipo_factura']??'')==='NOTA_CREDITO'&&str_starts_with(strtoupper(trim((string)($data['nota']??''))),'DEVOLUCION:');
        if(($return||($data['tipo_factura']??'FACTURA_VENTA')==='FACTURA_VENTA')&&self::rows($db,"SELECT 1 FROM sqlite_master WHERE type='table' AND name='_pharmacy_lots'")) {
            foreach(json_decode((string)($data['productos']??'[]'),true)?:[] as $line)if(!empty($line['accesorio_id'])&&self::rows($db,'SELECT 1 FROM _pharmacy_lots WHERE product_id=?',[(int)$line['accesorio_id']]))self::fail($return?'La devolucion de medicamentos requiere conciliacion de lotes; no se registro la nota de credito.':'Los productos con lotes requieren dispensacion atomica.');
        }
    }
    public static function warehouse(PDO $db,array $w): array {
        $id=(int)($w['id']??0);$uid=(string)($w['uid']??'');
        if(!$id||!$uid||!self::rows($db,"SELECT 1 FROM apariencia_almacen WHERE almacen_id=? AND almacen_uid=? AND modo_tienda='farmacia'",[$id,$uid]))self::fail('Esta funcion requiere una sucursal en modo farmacia.');
        return [$id,$uid];
    }
    public static function install(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS _pharmacy_lots(uid TEXT PRIMARY KEY,warehouse_id INTEGER NOT NULL,warehouse_uid TEXT NOT NULL,product_id INTEGER NOT NULL,product_uid TEXT NOT NULL,lot_number TEXT NOT NULL,expires_at TEXT NOT NULL,quantity REAL NOT NULL CHECK(quantity>=0),initial_quantity REAL NOT NULL,unit_cost REAL NOT NULL,supplier TEXT NOT NULL,status TEXT NOT NULL,revision INTEGER NOT NULL DEFAULT 1,request TEXT NOT NULL,created_at TEXT NOT NULL);
        CREATE UNIQUE INDEX IF NOT EXISTS pharmacy_lot_number ON _pharmacy_lots(warehouse_uid,product_id,lot_number);
        CREATE TABLE IF NOT EXISTS _pharmacy_prescriptions(uid TEXT PRIMARY KEY,warehouse_uid TEXT NOT NULL,customer_id INTEGER NOT NULL,data TEXT NOT NULL,status TEXT NOT NULL,created_at TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS _pharmacy_movements(id INTEGER PRIMARY KEY,warehouse_uid TEXT NOT NULL,lot_uid TEXT NOT NULL,product_id INTEGER NOT NULL,quantity REAL NOT NULL,type TEXT NOT NULL,reason TEXT NOT NULL,invoice_uid TEXT,actor TEXT NOT NULL,created_at TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS _pharmacy_dispensations(uid TEXT PRIMARY KEY,warehouse_uid TEXT NOT NULL,invoice_uid TEXT NOT NULL UNIQUE,prescription_uid TEXT,customer_id INTEGER,data TEXT NOT NULL,created_at TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS _pharmacy_writeoffs(uid TEXT PRIMARY KEY,request TEXT NOT NULL);");
        // Stock cannot be removed through generic adjustments or transfers while allocated to lots.
        if(self::rows($db,"SELECT 1 FROM sqlite_master WHERE type='table' AND name='accesorios'"))$db->exec("CREATE TRIGGER IF NOT EXISTS pharmacy_stock_update BEFORE UPDATE OF cantidad,almacen_uid ON accesorios WHEN EXISTS(SELECT 1 FROM _pharmacy_lots WHERE product_id=OLD.id) BEGIN SELECT CASE WHEN NEW.almacen_uid<>OLD.almacen_uid OR NEW.cantidad<(SELECT COALESCE(SUM(quantity),0)-0.000001 FROM _pharmacy_lots WHERE product_id=OLD.id) THEN RAISE(ABORT,'El producto tiene lotes de farmacia; no se puede reducir o trasladar stock asignado fuera de farmacia.') END; END;
        CREATE TRIGGER IF NOT EXISTS pharmacy_stock_delete BEFORE DELETE ON accesorios WHEN EXISTS(SELECT 1 FROM _pharmacy_lots WHERE product_id=OLD.id) BEGIN SELECT RAISE(ABORT,'No se puede eliminar un producto con trazabilidad de farmacia.'); END;");
        if(self::rows($db,"SELECT 1 FROM sqlite_master WHERE type='table' AND name='facturas'"))$db->exec("CREATE TRIGGER IF NOT EXISTS pharmacy_invoice_delete BEFORE DELETE ON facturas WHEN EXISTS(SELECT 1 FROM _pharmacy_dispensations WHERE invoice_uid=OLD.uid) BEGIN SELECT RAISE(ABORT,'La factura tiene dispensaciones; no puede eliminarse.'); END;
        CREATE TRIGGER IF NOT EXISTS pharmacy_invoice_update BEFORE UPDATE OF total,otro,estado_factura,productos,cod_cliente,uid,almacen_uid,almacen_id ON facturas WHEN EXISTS(SELECT 1 FROM _pharmacy_dispensations WHERE invoice_uid=OLD.uid) AND (NEW.total IS NOT OLD.total OR NEW.productos IS NOT OLD.productos OR NEW.cod_cliente IS NOT OLD.cod_cliente OR NEW.uid IS NOT OLD.uid OR NEW.almacen_uid IS NOT OLD.almacen_uid OR NEW.almacen_id IS NOT OLD.almacen_id OR json_extract(CASE WHEN json_valid(NEW.otro) THEN NEW.otro ELSE '{}' END,'$.pharmacy') IS NOT json_extract(CASE WHEN json_valid(OLD.otro) THEN OLD.otro ELSE '{}' END,'$.pharmacy') OR (NEW.estado_factura IS NOT OLD.estado_factura AND UPPER(NEW.estado_factura) IN ('ANULADA','DEVUELTA','CANCELADA'))) BEGIN SELECT RAISE(ABORT,'La factura de farmacia conserva su trazabilidad; la edicion o devolucion requiere conciliacion de lotes.'); END;");
    }
    private static function catalog(PDO $db,string $uid): array { $r=self::rows($db,'SELECT valor FROM configuracion WHERE clave=?',['pharmacy_catalog_v1_'.$uid]);return json_decode((string)($r[0]['valor']??'{}'),true)?:['revision'=>0,'products'=>[]]; }
    private static function product(PDO $db,int $id,string $uid): array {
        $r=self::rows($db,'SELECT * FROM accesorios WHERE id=? AND almacen_uid=?',[$id,$uid]);if(!$r)self::fail('El producto no pertenece a esta sucursal.');return $r[0];
    }
    private static function date(string $s): string { $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$s);if(!$d||$d->format('Y-m-d')!==$s)self::fail('Fecha invalida.');return $s; }
    private static function today(PDO $db): string { $zone='America/Santo_Domingo';try{$r=self::rows($db,"SELECT valor FROM configuracion WHERE clave='sistema_zona_horaria'");$tz=new \DateTimeZone((string)($r[0]['valor']??$zone));}catch(\Throwable){$tz=new \DateTimeZone($zone);}return (new \DateTimeImmutable('now',$tz))->format('Y-m-d'); }
    private static function text(mixed $s,int $max=200,bool $required=false): string { $s=trim((string)$s);if(($required&&$s==='')||mb_strlen($s)>$max)self::fail('Completa los campos requeridos y respeta su longitud maxima.');return $s; }
    private static function number(mixed $v,bool $zero=false): float { if(!is_numeric($v)||!is_finite((float)$v)||($zero?(float)$v<0:(float)$v<=0)||(float)$v>100000000)self::fail('Cantidad o importe invalido.');return (float)$v; }
    private static function quantity(mixed $v): float { $n=self::number($v);if(floor($n)!==$n)self::fail('Usa cantidades enteras en la unidad de venta del catalogo.');return $n; }
    private static function uid(mixed $uid): string { $uid=(string)$uid;if(!preg_match('/^[A-Za-z0-9_-]{8,100}$/D',$uid))self::fail('Identificador invalido.');return $uid; }
    private static function movement(PDO $db,string $w,string $lot,int $product,float $qty,string $type,string $reason,string $actor,?string $invoice=null):void { $db->prepare('INSERT INTO _pharmacy_movements(warehouse_uid,lot_uid,product_id,quantity,type,reason,invoice_uid,actor,created_at) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$w,$lot,$product,$qty,$type,$reason,$invoice,$actor,gmdate('Y-m-d H:i:s')]); }
    public static function handle(PDO $db,string $channel,array $p,array $actor,bool $admin): array {
        [$wid,$w]=self::warehouse($db,(array)($p['warehouse']??[]));
        $role=strtolower((string)($actor['nivel_seguridad']??$actor['rol']??''));
        if(!$admin&&!in_array($role,['cajero','vendedor','usuario','gerente'],true))throw new RuntimeException('No tienes acceso a farmacia.',403);
        $permissions=$actor['permisos']??[];if(is_string($permissions))$permissions=json_decode($permissions,true)?:[];
        if(!$admin&&!in_array('farmacia',(array)$permissions,true)&&empty($permissions['farmacia']))throw new RuntimeException('Se requiere el permiso Farmacia.',403);
        self::install($db);
        if($channel==='farmacia:obtenerEstado') {
            $catalog=self::catalog($db,$w);$lots=[];
            foreach(self::rows($db,'SELECT * FROM _pharmacy_lots WHERE warehouse_uid=? ORDER BY expires_at,created_at',[$w]) as $r)$lots[]=['uid'=>$r['uid'],'productId'=>(int)$r['product_id'],'productUid'=>$r['product_uid'],'lotNumber'=>$r['lot_number'],'expiresAt'=>$r['expires_at'],'quantity'=>(float)$r['quantity'],'initialQuantity'=>(float)$r['initial_quantity'],'unitCost'=>(float)$r['unit_cost'],'supplier'=>$r['supplier'],'status'=>$r['status'],'revision'=>(int)$r['revision']];
            $rx=[];foreach(self::rows($db,'SELECT * FROM _pharmacy_prescriptions WHERE warehouse_uid=? ORDER BY created_at DESC',[$w]) as $r)$rx[]=[...json_decode($r['data'],true),'status'=>$r['status']];
            $sales=[];foreach(self::rows($db,'SELECT * FROM _pharmacy_dispensations WHERE warehouse_uid=? ORDER BY created_at DESC',[$w]) as $r)$sales[]=[...json_decode($r['data'],true),'uid'=>$r['uid'],'invoiceUid'=>$r['invoice_uid'],'createdAt'=>$r['created_at']];
            $used=[];foreach($sales as $sale)foreach($sale['items'] as $item)$used[$sale['prescriptionUid']][$item['productId']]=($used[$sale['prescriptionUid']][$item['productId']]??0)+$item['quantity'];
            foreach($rx as &$prescription)foreach($prescription['items'] as &$item)$item['dispensedQuantity']=$used[$prescription['uid']][$item['productId']]??0;unset($prescription,$item);
            $moves=[];foreach(self::rows($db,'SELECT * FROM _pharmacy_movements WHERE warehouse_uid=? ORDER BY id DESC LIMIT 2000',[$w]) as $r)$moves[]=['id'=>(int)$r['id'],'lotUid'=>$r['lot_uid'],'productId'=>(int)$r['product_id'],'quantity'=>(float)$r['quantity'],'type'=>$r['type'],'reason'=>$r['reason'],'invoiceUid'=>$r['invoice_uid'],'actor'=>$r['actor'],'createdAt'=>$r['created_at']];
            return ['success'=>true,'data'=>['catalog'=>$catalog,'revision'=>$catalog['revision']??0,'products'=>$catalog['products']??[],'lots'=>$lots,'prescriptions'=>$rx,'dispensations'=>$sales,'movements'=>$moves]];
        }
        if(!$admin&&!in_array($channel,['farmacia:guardarReceta','farmacia:anularReceta'],true))throw new RuntimeException('Solo Administrador o Soporte puede modificar inventario de farmacia.',403);
        $db->exec('BEGIN IMMEDIATE');
        try {
            $result=[];$who=(string)($actor['usuario']??$actor['email']??$role);
            if($channel==='farmacia:guardarCatalogo') {
                $old=self::catalog($db,$w);if((int)($old['revision']??0)!==(int)($p['expectedRevision']??-1))throw new RuntimeException('El catalogo cambio. Actualiza antes de guardar.',409);
                $products=$p['settings']['products']??null;if(!is_array($products)||count($products)>10000)self::fail('Catalogo invalido.');$ids=[];$clean=[];
                foreach($products as $r){$id=(int)($r['productId']??0);$prod=self::product($db,$id,$w);if(isset($ids[$id])||($r['productUid']??'')!==$prod['uid'])self::fail('Producto duplicado o desactualizado.');$ids[$id]=true;
                    $c=['productId'=>$id,'productUid'=>$prod['uid']];foreach(['activeIngredient','strength','presentation','laboratory','registration','storage'] as $field)$c[$field]=self::text($r[$field]??'',$field==='storage'?1000:200,in_array($field,['activeIngredient','presentation'],true));
                    $c['controlled']=(bool)($r['controlled']??false);$c['requiresPrescription']=$c['controlled']||(bool)($r['requiresPrescription']??false);$c['minStock']=self::number($r['minStock']??0,true);$clean[]=$c;
                }
                foreach($old['products']??[] as $r)if(!isset($ids[$r['productId']])&&self::rows($db,'SELECT 1 FROM _pharmacy_lots WHERE warehouse_uid=? AND product_id=?',[$w,$r['productId']]))self::fail('No puedes quitar productos con historial de lotes.');
                $result=['revision'=>(int)($old['revision']??0)+1,'products'=>$clean];$key='pharmacy_catalog_v1_'.$w;$json=json_encode($result,JSON_THROW_ON_ERROR);$now=gmdate('Y-m-d H:i:s');
                $q=$db->prepare('UPDATE configuracion SET valor=?,updated_at=? WHERE clave=?');$q->execute([$json,$now,$key]);if(!$q->rowCount())$db->prepare('INSERT INTO configuracion(clave,valor,categoria,uid,created_at,updated_at) VALUES(?,?,?,?,?,?)')->execute([$key,$json,'farmacia','pharmacy_'.bin2hex(random_bytes(16)),$now,$now]);
            } elseif($channel==='farmacia:recibirLote') {
                $uid=self::uid($p['uid']??'');$id=(int)($p['productId']??0);$prod=self::product($db,$id,$w);$profiles=array_column(self::catalog($db,$w)['products']??[],null,'productId');if(!isset($profiles[$id]))self::fail('Registra primero la ficha del medicamento.');
                $qty=self::quantity($p['quantity']??null);$cost=self::number($p['unitCost']??0,true);$expiry=self::date((string)($p['expiresAt']??''));$lot=self::text($p['lotNumber']??'',100,true);$supplier=self::text($p['supplier']??'');$source=$p['source']??'';if(!in_array($source,['EXISTENTE','COMPRA'],true))self::fail('Origen de inventario invalido.');
                $request=json_encode([$w,$id,$qty,$cost,$expiry,$lot,$supplier,$source],JSON_THROW_ON_ERROR);$existing=self::rows($db,'SELECT * FROM _pharmacy_lots WHERE uid=?',[$uid]);
                if($existing){if($existing[0]['request']!==$request)self::fail('El reintento no coincide con el lote original.');}
                else {
                    $assigned=(float)(self::rows($db,'SELECT COALESCE(SUM(quantity),0) AS n FROM _pharmacy_lots WHERE warehouse_uid=? AND product_id=?',[$w,$id])[0]['n']);
                    if($source==='EXISTENTE'&&$assigned+$qty>(float)$prod['cantidad']+0.000001)self::fail('La cantidad supera el inventario existente sin lote.');
                    if($source==='COMPRA')$db->prepare('UPDATE accesorios SET cantidad=cantidad+?,updated_at=? WHERE id=?')->execute([$qty,gmdate('Y-m-d H:i:s'),$id]);
                    $db->prepare('INSERT INTO _pharmacy_lots(uid,warehouse_id,warehouse_uid,product_id,product_uid,lot_number,expires_at,quantity,initial_quantity,unit_cost,supplier,status,request,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$uid,$wid,$w,$id,$prod['uid'],$lot,$expiry,$qty,$qty,$cost,$supplier,'DISPONIBLE',$request,gmdate('Y-m-d H:i:s')]);
                    self::movement($db,$w,$uid,$id,$qty,$source==='COMPRA'?'ENTRADA':'ASIGNACION','Recepcion de lote',$who);
                }$result=['uid'=>$uid];
            } elseif($channel==='farmacia:cambiarEstadoLote') {
                $uid=self::uid($p['uid']??'');$r=self::rows($db,'SELECT * FROM _pharmacy_lots WHERE uid=? AND warehouse_uid=?',[$uid,$w])[0]??null;if(!$r)self::fail('Lote no encontrado.');if((int)$r['revision']!==(int)($p['expectedRevision']??-1))throw new RuntimeException('El lote cambio. Actualiza antes de guardar.',409);
                $status=$p['status']??'';if(!in_array($status,['DISPONIBLE','CUARENTENA','RETIRADO'],true))self::fail('Estado invalido.');if($r['status']==='RETIRADO')self::fail('Un lote retirado no puede volver a circular.');$reason=self::text($p['reason']??'',500,true);
                $db->prepare('UPDATE _pharmacy_lots SET status=?,revision=revision+1 WHERE uid=?')->execute([$status,$uid]);self::movement($db,$w,$uid,(int)$r['product_id'],0,$status,$reason,$who);$result=['uid'=>$uid,'revision'=>(int)$r['revision']+1];
            } elseif($channel==='farmacia:darBajaLote') {
                $uid=self::uid($p['uid']??'');$lotUid=self::uid($p['lotUid']??'');$quantity=self::quantity($p['quantity']??null);$reason=self::text($p['reason']??'',500,true);$request=json_encode([$w,$lotUid,$quantity,$reason],JSON_THROW_ON_ERROR);$old=self::rows($db,'SELECT request FROM _pharmacy_writeoffs WHERE uid=?',[$uid]);
                if($old){if($old[0]['request']!==$request)self::fail('El reintento no coincide con la baja original.');}
                else {$lot=self::rows($db,'SELECT * FROM _pharmacy_lots WHERE uid=? AND warehouse_uid=?',[$lotUid,$w])[0]??null;if(!$lot)self::fail('Lote no encontrado.');if($lot['status']==='DISPONIBLE'&&$lot['expires_at']>=self::today($db))self::fail('Pon el lote en cuarentena o retirado antes de registrar su baja.');if((float)$lot['quantity']<$quantity)self::fail('La baja supera las unidades restantes del lote.');
                    $db->prepare('UPDATE _pharmacy_lots SET quantity=quantity-?,revision=revision+1 WHERE uid=?')->execute([$quantity,$lotUid]);$q=$db->prepare('UPDATE accesorios SET cantidad=cantidad-?,updated_at=? WHERE id=? AND cantidad>=?');$q->execute([$quantity,gmdate('Y-m-d H:i:s'),$lot['product_id'],$quantity]);if($q->rowCount()!==1)self::fail('Inventario insuficiente para la baja.');self::movement($db,$w,$lotUid,(int)$lot['product_id'],-$quantity,'BAJA',$reason,$who);$db->prepare('INSERT INTO _pharmacy_writeoffs VALUES(?,?)')->execute([$uid,$request]);
                }$result=['uid'=>$uid];
            } elseif($channel==='farmacia:guardarReceta') {
                $uid=self::uid($p['uid']??'');$customer=(int)($p['customerId']??0);if(!self::rows($db,'SELECT 1 FROM clientes WHERE id=?',[$customer]))self::fail('Selecciona un cliente registrado.');
                $data=['uid'=>$uid,'customerId'=>$customer,'prescriber'=>self::text($p['prescriber']??'',200,true),'reference'=>self::text($p['reference']??'',200,true),'issuedAt'=>self::date((string)($p['issuedAt']??'')),'expiresAt'=>self::date((string)($p['expiresAt']??'')),'notes'=>self::text($p['notes']??'',1000),'items'=>[]];if($data['expiresAt']<$data['issuedAt'])self::fail('El vencimiento precede la emision.');
                $items=$p['items']??[];if(!is_array($items)||!count($items)||count($items)>100)self::fail('Agrega medicamentos a la receta.');$ids=[];foreach($items as $r){$id=(int)($r['productId']??0);self::product($db,$id,$w);if(isset($ids[$id]))self::fail('Medicamento repetido.');$ids[$id]=true;$data['items'][]=['productId'=>$id,'quantity'=>self::quantity($r['quantity']??null)];}
                $json=json_encode($data,JSON_THROW_ON_ERROR);$old=self::rows($db,'SELECT * FROM _pharmacy_prescriptions WHERE uid=?',[$uid]);if($old){if($old[0]['warehouse_uid']!==$w||$old[0]['data']!==$json)self::fail('Las recetas registradas no se sobrescriben.');}else $db->prepare("INSERT INTO _pharmacy_prescriptions VALUES(?,?,?,?,'ACTIVA',?)")->execute([$uid,$w,$customer,$json,gmdate('Y-m-d H:i:s')]);$result=$data;
            } elseif($channel==='farmacia:anularReceta') {
                $uid=self::uid($p['uid']??'');$reason=self::text($p['reason']??'',500,true);$r=self::rows($db,'SELECT data FROM _pharmacy_prescriptions WHERE uid=? AND warehouse_uid=?',[$uid,$w]);if(!$r)self::fail('Receta no encontrada.');$data=json_decode($r[0]['data'],true);$data['cancellationReason']=$reason;$data['cancelledBy']=$who;$data['cancelledAt']=gmdate('Y-m-d H:i:s');$db->prepare("UPDATE _pharmacy_prescriptions SET status='ANULADA',data=? WHERE uid=?")->execute([json_encode($data,JSON_THROW_ON_ERROR),$uid]);$result=['uid'=>$uid];
            } else self::fail('Operacion de farmacia no disponible.');
            $db->exec('COMMIT');return ['success'=>true,'data'=>$result];
        }catch(\Throwable $e){$db->exec('ROLLBACK');throw $e;}
    }

    /** Called before ordinary inventory decrements, inside guardarVenta BEGIN IMMEDIATE. */
    public static function applySale(PDO $db,array $invoice,array $payload): array {
        $w=(string)($invoice['almacen_uid']??'');$exists=self::rows($db,"SELECT 1 FROM sqlite_master WHERE type='table' AND name='apariencia_almacen'");if(!$exists)return $invoice;
        $active=(bool)self::rows($db,"SELECT 1 FROM apariencia_almacen WHERE almacen_uid=? AND almacen_id=? AND modo_tienda='farmacia'",[$w,(int)($invoice['almacen_id']??0)]);
        if(!$active){if(self::rows($db,"SELECT 1 FROM sqlite_master WHERE type='table' AND name='_pharmacy_lots'"))foreach($payload['inventario']??[] as $item)if(($item['tabla']??'')==='accesorios'&&self::rows($db,'SELECT 1 FROM _pharmacy_lots WHERE product_id=?',[(int)($item['id']??0)]))self::fail('Los productos con lotes deben venderse desde el modo farmacia.');return $invoice;}
        self::install($db);$products=array_column(self::catalog($db,$w)['products']??[],null,'productId');$today=self::today($db);$alloc=[];$totals=[];
        foreach($payload['inventario']??[] as $item){if(($item['tabla']??'')!=='accesorios')self::fail('En farmacia solo se venden productos del catalogo con lotes.');$id=(int)($item['id']??0);if(!isset($products[$id]))self::fail('Completa la ficha de farmacia y asigna lotes antes de vender este producto.');$totals[$id]=($totals[$id]??0)+self::quantity($item['cantidad']??null);}
        $lines=json_decode((string)($invoice['productos']??'[]'),true);if(!is_array($lines))self::fail('Detalle de factura invalido.');$lineTotals=[];foreach($lines as $line){if(($line['tipo']??'')!=='accesorio'||empty($line['accesorio_id']))self::fail('En farmacia solo se venden productos del catalogo con lotes.');$id=(int)$line['accesorio_id'];$lineTotals[$id]=($lineTotals[$id]??0)+self::quantity($line['cantidad']??null);}
        if(count($lineTotals)!==count($totals))self::fail('El detalle de la factura no coincide con el inventario a dispensar.');foreach($totals as $id=>$qty)if(!isset($lineTotals[$id])||abs($lineTotals[$id]-$qty)>0.000001)self::fail('Las cantidades de factura y dispensacion no coinciden.');
        if(!$totals)return $invoice;
        $invoiceUid=self::uid($invoice['uid']??'');$rxUid=(string)($payload['pharmacy']['prescriptionUid']??'');$rx=null;$rxItems=[];$used=[];
        if($rxUid!==''){$r=self::rows($db,'SELECT * FROM _pharmacy_prescriptions WHERE uid=? AND warehouse_uid=?',[$rxUid,$w])[0]??null;if(!$r||$r['status']!=='ACTIVA')self::fail('Receta no disponible.');$rx=json_decode($r['data'],true);if($rx['issuedAt']>$today||$rx['expiresAt']<$today||(int)($invoice['cod_cliente']??0)!==(int)$r['customer_id'])self::fail('La receta esta vencida, aun no es valida o pertenece a otro cliente.');$rxItems=array_column($rx['items'],null,'productId');foreach(self::rows($db,'SELECT data FROM _pharmacy_dispensations WHERE prescription_uid=?',[$rxUid]) as $sale)foreach(json_decode($sale['data'],true)['items'] as $item)$used[$item['productId']]=($used[$item['productId']]??0)+$item['quantity'];}
        foreach($totals as $id=>$qty){$product=self::product($db,$id,$w);$profile=$products[$id];if(($profile['requiresPrescription']??false)||($profile['controlled']??false)||isset($rxItems[$id])){if(!$rx||!isset($rxItems[$id])||($used[$id]??0)+$qty>$rxItems[$id]['quantity']+0.000001)self::fail('Se requiere receta vigente con cantidad suficiente para este medicamento.');}
            $all=self::rows($db,'SELECT COALESCE(SUM(quantity),0) AS n FROM _pharmacy_lots WHERE warehouse_uid=? AND product_id=?',[$w,$id]);if((float)$all[0]['n']>(float)$product['cantidad']+0.000001)self::fail('El stock fue modificado fuera de farmacia. Reconcilia los lotes antes de vender.');
            $remaining=$qty;foreach(self::rows($db,"SELECT * FROM _pharmacy_lots WHERE warehouse_uid=? AND product_id=? AND status='DISPONIBLE' AND expires_at>=? AND quantity>0 ORDER BY expires_at,created_at,uid",[$w,$id,$today]) as $lot){if($remaining<0.000001)break;$take=min($remaining,(float)$lot['quantity']);$db->prepare('UPDATE _pharmacy_lots SET quantity=quantity-?,revision=revision+1 WHERE uid=?')->execute([$take,$lot['uid']]);$alloc[]=['lotUid'=>$lot['uid'],'lotNumber'=>$lot['lot_number'],'expiresAt'=>$lot['expires_at'],'productId'=>$id,'quantity'=>$take];self::movement($db,$w,$lot['uid'],$id,-$take,'DISPENSACION','Venta FEFO','POS',$invoiceUid);$remaining-=$take;}
            if($remaining>0.000001)self::fail('No hay suficientes unidades en lotes disponibles y vigentes.');
        }
        $items=[];foreach($totals as $id=>$qty)$items[]=['productId'=>$id,'quantity'=>$qty];$data=['prescriptionUid'=>$rxUid,'customerId'=>(int)($invoice['cod_cliente']??0),'items'=>$items,'allocations'=>$alloc];
        $db->prepare('INSERT INTO _pharmacy_dispensations VALUES(?,?,?,?,?,?,?)')->execute(['disp_'.$invoiceUid,$w,$invoiceUid,$rxUid?:null,(int)($invoice['cod_cliente']??0),json_encode($data,JSON_THROW_ON_ERROR),gmdate('Y-m-d H:i:s')]);
        $meta=json_decode((string)($invoice['otro']??'{}'),true)?:[];$meta['pharmacy']=$data;$invoice['otro']=json_encode($meta,JSON_THROW_ON_ERROR);return $invoice;
    }
}
