<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use RuntimeException;

final class HoldRuntimeService
{
    private static function permission(array $actor, string $key): bool
    {
        $raw = $actor['permisos'] ?? null;
        if ($raw === null || $raw === '') return true;
        $permissions = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($permissions)) return false;
        if (!$permissions) return true;
        return array_is_list($permissions) ? in_array($key, $permissions, true) : !empty($permissions[$key]);
    }

    public static function handle(PDO $db, array $input, array $actor, bool $admin): array
    {
        $role = strtolower((string)(($actor['nivel_seguridad'] ?? '') ?: ($actor['rol'] ?? '')));
        $cashier = $admin || in_array($role, ['cajero', 'caja'], true);
        $operation = $input['operation'] ?? '';
        if (!$cashier && !self::permission($actor, 'vender') && !( $operation === 'list' && self::permission($actor, 'ventas'))) throw new RuntimeException('Sin permiso para acceder al POS.', 403);
        if ($operation === 'update' && !$cashier && !self::permission($actor, 'ventas-espera-editar')) throw new RuntimeException('Necesitas permiso para editar ventas en espera.', 403);
        if ($operation === 'delete' && !$cashier) throw new RuntimeException('Solo Caja, Administrador o Soporte pueden eliminar ventas en espera.', 403);
        $warehouse = $input['warehouse'] ?? [];
        $warehouseUid = trim((string)($warehouse['uid'] ?? ''));
        $warehouseId = (int)($warehouse['id'] ?? 0);
        if ($operation === 'create' && $warehouseUid === '' && $warehouseId <= 0) throw new RuntimeException('Selecciona la sucursal.');
        $db->exec("CREATE TABLE IF NOT EXISTS ventas_pausadas (id INTEGER PRIMARY KEY AUTOINCREMENT,uid TEXT DEFAULT '',codigo TEXT NOT NULL DEFAULT '',datos TEXT NOT NULL DEFAULT '{}',cliente_nombre TEXT DEFAULT '',total REAL DEFAULT 0,items_count INTEGER DEFAULT 0,usuario TEXT DEFAULT '',estado TEXT DEFAULT 'PAUSADA',almacen_id INTEGER DEFAULT 0,almacen_uid TEXT DEFAULT '',created_at TEXT,updated_at TEXT,revision INTEGER DEFAULT 0)");
        $columns = array_column($db->query('PRAGMA table_info(ventas_pausadas)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (!in_array('revision', $columns, true)) $db->exec('ALTER TABLE ventas_pausadas ADD COLUMN revision INTEGER DEFAULT 0');
        $scope = $warehouseUid !== '' ? 'almacen_uid=?' : 'almacen_id=?';
        $scopeValue = $warehouseUid !== '' ? $warehouseUid : $warehouseId;
        if ($operation === 'list') {
            $stmt = $db->prepare("SELECT * FROM ventas_pausadas WHERE COALESCE(estado,'') <> 'ELIMINADA' ORDER BY id DESC");
            $stmt->execute();
            return ['success'=>true, 'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)];
        }
        $data = (array)($input['data'] ?? []);
        if (isset($data['estado']) && $operation === 'update' && !$cashier) throw new RuntimeException('Solo Caja puede retirar ventas en espera.', 403);
        $db->exec('BEGIN IMMEDIATE');
        try {
            if ($operation === 'create') {
                $code = trim((string)($data['codigo'] ?? ''));
                if ($code === '') throw new RuntimeException('Falta el identificador de la venta.');
                $find = $db->prepare("SELECT * FROM ventas_pausadas WHERE codigo=? AND $scope LIMIT 1");
                $find->execute([$code, $scopeValue]);
                $existing = $find->fetch(PDO::FETCH_ASSOC);
                if ($existing) {
                    if (empty($input['import'])) {
                        if ($existing['estado'] === 'ELIMINADA') throw new RuntimeException('Esta venta fue eliminada desde otro equipo. Actualiza la lista.', 409);
                        $before = json_decode((string)$existing['datos'], true) ?: [];
                        $after = json_decode((string)($data['datos'] ?? '{}'), true) ?: [];
                        unset($before['hora'], $before['fecha'], $after['hora'], $after['fecha']);
                        if ($before != $after) throw new RuntimeException('Esta venta ya se guardo con otros datos. Revisa la lista antes de volver a enviarla.', 409);
                    }
                    $db->exec('COMMIT'); return ['success'=>true,'data'=>$existing];
                }
                $data = array_intersect_key($data, array_flip(['codigo','datos','cliente_nombre','total','items_count','usuario']));
                $data += ['uid'=>bin2hex(random_bytes(16)), 'almacen_uid'=>$warehouseUid, 'almacen_id'=>$warehouseId, 'estado'=>'PAUSADA', 'revision'=>0, 'created_at'=>gmdate('c'), 'updated_at'=>gmdate('c')];
                $keys = array_keys($data);
                $stmt = $db->prepare('INSERT INTO ventas_pausadas ('.implode(',', $keys).') VALUES ('.implode(',', array_fill(0, count($keys), '?')).')');
                $stmt->execute(array_values($data));
                $id = (int)$db->lastInsertId();
            } elseif ($operation === 'delete') {
                $id = (int)($input['id'] ?? 0);
                $stmt = $db->prepare("UPDATE ventas_pausadas SET estado='ELIMINADA',updated_at=?,revision=COALESCE(revision,0)+1 WHERE id=? AND COALESCE(estado,'') <> 'ELIMINADA' AND COALESCE(revision,0)=CAST(? AS INTEGER)");
                $stmt->execute([gmdate('c'), $id, (int)($input['revision'] ?? -1)]);
                if ($stmt->rowCount() !== 1) throw new RuntimeException('Otro equipo modifico o elimino esta venta. Actualiza la lista antes de eliminarla.', 409);
            } elseif ($operation === 'update') {
                $id = (int)($input['id'] ?? 0);
                $data = array_intersect_key($data, array_flip(['datos','cliente_nombre','total','items_count','usuario','estado']));
                if (isset($data['estado']) && !in_array($data['estado'], ['RECUPERADA','CANCELADA'], true)) throw new RuntimeException('Estado no valido.');
                $data['updated_at'] = gmdate('c');
                $set = implode(',', array_map(fn($key)=>"$key=?", array_keys($data)));
                $stmt = $db->prepare("UPDATE ventas_pausadas SET $set,revision=COALESCE(revision,0)+1 WHERE id=? AND estado='PAUSADA' AND COALESCE(revision,0)=CAST(? AS INTEGER)");
                $stmt->execute([...array_values($data), $id, (int)($input['revision'] ?? -1)]);
                if ($stmt->rowCount() !== 1) throw new RuntimeException('Otro equipo modifico o retiro esta venta. Conserva tu carrito y vuelve a consultar la venta en espera.', 409);
            } else throw new RuntimeException('Operacion no valida.');
            $stmt = $db->prepare('SELECT * FROM ventas_pausadas WHERE id=?');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $db->exec('COMMIT');
            return ['success'=>true,'data'=>$row];
        } catch (\Throwable $error) { $db->exec('ROLLBACK'); throw $error; }
    }
}
