<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Support;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/** Browser-compatible runtime for TMPOS.
 *
 * Every call receives an already-authorized project. No database path or API
 * key is accepted from the browser, which keeps tenant selection server-side.
 */
final class SystemRuntimeService
{
    public function __construct(private SchemaService $schema, private LogService $logs, private SharedDocumentService $sharedDocuments)
    {
    }

    public function isWrite(string $action, array $input): bool
    {
        if (in_array($action, ['db/insert', 'db/update', 'db/delete', 'config/set'], true)) return true;
        if ($action !== 'invoke') return false;
        $channel = (string) ($input['channel'] ?? '');
        if (str_starts_with($channel, 'db:get') || in_array($channel, [
            'config:get', 'auth:login', 'caja:getTurnoActivo', 'caja:getTurnoAbierto',
            'cuadre:listar', 'cuadre:ventasTurno', 'cuadre:gastosTurno', 'app:getName',
            'app:getVersion', 'getServerUrl', 'getPrinters', 'scan:bluetooth',
        ], true)) return false;
        if ($channel === 'consultaservidor') {
            $op = (string) (($input['args'] ?? [])[0] ?? '');
            return !in_array($op, ['getAllConfig', 'tableExists', 'getTableColumns', 'getAllTables', 'getCreateTableSQL', 'getTableRowCount', 'tableAdminInfo'], true);
        }
        return true;
    }

    public function handle(array $project, string $action, array $input, array $actor): mixed
    {
        $db = $this->schema->connection($project);
        return match ($action) {
            'db/getAll' => $this->getAll($db, $input),
            'db/getWhere' => $this->getWhere($db, $input),
            'db/getModified' => $this->getModified($db, $input),
            'db/getById' => $this->getById($db, $input),
            'db/insert' => $this->insert($db, $project, $input, $actor),
            'db/update' => $this->update($db, $project, $input, $actor),
            'db/delete' => $this->delete($db, $project, $input, $actor),
            'db/bitacoraList' => $this->bitacora($db, (int) ($input['limite'] ?? 1000)),
            'db/bitacoraDeleteAll' => $this->clearBitacora($db),
            'config/get' => $this->configGet($db, (string) ($input['clave'] ?? '')),
            'config/set' => $this->configSet($db, $input),
            'invoke' => $this->invoke($db, $project, (string) ($input['channel'] ?? ''), (array) ($input['args'] ?? []), $actor),
            default => throw new RuntimeException('Accion del sistema no disponible.', 404),
        };
    }

    public function authenticate(array $project, array $input): array
    {
        return $this->authLogin($this->schema->connection($project), $input);
    }

    private function getAll(PDO $db, array $input): array
    {
        $table = $this->table($db, (string) ($input['tabla'] ?? ''));
        return ['success' => true, 'data' => $db->query("SELECT * FROM $table ORDER BY id ASC")->fetchAll()];
    }

    private function getWhere(PDO $db, array $input): array
    {
        $table = $this->table($db, (string) ($input['tabla'] ?? ''));
        $where = trim((string) ($input['where'] ?? ''));
        if ($where === '' || preg_match('/(;|--|\/\*|\*\/|\b(?:ATTACH|DETACH|PRAGMA|VACUUM|DROP|ALTER|CREATE|INSERT|UPDATE|DELETE|REPLACE)\b)/i', $where)) {
            throw new InvalidArgumentException('Filtro SQL no valido.');
        }
        $params = array_values((array) ($input['params'] ?? []));
        $stmt = $db->prepare("SELECT * FROM $table WHERE $where");
        $stmt->execute($params);
        return ['success' => true, 'data' => $stmt->fetchAll()];
    }

    private function getModified(PDO $db, array $input): array
    {
        $table = $this->table($db, (string) ($input['tabla'] ?? ''));
        $stmt = $db->prepare("SELECT * FROM $table WHERE updated_at >= ? ORDER BY updated_at ASC");
        $stmt->execute([(string) ($input['desde'] ?? '')]);
        return ['success' => true, 'data' => $stmt->fetchAll()];
    }

    private function getById(PDO $db, array $input): array
    {
        $table = $this->table($db, (string) ($input['tabla'] ?? ''));
        $stmt = $db->prepare("SELECT * FROM $table WHERE id = ? LIMIT 1");
        $stmt->execute([(int) ($input['id'] ?? 0)]);
        return ['success' => true, 'data' => $stmt->fetch() ?: null];
    }

