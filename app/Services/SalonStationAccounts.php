<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use RuntimeException;
require_once __DIR__.'/SalonStationCommissions.php';

/** Shared station tabs; invoice completion runs inside the existing sale transaction. */
final class SalonStationAccounts
{
    public static function handle(PDO $db,array $input,array $actor,bool $admin): array
    {
        $role=strtolower((string)(($actor['nivel_seguridad']??'')?:($actor['rol']??'')));
        if (!$admin && !in_array($role,['cajero','vendedor','usuario'],true)) throw new RuntimeException('Sin permiso para atender puestos.',403);
        [$warehouseId,$warehouseUid]=SalonRuntimeService::warehouse($db,(array)($input['warehouse']??[]));
        $db->exec("CREATE TABLE IF NOT EXISTS _salon_station_accounts(uid TEXT PRIMARY KEY,warehouse_uid TEXT NOT NULL,station_uid TEXT NOT NULL,date TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'OPEN',revision INTEGER NOT NULL DEFAULT 0,data TEXT NOT NULL,invoice_uid TEXT NOT NULL DEFAULT '')");
        $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS salon_one_open_station ON _salon_station_accounts(warehouse_uid,station_uid) WHERE status='OPEN'");
        $op=(array)$input['accountOperation'];$type=$op['type']??'';
        if (in_array($type,['inventorySettings','saveInventorySettings'],true)) {
            require_once __DIR__.'/SalonInventoryPolicy.php';
            return ['success'=>true,'data'=>$type==='inventorySettings'?SalonInventoryPolicy::get($db,$warehouseUid):SalonInventoryPolicy::save($db,$warehouseUid,$op,$actor,$admin)];
        }
        if ($type==='commissionRules') return ['success'=>true,'data'=>SalonStationCommissions::rules($db)];
        if ($type==='setCommissionRules') return ['success'=>true,'data'=>SalonStationCommissions::setRules($db,$op,$actor,$admin)];
        if ($type==='staff') return ['success'=>true,'data'=>SalonStationCommissions::staff($db)];
        if ($type==='setEmployment') return ['success'=>true,'data'=>SalonStationCommissions::setStaff($db,$op,$actor,$admin)];
        if ($type==='list') {
            $q=$db->prepare("SELECT * FROM _salon_station_accounts WHERE warehouse_uid=? AND (status='OPEN' OR (status='CLOSED' AND date=?)) ORDER BY rowid DESC");$q->execute([$warehouseUid,(string)($op['date']??'')]);
            return ['success'=>true,'data'=>array_map(function($row) use($db) { $row=self::decode($row); if ($row['status']==='OPEN') $row['data']['commission']=SalonStationCommissions::quote($db,$row['data']); return $row; },$q->fetchAll(PDO::FETCH_ASSOC))];
        }
        if ($type==='clear') {
            if (!$admin && $role!=='cajero') throw new RuntimeException('Solo Caja, Administrador o Soporte puede liberar clientes de puestos.',403);
            $uid=(string)($op['uid']??'');
            if (!preg_match('/^[\w-]{1,100}$/D',$uid) || !is_int($op['revision']??null) || $op['revision']<0) throw new RuntimeException('Cuenta o revision invalida.');
            $db->exec('BEGIN IMMEDIATE');
            try {
                $q=$db->prepare('SELECT * FROM _salon_station_accounts WHERE uid=? AND warehouse_uid=?');$q->execute([$uid,$warehouseUid]);$row=$q->fetch(PDO::FETCH_ASSOC);
                if (!$row || $row['status']!=='OPEN' || (int)$row['revision']!==$op['revision']) throw new RuntimeException('La cuenta cambio o ya fue facturada. Actualiza el puesto.',409);
                $data=json_decode($row['data'],true,512,JSON_THROW_ON_ERROR);
                $cents=0;
                foreach (($data['cart']??[]) as $line) {
                    $price=(float)($line['precio']??0);$quantity=(int)($line['cantidad']??0);
                    if (!is_finite($price) || $price<0 || $quantity<1) throw new RuntimeException('La cuenta tiene importes invalidos.');
                    $cents+=(int)round($price*$quantity*100);
                }
                if ($cents!==0 || $row['invoice_uid']!=='') throw new RuntimeException('Solo puedes quitar el cliente cuando la cuenta esta en cero.');
                $q=$db->prepare("UPDATE _salon_station_accounts SET status='CLEARED',revision=revision+1 WHERE uid=? AND warehouse_uid=? AND status='OPEN' AND revision=?");
                $q->execute([$uid,$warehouseUid,$op['revision']]);
                if ($q->rowCount()!==1) throw new RuntimeException('La cuenta cambio. Actualiza el puesto.',409);
                $db->exec('COMMIT');
                return ['success'=>true,'data'=>['uid'=>$uid,'status'=>'CLEARED']];
            } catch (\Throwable $e) {$db->exec('ROLLBACK');throw $e;}
        }
        if ($type!=='save') throw new RuntimeException('Operacion de cuenta no disponible.');
        $uid=(string)($op['uid']??'');
        if (!preg_match('/^[\w-]{1,100}$/D',$uid)) throw new RuntimeException('Cuenta invalida.');
        $adjustment=$op['priceChange']??null;
        if ($adjustment!==null) self::authorizePrices($db,$actor,(string)($op['otp']??''));
        $db->exec('BEGIN IMMEDIATE');
        try {
            $q=$db->prepare('SELECT * FROM _salon_station_accounts WHERE uid=?');$q->execute([$uid]);$old=$q->fetch(PDO::FETCH_ASSOC);
            if ($old && ($old['warehouse_uid']!==$warehouseUid || $old['status']!=='OPEN' || (int)$old['revision']!==(int)($op['revision']??-1))) throw new RuntimeException('La cuenta cambio o ya fue facturada. Actualiza el puesto.',409);
            $stationUid=$old['station_uid']??(string)($op['stationUid']??'');$date=$old['date']??(string)($op['date']??'');
            $previous=$old?json_decode($old['data'],true):null;
            if (!$old) {
                $parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
                if (!$parsed || $parsed->format('Y-m-d')!==$date) throw new RuntimeException('Fecha invalida.');
                $q=$db->prepare('SELECT valor FROM configuracion WHERE clave=?');$q->execute(['salon_planning_v1_'.$warehouseUid]);$planning=json_decode((string)$q->fetchColumn(),true)?:[];
                $station=array_column($planning['stations']??[],null,'uid')[$stationUid]??null;
                $assigned=array_values(array_filter($planning['stationAssignments']??[],fn($a)=>$a['stationUid']===$stationUid && $a['date']===$date));
                if (!$station || empty($station['active']) || count($assigned)!==1) throw new RuntimeException('Asigna una operadora activa al puesto primero.');
                $previous=['operator'=>$assigned[0]];
            }
            $operator=$previous['operator'];
            $q=$db->prepare('SELECT estado FROM usuarios WHERE id=?');$q->execute([$operator['userId']]);$state=strtoupper((string)$q->fetchColumn());
            if (!in_array($state,['ACTIVO','ACTIVADO','ACTIVA','ACTIVADA','1','TRUE'],true)) throw new RuntimeException('La operadora ya no esta activa.');
            $q=$db->prepare('SELECT * FROM clientes WHERE id=?');$q->execute([(int)($op['customerId']??0)]);$client=$q->fetch(PDO::FETCH_ASSOC);
            if (!$client || (!empty($client['almacen_uid']) && $client['almacen_uid']!==$warehouseUid)) throw new RuntimeException('Selecciona un cliente de esta sucursal.');
            $lines=$op['lines']??[];
            if (!is_array($lines) || count($lines)>100) throw new RuntimeException('La cuenta admite hasta 100 lineas.');
            $cart=[];$seen=[];
            foreach ($lines as $line) {
                $table=$line['table']??'';$id=(int)($line['id']??0);$quantity=$line['quantity']??0;
                if (!in_array($table,['servicios','accesorios'],true) || !is_int($quantity) || $quantity<1 || $quantity>1000 || isset($seen[$table.':'.$id])) throw new RuntimeException('Producto o cantidad invalida.');
                $seen[$table.':'.$id]=true;
                $q=$db->prepare("SELECT * FROM $table WHERE id=?");$q->execute([$id]);$item=$q->fetch(PDO::FETCH_ASSOC);
                if (!$item || (!empty($item['almacen_uid']) && $item['almacen_uid']!==$warehouseUid) || strtoupper((string)($item['estado']??'ACTIVO'))==='INACTIVO') throw new RuntimeException('Producto no disponible en esta sucursal.');
                $prior=null;
                foreach (($previous['cart']??[]) as $entry) if (($entry['tipo']==='servicio'?'servicios':'accesorios')===$table && (int)($entry['servicio_id']??$entry['accesorio_id']??0)===$id) {$prior=$entry;break;}
                $price=(float)($prior['precio']??$item['precio_venta']??0);
                if (!is_finite($price) || $price<0) throw new RuntimeException('Precio invalido.');
                $cart[]=['tipo'=>$table==='servicios'?'servicio':'accesorio',($table==='servicios'?'servicio_id':'accesorio_id')=>$id,'nombre'=>$item['nombre'],'codigo'=>$item['codigo_barra']??'','cantidad'=>$quantity,'precio'=>$price,'precio_normal'=>$prior['precio_normal']??$price,'costo'=>(float)($item['costo']??0),'stock'=>(float)($item['cantidad']??0),'tipo_comision'=>$item['tipo_comision']??'','valor_comision'=>(float)($item['valor_comision']??0)];
            }
            $data=['operator'=>$operator,'customer'=>array_intersect_key($client,array_flip(['id','uid','nombre','telefono','codigo','rnc','cedula','direccion','email'])),'cart'=>$cart];
            $data['priceChanges']=$previous['priceChanges']??[];
            if ($adjustment!==null) {
                if (!$old || !$cart || !is_array($adjustment)) throw new RuntimeException('Selecciona una cuenta abierta con productos.');
                $mode=$adjustment['mode']??'';
                $value=$mode==='all' ? ($adjustment['percent']??null) : ($adjustment['price']??null);
                if (!in_array($mode,['all','single'],true) || !is_numeric($value) || !is_finite((float)$value) || $value<0 || ($mode==='all' && $value>100) || ($mode==='single' && $value>100000000)) throw new RuntimeException('Precio o porcentaje invalido.');
                $changes=[];
                foreach ($data['cart'] as &$line) {
                    $table=$line['tipo']==='servicio'?'servicios':'accesorios';$id=(int)($line['servicio_id']??$line['accesorio_id']);
                    if ($mode==='single' && ($table!==($adjustment['table']??'') || $id!==(int)($adjustment['id']??0))) continue;
                    $before=$line['precio'];$line['precio']=round($mode==='all' ? $before*(1-(float)$value/100) : (float)$value,2);
                    $changes[]=['table'=>$table,'id'=>$id,'before'=>$before,'after'=>$line['precio']];
                }
                unset($line);
                if (!$changes) throw new RuntimeException('El producto ya no esta en la cuenta.');
                $data['priceChanges'][]=['at'=>gmdate('c'),'actorId'=>(int)($actor['id']??0),'actor'=>$actor['nombre']??$actor['email']??$role,'revision'=>(int)$old['revision'],'mode'=>$mode,'value'=>(float)$value,'lines'=>$changes];
            }
            $revision=$old?(int)$old['revision']+1:0;
            if ($old) $db->prepare('UPDATE _salon_station_accounts SET data=?,revision=? WHERE uid=?')->execute([json_encode($data,JSON_THROW_ON_ERROR),$revision,$uid]);
            else $db->prepare('INSERT INTO _salon_station_accounts(uid,warehouse_uid,station_uid,date,data) VALUES(?,?,?,?,?)')->execute([$uid,$warehouseUid,$stationUid,$date,json_encode($data,JSON_THROW_ON_ERROR)]);
            $q=$db->prepare('SELECT * FROM _salon_station_accounts WHERE uid=?');$q->execute([$uid]);$result=self::decode($q->fetch(PDO::FETCH_ASSOC));
            $db->exec('COMMIT');return ['success'=>true,'data'=>$result];
        } catch (\Throwable $e) {$db->exec('ROLLBACK');throw $e;}
    }
    public static function decode(array $row): array { $row['data']=json_decode($row['data'],true,512,JSON_THROW_ON_ERROR);return $row; }
    private static function authorizePrices(PDO $db,array $actor,string $code): void
    {
        // Keep failed attempts outside the account transaction so rollback cannot erase them.
        $db->exec('CREATE TABLE IF NOT EXISTS _salon_price_otp_attempts(actor TEXT PRIMARY KEY,failures INTEGER NOT NULL,started INTEGER NOT NULL)');
        $key=(string)($actor['id']??'trusted');$now=time();
        $db->exec('BEGIN IMMEDIATE');
        try {
            $q=$db->prepare('SELECT * FROM _salon_price_otp_attempts WHERE actor=?');$q->execute([$key]);$attempt=$q->fetch(PDO::FETCH_ASSOC);
            $failures=$attempt && $now-(int)$attempt['started']<300 ? (int)$attempt['failures'] : 0;
            if ($failures>=5) throw new RuntimeException('Demasiados intentos OTP. Espera cinco minutos antes de reintentar.',429);
            $exists=$db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='otp_config'")->fetchColumn();
            if (!$exists || !$db->query("SELECT 1 FROM otp_config WHERE uid='proyecto-otp-config'")->fetchColumn()) throw new RuntimeException('Configura primero el Centro OTP.');
            if (!class_exists(ProjectOtpService::class)) require_once __DIR__.'/ProjectOtpService.php';
            $valid=(new ProjectOtpService())->validate($db,$code,gmdate('Y-m-d H:i:s'));
            if ($valid) $db->prepare('DELETE FROM _salon_price_otp_attempts WHERE actor=?')->execute([$key]);
            else $db->prepare('INSERT OR REPLACE INTO _salon_price_otp_attempts(actor,failures,started) VALUES(?,?,?)')->execute([$key,$failures+1,$failures?(int)$attempt['started']:$now]);
            $db->exec('COMMIT');
        } catch (\Throwable $e) {$db->exec('ROLLBACK');throw $e;}
        if (!$valid) throw new RuntimeException('Codigo OTP incorrecto o vencido. Consulta el Centro OTP.',403);
    }
    public static function finish(PDO $db,array $invoice): array
    {
        $meta=json_decode((string)($invoice['otro']??'{}'),true)?:[];$reference=$meta['salon_account']??null;
        if (!$reference) return $invoice;
        $uid=(string)($reference['uid']??'');
        $q=$db->prepare('SELECT * FROM _salon_station_accounts WHERE uid=?');$q->execute([$uid]);$row=$q->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['status']!=='OPEN' || $row['warehouse_uid']!==($invoice['almacen_uid']??'') || (int)$row['revision']!==(int)($reference['revision']??-1)) throw new RuntimeException('La cuenta del puesto cambio o ya fue facturada. Actualiza antes de cobrar.',409);
        $data=json_decode($row['data'],true);
        if (($invoice['uid']??'')!=='salon_account_invoice_'.$uid || ($invoice['estado_factura']??'')!=='PAGADA' || (int)($invoice['cod_cliente']??0)!==(int)$data['customer']['id'] || ($invoice['vendedor']??'')!==$data['operator']['stylistName']) throw new RuntimeException('Conserva cliente y operadora del puesto al facturar.');
        $lines=json_decode((string)($invoice['productos']??'[]'),true);
        $basis=static fn($items)=>array_map(static fn($i)=>[$i['tipo'],(int)($i['servicio_id']??0),(int)($i['accesorio_id']??0),(float)$i['cantidad'],round((float)$i['precio'],2)],$items);
        if (!$data['cart'] || $basis($lines)!==$basis($data['cart'])) throw new RuntimeException('Los servicios o productos cambiaron. Edita la cuenta desde el puesto.');
        if (isset($meta['pos_commission']['row']) && (int)($meta['pos_commission']['row']['vendedor_id']??0)!==(int)$data['operator']['userId']) throw new RuntimeException('La comision debe pertenecer a la operadora del puesto.');
        $quote=SalonStationCommissions::quote($db,$data,(float)($invoice['descuento']??0));
        if ($quote['employment']==='unclassified') throw new RuntimeException('Clasifica la operadora como fija o temporal antes de facturar.');
        if ((int)($reference['policyRevision']??-1)!==(int)$quote['policyRevision']) throw new RuntimeException('Las reglas de comision cambiaron. Vuelve a abrir el cobro.',409);
        if (($reference['employment']??'')!==$quote['employment'] || (int)($reference['employmentRevision']??-1)!==$quote['revision']) throw new RuntimeException('La clasificacion de la operadora cambio. Vuelve a abrir el cobro.',409);
        unset($meta['pos_commission']);
        $meta['salon_commission']=$quote;
        $invoice['otro']=json_encode($meta,JSON_THROW_ON_ERROR);
        $data['commission']=$quote;
        $db->prepare('UPDATE _salon_station_accounts SET data=? WHERE uid=?')->execute([json_encode($data,JSON_THROW_ON_ERROR),$uid]);
        $db->prepare("UPDATE _salon_station_accounts SET status='CLOSED',invoice_uid=?,revision=revision+1 WHERE uid=?")->execute([$invoice['uid'],$uid]);
        return $invoice;
    }
}
