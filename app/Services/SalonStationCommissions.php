<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use RuntimeException;

final class SalonStationCommissions
{
    private const KEY='salon_employment_v1';
    public static function rules(PDO $db): array
    {
        $q=$db->prepare('SELECT valor FROM configuracion WHERE clave=?');$q->execute(['salon_commission_rules_v1']);
        return json_decode((string)($q->fetchColumn()?:'null'),true) ?? ['revision'=>1,'fixed'=>['products'=>10,'services'=>10],'temporary'=>['products'=>10,'services'=>40]];
    }
    public static function setRules(PDO $db,array $op,array $actor,bool $admin): array
    {
        if (!$admin) throw new RuntimeException('Solo Administracion puede configurar las comisiones.',403);
        $rules=[];
        foreach (['fixed','temporary'] as $type) foreach (['products','services'] as $kind) {
            $value=$op['rules'][$type][$kind]??null;
            if (!is_numeric($value) || !is_finite((float)$value) || $value<0 || $value>100 || abs(round((float)$value,2)-(float)$value)>0.000001) throw new RuntimeException('Los porcentajes deben estar entre 0 y 100, con hasta dos decimales.');
            $rules[$type][$kind]=(float)$value;
        }
        $db->exec('BEGIN IMMEDIATE');
        try {
            $old=self::rules($db);
            if ((int)($op['revision']??-1)!==(int)$old['revision']) throw new RuntimeException('Las reglas cambiaron. Actualiza antes de guardar.',409);
            $now=gmdate('Y-m-d H:i:s');$key='salon_commission_rules_v1';
            $rules['revision']=(int)$old['revision']+1;$rules['actorId']=(int)($actor['id']??0);$rules['at']=$now;
            $q=$db->prepare('SELECT 1 FROM configuracion WHERE clave=?');$q->execute([$key]);
            if ($q->fetchColumn()) $db->prepare('UPDATE configuracion SET valor=?,updated_at=? WHERE clave=?')->execute([json_encode($rules),$now,$key]);
            else $db->prepare('INSERT INTO configuracion(clave,valor,categoria,uid,created_at,updated_at) VALUES(?,?,?,?,?,?)')->execute([$key,json_encode($rules),'salon','salon_'.bin2hex(random_bytes(16)),$now,$now]);
            $db->exec('COMMIT');return $rules;
        } catch (\Throwable $e) {$db->exec('ROLLBACK');throw $e;}
    }
    public static function staff(PDO $db): array
    {
        $q=$db->prepare('SELECT valor FROM configuracion WHERE clave=?');$q->execute([self::KEY]);
        return json_decode((string)($q->fetchColumn()?:'{}'),true)?:[];
    }
    public static function setStaff(PDO $db,array $op,array $actor,bool $admin): array
    {
        if (!$admin) throw new RuntimeException('Solo Administracion puede clasificar operadoras.',403);
        $id=(int)($op['userId']??0);$type=$op['employment']??'';
        if (!in_array($type,['fixed','temporary','unclassified'],true)) throw new RuntimeException('Clasificacion invalida.');
        $q=$db->prepare('SELECT * FROM usuarios WHERE id=?');$q->execute([$id]);$user=$q->fetch(PDO::FETCH_ASSOC);
        $role=strtolower((string)(($user['nivel_seguridad']??'')?:($user['rol']??'')));
        if (!$user || !in_array($role,['vendedor','usuario'],true)) throw new RuntimeException('Selecciona una operadora de Usuarios.');
        $db->exec('BEGIN IMMEDIATE');
        try {
            $staff=self::staff($db);$prior=$staff[$id]??null;
            if ((int)($prior['revision']??0)!==(int)($op['revision']??-1)) throw new RuntimeException('La clasificacion cambio. Actualiza antes de guardar.',409);
            $staff[$id]=['employment'=>$type,'revision'=>(int)($prior['revision']??0)+1,'actorId'=>(int)($actor['id']??0),'at'=>gmdate('c')];
            $q=$db->prepare('SELECT 1 FROM configuracion WHERE clave=?');$q->execute([self::KEY]);
            $now=gmdate('Y-m-d H:i:s');
            if ($q->fetchColumn()) $db->prepare('UPDATE configuracion SET valor=?,updated_at=? WHERE clave=?')->execute([json_encode($staff),$now,self::KEY]);
            else $db->prepare('INSERT INTO configuracion(clave,valor,categoria,uid,created_at,updated_at) VALUES(?,?,?,?,?,?)')->execute([self::KEY,json_encode($staff),'salon','salon_'.bin2hex(random_bytes(16)),$now,$now]);
            $db->exec('COMMIT');return $staff;
        } catch (\Throwable $e) {$db->exec('ROLLBACK');throw $e;}
    }
    public static function quote(PDO $db,array $data,float $discount=0): array
    {
        $person=self::staff($db)[(int)$data['operator']['userId']]??[];
        $type=$person['employment']??'unclassified';$detail=[];$products=0;$services=0;
        $rules=self::rules($db);$rates=$rules[$type]??['products'=>0,'services'=>0];
        $subtotal=array_sum(array_map(fn($i)=>(float)$i['precio']*(float)$i['cantidad'],$data['cart']));
        if (!is_finite($discount) || $discount<0 || $discount>$subtotal) throw new RuntimeException('Descuento invalido.');
        $factor=$subtotal>0?($subtotal-$discount)/$subtotal:0;
        foreach ($data['cart'] as $line) {
            $service=$line['tipo']==='servicio';$rate=$rates[$service?'services':'products'];
            $base=(float)$line['precio']*(float)$line['cantidad']*$factor;$amount=round($base*$rate/100,2);
            if ($service) $services+=$amount; else $products+=$amount;
            $detail[]=['productKey'=>$line['tipo'].':'.($line['servicio_id']??$line['accesorio_id']),'productName'=>$line['nombre'],'quantity'=>$line['cantidad'],'base'=>$base,'type'=>'PERCENTAGE','value'=>$rate,'amount'=>$amount,'source'=>'PRODUCT'];
        }
        return ['employment'=>$type,'revision'=>(int)($person['revision']??0),'policyRevision'=>$rules['revision'],'rates'=>$rates,'userId'=>(int)$data['operator']['userId'],'name'=>$data['operator']['stylistName'],'products'=>round($products,2),'services'=>round($services,2),'total'=>round($products+$services,2),'lines'=>$detail];
    }
    // Called after invoice insertion, within the same sale transaction.
    public static function record(PDO $db,int $invoiceId,array $invoice): void
    {
        $meta=json_decode((string)($invoice['otro']??'{}'),true)?:[];
        $quote=$meta['salon_commission']??null;
        if (!$quote || empty($meta['salon_account']['uid']) || $quote['total']<=0) return;
        $row=['uid'=>'salon_commission_'.$meta['salon_account']['uid'],'factura_id'=>$invoiceId,'no_factura'=>$invoice['no_factura'],'productos'=>json_encode($quote['lines']),'vendedor'=>$quote['name'],'vendedor_id'=>$quote['userId'],'total_venta'=>$invoice['total'],'porcentaje'=>0,'monto'=>$quote['total'],'estado'=>'PENDIENTE','usuario'=>$invoice['usuario']??'POS','almacen_id'=>$invoice['almacen_id'],'almacen_uid'=>$invoice['almacen_uid'],'created_at'=>gmdate('Y-m-d H:i:s'),'updated_at'=>gmdate('Y-m-d H:i:s')];
        $columns=implode(',',array_keys($row));$placeholders=implode(',',array_fill(0,count($row),'?'));
        $db->prepare("INSERT INTO comisiones($columns) VALUES($placeholders)")->execute(array_values($row));
    }
}
