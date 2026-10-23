<?php
declare(strict_types=1);
namespace App\Services;

use PDO;
use RuntimeException;

final class SalonRuntimeService
{
    private static function exists(PDO $db, string $table): bool
    {
        $q = $db->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
        $q->execute([$table]);
        return (bool) $q->fetchColumn();
    }

    public static function warehouse(PDO $db, array $warehouse): array
    {
        $id = (int) ($warehouse['id'] ?? 0); $uid = (string) ($warehouse['uid'] ?? '');
        if ($id < 1 || $uid === '' || !self::exists($db, 'apariencia_almacen')) throw new RuntimeException('Selecciona una sucursal en modo salon.');
        $q = $db->prepare('SELECT 1 FROM apariencia_almacen WHERE almacen_id=? AND almacen_uid=? AND modo_tienda=?');
        $q->execute([$id, $uid, 'salon']);
        if (!$q->fetchColumn()) throw new RuntimeException('Esta funcion solo esta disponible en modo salon.');
        return [$id, $uid];
    }

    public static function install(PDO $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS _salon_deposits (uid TEXT PRIMARY KEY, appointment_uid TEXT NOT NULL, amount_cents INTEGER NOT NULL CHECK(amount_cents>0), bank_id INTEGER NOT NULL, bank_name TEXT NOT NULL, reference TEXT NOT NULL, warehouse_id INTEGER NOT NULL, warehouse_uid TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'DISPONIBLE', invoice_uid TEXT, actor TEXT NOT NULL, created_at TEXT NOT NULL, refunded_at TEXT)");
        $db->exec('CREATE INDEX IF NOT EXISTS salon_deposit_appointment ON _salon_deposits(appointment_uid,status)');
    }

