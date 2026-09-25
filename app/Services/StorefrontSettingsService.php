<?php
declare(strict_types=1);
namespace App\Services;

final class StorefrontSettingsService
{
    public function __construct(private StorefrontService $stores) {}

    public function handle(array $project, string $operation, array $input = []): array
    {
        $uid = (string) $project['uid'];
        $store = $this->stores->findForProject($uid);
        if ($operation === 'save') {
            // The dashboard uses HTML checkbox presence, while the app sends booleans.
            // Merge with current settings so an omitted field is never reset.
            $fields = array_intersect_key($input, $store);
            foreach (['uid', 'project_uid', 'created_at', 'updated_at', 'url'] as $key) unset($fields[$key]);
            $fields = array_replace($store, $fields);
            foreach (['enabled', 'promo_enabled', 'show_featured', 'show_new_arrivals', 'show_brands', 'announcement_enabled', 'show_stock', 'show_sku', 'pickup_enabled', 'delivery_enabled', 'shipping_enabled'] as $key) {
                if (empty($fields[$key])) unset($fields[$key]);
            }
            $store = $this->stores->update($uid, $fields);
        } elseif ($operation !== 'get') {
            throw new \InvalidArgumentException('Operación de página web no válida.');
        }
        return ['settings' => $store, 'warehouses' => $this->stores->warehousesForProject($uid), 'tables' => $this->stores->tablesForProject($uid)];
    }
}
