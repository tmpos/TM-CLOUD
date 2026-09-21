<?php

declare(strict_types=1);

$root = dirname(__DIR__);
foreach ([
    'app/Core/Support.php',
    'app/Core/Database.php',
    'app/Core/Auth.php',
    'app/Core/Http.php',
    'app/Services/LogService.php',
    'app/Services/ProjectService.php',
    'app/Services/SchemaService.php',
    'app/Services/RecordService.php',
    'app/Services/StorefrontService.php',
    'app/Services/CredentialCipher.php',
    'app/Services/StorefrontCommerceService.php',
    'app/Services/StorefrontInventoryService.php',
    'app/Services/WebhookService.php',
] as $file) {
    require_once $root . '/' . $file;
}

use App\Core\Database;
use App\Services\LogService;
use App\Services\ProjectService;
use App\Services\RecordService;
use App\Services\SchemaService;
use App\Services\StorefrontService;
use App\Services\CredentialCipher;
use App\Services\StorefrontCommerceService;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$storage = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tmpbase-storefront-' . bin2hex(random_bytes(6));
foreach ([$storage, "$storage/projects", "$storage/backups", "$storage/uploads"] as $directory) {
    mkdir($directory, 0775, true);
}
$database = $storage . DIRECTORY_SEPARATOR . 'central.sqlite';
$config = ['storage' => $storage, 'database' => $database, 'url' => 'https://api.example.test'];

