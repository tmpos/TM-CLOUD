<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Support;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class StorefrontAdminService
{
    public function __construct(
        private PDO $db,
        private StorefrontService $storefronts,
        private StorefrontInventoryService $inventory,
        private LogService $logs,
    ) {
    }

    public function provision(string $projectUid, array $input): array
    {
        $name = mb_substr(trim((string) ($input['name'] ?? '')), 0, 100);
        $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $role = (string) ($input['role'] ?? 'owner');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Escribe el nombre y un correo válido.');
        }
        if (!in_array($role, ['owner', 'manager', 'cashier', 'kitchen'], true)) {
            throw new InvalidArgumentException('El rol seleccionado no es válido.');
        }
        $store = $this->db->prepare('SELECT uid FROM storefronts WHERE project_uid=? LIMIT 1');
        $store->execute([$projectUid]);
        $storefrontUid = (string) ($store->fetchColumn() ?: '');
        if ($storefrontUid === '') {
            throw new RuntimeException('La tienda web todavía no está creada.');
        }

        $find = $this->db->prepare('SELECT * FROM storefront_admin_users WHERE email=? LIMIT 1');
        $find->execute([$email]);
        $user = $find->fetch();
        $now = Support::now();
        if (!$user) {
            if (strlen($password) < 10) {
                throw new InvalidArgumentException('La contraseña debe tener al menos 10 caracteres.');
            }
            $userUid = Support::uid('sau_');
            $this->db->prepare(
                'INSERT INTO storefront_admin_users (uid,name,email,password_hash,status,created_at,updated_at)
                 VALUES (?,?,?,?,?,?,?)'
            )->execute([$userUid, $name, $email, password_hash($password, PASSWORD_DEFAULT), 'active', $now, $now]);
        } else {
            $userUid = (string) $user['uid'];
            $fields = [$name, $now];
            $sql = 'UPDATE storefront_admin_users SET name=?,updated_at=?';
            if ($password !== '') {
                if (strlen($password) < 10) {
                    throw new InvalidArgumentException('La contraseña debe tener al menos 10 caracteres.');
                }
                $sql .= ',password_hash=?';
                $fields[] = password_hash($password, PASSWORD_DEFAULT);
            }
            $sql .= ' WHERE uid=?';
            $fields[] = $userUid;
            $this->db->prepare($sql)->execute($fields);
        }
        $this->db->prepare(
            'INSERT INTO storefront_admin_memberships
             (uid,user_uid,storefront_uid,role,created_at,updated_at) VALUES (?,?,?,?,?,?)
             ON CONFLICT(user_uid,storefront_uid) DO UPDATE SET role=excluded.role,updated_at=excluded.updated_at'
        )->execute([Support::uid('sam_'), $userUid, $storefrontUid, $role, $now, $now]);
        $this->logs->write('storefront.admin.provisioned', $projectUid, 'storefront_admin_users', $userUid, null, [
            'email' => $email, 'role' => $role,
        ]);
        return ['uid' => $userUid, 'name' => $name, 'email' => $email, 'role' => $role];
    }

    public function attempt(string $email, string $password, ?string $storeSlug = null): bool
    {
        $storeSlug = trim((string) $storeSlug);
        if ($storeSlug !== '') {
            $stmt = $this->db->prepare(
                "SELECT u.*,s.uid AS requested_storefront_uid
                 FROM storefront_admin_users u
                 JOIN storefront_admin_memberships m ON m.user_uid=u.uid
                 JOIN storefronts s ON s.uid=m.storefront_uid
                 WHERE u.email=? AND u.status='active' AND s.slug=? AND s.enabled=1
                 LIMIT 1"
            );
            $stmt->execute([mb_strtolower(trim($email)), $storeSlug]);
        } else {
            $stmt = $this->db->prepare(
                "SELECT u.* FROM storefront_admin_users u
                 WHERE u.email=? AND u.status='active'
                 AND EXISTS(SELECT 1 FROM storefront_admin_memberships m WHERE m.user_uid=u.uid)
                 LIMIT 1"
            );
            $stmt->execute([mb_strtolower(trim($email))]);
        }
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            return false;
        }
        $memberships = $this->memberships((string) $user['uid']);
        if ($memberships === []) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['storefront_admin'] = [
            'uid' => $user['uid'],
            'name' => $user['name'],
            'email' => $user['email'],
            'storefront_uid' => $user['requested_storefront_uid'] ?? $memberships[0]['storefront_uid'],
        ];
        $this->db->prepare('UPDATE storefront_admin_users SET last_login_at=?,updated_at=? WHERE uid=?')
            ->execute([Support::now(), Support::now(), $user['uid']]);
        return true;
    }

    public function check(): bool
    {
        return isset($_SESSION['storefront_admin']['uid']);
    }

    public function user(): ?array
    {
        return $_SESSION['storefront_admin'] ?? null;
    }

    public function logout(): void
    {
        unset($_SESSION['storefront_admin']);
        session_regenerate_id(true);
    }

    public function selectStore(string $storefrontUid): void
    {
        $userUid = (string) ($_SESSION['storefront_admin']['uid'] ?? '');
        $stmt = $this->db->prepare(
            'SELECT 1 FROM storefront_admin_memberships WHERE user_uid=? AND storefront_uid=? LIMIT 1'
        );
        $stmt->execute([$userUid, $storefrontUid]);
        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('No tienes acceso a ese negocio.', 403);
        }
        $_SESSION['storefront_admin']['storefront_uid'] = $storefrontUid;
    }

    public function memberships(?string $userUid = null): array
    {
        $userUid ??= (string) ($_SESSION['storefront_admin']['uid'] ?? '');
        $stmt = $this->db->prepare(
            "SELECT m.storefront_uid,m.role,s.store_name,s.slug,s.project_uid
             FROM storefront_admin_memberships m
             JOIN storefronts s ON s.uid=m.storefront_uid
             WHERE m.user_uid=? AND s.enabled=1 ORDER BY s.store_name"
        );
        $stmt->execute([$userUid]);
        return $stmt->fetchAll();
    }

    public function currentStore(?string $storeSlug = null): array
    {
        $user = $this->user();
        if (!$user) {
            throw new RuntimeException('Tu sesión expiró.', 401);
        }
        $storeSlug = trim((string) $storeSlug);
        if ($storeSlug !== '') {
            $stmt = $this->db->prepare(
                "SELECT s.uid AS storefront_uid,s.slug,m.role FROM storefront_admin_memberships m
                 JOIN storefronts s ON s.uid=m.storefront_uid
                 WHERE m.user_uid=? AND s.slug=? AND s.enabled=1 LIMIT 1"
            );
            $stmt->execute([$user['uid'], $storeSlug]);
        } else {
            $stmt = $this->db->prepare(
                "SELECT s.uid AS storefront_uid,s.slug,m.role FROM storefront_admin_memberships m
                 JOIN storefronts s ON s.uid=m.storefront_uid
                 WHERE m.user_uid=? AND m.storefront_uid=? AND s.enabled=1 LIMIT 1"
            );
            $stmt->execute([$user['uid'], $user['storefront_uid']]);
        }
        $membership = $stmt->fetch();
        if (!$membership) {
            throw new RuntimeException('No tienes acceso a este negocio.', 403);
        }
        $_SESSION['storefront_admin']['storefront_uid'] = $membership['storefront_uid'];
        $store = $this->storefronts->findBySlug((string) $membership['slug']);
        $store['admin_role'] = $membership['role'];
        return $store;
    }

    public function metrics(array $store): array
    {
        $today = gmdate('Y-m-d');
        $stmt = $this->db->prepare(
            "SELECT
             COUNT(*) AS orders,
             COALESCE(SUM(total),0) AS sales,
             COALESCE(SUM(CASE WHEN substr(created_at,1,10)=? THEN total ELSE 0 END),0) AS today_sales,
             SUM(CASE WHEN substr(created_at,1,10)=? THEN 1 ELSE 0 END) AS today_orders,
             SUM(CASE WHEN status IN ('pending','confirmed','preparing','ready') OR (source='web' AND status='completed') THEN 1 ELSE 0 END) AS open_orders,
             SUM(CASE WHEN status='dispatched' THEN 1 ELSE 0 END) AS dispatched_orders,
             SUM(CASE WHEN status='delivered' THEN 1 ELSE 0 END) AS delivered_orders,
             SUM(CASE WHEN source='web' THEN 1 ELSE 0 END) AS web_orders,
             SUM(CASE WHEN source='pos' THEN 1 ELSE 0 END) AS pos_sales
             FROM storefront_orders WHERE storefront_uid=?"
        );
        $stmt->execute([$today, $today, $store['uid']]);
        $metrics = $stmt->fetch() ?: [];
        $catalog = $this->storefronts->catalog($store);
        $products = $catalog['products'];
        $metrics['products'] = count($products);
        $metrics['available_products'] = count(array_filter($products, static fn (array $p): bool => (bool) $p['available']));
        $metrics['low_stock'] = count(array_filter($products, static fn (array $p): bool =>
            $p['stock'] !== null && (float) $p['stock'] > 0 && (float) $p['stock'] <= 5
        ));
        $system = $this->storefronts->systemOverview($store);
        $metrics['web_customers'] = count($this->customers($store, 500));
        $metrics['customers'] = max((int) $metrics['web_customers'], (int) ($system['native_customers'] ?? 0));
        return array_merge($metrics, $system);
    }

    public function orders(array $store, int $limit = 100): array
    {
        $stmt = $this->db->prepare(
            "SELECT o.*,(SELECT COUNT(*) FROM storefront_order_items i WHERE i.order_uid=o.uid) AS item_count
             FROM storefront_orders o WHERE o.storefront_uid=? ORDER BY o.id DESC LIMIT ?"
        );
        $stmt->bindValue(1, $store['uid']);
        $stmt->bindValue(2, max(1, min(500, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function order(array $store, string $uid): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM storefront_orders WHERE uid=? AND storefront_uid=? LIMIT 1'
        );
        $stmt->execute([$uid, $store['uid']]);
        $order = $stmt->fetch();
        if (!$order) {
            throw new RuntimeException('Pedido no encontrado.', 404);
        }

        $items = $this->db->prepare(
            'SELECT * FROM storefront_order_items WHERE order_uid=? ORDER BY id ASC'
        );
        $items->execute([$uid]);
        $order['items'] = $items->fetchAll();

        return $order;
    }

    public function customers(array $store, int $limit = 100): array
    {
        $stmt = $this->db->prepare(
            "SELECT customer_name,customer_document,customer_phone,customer_email,
             COUNT(*) AS orders,COALESCE(SUM(total),0) AS total_spent,MAX(created_at) AS last_order
             FROM storefront_orders WHERE storefront_uid=?
             GROUP BY COALESCE(NULLIF(customer_document,''),NULLIF(customer_email,''),NULLIF(customer_phone,''),customer_name)
             ORDER BY last_order DESC LIMIT ?"
        );
        $stmt->bindValue(1, $store['uid']);
        $stmt->bindValue(2, max(1, min(500, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function createPosSale(array $store, array $input, array $admin): array
    {
        $requestedItems = is_array($input['items'] ?? null) ? array_slice($input['items'], 0, 100) : [];
        if ($requestedItems === []) {
            throw new InvalidArgumentException('Agrega productos a la venta.');
        }
        $items = [];
        $subtotal = 0.0;
        foreach ($requestedItems as $requested) {
            if (!is_array($requested)) continue;
            $quantity = max(1, min(999, (int) ($requested['quantity'] ?? 1)));
            $detail = $this->storefronts->productDetail($store, (string) ($requested['uid'] ?? ''));
            $product = $detail['product'];
            $sourceTable = (string) $detail['table'];
            if (!$product['available'] || $product['price'] === null) {
                throw new InvalidArgumentException($product['name'] . ' no está disponible para vender.');
            }
            if ($product['stock'] !== null && $quantity > (float) $product['stock']) {
                throw new InvalidArgumentException('Existencia insuficiente para ' . $product['name'] . '.');
            }
            $unitPrice = round((float) $product['price'], 2);
            $lineTotal = round($unitPrice * $quantity, 2);
            $subtotal += $lineTotal;
            $note = mb_substr(trim((string) ($requested['note'] ?? '')), 0, 250);
            $imei = mb_substr(trim((string) ($requested['imei'] ?? '')), 0, 40);
            $metadata = [
                'note' => $note,
                'imei' => $imei,
                'kind' => $product['kind'] ?? 'product',
                'source_table' => $sourceTable,
            ];
            $items[] = compact('product', 'sourceTable', 'quantity', 'unitPrice', 'lineTotal', 'note', 'imei', 'metadata');
        }
        if ($items === []) throw new InvalidArgumentException('Agrega productos válidos.');
        $subtotal = round($subtotal, 2);
        $discount = max(0, min($subtotal, round((float) ($input['discount'] ?? 0), 2)));
        $taxRate = max(0, min(30, (float) ($input['tax_rate'] ?? 0)));
        $tax = round(($subtotal - $discount) * ($taxRate / 100), 2);
        $total = round($subtotal - $discount + $tax, 2);
        $payment = (string) ($input['payment_method'] ?? 'cash');
        if (!in_array($payment, ['cash', 'card', 'transfer', 'credit'], true)) {
            throw new InvalidArgumentException('Método de pago inválido.');
        }
        $now = Support::now();
        $uid = Support::uid('ord_');
        $number = 'POS-' . gmdate('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $name = mb_substr(trim((string) ($input['customer_name'] ?? 'Consumidor final')), 0, 120) ?: 'Consumidor final';
        $movements = [];
        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                "INSERT INTO storefront_orders
                 (uid,order_number,project_uid,storefront_uid,customer_name,customer_email,customer_phone,customer_document,
                  delivery_method,customer_notes,payment_provider,currency,subtotal,discount_total,tax_total,total,status,
                  payment_status,paid_at,source,admin_user_uid,table_reference,created_at,updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
            )->execute([
                $uid, $number, $store['project_uid'], $store['uid'], $name,
                mb_substr(trim((string) ($input['customer_email'] ?? '')), 0, 160) ?: null,
                mb_substr(trim((string) ($input['customer_phone'] ?? '')), 0, 40),
                mb_substr(trim((string) ($input['customer_document'] ?? '')), 0, 50) ?: null,
                'counter', mb_substr(trim((string) ($input['notes'] ?? '')), 0, 600) ?: null,
                $payment, $store['currency'], $subtotal, $discount, $tax, $total, 'completed',
                $payment === 'credit' ? 'pending' : 'paid', $payment === 'credit' ? null : $now,
                'pos', $admin['uid'], mb_substr(trim((string) ($input['table_reference'] ?? '')), 0, 80) ?: null,
                $now, $now,
            ]);
            $insert = $this->db->prepare(
                'INSERT INTO storefront_order_items
                 (uid,order_uid,product_uid,product_name,product_sku,product_image,unit_price,quantity,line_total,metadata,created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)'
            );
            foreach ($items as $item) {
                $product = $item['product'];
                $insert->execute([
                    Support::uid('itm_'), $uid, $product['uid'], $product['name'], $product['sku'] ?: null,
                    $product['image'] ?: null, $item['unitPrice'], $item['quantity'], $item['lineTotal'],
                    Support::json($item['metadata']), $now,
                ]);
            }
            $orderData = [
                'uid' => $uid,
                'order_number' => $number,
                'customer_name' => $name,
            ];
            $movements = $this->inventory->commit($store, $orderData, $items, true);
            $this->recordMovements($store, $uid, $movements);
            $this->db->prepare(
                "UPDATE storefront_orders SET inventory_status='committed',inventory_committed_at=?,updated_at=? WHERE uid=?"
            )->execute([$now, $now, $uid]);
            $this->addEvent($store, $uid, (string) ($admin['uid'] ?? ''), 'sale_created', null, 'completed', 'Venta registrada desde el POS.');
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($movements !== []) {
                try {
                    $this->inventory->restore($store, ['uid' => $uid, 'order_number' => $number], $movements);
                } catch (\Throwable) {
                    // The original exception remains the most useful response; the inventory log preserves the incident.
                }
            }
            throw $e;
        }
        $this->logs->write('storefront.pos.sale', (string) $store['project_uid'], 'storefront_orders', $uid, null, [
            'order_number' => $number, 'total' => $total, 'admin_user_uid' => $admin['uid'],
        ]);
        return ['uid' => $uid, 'order_number' => $number, 'total' => $total, 'currency' => $store['currency']];
    }

    public function updateOrderStatus(array $store, string $uid, string $status, array $admin = []): void
    {
        if (!in_array($status, ['pending', 'confirmed', 'preparing', 'ready', 'dispatched', 'delivered', 'completed', 'cancelled'], true)) {
            throw new InvalidArgumentException('Estado inválido.');
        }
        $order = $this->order($store, $uid);
        $previous = (string) ($order['status'] ?? '');
        $committingStatuses = ['confirmed', 'preparing', 'ready', 'dispatched', 'delivered', 'completed'];
        $needsInventory = in_array($status, $committingStatuses, true)
            && (string) ($order['inventory_status'] ?? 'pending') !== 'committed';
        if ($previous === $status && !$needsInventory) {
            return;
        }

        $movements = [];
        $restoredMovements = [];
        $now = Support::now();
        $this->db->beginTransaction();
        try {
            if (
                in_array($status, $committingStatuses, true)
                && (string) ($order['inventory_status'] ?? 'pending') !== 'committed'
            ) {
                $movements = $this->inventory->commit($store, $order, $order['items'], false);
                if ((string) ($order['inventory_status'] ?? '') === 'released') {
                    $this->db->prepare('DELETE FROM storefront_inventory_movements WHERE order_uid=?')->execute([$uid]);
                }
                $this->recordMovements($store, $uid, $movements);
                $this->db->prepare(
                    "UPDATE storefront_orders SET inventory_status='committed',inventory_committed_at=?,inventory_released_at=NULL WHERE uid=? AND storefront_uid=?"
                )->execute([$now, $uid, $store['uid']]);
            } elseif (
                $status === 'cancelled'
                && (string) ($order['inventory_status'] ?? '') === 'committed'
            ) {
                $storedMovements = $this->inventoryMovements($uid);
                $this->inventory->restore($store, $order, $storedMovements);
                $restoredMovements = $storedMovements;
                $this->db->prepare(
                    "UPDATE storefront_orders SET inventory_status='released',inventory_released_at=? WHERE uid=? AND storefront_uid=?"
                )->execute([$now, $uid, $store['uid']]);
            }

            $stmt = $this->db->prepare('UPDATE storefront_orders SET status=?,updated_at=? WHERE uid=? AND storefront_uid=?');
            $stmt->execute([$status, $now, $uid, $store['uid']]);
            if ($stmt->rowCount() < 1) {
                throw new RuntimeException('Pedido no encontrado.', 404);
            }
            $this->addEvent(
                $store,
                $uid,
                (string) ($admin['uid'] ?? ''),
                'status_changed',
                $previous,
                $status,
                'Estado actualizado desde el panel administrativo.'
            );
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($movements !== []) {
                try {
                    $this->inventory->restore($store, $order, $movements);
                } catch (\Throwable) {
                }
            } elseif ($restoredMovements !== []) {
                try {
                    $this->inventory->commit($store, $order, $order['items'], false);
                } catch (\Throwable) {
                }
            }
            throw $e;
        }
    }

    public function addEvent(
        array $store,
        string $orderUid,
        string $userUid,
        string $eventType,
        ?string $fromStatus,
        ?string $toStatus,
        string $note = '',
        array $metadata = [],
    ): void {
        $this->db->prepare(
            'INSERT INTO storefront_order_events
             (uid,order_uid,storefront_uid,user_uid,event_type,from_status,to_status,note,metadata,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            Support::uid('evt_'),
            $orderUid,
            $store['uid'],
            $userUid !== '' ? $userUid : null,
            $eventType,
            $fromStatus,
            $toStatus,
            $note !== '' ? mb_substr($note, 0, 500) : null,
            $metadata !== [] ? Support::json($metadata) : null,
            Support::now(),
        ]);
    }

    private function recordMovements(array $store, string $orderUid, array $movements): void
    {
        $insert = $this->db->prepare(
            'INSERT INTO storefront_inventory_movements
             (uid,order_uid,project_uid,item_uid,movement_type,table_name,stock_column,key_column,row_key,
              product_uid,product_name,imei_uid,imei_value,quantity,before_value,after_value,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        foreach ($movements as $movement) {
            $insert->execute([
                Support::uid('mov_'),
                $orderUid,
                $store['project_uid'],
                $movement['item_uid'] ?? null,
                $movement['type'] ?? 'stock',
                $movement['table_name'] ?? '',
                $movement['stock_column'] ?? null,
                $movement['key_column'] ?? null,
                isset($movement['row_key']) ? (string) $movement['row_key'] : null,
                $movement['product_uid'] ?? null,
                $movement['product_name'] ?? null,
                $movement['imei_uid'] ?? null,
                $movement['imei'] ?? null,
                (float) ($movement['quantity'] ?? 0),
                isset($movement['before_value']) ? (string) $movement['before_value'] : null,
                isset($movement['after_value']) ? (string) $movement['after_value'] : null,
                Support::now(),
            ]);
        }
        $imeisByProduct = [];
        foreach ($movements as $movement) {
            if (($movement['type'] ?? '') !== 'imei' || trim((string) ($movement['imei'] ?? '')) === '') {
                continue;
            }
            $imeisByProduct[(string) ($movement['product_uid'] ?? '')][] = (string) $movement['imei'];
        }
        foreach ($imeisByProduct as $productUid => $imeis) {
            if ($productUid === '') {
                continue;
            }
            $item = $this->db->prepare(
                'SELECT uid,metadata FROM storefront_order_items WHERE order_uid=? AND product_uid=? ORDER BY id ASC LIMIT 1'
            );
            $item->execute([$orderUid, $productUid]);
            $row = $item->fetch();
            if (!$row) {
                continue;
            }
            $metadata = json_decode((string) ($row['metadata'] ?? ''), true);
            $metadata = is_array($metadata) ? $metadata : [];
            $metadata['imei'] = $imeis[0];
            $metadata['imeis'] = array_values($imeis);
            $this->db->prepare('UPDATE storefront_order_items SET metadata=? WHERE uid=?')
                ->execute([Support::json($metadata), $row['uid']]);
        }
    }

    private function inventoryMovements(string $orderUid): array
    {
        $stmt = $this->db->prepare(
            'SELECT movement_type AS type,table_name,stock_column,key_column,row_key,product_uid,product_name,
                    imei_uid,imei_value AS imei,quantity,before_value,after_value
             FROM storefront_inventory_movements WHERE order_uid=? ORDER BY id ASC'
        );
        $stmt->execute([$orderUid]);
        return $stmt->fetchAll();
    }
}
