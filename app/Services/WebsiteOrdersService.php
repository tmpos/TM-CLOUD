<?php
declare(strict_types=1);
namespace App\Services;

use PDO;
use InvalidArgumentException;
use RuntimeException;

/** Read-only access to web checkout orders; project identity is supplied by authentication. */
final class WebsiteOrdersService
{
    public function __construct(private PDO $db) {}

    public function handle(array $project, string $operation, array $input = []): array
    {
        $where = "project_uid=? AND source='web'";
        $params = [(string) $project['uid']];
        if ($operation === 'detail') {
            $stmt = $this->db->prepare("SELECT * FROM storefront_orders WHERE $where AND uid=?");
            $stmt->execute([...$params, (string) ($input['uid'] ?? '')]);
            $order = $stmt->fetch();
            if (!$order) throw new RuntimeException('Pedido no encontrado.', 404);
            // Provider payloads may contain private gateway data and are not part of this screen.
            unset($order['provider_payload'], $order['admin_user_uid']);
            $stmt = $this->db->prepare('SELECT uid,product_uid,product_name,product_sku,unit_price,quantity,line_total FROM storefront_order_items WHERE order_uid=? ORDER BY id');
            $stmt->execute([$order['uid']]);
            $order['items'] = $stmt->fetchAll();
            return ['order' => $order];
        }
        if ($operation !== 'list') throw new InvalidArgumentException('Operación de pedidos no válida.');
        $search = mb_substr(trim((string) ($input['search'] ?? '')), 0, 120);
        if ($search !== '') {
            $where .= " AND (order_number LIKE ? ESCAPE '!' OR customer_name LIKE ? ESCAPE '!' OR customer_phone LIKE ? ESCAPE '!')";
            $pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search) . '%';
            array_push($params, $pattern, $pattern, $pattern);
        }
        $status = (string) ($input['status'] ?? '');
        if ($status !== '') {
            if (!in_array($status, ['pending','confirmed','preparing','ready','dispatched','delivered','completed','cancelled'], true)) throw new InvalidArgumentException('Estado no válido.');
            $where .= ' AND status=?'; $params[] = $status;
        }
        $count = $this->db->prepare("SELECT COUNT(*) FROM storefront_orders WHERE $where");
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $limit = max(1, min(100, (int) ($input['limit'] ?? 25)));
        $page = min(max(1, (int) ($input['page'] ?? 1)), max(1, (int) ceil($total / $limit)));
        $stmt = $this->db->prepare("SELECT uid,order_number,customer_name,customer_phone,created_at,status,payment_status,payment_provider,delivery_method,currency,total FROM storefront_orders WHERE $where ORDER BY id DESC LIMIT ? OFFSET ?");
        foreach ($params as $i => $value) $stmt->bindValue($i + 1, $value);
        $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, ($page - 1) * $limit, PDO::PARAM_INT);
        $stmt->execute();
        return ['orders' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'limit' => $limit];
    }
}