    private function insert(PDO $db, array $project, array $input, array $actor): array
    {
        $tableName = (string) ($input['tabla'] ?? '');
        $table = $this->table($db, $tableName);
        $this->ensureAccessoryCommissionColumns($db, $tableName);
        $data = $this->cleanData($db, $tableName, (array) ($input['data'] ?? []), true);
        $now = gmdate('Y-m-d H:i:s');
        if ($this->hasColumn($db, $tableName, 'uid') && empty($data['uid'])) $data['uid'] = Support::uid('rec_');
        if ($this->hasColumn($db, $tableName, 'created_at') && empty($data['created_at'])) $data['created_at'] = $now;
        if ($this->hasColumn($db, $tableName, 'updated_at') && empty($data['updated_at'])) $data['updated_at'] = $now;
        if (!$data) throw new InvalidArgumentException('No hay datos para guardar.');
        $columns = array_keys($data);
        $sql = "INSERT INTO $table (" . implode(',', array_map([Support::class, 'quoteIdentifier'], $columns)) . ') VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')';
        $db->prepare($sql)->execute(array_values($data));
        $id = (int) $db->lastInsertId();
        $this->audit($db, $tableName, $id, 'CREATE', $actor, $data, null);
        $this->logs->write('system.record.created', $project['uid'], $tableName, (string) ($data['uid'] ?? $id), null, ['actor' => $actor['email'] ?? '', 'id' => $id]);
        return ['success' => true, 'data' => ['id' => $id, 'uid' => $data['uid'] ?? null]];
    }

    private function update(PDO $db, array $project, array $input, array $actor): array
    {
        $tableName = (string) ($input['tabla'] ?? '');
        $table = $this->table($db, $tableName);
        $this->ensureAccessoryCommissionColumns($db, $tableName);
        $id = (int) ($input['id'] ?? 0);
        $old = $this->row($db, $table, $id);
        $data = $this->cleanData($db, $tableName, (array) ($input['data'] ?? []), false);
        unset($data['id'], $data['uid'], $data['created_at']);
        if ($this->hasColumn($db, $tableName, 'updated_at')) $data['updated_at'] = gmdate('Y-m-d H:i:s');
        if (!$data) return ['success' => true, 'data' => ['id' => $id]];
        $sets = implode(',', array_map(fn (string $column): string => Support::quoteIdentifier($column) . ' = ?', array_keys($data)));
        $db->prepare("UPDATE $table SET $sets WHERE id = ?")->execute([...array_values($data), $id]);
        $this->audit($db, $tableName, $id, 'UPDATE', $actor, $data, $old);
        $this->logs->write('system.record.updated', $project['uid'], $tableName, (string) ($old['uid'] ?? $id), $old, ['actor' => $actor['email'] ?? '', 'changes' => $data]);
        return ['success' => true, 'data' => ['id' => $id]];
    }

    private function delete(PDO $db, array $project, array $input, array $actor): array
    {
        $tableName = (string) ($input['tabla'] ?? '');
        $table = $this->table($db, $tableName);
        $this->ensureAccessoryCommissionColumns($db, $tableName);
        $id = (int) ($input['id'] ?? 0);
        $old = $this->row($db, $table, $id);
        $db->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
        $this->audit($db, $tableName, $id, 'DELETE', $actor, null, $old);
        $this->logs->write('system.record.deleted', $project['uid'], $tableName, (string) ($old['uid'] ?? $id), $old, ['actor' => $actor['email'] ?? '']);
        return ['success' => true];
    }

    private function invoke(PDO $db, array $project, string $channel, array $args, array $actor): mixed
    {
        if ($channel === 'auth:login') return $this->authLogin($db, (array) ($args[0] ?? []));
        if ($channel === 'config:get') return $this->configGet($db, (string) ($args[0] ?? ''));
        if ($channel === 'config:set') return $this->configSet($db, ['clave' => $args[0] ?? '', 'valor' => $args[1] ?? '', 'categoria' => $args[2] ?? 'general']);
        if ($channel === 'facturas:crearEnlacePdf') return $this->shareInvoice($db, $project, (array) ($args[0] ?? []));
        if ($channel === 'db:exec') return $this->executeSql($db, (string) ($args[0] ?? ''), true);
        if ($channel === 'consultaservidor') return $this->consultaServidor($db, $args);
        if (in_array($channel, ['caja:getTurnoActivo', 'caja:getTurnoAbierto'], true)) return $this->turnoActivo($db, (string) ($args[0] ?? ''));
        if ($channel === 'caja:abrirTurno') return $this->abrirTurno($db, (array) ($args[0] ?? []));
        if ($channel === 'caja:cerrarTurno') return $this->cerrarTurno($db, (int) ($args[0] ?? 0), (array) ($args[1] ?? []));
        if ($channel === 'caja:getMovimientos') return $this->movimientosCaja($db, (int) ($args[0] ?? 0));
        if ($channel === 'caja:registrarMovimiento') return $this->registrarMovimientoCaja($db, (array) ($args[0] ?? []));
        if ($channel === 'cuadre:listar') return $this->listarCuadres($db, (string) ($args[0] ?? ''));
        if ($channel === 'cuadre:ventasTurno') return $this->ventasTurno($db, (string) ($args[0] ?? ''));
        if ($channel === 'cuadre:gastosTurno') return $this->gastosTurno($db, (string) ($args[0] ?? ''));
        if ($channel === 'cuadre:realizar') return $this->realizarCuadre($db, (array) ($args[0] ?? []));
        if ($channel === 'gastos:guardarConPago') return $this->guardarGasto($db, (array) ($args[0] ?? []));
        if ($channel === 'gastos:eliminarConPago') return $this->eliminarGasto($db, (int) ($args[0] ?? 0));
        if ($channel === 'ventas:guardarAtomica') return $this->guardarVenta($db, (array) ($args[0] ?? []));
        if ($channel === 'ventas:cobrarPendiente') return $this->cobrarPendiente($db, (array) ($args[0] ?? []));
        if ($channel === 'transferencia:realizar') return $this->transferir($db, (array) ($args[0] ?? []));
        if ($channel === 'ajuste:realizar') return $this->ajustar($db, (array) ($args[0] ?? []));
        if ($channel === 'precio:registrarHistorial') return $this->registrarPrecios($db, (array) ($args[0] ?? []));
        if ($channel === 'auditoria:registrar') { $payload = (array) ($args[0] ?? []); $this->audit($db, (string) ($payload['tabla'] ?? 'sistema'), (int) ($payload['registro_id'] ?? 0), (string) ($payload['accion'] ?? 'ACTION'), $actor, $payload['datos_nuevos'] ?? null, $payload['datos_anteriores'] ?? null); return ['success' => true]; }
        if ($channel === 'app:getName') return 'TMPOS Web';
        if ($channel === 'app:getVersion') return '2.13.3-web';
        if ($channel === 'getServerUrl') return ['success' => true, 'url' => '/sistema'];
        if (str_starts_with($channel, 'licencia:')) return ['success' => true, 'estado' => 'activo', 'data' => ['estado' => 'activo', 'nombre_empresa' => $project['name'], 'diasRestantes' => null]];
        if (in_array($channel, ['getPrinters', 'scan:bluetooth'], true)) return ['success' => true, 'data' => []];
        if ($channel === 'backup:create') return ['success' => true, 'data' => ['server_managed' => true]];
        if ($channel === 'backup:list') return ['success' => true, 'data' => []];
        throw new RuntimeException("La funcion $channel aun no esta disponible en el navegador.", 422);
    }

    private function shareInvoice(PDO $db, array $project, array $input): array
    {
        $recordUid = trim((string) ($input['record_uid'] ?? ''));
        if ($recordUid === '') throw new InvalidArgumentException('La factura no tiene identificador de sincronizacion.');
        $stmt = $db->prepare('SELECT uid FROM facturas WHERE uid = ? LIMIT 1');
        $stmt->execute([$recordUid]);
        if (!$stmt->fetchColumn()) throw new RuntimeException('La factura no existe o aun no esta sincronizada.', 404);
        $expiresAt = gmdate('Y-m-d H:i:s', time() + 2592000);
        return ['success' => true, 'data' => $this->sharedDocuments->create(
            (string) $project['uid'],
            'invoice',
            'facturas',
            $recordUid,
            $expiresAt
        )];
    }

    private function authLogin(PDO $db, array $input): array
    {
        $mode = (string) ($input['mode'] ?? 'credentials');
        $userColumns = $this->columns($db, 'usuarios');
        $activeFilters = [];
        if (in_array('estado', $userColumns, true)) {
            $activeFilters[] = "UPPER(TRIM(COALESCE(estado,''))) IN ('ACTIVADO','ACTIVO')";
        }
        if (in_array('activo', $userColumns, true)) {
            $activeFilters[] = 'COALESCE(activo, 1) = 1';
        }
        if (in_array('deleted_at', $userColumns, true)) {
            $activeFilters[] = 'deleted_at IS NULL';
        }
        $activeSql = $activeFilters ? ' AND ' . implode(' AND ', $activeFilters) : '';

        if ($mode === 'pin') {
            $pin = trim((string) ($input['pin'] ?? ''));
            if (preg_match('/^\d{4}$/D', $pin) !== 1) return ['success' => false, 'error' => 'El PIN debe tener 4 digitos'];
            $stmt = $db->prepare("SELECT * FROM usuarios WHERE TRIM(pin) = ?$activeSql LIMIT 1");
            $stmt->execute([$pin]);
            $user = $stmt->fetch();
        } else {
            $identity = strtolower(trim((string) ($input['usuario'] ?? '')));
            $secret = (string) ($input['password'] ?? '');
            $stmt = $db->prepare("SELECT * FROM usuarios WHERE (LOWER(TRIM(usuario))=? OR LOWER(TRIM(email))=? OR LOWER(TRIM(nombre))=?)$activeSql LIMIT 1");
            $stmt->execute([$identity, $identity, $identity]);
            $candidate = $stmt->fetch();
            $password = (string) ($candidate['password'] ?? '');
            $pin = (string) ($candidate['pin'] ?? '');
            $valid = $candidate && (
                ($password !== '' && hash_equals($password, $secret)) ||
                ($password !== '' && password_verify($secret, $password)) ||
                ($pin !== '' && hash_equals($pin, $secret))
            );
            $user = $valid ? $candidate : false;
        }
        return $user ? ['success' => true, 'data' => $user] : ['success' => false, 'error' => $mode === 'pin' ? 'PIN incorrecto' : 'Usuario o contrasena incorrectos'];
    }

    private function guardarVenta(PDO $db, array $payload): array
    {
        $factura = (array) ($payload['factura'] ?? []);
        if (trim((string) ($factura['no_factura'] ?? '')) === '') throw new InvalidArgumentException('La venta no tiene numero de factura.');
        $db->beginTransaction();
        try {
            $exists = $db->prepare('SELECT 1 FROM facturas WHERE no_factura = ? LIMIT 1');
            $exists->execute([$factura['no_factura']]);
            if ($exists->fetchColumn()) throw new RuntimeException('La factura ya existe.');
            $facturaId = $this->insertRaw($db, 'facturas', $factura);
            if (!empty($payload['cuenta_cobrar'])) $this->insertRaw($db, 'cuentas_cobrar', (array) $payload['cuenta_cobrar']);
            if (!empty($payload['comprobante_id'])) $db->prepare('UPDATE comprobantes_fiscales SET secuencia_actual=secuencia_actual+1, updated_at=? WHERE id=?')->execute([gmdate('Y-m-d H:i:s'), (int) $payload['comprobante_id']]);
            foreach ((array) ($payload['inventario'] ?? []) as $item) {
                $tableName = (string) ($item['tabla'] ?? '');
                if (!in_array($tableName, ['imei', 'serial', 'accesorios'], true)) throw new InvalidArgumentException('Producto de inventario no valido.');
                $table = Support::quoteIdentifier($tableName);
                if ($tableName === 'accesorios') $db->prepare("UPDATE $table SET cantidad=cantidad-?, updated_at=? WHERE id=? AND cantidad>=?")->execute([(float) ($item['cantidad'] ?? 0), gmdate('Y-m-d H:i:s'), (int) $item['id'], (float) ($item['cantidad'] ?? 0)]);
                else $this->updateRaw($db, $tableName, (int) $item['id'], (array) ($item['cambios'] ?? []));
            }
            foreach ((array) ($payload['bancos'] ?? []) as $mov) if ((int) ($mov['id'] ?? 0) > 0 && (float) ($mov['monto'] ?? 0) > 0) $db->prepare('UPDATE bancos SET saldo=saldo+?, fecha_transaccion=?, updated_at=? WHERE id=?')->execute([(float) $mov['monto'], gmdate('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s'), (int) $mov['id']]);
            $db->commit();
            return ['success' => true, 'data' => ['id' => $facturaId]];
        } catch (\Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
    }

    private function cobrarPendiente(PDO $db, array $p): array
    {
        $id = (int) ($p['factura_id'] ?? 0);
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT * FROM facturas WHERE id=? AND UPPER(COALESCE(estado_factura,''))='PENDIENTE'"); $stmt->execute([$id]);
            $factura = $stmt->fetch() ?: throw new RuntimeException('La factura no esta pendiente.');
            $total = (float) ($factura['total'] ?? 0); $efectivo=(float)($p['efectivo']??0); $tarjeta=(float)($p['tarjeta']??0); $transferencia=(float)($p['transferencia']??0);
            if (abs(($efectivo+$tarjeta+$transferencia)-$total) >= .01) throw new InvalidArgumentException('La distribucion del pago no coincide con el total.');
            $db->prepare("UPDATE facturas SET estado_factura='PAGADA', metodo_pago=?, efectivo=?, tarjeta=?, transferencia=?, updated_at=? WHERE id=?")->execute([(string)($p['metodo_pago']??''),$efectivo,$tarjeta,$transferencia,gmdate('Y-m-d H:i:s'),$id]);
            if ((int)($p['banco_id']??0)>0 && $tarjeta+$transferencia>0) $db->prepare('UPDATE bancos SET saldo=saldo+?, updated_at=? WHERE id=?')->execute([$tarjeta+$transferencia,gmdate('Y-m-d H:i:s'),(int)$p['banco_id']]);
            $db->commit(); return ['success'=>true,'data'=>['id'=>$id]];
        } catch (\Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
    }

    private function turnoActivo(PDO $db, string $almacen): array
    {
        if ($almacen !== '') { $stmt=$db->prepare("SELECT * FROM caja_turnos WHERE estado='abierto' AND (almacen_uid=? OR COALESCE(almacen_uid,'')='') ORDER BY CASE WHEN almacen_uid=? THEN 0 ELSE 1 END,id DESC LIMIT 1"); $stmt->execute([$almacen,$almacen]); }
        else $stmt=$db->query("SELECT * FROM caja_turnos WHERE estado='abierto' ORDER BY id DESC LIMIT 1");
        return ['success'=>true,'data'=>$stmt->fetch()?:null];
    }

    private function abrirTurno(PDO $db, array $data): array { return ['success'=>true,'data'=>['id'=>$this->insertRaw($db,'caja_turnos',$data+['entradas'=>0,'retiros'=>0,'estado'=>'abierto'])]]; }
    private function cerrarTurno(PDO $db, int $id, array $data): array { $db->prepare("UPDATE caja_turnos SET estado='cerrado',monto_final=?,efectivo_esperado=?,diferencia=?,cierre_ciego=?,updated_at=? WHERE id=? AND estado='abierto'")->execute([(float)($data['monto_final']??0),(float)($data['efectivo_esperado']??0),(float)($data['diferencia']??0),!empty($data['cierre_ciego'])?1:0,gmdate('Y-m-d H:i:s'),$id]); return ['success'=>true]; }

    private function movimientosCaja(PDO $db, int $turnoId): array
    {
        if (!$this->exists($db, 'caja_movimientos')) return ['success' => true, 'data' => []];
        $stmt = $db->prepare('SELECT * FROM caja_movimientos WHERE turno_id=? ORDER BY id DESC'); $stmt->execute([$turnoId]);
        return ['success' => true, 'data' => $stmt->fetchAll()];
    }

    private function registrarMovimientoCaja(PDO $db, array $data): array
    {
        $id = $this->insertRaw($db, 'caja_movimientos', $data);
        $type = strtolower((string) ($data['tipo'] ?? 'entrada'));
        $column = in_array($type, ['retiro', 'salida'], true) ? 'retiros' : 'entradas';
        $db->prepare("UPDATE caja_turnos SET $column=COALESCE($column,0)+?,updated_at=? WHERE id=?")->execute([(float) ($data['monto'] ?? $data['cantidad'] ?? 0), gmdate('Y-m-d H:i:s'), (int) ($data['turno_id'] ?? 0)]);
        return ['success' => true, 'data' => ['id' => $id]];
    }

    private function listarCuadres(PDO $db, string $warehouse): array
    {
        if ($warehouse !== '') { $stmt=$db->prepare("SELECT * FROM cuadres WHERE almacen_uid=? OR COALESCE(almacen_uid,'')='' ORDER BY created_at DESC");$stmt->execute([$warehouse]); }
        else $stmt=$db->query('SELECT * FROM cuadres ORDER BY created_at DESC');
        return ['success'=>true,'data'=>$stmt->fetchAll()];
    }

    private function ventasTurno(PDO $db, string $warehouse): array
    {
        $turno=$this->turnoActivo($db,$warehouse)['data'];
        if(!$turno)return['success'=>true,'data'=>['total'=>0,'efectivo'=>0,'tarjeta'=>0,'transferencia'=>0,'abonos_cxc'=>0,'cantidad_abonos_cxc'=>0]];
        $where="estado_factura='PAGADA' AND created_at>=?"; $params=[$turno['created_at']]; if($warehouse!==''){$where.=" AND (almacen_uid=? OR COALESCE(almacen_uid,'')='')";$params[]=$warehouse;}
        $stmt=$db->prepare("SELECT metodo_pago,total,efectivo,tarjeta,transferencia FROM facturas WHERE $where");$stmt->execute($params);$totals=['total'=>0.0,'efectivo'=>0.0,'tarjeta'=>0.0,'transferencia'=>0.0,'abonos_cxc'=>0.0,'cantidad_abonos_cxc'=>0];
        foreach($stmt->fetchAll()as$row){$method=strtoupper((string)($row['metodo_pago']??''));if(str_contains($method,'CREDITO')||str_contains($method,'CRÉDITO'))continue;$total=(float)($row['total']??0);$cash=(float)($row['efectivo']??0);$card=(float)($row['tarjeta']??0);$transfer=(float)($row['transferencia']??0);if($cash+$card+$transfer==0){if(str_contains($method,'TARJETA'))$card=$total;elseif(str_contains($method,'TRANSFERENCIA'))$transfer=$total;else$cash=$total;}$totals['total']+=$total;$totals['efectivo']+=$cash;$totals['tarjeta']+=$card;$totals['transferencia']+=$transfer;}
        return['success'=>true,'data'=>$totals];
    }

    private function gastosTurno(PDO $db, string $warehouse): array
    {
        $turno=$this->turnoActivo($db,$warehouse)['data'];if(!$turno)return['success'=>true,'data'=>['total'=>0,'cantidad'=>0]];
        $where='created_at>=?';$params=[$turno['created_at']];if($warehouse!==''){$where.=" AND (almacen_uid=? OR COALESCE(almacen_uid,'')='')";$params[]=$warehouse;}$stmt=$db->prepare("SELECT COALESCE(SUM(cantidad),0) total,COUNT(*) cantidad FROM gastos WHERE $where");$stmt->execute($params);return['success'=>true,'data'=>$stmt->fetch()];
    }

    private function realizarCuadre(PDO $db, array $data): array
    {
        $db->beginTransaction();try{$id=$this->insertRaw($db,'cuadres',$data);if(!empty($data['turno_id']))$this->cerrarTurno($db,(int)$data['turno_id'],['monto_final'=>$data['monto_contado']??$data['monto_final']??0,'efectivo_esperado'=>$data['efectivo_esperado']??0,'diferencia'=>$data['diferencia']??0,'cierre_ciego'=>$data['cierre_ciego']??false]);$db->commit();return['success'=>true,'data'=>['id'=>$id]];}catch(\Throwable$e){if($db->inTransaction())$db->rollBack();throw$e;}
    }

    private function guardarGasto(PDO $db, array $p): array
    {
        $id=(int)($p['id']??0);$amount=(float)($p['cantidad']??0);$method=strtoupper(trim((string)($p['metodo_pago']??'EFECTIVO')));if($amount<=0)throw new InvalidArgumentException('El monto del gasto debe ser mayor que cero.');if(!in_array($method,['EFECTIVO','TRANSFERENCIA'],true))throw new InvalidArgumentException('Metodo de pago no valido.');
        $db->beginTransaction();try{$old=null;if($id){$stmt=$db->prepare('SELECT * FROM gastos WHERE id=?');$stmt->execute([$id]);$old=$stmt->fetch()?:throw new RuntimeException('El gasto no existe.');if(strtoupper((string)($old['metodo_pago']??''))==='TRANSFERENCIA')$this->changeBank($db,(int)($old['banco_id']??0),(string)($old['banco_uid']??''),(float)($old['cantidad']??0));}
            $bank=null;if($method==='TRANSFERENCIA'){$bank=$this->bank($db,(int)($p['banco_id']??0),(string)($p['banco_uid']??''));if(!$bank)throw new RuntimeException('No se encontro el banco seleccionado.');if((float)$bank['saldo']<$amount)throw new RuntimeException('Fondos insuficientes en el banco.');$this->changeBank($db,(int)$bank['id'],(string)($bank['uid']??''),-$amount);}
            $data=$p;unset($data['id']);$data['metodo_pago']=$method;$data['banco_id']=$bank['id']??0;$data['banco_uid']=$bank['uid']??'';$data['banco_nombre']=$bank['nombre']??'';if($id)$this->updateRaw($db,'gastos',$id,$data);else$id=$this->insertRaw($db,'gastos',$data);$db->commit();return['success'=>true,'data'=>['id'=>$id]];}catch(\Throwable$e){if($db->inTransaction())$db->rollBack();throw$e;}
    }

    private function eliminarGasto(PDO $db, int $id): array
    {
        $db->beginTransaction();try{$stmt=$db->prepare('SELECT * FROM gastos WHERE id=?');$stmt->execute([$id]);$row=$stmt->fetch()?:throw new RuntimeException('El gasto no existe.');if(strtoupper((string)($row['metodo_pago']??''))==='TRANSFERENCIA')$this->changeBank($db,(int)($row['banco_id']??0),(string)($row['banco_uid']??''),(float)($row['cantidad']??0));$db->prepare('DELETE FROM gastos WHERE id=?')->execute([$id]);$db->commit();return['success'=>true];}catch(\Throwable$e){if($db->inTransaction())$db->rollBack();throw$e;}
    }

    private function bank(PDO $db,int $id,string $uid):array|false { if($uid!==''){$stmt=$db->prepare('SELECT * FROM bancos WHERE uid=? LIMIT 1');$stmt->execute([$uid]);}else{$stmt=$db->prepare('SELECT * FROM bancos WHERE id=? LIMIT 1');$stmt->execute([$id]);}return$stmt->fetch(); }
    private function changeBank(PDO $db,int $id,string $uid,float $delta):void { $bank=$this->bank($db,$id,$uid);if(!$bank)throw new RuntimeException('No se encontro el banco asociado.');$db->prepare('UPDATE bancos SET saldo=?,fecha_transaccion=?,updated_at=? WHERE id=?')->execute([(float)$bank['saldo']+$delta,gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s'),(int)$bank['id']]); }

    private function registrarPrecios(PDO $db,array $p):array { foreach((array)($p['cambios']??[])as$change)if((string)($change['anterior']??'')!==(string)($change['nuevo']??''))$this->insertRaw($db,'historial_precios',['tabla'=>$p['tabla']??'','producto_id'=>$p['producto_id']??0,'producto_nombre'=>$p['producto_nombre']??'','campo'=>$change['campo']??'','valor_anterior'=>$change['anterior']??'','valor_nuevo'=>$change['nuevo']??'','usuario'=>'','almacen_id'=>$p['almacen_id']??0,'almacen_uid'=>$p['almacen_uid']??'']);return['success'=>true]; }

    private function transferir(PDO $db, array $p): array
    {
        $tableName=(string)($p['tabla']??''); if(!in_array($tableName,['imei','serial','accesorios','electrodomesticos','piezas'],true)) throw new InvalidArgumentException('Tabla no permitida.');
        $table=$this->table($db,$tableName); $db->beginTransaction();
        try { foreach((array)($p['items']??[]) as $item){ $id=(int)($item['id']??0); if($tableName==='accesorios' && (float)($item['cantidad']??1)>0){ $stmt=$db->prepare("SELECT * FROM $table WHERE id=?");$stmt->execute([$id]);$row=$stmt->fetch()?:throw new RuntimeException('Producto no encontrado.');$qty=(float)$item['cantidad'];if((float)$row['cantidad']<$qty)throw new RuntimeException('Cantidad insuficiente.');$db->prepare("UPDATE $table SET cantidad=cantidad-?,updated_at=? WHERE id=?")->execute([$qty,gmdate('Y-m-d H:i:s'),$id]);$copy=$row;unset($copy['id']);$copy['cantidad']=$qty;$copy['almacen_id']=(int)($p['destino_id']??0);$copy['almacen_uid']=(string)($p['destino_uid']??'');$copy['uid']=Support::uid('rec_');$this->insertRaw($db,$tableName,$copy);}else{$db->prepare("UPDATE $table SET almacen_id=?,almacen_uid=?,updated_at=? WHERE id=?")->execute([(int)($p['destino_id']??0),(string)($p['destino_uid']??''),gmdate('Y-m-d H:i:s'),$id]);}} if(!empty($p['transferencia']))$this->insertRaw($db,'transferencias',(array)$p['transferencia']);$db->commit();return['success'=>true];}catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw$e;}
    }

    private function ajustar(PDO $db, array $p): array
    {
        $tableName=(string)($p['tabla']??'');$table=$this->table($db,$tableName);$id=(int)($p['producto_id']??0);$stmt=$db->prepare("SELECT * FROM $table WHERE id=?");$stmt->execute([$id]);$row=$stmt->fetch()?:throw new RuntimeException('Producto no encontrado.');$before=(float)($row['cantidad']??0);$after=(float)($p['cantidad_nueva']??0);$db->prepare("UPDATE $table SET cantidad=?,updated_at=? WHERE id=?")->execute([$after,gmdate('Y-m-d H:i:s'),$id]);$this->insertRaw($db,'ajustes_inventario',['tabla'=>$tableName,'producto_id'=>$id,'producto_nombre'=>$row['nombre']??'','cantidad_anterior'=>$before,'cantidad_nueva'=>$after,'diferencia'=>$after-$before,'tipo'=>$p['tipo']??'','motivo'=>$p['motivo']??'','almacen_id'=>$p['almacen_id']??0,'almacen_uid'=>$p['almacen_uid']??'']);return['success'=>true,'data'=>['anterior'=>$before,'nueva'=>$after,'diferencia'=>$after-$before]];
    }

    private function consultaServidor(PDO $db, array $args): mixed
    {
        $op=(string)($args[0]??'');$name=(string)($args[1]??'');
        if($op==='getAllConfig')return['VITE_LINKURL'=>'','VITE_LINK_API'=>'','VITE_TOKEN'=>'','VITE_PATRON_TELEFONO'=>'^[0-9]{10}$','VITE_IMPRESORA_LOCAL'=>'','VITE_PATRON_CEDULA'=>'^[0-9]{11}$','VITE_TOKEN_CORTO'=>'','SERVIDORLOCAL'=>'','OFFLINE'=>'true'];
        if(in_array($op,['getDataAsArray','getAllData','getDataProductosArray'],true)){
            $table=$this->table($db,$name);
            return$db->query("SELECT * FROM $table ORDER BY id ASC")->fetchAll();
        }
        if($op==='getDataByField'){
            $table=$this->table($db,$name);$field=(string)($args[2]??'');
            if(!in_array($field,$this->columns($db,$name),true))throw new InvalidArgumentException('Campo no permitido.');
            $stmt=$db->prepare('SELECT * FROM '.$table.' WHERE '.Support::quoteIdentifier($field).'=? LIMIT 1');$stmt->execute([$args[3]??null]);
            return$stmt->fetch()?:null;
        }
        if(in_array($op,['getDataByCondition','getDataArrayByCondition'],true)){
            $table=$this->table($db,$name);$field=(string)($args[2]??'');
            if(!in_array($field,$this->columns($db,$name),true))throw new InvalidArgumentException('Campo no permitido.');
            $stmt=$db->prepare('SELECT * FROM '.$table.' WHERE '.Support::quoteIdentifier($field).'=? ORDER BY id ASC');$stmt->execute([$args[3]??null]);
            return$stmt->fetchAll();
        }
        if(in_array($op,['getDataByDoubleCondition','getDataArrayByTwoConditions'],true)){
            $table=$this->table($db,$name);$field1=(string)($args[2]??'');$field2=(string)($args[4]??'');$columns=$this->columns($db,$name);
            if(!in_array($field1,$columns,true)||!in_array($field2,$columns,true))throw new InvalidArgumentException('Campo no permitido.');
            $stmt=$db->prepare('SELECT * FROM '.$table.' WHERE '.Support::quoteIdentifier($field1).'=? AND '.Support::quoteIdentifier($field2).'=? ORDER BY id ASC');$stmt->execute([$args[3]??null,$args[5]??null]);
            return$stmt->fetchAll();
        }
        if($op==='getLastXRows'){
            $table=$this->table($db,$name);$limit=max(1,min(1000,(int)($args[2]??1)));
            return$db->query("SELECT * FROM $table ORDER BY id DESC LIMIT $limit")->fetchAll();
        }
        if(in_array($op,['insertData','insertMultipleData'],true)){
            $payload=$this->legacyPayload($args[2]??[]);$rows=$op==='insertMultipleData'&&array_is_list($payload)?$payload:[$payload];$ids=[];
            $db->beginTransaction();try{foreach($rows as$row)$ids[]=$this->insertRaw($db,$name,(array)$row);$db->commit();}catch(\Throwable$e){if($db->inTransaction())$db->rollBack();throw$e;}
            return['ok',['ids'=>$ids]];
        }
        if($op==='updateData'){
            $payload=$this->legacyPayload($args[2]??[]);$id=(int)($payload['id']??0);if($id<1)throw new InvalidArgumentException('ID requerido para actualizar.');$this->row($db,$this->table($db,$name),$id);$this->updateRaw($db,$name,$id,$payload);return['ok'];
        }
        if($op==='updateDataByField'){
            $field=(string)($args[2]??'');$value=$args[3]??null;$payload=$this->legacyPayload($args[4]??[]);$table=$this->table($db,$name);
            if(!in_array($field,$this->columns($db,$name),true))throw new InvalidArgumentException('Campo no permitido.');$payload=$this->cleanData($db,$name,$payload,false);if(!$payload)return['ok'];
            $set=implode(',',array_map(fn($column)=>Support::quoteIdentifier($column).'=?',array_keys($payload)));$db->prepare("UPDATE $table SET $set WHERE ".Support::quoteIdentifier($field).'=?')->execute([...array_values($payload),$value]);return['ok'];
        }
        if($op==='deleteEntry'){$table=$this->table($db,$name);$db->prepare("DELETE FROM $table WHERE id=?")->execute([(int)($args[2]??0)]);return['ok'];}
        if($op==='deleteByField'){$table=$this->table($db,$name);$field=(string)($args[2]??'');if(!in_array($field,$this->columns($db,$name),true))throw new InvalidArgumentException('Campo no permitido.');$db->prepare('DELETE FROM '.$table.' WHERE '.Support::quoteIdentifier($field).'=?')->execute([$args[3]??null]);return['ok'];}
        if($op==='deleteAll'){$table=$this->table($db,$name);$db->exec("DELETE FROM $table");return['ok'];}
        if($op==='tableExists'){ $stmt=$db->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");$stmt->execute([$name]);return$stmt->fetchColumn()?['ok']:['error']; }
        if($op==='getTableColumns'){ $table=$this->table($db,$name);$rows=$db->query("PRAGMA table_info($table)")->fetchAll();return(($args[2]??'')==='names')?array_column($rows,'name'):$rows; }
        if($op==='getAllTables')return['data'=>array_column($db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(),'name')];
        if($op==='getTableRowCount'){ $table=$this->table($db,$name);return['success'=>true,'count'=>(int)$db->query("SELECT COUNT(*) FROM $table")->fetchColumn()]; }
        if(in_array($op,['rawQuery','executeSQL'],true))return$this->executeSql($db,(string)($args[1]??''),$op==='executeSQL');
        if($op==='vaciarTabla'){ $table=$this->table($db,$name);$db->exec("DELETE FROM $table");return['success'=>true]; }
        throw new RuntimeException("Operacion $op no disponible.",422);
    }

    private function legacyPayload(mixed $payload): array
    {
        if(is_array($payload))return$payload;
        $decoded=json_decode((string)$payload,true);
        if(!is_array($decoded))throw new InvalidArgumentException('Los datos enviados no son JSON valido.');
        return$decoded;
    }

    private function executeSql(PDO $db, string $sql, bool $allowWrite): array
    {
        $sql=trim($sql);if($sql===''||strlen($sql)>100000||preg_match('/\b(?:ATTACH|DETACH)\b/i',$sql))throw new InvalidArgumentException('SQL no valido.');$read=(bool)preg_match('/^(SELECT|PRAGMA|EXPLAIN|WITH)\b/i',$sql);if(!$read&&!$allowWrite)throw new RuntimeException('Escritura SQL no permitida.',403);$stmt=$db->prepare($sql);$stmt->execute();if($stmt->columnCount()>0){$rows=$stmt->fetchAll();return['success'=>true,'type'=>'select','rows'=>array_map(fn($r,$i)=>$r+['__index'=>$i],$rows,array_keys($rows)),'columns'=>$rows?array_keys($rows[0]):[],'count'=>count($rows)];}return['success'=>true,'type'=>'execute','changes'=>$stmt->rowCount()];
    }

    private function configGet(PDO $db,string $key):array { $stmt=$db->prepare('SELECT valor FROM configuracion WHERE clave=? LIMIT 1');$stmt->execute([$key]);return['success'=>true,'data'=>(string)($stmt->fetchColumn()?:'')]; }
    private function configSet(PDO $db,array $p):array
    {
        $key=trim((string)($p['clave']??''));
        if($key==='')throw new InvalidArgumentException('Clave requerida.');
        $value=(string)($p['valor']??'');
        $category=(string)($p['categoria']??'general');
        $now=gmdate('Y-m-d H:i:s');
        $update=$db->prepare('UPDATE configuracion SET valor=?,categoria=?,updated_at=? WHERE clave=?');
        $update->execute([$value,$category,$now,$key]);
        if($update->rowCount()===0){
            $exists=$db->prepare('SELECT 1 FROM configuracion WHERE clave=? LIMIT 1');
            $exists->execute([$key]);
            if(!$exists->fetchColumn()){
                $insert=$db->prepare('INSERT INTO configuracion(clave,valor,categoria,uid,created_at,updated_at) VALUES(?,?,?,?,?,?)');
                $insert->execute([$key,$value,$category,Support::uid('rec_'),$now,$now]);
            }
        }
        return['success'=>true];
    }
    private function bitacora(PDO $db,int $limit):array { if(!$this->exists($db,'bitacora'))return['success'=>true,'data'=>[]];$limit=max(1,min(5000,$limit));return['success'=>true,'data'=>$db->query("SELECT * FROM bitacora ORDER BY id DESC LIMIT $limit")->fetchAll()]; }
    private function clearBitacora(PDO $db):array { if($this->exists($db,'bitacora'))$db->exec('DELETE FROM bitacora');return['success'=>true]; }
    private function audit(PDO $db,string $table,int $id,string $action,array $actor,mixed $new,mixed $old):void { if(!$this->exists($db,'bitacora'))return;try{$stmt=$db->prepare('INSERT INTO bitacora(tabla,registro_id,accion,usuario,datos_nuevos,datos_anteriores,uid,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)');$now=gmdate('Y-m-d H:i:s');$stmt->execute([$table,$id,$action,(string)($actor['email']??''),json_encode($new,JSON_UNESCAPED_UNICODE),json_encode($old,JSON_UNESCAPED_UNICODE),Support::uid('rec_'),$now,$now]);}catch(\Throwable){} }
    private function insertRaw(PDO $db,string $tableName,array $data):int { $this->table($db,$tableName);$data=$this->cleanData($db,$tableName,$data,true);$now=gmdate('Y-m-d H:i:s');if($this->hasColumn($db,$tableName,'uid')&&empty($data['uid']))$data['uid']=Support::uid('rec_');if($this->hasColumn($db,$tableName,'created_at')&&empty($data['created_at']))$data['created_at']=$now;if($this->hasColumn($db,$tableName,'updated_at'))$data['updated_at']=$now;$cols=array_keys($data);$db->prepare('INSERT INTO '.Support::quoteIdentifier($tableName).' ('.implode(',',array_map([Support::class,'quoteIdentifier'],$cols)).') VALUES ('.implode(',',array_fill(0,count($cols),'?')).')')->execute(array_values($data));return(int)$db->lastInsertId(); }
    private function updateRaw(PDO $db,string $tableName,int $id,array $data):void { $data=$this->cleanData($db,$tableName,$data,false);if($this->hasColumn($db,$tableName,'updated_at'))$data['updated_at']=gmdate('Y-m-d H:i:s');unset($data['id']);if(!$data)return;$set=implode(',',array_map(fn($c)=>Support::quoteIdentifier($c).'=?',array_keys($data)));$db->prepare('UPDATE '.Support::quoteIdentifier($tableName)." SET $set WHERE id=?")->execute([...array_values($data),$id]); }
    private function ensureAccessoryCommissionColumns(PDO $db, string $table): void
    {
        if ($table !== 'accesorios') return;
        if (!$this->hasColumn($db, $table, 'tipo_comision')) {
            $db->exec("ALTER TABLE accesorios ADD COLUMN tipo_comision TEXT DEFAULT ''");
        }
        if (!$this->hasColumn($db, $table, 'valor_comision')) {
            $db->exec('ALTER TABLE accesorios ADD COLUMN valor_comision REAL DEFAULT 0');
        }
    }
    private function table(PDO $db,string $name):string { Support::identifier($name,'table name');if(!$this->exists($db,$name))throw new RuntimeException('La tabla no existe.',404);return Support::quoteIdentifier($name); }
    private function exists(PDO $db,string $name):bool { $stmt=$db->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");$stmt->execute([$name]);return(bool)$stmt->fetchColumn(); }
    private function columns(PDO $db,string $table):array { return array_column($db->query('PRAGMA table_info('.Support::quoteIdentifier($table).')')->fetchAll(),'name'); }
    private function hasColumn(PDO $db,string $table,string $column):bool { return in_array($column,$this->columns($db,$table),true); }
    private function cleanData(PDO $db,string $table,array $data,bool $insert):array { $allowed=$this->columns($db,$table);foreach(array_keys($data)as$key){if(!in_array($key,$allowed,true)||(!$insert&&$key==='id'))unset($data[$key]);elseif(is_array($data[$key])||is_object($data[$key]))$data[$key]=json_encode($data[$key],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}unset($data['id']);return$data; }
    private function row(PDO $db,string $table,int $id):array { $stmt=$db->prepare("SELECT * FROM $table WHERE id=? LIMIT 1");$stmt->execute([$id]);return$stmt->fetch()?:throw new RuntimeException('Registro no encontrado.',404); }
}
