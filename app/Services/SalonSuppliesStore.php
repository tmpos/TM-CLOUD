<?php
declare(strict_types=1);
namespace App\Services;

use PDO;
use RuntimeException;

final class SalonSuppliesStore
{
    private static function text(array $row, string $key, int $max, bool $required = false): string
    {
        $value = $row[$key] ?? '';
        if (!is_string($value) || strlen($value) > $max || ($required && trim($value) === '')) throw new RuntimeException('Campo de insumos invalido: '.$key);
        return $value;
    }

    private static function number(array $row, string $key, float $min, float $max): float
    {
        $value = $row[$key] ?? null;
        if ((!is_int($value) && !is_float($value)) || !is_finite((float)$value) || $value < $min || $value > $max) throw new RuntimeException('Cantidad o costo de insumo invalido: '.$key);
        return (float)$value;
    }

    public static function save(PDO $db, array $input, bool $admin): array
    {
        if (!$admin) throw new RuntimeException('Solo Administrador o Soporte puede modificar insumos.', 403);
        $warehouse = $input['warehouse'] ?? [];
        $suffix = (string)($warehouse['uid'] ?? '');
        if ($suffix === '') $suffix = (string)($warehouse['id'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $suffix)) throw new RuntimeException('Sucursal invalida.');
        $key = 'salon_supplies_v1_'.$suffix;
        $revision = $input['expectedRevision'] ?? null;
        $state = $input['settings'] ?? null;
        if (!is_int($revision) || $revision < 0 || !is_array($state) || !isset($state['supplies'], $state['movements']) || !is_array($state['supplies']) || !is_array($state['movements']) || !array_is_list($state['supplies']) || !array_is_list($state['movements'])) throw new RuntimeException('Registro de insumos invalido.');
        if (count($state['supplies']) > 2000 || count($state['movements']) > 20000 || strlen(json_encode($state, JSON_THROW_ON_ERROR)) > 8000000) throw new RuntimeException('El registro de insumos supera el limite permitido.');
        $db->exec('BEGIN IMMEDIATE');
        try {
            $q = $db->prepare('SELECT valor FROM configuracion WHERE clave=?'); $q->execute([$key]); $json = $q->fetchColumn();
            $old = $json === false ? ['supplies'=>[], 'movements'=>[], 'revision'=>0] : json_decode((string)$json, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($old) || !isset($old['supplies'], $old['movements']) || !is_array($old['supplies']) || !is_array($old['movements'])) throw new RuntimeException('El registro guardado de insumos no es valido.');
            if (($old['revision'] ?? 0) !== $revision) throw new RuntimeException('Los insumos cambiaron en otro equipo. Actualiza antes de guardar.', 409);
            $supplies = []; $balances = []; $used = [];
            foreach ($old['movements'] as $movement) $used[$movement['supplyUid']] = true;
            foreach ($state['supplies'] as $supply) {
                if (!is_array($supply)) throw new RuntimeException('Insumo invalido.');
                $uid = self::text($supply, 'uid', 100, true);
                if (isset($supplies[$uid])) throw new RuntimeException('Insumo duplicado.');
                self::text($supply, 'name', 480, true); self::text($supply, 'unit', 80, true); self::number($supply, 'minimum', 0, 1000000000);
                if (!isset($supply['active']) || !is_bool($supply['active'])) throw new RuntimeException('Estado de insumo invalido.');
                $supplies[$uid] = $supply;
                $balances[$uid] = ['quantity'=>0.0, 'value'=>0.0];
            }
            foreach ($old['supplies'] as $supply) {
                if (!isset($supplies[$supply['uid']])) throw new RuntimeException('No se permite borrar insumos; desactivalos.');
                if (isset($used[$supply['uid']]) && $supplies[$supply['uid']]['unit'] !== $supply['unit']) throw new RuntimeException('No se puede cambiar la unidad de un insumo con movimientos.');
            }
            $oldCount = count($old['movements']);
            if (count($state['movements']) < $oldCount || count($state['movements']) > $oldCount + 100) throw new RuntimeException('No se permite borrar movimientos ni agregar mas de 100 por operacion.');
            $seen = [];
            foreach ($state['movements'] as $index => &$movement) {
                if (!is_array($movement)) throw new RuntimeException('Movimiento invalido.');
                if ($index < $oldCount) {
                    $comparison = $movement; $previous = $old['movements'][$index];
                    ksort($comparison); ksort($previous);
                    if (json_encode($comparison, JSON_THROW_ON_ERROR) !== json_encode($previous, JSON_THROW_ON_ERROR)) throw new RuntimeException('No se permite modificar el historial de movimientos.');
                }
                $uid = self::text($movement, 'uid', 100, true); $supplyUid = self::text($movement, 'supplyUid', 100, true);
                if (isset($seen[$uid]) || !isset($supplies[$supplyUid])) throw new RuntimeException('Movimiento duplicado o insumo inexistente.');
                $seen[$uid] = true;
                if ($index >= $oldCount && !$supplies[$supplyUid]['active']) throw new RuntimeException('Selecciona un insumo activo.');
                $kind = $movement['kind'] ?? '';
                if (!in_array($kind, ['IN', 'USE'], true)) throw new RuntimeException('Tipo de movimiento invalido.');
                $quantity = self::number($movement, 'quantity', 0.0001, 1000000000);
                if (abs($quantity - round($quantity, 4)) > 0.00000001) throw new RuntimeException('La cantidad admite hasta cuatro decimales.');
                $cost = self::number($movement, 'unitCost', 0, 1000000000);
                $date = self::text($movement, 'date', 40, true);
                if (strtotime($date) === false) throw new RuntimeException('Fecha de movimiento invalida.');
                self::text($movement, 'service', 640, $kind === 'USE'); self::text($movement, 'invoiceUid', 150); self::text($movement, 'note', 1000);
                $balance = &$balances[$supplyUid];
                if ($kind === 'USE') {
                    if ($quantity > $balance['quantity'] + 0.00000001) throw new RuntimeException('No hay existencias suficientes.');
                    if ($index >= $oldCount) $movement['unitCost'] = $cost = $balance['quantity'] > 0 ? max(0, $balance['value'] / $balance['quantity']) : 0;
                }
                $sign = $kind === 'IN' ? 1 : -1;
                $balance['quantity'] = round($balance['quantity'] + $sign * $quantity, 4);
                $balance['value'] = max(0, $balance['value'] + $sign * $quantity * $cost);
                unset($balance);
            }
            unset($movement);
            $state = ['supplies'=>array_values($supplies), 'movements'=>$state['movements'], 'revision'=>$revision+1];
            $json = json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $q = $db->prepare('SELECT 1 FROM configuracion WHERE clave=?'); $q->execute([$key]); $exists = (bool)$q->fetchColumn();
            if ($exists) {
                $q = $db->prepare('UPDATE configuracion SET valor=?,updated_at=? WHERE clave=?'); $q->execute([$json, gmdate('Y-m-d H:i:s'), $key]);
            } else {
                $q = $db->prepare('INSERT INTO configuracion(clave,valor,categoria,uid,created_at,updated_at) VALUES(?,?,?,?,?,?)');
                $now = gmdate('Y-m-d H:i:s'); $q->execute([$key,$json,'salon',bin2hex(random_bytes(16)),$now,$now]);
            }
            $db->exec('COMMIT');
            return ['success'=>true, 'data'=>$state];
        } catch (\Throwable $e) { $db->exec('ROLLBACK'); throw $e; }
    }
}