    public static function handle(PDO $db, string $channel, array $input, array $actor, bool $admin): array
    {
        if (in_array($channel,['salon:guardarInsumos','salon:guardarPlanificacion'],true)) {
            self::warehouse($db,(array)($input['warehouse']??[]));
            if ($channel==='salon:guardarPlanificacion' && isset($input['accountOperation'])) {
                if (in_array($input['accountOperation']['type']??'',['history','historyDetail'],true)) {
                    require_once __DIR__.'/SalonInvoiceHistory.php';
                    return SalonInvoiceHistory::handle($db,$input,$actor,$admin);
                }
                require_once __DIR__.'/SalonStationAccounts.php';
                return SalonStationAccounts::handle($db,$input,$actor,$admin);
            }
            return $channel==='salon:guardarInsumos' ? SalonSuppliesStore::save($db,$input,$admin) : SalonPlanningStore::save($db,$input,$admin,$actor);
        }
        $roles = ['admin'=>'administrador','ceo'=>'administrador','administrador'=>'administrador','soporte'=>'soporte','cajero'=>'cajero'];
        $level = strtolower(trim((string)($actor['nivel_seguridad'] ?? '')));
        $role = $roles[$level] ?? strtolower(trim((string)($actor['rol'] ?? '')));
        if (!$admin && $role !== 'cajero') throw new RuntimeException('Solo Caja, Administrador o Soporte puede gestionar anticipos.',403);
        [$warehouseId, $warehouseUid] = self::warehouse($db, (array)($input['warehouse'] ?? []));
        self::install($db);
        if ($channel === 'salon:listarAnticipos') {
            $q = $db->prepare('SELECT * FROM _salon_deposits WHERE warehouse_id=? AND warehouse_uid=? ORDER BY created_at DESC');
            $q->execute([$warehouseId,$warehouseUid]);
            return ['success'=>true,'data'=>$q->fetchAll(PDO::FETCH_ASSOC)];
        }
        $db->exec('BEGIN IMMEDIATE');
        try {
            $uid = (string)($input['uid'] ?? '');
            if (!preg_match('/^[A-Za-z0-9_-]{8,100}$/D',$uid)) throw new RuntimeException('Identificador de anticipo invalido.');
            $q = $db->prepare('SELECT * FROM _salon_deposits WHERE uid=?'); $q->execute([$uid]); $old = $q->fetch(PDO::FETCH_ASSOC);
            if ($old && ((int)$old['warehouse_id'] !== $warehouseId || $old['warehouse_uid'] !== $warehouseUid)) throw new RuntimeException('El anticipo pertenece a otra sucursal.');
            if ($channel === 'salon:registrarAnticipo') {
                $amount = (float)($input['amount'] ?? 0); $cents = (int)round($amount*100);
                if (!is_finite($amount) || $cents<=0 || $cents>100000000 || abs($amount*100-$cents)>0.001) throw new RuntimeException('Indica un monto positivo con hasta dos decimales.');
                $appointmentUid = (string)($input['appointmentUid'] ?? ''); $bankId = (int)($input['bankId'] ?? 0);
                $reference = trim((string)($input['reference'] ?? ''));
                if ($reference === '' || mb_strlen($reference)>160) throw new RuntimeException('Indica la referencia de transferencia (maximo 160 caracteres).');
                if ($old) {
                    if ($old['appointment_uid'] !== $appointmentUid || (int)$old['amount_cents'] !== $cents || (int)$old['bank_id'] !== $bankId || $old['reference'] !== $reference) throw new RuntimeException('El reintento no coincide con el anticipo original.');
                } else {
                    $q=$db->prepare('SELECT 1 FROM _salon_deposits WHERE bank_id=? AND reference=? COLLATE NOCASE');$q->execute([$bankId,$reference]);
                    if ($q->fetchColumn()) throw new RuntimeException('Esta referencia bancaria ya fue registrada como anticipo.');
                    $q=$db->prepare('SELECT * FROM citas_spa WHERE uid=?');$q->execute([$appointmentUid]);$appointment=$q->fetch(PDO::FETCH_ASSOC);
                    if (!$appointment || $appointment['almacen_uid'] !== $warehouseUid || (int)$appointment['almacen_id'] !== $warehouseId || !in_array($appointment['estado'],['PENDIENTE','CONFIRMADA'],true)) throw new RuntimeException('La cita no esta disponible en esta sucursal.');
                    $q=$db->prepare('SELECT 1 FROM facturas WHERE uid=?');$q->execute(['salon_invoice_'.$appointmentUid]);
                    if ($q->fetchColumn()) throw new RuntimeException('La cita ya fue facturada.');
                    $booking=json_decode((string)($appointment['nota']??''),true)['tmpos_salon']??null;
                    if (!$booking || !isset($booking['p'])) throw new RuntimeException('Asigna profesional y servicio antes de recibir un anticipo.');
                    $q=$db->prepare("SELECT COALESCE(SUM(amount_cents),0) FROM _salon_deposits WHERE appointment_uid=? AND status='DISPONIBLE'");$q->execute([$appointmentUid]);
                    if ((int)$q->fetchColumn()+$cents>(int)round((float)$booking['p']*100)) throw new RuntimeException('Los anticipos no pueden superar el precio reservado.');
                    $q=$db->prepare('SELECT * FROM bancos WHERE id=?');$q->execute([$bankId]);$bank=$q->fetch(PDO::FETCH_ASSOC);
                    if (!$bank || (!empty($bank['almacen_uid']) && $bank['almacen_uid']!==$warehouseUid)) throw new RuntimeException('Selecciona un banco de la sucursal.');
                    $db->prepare('INSERT INTO _salon_deposits(uid,appointment_uid,amount_cents,bank_id,bank_name,reference,warehouse_id,warehouse_uid,actor,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([$uid,$appointmentUid,$cents,$bankId,$bank['nombre']??'', $reference,$warehouseId,$warehouseUid,(string)($actor['usuario']??$actor['email']??'Caja'),gmdate('Y-m-d H:i:s')]);
                    $db->prepare('UPDATE bancos SET saldo=saldo+?, updated_at=? WHERE id=?')->execute([$cents/100,gmdate('Y-m-d H:i:s'),$bankId]);
                }
            } elseif ($channel === 'salon:devolverAnticipo') {
                if (!$old || $old['status']==='APLICADO') throw new RuntimeException('Solo se pueden devolver anticipos disponibles.');
                if ($old['status'] !== 'DEVUELTO') {
                    $q=$db->prepare('UPDATE bancos SET saldo=saldo-?, updated_at=? WHERE id=? AND saldo>=?');
                    $q->execute([$old['amount_cents']/100,gmdate('Y-m-d H:i:s'),$old['bank_id'],$old['amount_cents']/100]);
                    if ($q->rowCount()!==1) throw new RuntimeException('El banco no existe o no tiene saldo suficiente para registrar la devolucion.');
                    $db->prepare("UPDATE _salon_deposits SET status='DEVUELTO',refunded_at=? WHERE uid=?")->execute([gmdate('Y-m-d H:i:s'),$uid]);
                }
            } else throw new RuntimeException('Operacion de salon no disponible.');
            $q=$db->prepare('SELECT * FROM _salon_deposits WHERE uid=?');$q->execute([$uid]);$result=$q->fetch(PDO::FETCH_ASSOC);
            $db->exec('COMMIT'); return ['success'=>true,'data'=>$result];
        } catch (\Throwable $e) { $db->exec('ROLLBACK'); throw $e; }
    }

    /** Called inside the existing atomic invoice transaction, after idempotency lookup. */
    public static function applyDeposit(PDO $db, array $invoice, array $payload): array
    {
        if (isset(json_decode((string)($invoice['otro']??'{}'),true)['salon_account'])) {
            require_once __DIR__.'/SalonStationAccounts.php';
            $invoice=SalonStationAccounts::finish($db,$invoice);
        }
        $meta=json_decode((string)($invoice['otro']??'{}'),true) ?: [];
        $booking=$meta['salon_booking']??null;
        if (!$booking) return $invoice;
        $appointmentUid=(string)($booking['appointmentUid']??'');
        $entries=[];
        if (self::exists($db,'_salon_deposits')) {
            $q=$db->prepare("SELECT * FROM _salon_deposits WHERE appointment_uid=? AND status='DISPONIBLE' ORDER BY uid");$q->execute([$appointmentUid]);$entries=$q->fetchAll(PDO::FETCH_ASSOC);
        }
        $cents=array_sum(array_column($entries,'amount_cents'));
        $expected=(int)round((float)($meta['salon_deposit']['amount']??0)*100);
        if ($expected!==$cents) throw new RuntimeException('Los anticipos de la cita cambiaron. Actualiza el cobro antes de continuar.');
        if (!$cents) return $invoice;
        if (($invoice['uid']??'')!=='salon_invoice_'.$appointmentUid) throw new RuntimeException('La factura no corresponde a la cita del anticipo.');
        self::warehouse($db,['id'=>$invoice['almacen_id']??0,'uid'=>$invoice['almacen_uid']??'']);
        $q=$db->prepare('SELECT * FROM citas_spa WHERE uid=?');$q->execute([$appointmentUid]);$appointment=$q->fetch(PDO::FETCH_ASSOC);
        if (!$appointment || !in_array($appointment['estado'],['PENDIENTE','CONFIRMADA'],true) || $appointment['almacen_uid']!==$invoice['almacen_uid']) throw new RuntimeException('La cita del anticipo ya no esta disponible.');
        $normalize=static fn($s)=>preg_replace('/\s+/u',' ',mb_strtoupper(trim((string)$s),'UTF-8'));
        if ($normalize($appointment['nombre_cliente'])!==$normalize($invoice['nombre_cliente']??'')) throw new RuntimeException('Conserva el cliente de la cita al aplicar el anticipo.');
        foreach ($entries as $entry) if ($entry['warehouse_uid']!==$invoice['almacen_uid']) throw new RuntimeException('Anticipo de otra sucursal.');
        $total=(int)round((float)($invoice['total']??0)*100);
        if ($cents>$total || ($invoice['metodo_pago']??'')!=='EFECTIVO' || !empty($payload['bancos']) || !empty($payload['cuenta_cobrar']) || ($invoice['estado_factura']??'')!=='PAGADA') throw new RuntimeException('Con anticipo, cobra el saldo en efectivo. El total debe cubrir el anticipo.');
        if (abs((float)($invoice['efectivo']??0)-($total-$cents)/100)>0.005 || (float)($invoice['tarjeta']??0)!==0.0 || (float)($invoice['transferencia']??0)!==0.0 || (float)($invoice['cheque']??0)!==0.0) throw new RuntimeException('El cobro no coincide con el saldo despues del anticipo.');
        $meta['salon_deposit']=['amount'=>$cents/100,'uids'=>array_column($entries,'uid'),'method'=>'TRANSFERENCIA'];
        $invoice['otro']=json_encode($meta,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $db->prepare("UPDATE _salon_deposits SET status='APLICADO',invoice_uid=? WHERE appointment_uid=? AND status='DISPONIBLE'")->execute([$invoice['uid']??'',$appointmentUid]);
        return $invoice;
    }
}
