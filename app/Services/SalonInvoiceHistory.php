<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use RuntimeException;

final class SalonInvoiceHistory
{
    private static function authorize(array $actor,bool $admin): void
    {
        if ($admin) return;
        $value=$actor['permisos']??null;
        if ($value===null || $value==='') return; // Existing legacy module permission convention.
        $p=is_string($value)?json_decode($value,true):$value;
        if (!is_array($p)) throw new RuntimeException('Se requiere permiso de comisiones o nomina.',403);
        if (array_is_list($p)) {
            $keys=[];
            foreach ($p as $item) {
                if (is_string($item)) $keys[]=strtolower(trim($item));
                elseif (is_array($item) && ($item['ver']??true)!==false) foreach (['tabla','key','modulo','permiso'] as $key) if (!empty($item[$key])) $keys[]=strtolower(trim((string)$item[$key]));
            }
            $keys=array_diff($keys,['acciones-sensibles-v2']);
            if (!$keys || array_intersect($keys,['comisiones','nomina'])) return;
        } else {
            $p=array_change_key_case($p,CASE_LOWER);
            foreach (['comisiones','nomina'] as $key) if (($p[$key]??($p['contabilidad']??false))===true) return;
        }
        throw new RuntimeException('Se requiere permiso de comisiones o nomina.',403);
    }
    private static function query(PDO $db,string $sql,array $args): array
    {
        $q=$db->prepare($sql);$q->execute($args);return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function handle(PDO $db,array $input,array $actor,bool $admin): array
    {
        self::authorize($actor,$admin);
        [$wid,$wuid]=SalonRuntimeService::warehouse($db,(array)$input['warehouse']);
        $op=(array)$input['accountOperation'];$detail=($op['type']??'')==='historyDetail';
        $from=(string)($op['from']??'');$to=(string)($op['to']??'');
        foreach ([$from,$to] as $date) {
            $parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
            if (!$parsed || $parsed->format('Y-m-d')!==$date) throw new RuntimeException('Selecciona un rango de fechas valido.');
        }
        if ($from>$to) throw new RuntimeException('La fecha inicial no puede superar la final.');
        $operator=filter_var($op['operatorId']??0,FILTER_VALIDATE_INT);
        $page=filter_var($op['page']??0,FILTER_VALIDATE_INT);
        if ($operator===false || $operator<0 || $page===false || $page<0 || $page>1000000) throw new RuntimeException('Filtro o pagina invalida.');
        $base=<<<'SQL'
WITH sales AS (
 SELECT f.*,CASE WHEN json_valid(f.otro) THEN f.otro ELSE '{}' END AS metadata
 FROM facturas f WHERE f.tipo_factura='FACTURA_VENTA'
 AND (f.almacen_uid=? OR (COALESCE(f.almacen_uid,'')='' AND f.almacen_id=?))
 AND substr(f.fecha_emision,1,10)>=? AND substr(f.fecha_emision,1,10)<=?
), ledger AS (
 SELECT c.factura_id,COUNT(*) AS records,MAX(c.vendedor_id) AS sellerId,
 SUM(COALESCE(c.monto,0)) AS amount,MAX(c.total_venta) AS saleBasis,
 SUM(CASE WHEN UPPER(c.estado)='PENDIENTE' THEN c.monto ELSE 0 END) AS pending,
 SUM(CASE WHEN UPPER(c.estado)='PAGADA' THEN c.monto ELSE 0 END) AS paid,
 SUM(CASE WHEN UPPER(COALESCE(c.estado,'')) NOT IN ('PENDIENTE','PAGADA') OR c.monto<0 THEN 1 ELSE 0 END) AS unusual,
 MAX(c.fecha_pago) AS paidAt,MAX(c.estado) AS paymentStatus
 FROM comisiones c JOIN sales f ON f.id=c.factura_id
 WHERE c.almacen_uid=? OR (COALESCE(c.almacen_uid,'')='' AND COALESCE(c.almacen_id,0) IN (0,CAST(? AS INTEGER)))
 GROUP BY c.factura_id
), joined AS (
 SELECT f.id,f.uid,f.no_factura AS number,f.fecha_emision AS date,f.hora AS time,
 f.nombre_cliente AS customer,f.estado_factura AS invoiceStatus,f.total,f.metodo_pago AS method,
 CAST(COALESCE(json_extract(f.metadata,'$.salon_account.userId'),json_extract(f.metadata,'$.salon_booking.userId'),l.sellerId,0) AS INTEGER) AS operatorId,
 COALESCE(json_extract(f.metadata,'$.salon_account.stylistName'),json_extract(f.metadata,'$.salon_booking.stylistName'),NULLIF(f.vendedor,''),'Sin operadora') AS operatorName,
 COALESCE(json_extract(f.metadata,'$.salon_account.stationName'),'') AS station,
 COALESCE(json_extract(f.metadata,'$.salon_commission.total'),json_extract(f.metadata,'$.pos_commission.row.monto')) AS snapshotAmount,
 COALESCE(l.records,0) AS records,COALESCE(l.amount,0) AS recordedAmount,l.sellerId,l.saleBasis,
 COALESCE(l.pending,0) AS rawPending,COALESCE(l.paid,0) AS rawPaid,l.paidAt,l.paymentStatus,COALESCE(l.unusual,0) AS unusual
 FROM sales f LEFT JOIN ledger l ON l.factura_id=f.id
), classified AS (
 SELECT *,CASE
 WHEN UPPER(COALESCE(invoiceStatus,'')) IN ('ANULADA','CANCELADA') THEN 'EXCLUIDA'
 WHEN records>1 OR unusual>0 OR (records=1 AND sellerId<>operatorId)
 OR (snapshotAmount IS NOT NULL AND ABS(snapshotAmount-recordedAmount)>0.009)
 OR (records=1 AND ABS(COALESCE(saleBasis,total)-total)>0.009) THEN 'REVISAR'
 WHEN records=0 THEN 'SIN_COMISION' ELSE UPPER(paymentStatus) END AS commissionStatus
 FROM joined
), history AS (
 SELECT *,ROUND(COALESCE(snapshotAmount,recordedAmount),2) AS commission,
 CASE WHEN commissionStatus='PENDIENTE' THEN ROUND(rawPending,2) ELSE 0 END AS pending,
 CASE WHEN commissionStatus='PAGADA' THEN ROUND(rawPaid,2) ELSE 0 END AS paid
 FROM classified
)
SQL;
        $args=[$wuid,$wid,$from,$to,$wuid,$wid];
        $where=$operator>0?' WHERE operatorId=CAST(? AS INTEGER)':'';$filtered=$args;if ($operator>0) $filtered[]=$operator;
        $db->beginTransaction();
        try {
            if ($detail) {
                $uid=(string)($op['uid']??'');
                $rows=self::query($db,$base.' SELECT * FROM history WHERE uid=?'.($operator>0?' AND operatorId=CAST(? AS INTEGER)':''),array_merge($args,[$uid],$operator>0?[$operator]:[]));
                if (count($rows)!==1) throw new RuntimeException('La factura no pertenece a este historial o cambio de fecha. Actualiza los filtros.');
                $invoice=self::query($db,'SELECT productos,otro FROM facturas WHERE id=?',[$rows[0]['id']])[0];
                $commissions=self::query($db,'SELECT vendedor,vendedor_id,monto,estado,fecha_pago,productos FROM comisiones WHERE factura_id=? AND (almacen_uid=? OR (COALESCE(almacen_uid,\'\')=\'\' AND COALESCE(almacen_id,0) IN (0,CAST(? AS INTEGER))))',[$rows[0]['id'],$wuid,$wid]);
                $data=['invoice'=>$rows[0],'products'=>json_decode((string)$invoice['productos'],true)?:[],'snapshot'=>json_decode((string)$invoice['otro'],true)['salon_commission']??null,'commissions'=>$commissions];
            } else {
                $summary=self::query($db,$base." SELECT COUNT(*) AS invoices,ROUND(COALESCE(SUM(CASE WHEN commissionStatus<>'EXCLUIDA' THEN total ELSE 0 END),0),2) AS sales,ROUND(COALESCE(SUM(pending),0),2) AS pending,ROUND(COALESCE(SUM(paid),0),2) AS paid,COALESCE(SUM(CASE WHEN commissionStatus='REVISAR' THEN 1 ELSE 0 END),0) AS review,COALESCE(SUM(CASE WHEN commissionStatus='EXCLUIDA' THEN 1 ELSE 0 END),0) AS excluded FROM history".$where,$filtered)[0];
                $team=self::query($db,$base." SELECT operatorId,MAX(operatorName) AS name,COUNT(*) AS invoices,ROUND(SUM(pending),2) AS pending,ROUND(SUM(paid),2) AS paid FROM history".$where.' GROUP BY operatorId ORDER BY name COLLATE NOCASE',$filtered);
                $operators=self::query($db,$base.' SELECT operatorId AS id,MAX(operatorName) AS name FROM history WHERE operatorId>0 GROUP BY operatorId ORDER BY name COLLATE NOCASE',$args);
                $pages=max(1,(int)ceil((int)$summary['invoices']/25));$page=min($page,$pages-1);
                $rows=self::query($db,$base.' SELECT * FROM history'.$where.' ORDER BY date DESC,time DESC,id DESC LIMIT 25 OFFSET '.($page*25),$filtered);
                $data=['rows'=>$rows,'summary'=>$summary,'team'=>$team,'operators'=>$operators,'page'=>$page,'size'=>25,'total'=>(int)$summary['invoices']];
            }
            $db->commit();return ['success'=>true,'data'=>$data];
        } catch (\Throwable $e) {if ($db->inTransaction()) $db->rollBack();throw $e;}
    }
}