try {
    $db = Database::connect($database);
    Database::migrate($db);
    $logs = new LogService($db);
    $projects = new ProjectService($db, $config, $logs);
    $schema = new SchemaService($projects, $logs);
    $records = new RecordService($schema, $logs);
    $storefronts = new StorefrontService($db, $config, $projects, $schema, $records);
    $commerce = new StorefrontCommerceService($db, $config, new CredentialCipher($storage), $storefronts, $logs);

    $project = $projects->create(['name' => 'Catalog Settings Test']);
    $store = $storefronts->findForProject($project['uid']);
    check((int) $store['show_prices'] === 1, 'Existing stores should display prices by default.');
    $fields = static fn (array $names): array => array_map(static fn ($name) => ['name' => $name, 'type' => 'TEXT'], $names);
    $schema->createTable($project, 'empresa', $fields(['nombre', 'almacen_id']));
    $first = $records->create($project, 'empresa', ['nombre' => 'Principal', 'almacen_id' => '10']);
    $second = $records->create($project, 'empresa', ['nombre' => 'Sucursal', 'almacen_id' => '20']);
    $schema->createTable($project, 'accesorios', $fields(['nombre','precio_venta','precio_regular','cantidad','almacen_uid','almacen_id']));
    $a = $records->create($project, 'accesorios', ['nombre'=>'Principal','precio_venta'=>'125.75','precio_regular'=>'180','cantidad'=>'5','almacen_uid'=>$first['uid'],'almacen_id'=>'20']);
    $b = $records->create($project, 'accesorios', ['nombre'=>'Sucursal','precio_venta'=>'987.65','cantidad'=>'7','almacen_uid'=>$second['uid'],'almacen_id'=>'10']);
    $legacy = $records->create($project, 'accesorios', ['nombre'=>'Legacy','precio_venta'=>'25','cantidad'=>'3','almacen_id'=>'20']);
    $unassigned = $records->create($project, 'accesorios', ['nombre'=>'Sin almacén','precio_venta'=>'50','cantidad'=>'2','almacen_id'=>'0']);
    $ids = static fn (array $catalog): array => array_column($catalog['products'], 'uid');
    check($ids($storefronts->catalog($store)) === [$unassigned['uid'], $a['uid']], 'Default warehouse and UID precedence failed.');
    $originalColor = $store['primary_color'];
    $store = $storefronts->updateCatalogSettings($project['uid'], ['show_prices'=>'1','warehouse_uid'=>$second['uid']]);
    check($store['primary_color'] === $originalColor, 'Catalog settings overwrote presentation settings.');
    check($ids($storefronts->catalog($store)) === [$legacy['uid'],$b['uid']], 'Second warehouse or legacy numeric ID failed.');
    $blocked=false;
    try {$storefronts->productDetail($store,$a['uid']);} catch(RuntimeException $e){$blocked=$e->getCode()===404;}
    check($blocked,'Direct product URL exposed a different warehouse.');
    $blocked=false;
    try {$storefronts->updateCatalogSettings($project['uid'],['warehouse_uid'=>'foreign-warehouse']);} catch(InvalidArgumentException){$blocked=true;}
    check($blocked,'Foreign warehouse was accepted.');
    $store = $storefronts->updateCatalogSettings($project['uid'], ['show_prices'=>'0']);
    $public = $storefronts->catalog($store, '', '', ['min_price'=>900, 'sort'=>'price_desc']);
    check(count($public['products']) === 2, 'Hidden prices can be inferred through filters.');
    foreach ($public['products'] as $product) check($product['price'] === null && $product['compare_price'] === null, 'Public price leaked.');
    check($storefronts->productDetail($store,$b['uid'])['product']['price'] === null,'Detail leaked a hidden price.');
    check($storefronts->productDetail($store,$b['uid'],true)['product']['price'] === 987.65,'Administrative prices were hidden.');
    check($storefronts->catalog($store,'','',[],true)['products'][1]['price'] === 987.65,'Admin catalog lost prices.');
    $blocked=false;
    try {$commerce->createOrder($store,[]);} catch(InvalidArgumentException $e){$blocked=str_contains($e->getMessage(),'catálogo');}
    check($blocked,'Checkout allowed a hidden-price store.');
    $projectDb=$schema->connection($project);
    $missing=$store; $missing['warehouse_uid']='deleted';
    check($storefronts->catalog($missing)['products']===[],'Deleted warehouse fell back to another warehouse.');

    // Shared model tables with stock distributed between warehouses.
    $schema->createTable($project, 'telefonos', $fields(['nombre','precio_venta']));
    $phone=$records->create($project,'telefonos',['nombre'=>'Phone','precio_venta'=>'2500']);
    $schema->createTable($project, 'imei', $fields(['telefono_uid','imei','estado','almacen_uid','almacen_id']));
    $imeiA=$records->create($project,'imei',['telefono_uid'=>$phone['uid'],'imei'=>'111111111111111','estado'=>'DISPONIBLE','almacen_uid'=>$first['uid']]);
    $imeiB=$records->create($project,'imei',['telefono_uid'=>$phone['uid'],'imei'=>'222222222222222','estado'=>'DISPONIBLE','almacen_uid'=>$second['uid']]);
    $schema->createTable($project, 'electrodomesticos', $fields(['nombre']));
    $electronic=$records->create($project,'electrodomesticos',['nombre'=>'TV']);
    $schema->createTable($project, 'serial', $fields(['equipo_uid','precio_venta','estado','almacen_uid']));
    $records->create($project,'serial',['equipo_uid'=>$electronic['uid'],'precio_venta'=>'99','estado'=>'DISPONIBLE','almacen_uid'=>$first['uid']]);
    $records->create($project,'serial',['equipo_uid'=>$electronic['uid'],'precio_venta'=>'199','estado'=>'DISPONIBLE','almacen_uid'=>$second['uid']]);
    $store=$storefronts->updateCatalogSettings($project['uid'],['show_prices'=>'1']);
    check($storefronts->productDetail($store,$phone['uid'])['product']['stock']===1.0,'Phone stock includes another warehouse.');
    $tv=$storefronts->productDetail($store,$electronic['uid'])['product'];
    check($tv['stock']===1.0 && $tv['price']===199.0,'Serial inventory or price uses another warehouse.');
    $inventory=new \App\Services\StorefrontInventoryService($projects,$schema,$logs,new \App\Services\WebhookService($db));
    $warehouse=$storefronts->warehouseForStore($store);
    $items=[['product_uid'=>$phone['uid'],'product_name'=>'Phone','quantity'=>1,'metadata'=>['source_table'=>'telefonos','kind'=>'phone','warehouse'=>$warehouse]]];
    $otherStore=$storefronts->updateCatalogSettings($project['uid'],['warehouse_uid'=>$first['uid']]);
    $movements=$inventory->commit($otherStore,['uid'=>'test-order'], $items);
    check($movements[0]['row_key']===$imeiB['uid'],'Fulfillment used new settings instead of order warehouse.');
    $inventory->restore($store,['uid'=>'test-order'],$movements);
    $items[0]['metadata']['imei']='111111111111111';
    $blocked=false;
    try {$inventory->commit($store,['uid'=>'test-order'],$items,true);} catch(InvalidArgumentException){$blocked=true;}
    check($blocked,'Explicit IMEI from another warehouse was accepted.');
    $blocked=false;
    try {$inventory->commit($store,[],[['product_uid'=>$a['uid'],'quantity'=>1,'metadata'=>['source_table'=>'accesorios','warehouse'=>$warehouse]]]);} catch(RuntimeException){$blocked=true;}
    check($blocked,'Stock deduction crossed warehouses.');
    $moves=$inventory->commit($store,[],[['product_uid'=>$b['uid'],'quantity'=>1,'metadata'=>['source_table'=>'accesorios','warehouse'=>$warehouse]]]);
    check($moves[0]['after_value']===6.0,'Selected warehouse stock was not decremented.');
    $inventory->restore($store,[],$moves);

    // Render public HTML and the admin control for browser checks with fake data only.
    if (!function_exists('e')) {function e(mixed $value): string {return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}}
    $store=$storefronts->updateCatalogSettings($project['uid'],['show_prices'=>'0','warehouse_uid'=>$second['uid']]);
    $catalog=$allCatalog=$storefronts->catalog($store); $search=$category='';
    ob_start();require $root.'/app/Views/storefront.php';$html=ob_get_clean();
    check(!str_contains($html,'987.65')&&!str_contains($html,'2,500.00'),'Hidden price leaked in rendered HTML.');
    check(str_contains($html,'Consultar precio'),'Hidden-price catalog has no customer explanation.');
    file_put_contents('/tmp/storefront-catalog-hidden.html',$html);
    $detail=$storefronts->productDetail($store,$b['uid']); $product=$detail['product']; $related=$detail['related'];
    ob_start();require $root.'/app/Views/storefront-product.php';$html=ob_get_clean();
    check(!str_contains($html,'987.65'),'Product page leaked a price.');
    file_put_contents('/tmp/storefront-product-hidden.html',$html);
    $store=$storefronts->updateCatalogSettings($project['uid'],['show_prices'=>'1']);
    check($storefronts->productDetail($store,$b['uid'])['product']['price']===987.65,'Re-enabling prices failed.');
    echo "STOREFRONT_CATALOG_SETTINGS_OK\n";
} finally {
    if(isset($project['database_path'])) Database::disconnect($project['database_path']);
    Database::disconnect($database);
}
