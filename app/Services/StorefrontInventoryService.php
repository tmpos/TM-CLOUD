<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Support;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class StorefrontInventoryService
{
    private const AVAILABLE_STATES = ['', 'DISPONIBLE', 'AVAILABLE', 'EN INVENTARIO'];

    public function __construct(
        private ProjectService $projects,
        private SchemaService $schema,
        private LogService $logs,
    ) {
    }

    public function commit(array $store, array $order, array $items, bool $requireExplicitImei = false): array
    {
        $project = $this->projects->findActive((string) $store['project_uid']);
        $db = $this->schema->connection($project);
        $movements = [];
        $db->beginTransaction();
        try {
            foreach ($items as $item) {
                $metadata = $this->metadata($item['metadata'] ?? []);
                $kind = strtolower(trim((string) ($metadata['kind'] ?? $item['kind'] ?? 'product')));
                $table = trim((string) ($metadata['source_table'] ?? $item['source_table'] ?? ''));
                $productUid = trim((string) ($item['product_uid'] ?? $item['product']['uid'] ?? ''));
                $productName = trim((string) ($item['product_name'] ?? $item['product']['name'] ?? 'Producto'));
                $quantity = max(1, (int) ($item['quantity'] ?? 1));
                if ($productUid === '') {
                    throw new InvalidArgumentException('Uno de los productos no tiene identificador de inventario.');
                }

                if ($table === '') {
                    $table = $this->locateProductTable($db, $productUid);
                }
                if ($kind === 'phone' || strtolower($table) === 'telefonos') {
                    for ($position = 0; $position < $quantity; $position++) {
                        $explicit = $this->requestedImei($metadata, $position);
                        if ($requireExplicitImei && $explicit === '') {
                            throw new InvalidArgumentException('Debes indicar el IMEI de ' . $productName . '.');
                        }
                        $movements[] = $this->sellPhone(
                            $db,
                            $productUid,
                            $productName,
                            $explicit,
                            $order,
                        );
                    }
                    continue;
                }

                if ($table === '') {
                    continue;
                }
                $movement = $this->decreaseProduct($db, $table, $productUid, $productName, $quantity);
                if ($movement !== null) {
                    $movements[] = $movement;
                }
            }
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        try {
            $this->logs->write(
                'storefront.inventory.committed',
                (string) $store['project_uid'],
                'storefront_orders',
                (string) ($order['uid'] ?? ''),
                null,
                ['order_number' => $order['order_number'] ?? '', 'movements' => count($movements)]
            );
        } catch (\Throwable) {
        }
        return $movements;
    }

    public function restore(array $store, array $order, array $movements): void
    {
        if ($movements === []) {
            return;
        }
        $project = $this->projects->findActive((string) $store['project_uid']);
        $db = $this->schema->connection($project);
        $db->beginTransaction();
        try {
            foreach (array_reverse($movements) as $movement) {
                if (($movement['type'] ?? '') === 'imei') {
                    $table = Support::identifier((string) ($movement['table_name'] ?? 'imei'), 'table name');
                    $keyColumn = Support::identifier((string) ($movement['key_column'] ?? 'uid'), 'column name');
                    $stateColumn = Support::identifier((string) ($movement['stock_column'] ?? 'estado'), 'column name');
                    $stmt = $db->prepare(
                        'UPDATE ' . Support::quoteIdentifier($table)
                        . ' SET ' . Support::quoteIdentifier($stateColumn) . '=?'
                        . ' WHERE ' . Support::quoteIdentifier($keyColumn) . '=?'
                        . ' AND UPPER(TRIM(CAST(' . Support::quoteIdentifier($stateColumn) . " AS TEXT)))='VENDIDO'"
                    );
                    $stmt->execute([
                        (string) ($movement['before_value'] ?? 'DISPONIBLE') ?: 'DISPONIBLE',
                        $movement['row_key'] ?? '',
                    ]);
                    continue;
                }

                if (($movement['type'] ?? '') === 'stock') {
                    $table = Support::identifier((string) $movement['table_name'], 'table name');
                    $stockColumn = Support::identifier((string) $movement['stock_column'], 'column name');
                    $keyColumn = Support::identifier((string) ($movement['key_column'] ?? 'uid'), 'column name');
                    $stmt = $db->prepare(
                        'UPDATE ' . Support::quoteIdentifier($table)
                        . ' SET ' . Support::quoteIdentifier($stockColumn)
                        . '=COALESCE(CAST(' . Support::quoteIdentifier($stockColumn) . ' AS REAL),0)+?'
                        . ' WHERE ' . Support::quoteIdentifier($keyColumn) . '=?'
                    );
                    $stmt->execute([
                        abs((float) ($movement['quantity'] ?? 0)),
                        $movement['row_key'] ?? '',
                    ]);
                }
            }
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        try {
            $this->logs->write(
                'storefront.inventory.restored',
                (string) $store['project_uid'],
                'storefront_orders',
                (string) ($order['uid'] ?? ''),
                null,
                ['order_number' => $order['order_number'] ?? '', 'movements' => count($movements)]
            );
        } catch (\Throwable) {
        }
    }

    private function sellPhone(
        PDO $db,
        string $productUid,
        string $productName,
        string $requestedImei,
        array $order,
    ): array {
        if (!$this->tableExists($db, 'telefonos') || !$this->tableExists($db, 'imei')) {
            throw new RuntimeException('El inventario de teléfonos e IMEI no está disponible.');
        }
        $phone = $db->prepare('SELECT * FROM "telefonos" WHERE uid=? LIMIT 1');
        $phone->execute([$productUid]);
        $model = $phone->fetch();
        if (!$model) {
            throw new RuntimeException('No se encontró el modelo ' . $productName . ' en teléfonos.');
        }

        $columns = $this->columns($db, 'imei');
        $imeiColumn = $this->firstColumn($columns, ['imei', 'numero_imei', 'codigo', 'numero', 'serial']);
        $stateColumn = $this->firstColumn($columns, ['estado', 'estatus', 'status']);
        if ($imeiColumn === null || $stateColumn === null) {
            throw new RuntimeException('La tabla IMEI necesita las columnas IMEI y estado.');
        }

        $relations = [];
        $parameters = [];
        if (in_array('telefono_uid', $columns, true)) {
            $relations[] = '"telefono_uid"=?';
            $parameters[] = $productUid;
        }
        if (in_array('id_equi', $columns, true) && isset($model['id'])) {
            $relations[] = 'CAST("id_equi" AS TEXT)=?';
            $parameters[] = (string) $model['id'];
        }
        if (in_array('equipo', $columns, true) && trim((string) ($model['nombre'] ?? '')) !== '') {
            $relations[] = 'LOWER(TRIM(CAST("equipo" AS TEXT)))=LOWER(?)';
            $parameters[] = trim((string) $model['nombre']);
        }
        if ($relations === []) {
            throw new RuntimeException('No se puede relacionar el IMEI con el modelo ' . $productName . '.');
        }

        $where = '(' . implode(' OR ', $relations) . ')';
        if ($requestedImei !== '') {
            $where .= ' AND REPLACE(REPLACE(CAST(' . Support::quoteIdentifier($imeiColumn) . " AS TEXT),'-',''),' ','')=?";
            $parameters[] = $this->normalizeImei($requestedImei);
        }
        $available = implode(',', array_fill(0, count(self::AVAILABLE_STATES), '?'));
        $where .= ' AND UPPER(TRIM(COALESCE(CAST(' . Support::quoteIdentifier($stateColumn) . " AS TEXT),''))) IN ($available)";
        array_push($parameters, ...self::AVAILABLE_STATES);

        $stmt = $db->prepare('SELECT * FROM "imei" WHERE ' . $where . ' ORDER BY id ASC LIMIT 1');
        $stmt->execute($parameters);
        $imei = $stmt->fetch();
        if (!$imei) {
            throw new InvalidArgumentException(
                $requestedImei !== ''
                    ? 'El IMEI ' . $requestedImei . ' no está disponible para ' . $productName . '.'
                    : 'No quedan IMEI disponibles para ' . $productName . '.'
            );
        }

        $keyColumn = array_key_exists('uid', $imei) ? 'uid' : 'id';
        $assignments = [Support::quoteIdentifier($stateColumn) . '=?'];
        $values = ['VENDIDO'];
        $optional = [
            'vendido_at' => Support::now(),
            'fecha_venta' => Support::now(),
            'venta_order_uid' => (string) ($order['uid'] ?? ''),
            'order_uid' => (string) ($order['uid'] ?? ''),
            'no_factura' => (string) ($order['order_number'] ?? ''),
            'cliente' => (string) ($order['customer_name'] ?? ''),
        ];
        foreach ($optional as $column => $value) {
            if (in_array($column, $columns, true)) {
                $assignments[] = Support::quoteIdentifier($column) . '=?';
                $values[] = $value;
            }
        }
        if (in_array('updated_at', $columns, true)) {
            $assignments[] = '"updated_at"=?';
            $values[] = Support::now();
        }
        $values[] = $imei[$keyColumn];
        $values[] = (string) ($imei[$stateColumn] ?? '');
        $update = $db->prepare(
            'UPDATE "imei" SET ' . implode(',', $assignments)
            . ' WHERE ' . Support::quoteIdentifier($keyColumn) . '=?'
            . ' AND COALESCE(CAST(' . Support::quoteIdentifier($stateColumn) . " AS TEXT),'')=?"
        );
        $update->execute($values);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('El IMEI fue utilizado por otra venta. Actualiza e intenta nuevamente.');
        }

        return [
            'type' => 'imei',
            'table_name' => 'imei',
            'stock_column' => $stateColumn,
            'key_column' => $keyColumn,
            'row_key' => $imei[$keyColumn],
            'product_uid' => $productUid,
            'product_name' => $productName,
            'imei_uid' => (string) ($imei['uid'] ?? ''),
            'imei' => (string) ($imei[$imeiColumn] ?? ''),
            'quantity' => -1,
            'before_value' => (string) ($imei[$stateColumn] ?? ''),
            'after_value' => 'VENDIDO',
        ];
    }

    private function decreaseProduct(
        PDO $db,
        string $table,
        string $productUid,
        string $productName,
        int $quantity,
    ): ?array {
        $table = Support::identifier($table, 'table name');
        if (!$this->tableExists($db, $table)) {
            return null;
        }
        $columns = $this->columns($db, $table);
        $stockColumn = $this->firstColumn($columns, ['existencia', 'existencias', 'stock', 'cantidad', 'disponible']);
        if ($stockColumn === null || !in_array('uid', $columns, true)) {
            return null;
        }
        $select = $db->prepare(
            'SELECT ' . Support::quoteIdentifier($stockColumn)
            . ' FROM ' . Support::quoteIdentifier($table) . ' WHERE uid=? LIMIT 1'
        );
        $select->execute([$productUid]);
        $before = $select->fetchColumn();
        if ($before === false) {
            throw new RuntimeException('No se encontró ' . $productName . ' en el inventario.');
        }
        if ((float) $before < $quantity) {
            throw new InvalidArgumentException('Existencia insuficiente para ' . $productName . '.');
        }
        $update = $db->prepare(
            'UPDATE ' . Support::quoteIdentifier($table)
            . ' SET ' . Support::quoteIdentifier($stockColumn)
            . '=CAST(' . Support::quoteIdentifier($stockColumn) . ' AS REAL)-?'
            . ' WHERE uid=? AND CAST(' . Support::quoteIdentifier($stockColumn) . ' AS REAL)>=?'
        );
        $update->execute([$quantity, $productUid, $quantity]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('El inventario cambió durante la venta. Actualiza e intenta nuevamente.');
        }
        return [
            'type' => 'stock',
            'table_name' => $table,
            'stock_column' => $stockColumn,
            'key_column' => 'uid',
            'row_key' => $productUid,
            'product_uid' => $productUid,
            'product_name' => $productName,
            'quantity' => -$quantity,
            'before_value' => (float) $before,
            'after_value' => (float) $before - $quantity,
        ];
    }

    private function requestedImei(array $metadata, int $position): string
    {
        $imeis = $metadata['imeis'] ?? [];
        if (is_array($imeis) && isset($imeis[$position])) {
            return $this->normalizeImei((string) $imeis[$position]);
        }
        if ($position === 0) {
            $direct = trim((string) ($metadata['imei'] ?? ''));
            if ($direct !== '') {
                return $this->normalizeImei($direct);
            }
            $note = (string) ($metadata['note'] ?? '');
            if (preg_match('/\b[0-9][0-9 -]{12,20}[0-9]\b/', $note, $match)) {
                return $this->normalizeImei($match[0]);
            }
        }
        return '';
    }

    private function normalizeImei(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9]/', '', trim($value)) ?? '';
    }

    private function locateProductTable(PDO $db, string $uid): string
    {
        $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
        $preferred = ['accesorios', 'productos', 'inventario', 'items', 'articulos'];
        $tables = array_values(array_unique(array_merge($preferred, array_map('strval', $tables))));
        foreach ($tables as $table) {
            if (!$this->tableExists($db, $table)) {
                continue;
            }
            $columns = $this->columns($db, $table);
            if (!in_array('uid', $columns, true)) {
                continue;
            }
            $stmt = $db->prepare(
                'SELECT 1 FROM ' . Support::quoteIdentifier($table) . ' WHERE uid=? LIMIT 1'
            );
            $stmt->execute([$uid]);
            if ($stmt->fetchColumn()) {
                return $table;
            }
        }
        return '';
    }

    private function metadata(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function tableExists(PDO $db, string $table): bool
    {
        $stmt = $db->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND lower(name)=lower(?) LIMIT 1");
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }

    private function columns(PDO $db, string $table): array
    {
        return array_map(
            static fn (array $column): string => (string) $column['name'],
            $db->query('PRAGMA table_info(' . Support::quoteIdentifier($table) . ')')->fetchAll()
        );
    }

    private function firstColumn(array $columns, array $candidates): ?string
    {
        $lookup = array_change_key_case(array_combine($columns, $columns) ?: [], CASE_LOWER);
        foreach ($candidates as $candidate) {
            if (isset($lookup[strtolower($candidate)])) {
                return $lookup[strtolower($candidate)];
            }
        }
        return null;
    }
}
