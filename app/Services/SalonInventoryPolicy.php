<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use RuntimeException;

final class SalonInventoryPolicy
{
    public static function get(PDO $db,string $warehouseUid): array
    {
        $q=$db->prepare('SELECT valor FROM configuracion WHERE clave=?');$q->execute(['salon_inventory_v1_'.$warehouseUid]);
        return json_decode((string)($q->fetchColumn()?:'null'),true)??['revision'=>0,'allowNegativeStock'=>false];
    }
    public static function save(PDO $db,string $warehouseUid,array $op,array $actor,bool $admin): array
    {
        if (!$admin) throw new RuntimeException('Solo Administrador o Soporte puede configurar el inventario del salon.',403);
        if (!is_bool($op['allowNegativeStock']??null)) throw new RuntimeException('Selecciona si se permite cobrar sin existencia.');
        $db->exec('BEGIN IMMEDIATE');
        try {
            $old=self::get($db,$warehouseUid);
            if ((int)($op['revision']??-1)!==(int)$old['revision']) throw new RuntimeException('La configuracion cambio. Actualiza antes de guardar.',409);
            $now=gmdate('Y-m-d H:i:s');$key='salon_inventory_v1_'.$warehouseUid;
            $data=['revision'=>(int)$old['revision']+1,'allowNegativeStock'=>$op['allowNegativeStock'],'actorId'=>(int)($actor['id']??0),'at'=>$now];
            $q=$db->prepare('SELECT 1 FROM configuracion WHERE clave=?');$q->execute([$key]);
            if ($q->fetchColumn()) $db->prepare('UPDATE configuracion SET valor=?,updated_at=? WHERE clave=?')->execute([json_encode($data),$now,$key]);
            else $db->prepare('INSERT INTO configuracion(clave,valor,categoria,uid,created_at,updated_at) VALUES(?,?,?,?,?,?)')->execute([$key,json_encode($data),'salon','salon_'.bin2hex(random_bytes(16)),$now,$now]);
            $db->exec('COMMIT');return $data;
        } catch (\Throwable $e) {$db->exec('ROLLBACK');throw $e;}
    }
    // The caller has already validated the inventory row and its warehouse.
    // Account validation, invoice, commissions and this deduction share one transaction.
    public static function allows(PDO $db,array $invoice,string $table): bool
    {
        $meta=json_decode((string)($invoice['otro']??'{}'),true)?:[];
        if ($table!=='accesorios' || empty($meta['salon_account']['uid'])) return false;
        require_once __DIR__.'/SalonRuntimeService.php';
        [, $uid]=SalonRuntimeService::warehouse($db,['id'=>$invoice['almacen_id']??0,'uid'=>$invoice['almacen_uid']??'']);
        return (self::get($db,$uid)['allowNegativeStock']??false)===true;
    }
}
