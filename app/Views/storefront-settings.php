<?php
use App\Core\Csrf;
$paymentsByProvider = [];
foreach ($paymentMethods as $method) $paymentsByProvider[$method['provider']] = $method;
$providerHelp = [
    'cash' => 'El cliente paga en efectivo cuando retira el pedido.',
    'bank_transfer' => 'Muestra tus instrucciones bancarias después de crear el pedido.',
    'card_on_delivery' => 'Cobro presencial con terminal al entregar o retirar.',
    'azul' => 'Redirige a la página de pago segura provista por Azul.',
    'stripe' => 'Checkout alojado por Stripe. La clave secreta se guarda cifrada.',
    'paypal' => 'PayPal Orders con modo Sandbox o producción.',
];
?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div><a href="/projects/<?= e($project['uid']) ?>" class="text-sm text-slate-500 hover:text-brand">&larr; <?= e($project['name']) ?></a><h2 class="mt-2 text-2xl font-bold text-white">Comercio electrónico</h2><p class="mt-1 text-sm text-slate-500">Controla la identidad, el diseño, la entrega, el checkout y los pagos de la tienda.</p></div>
    <a class="btn-primary" target="_blank" rel="noopener" href="<?= e($store['url']) ?>">Ver tienda</a>
</div>

<nav class="card mb-5 flex gap-2 overflow-x-auto p-2 text-sm font-semibold">
    <a class="rounded-lg bg-brand px-4 py-2 text-white" href="#diseno">Diseño</a>
    <a class="rounded-lg px-4 py-2 text-slate-400 hover:bg-white/5 hover:text-white" href="#checkout">Checkout</a>
    <a class="rounded-lg px-4 py-2 text-slate-400 hover:bg-white/5 hover:text-white" href="#pagos">Pagos</a>
    <a class="rounded-lg px-4 py-2 text-slate-400 hover:bg-white/5 hover:text-white" href="#administradores">Administradores</a>
    <a class="rounded-lg px-4 py-2 text-slate-400 hover:bg-white/5 hover:text-white" href="#pedidos">Pedidos</a>
</nav>

<form id="diseno" method="post" action="/projects/<?= e($project['uid']) ?>/storefront" class="grid gap-5 xl:grid-cols-[1fr_360px] scroll-mt-5">
<input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
<div class="space-y-5">
<section class="card p-6">
    <div class="flex items-center justify-between gap-4"><div><h3 class="font-semibold text-white">Identidad de la tienda</h3><p class="mt-1 text-sm text-slate-500">Nombre, portada y dirección pública.</p></div><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="enabled" value="1" <?= $store['enabled']?'checked':'' ?>> Publicada</label></div>
    <div class="mt-6 grid gap-4 md:grid-cols-2">
        <label><span class="label">Nombre</span><input class="input" name="store_name" required maxlength="100" value="<?= e($store['store_name']) ?>"></label>
        <label><span class="label">Enlace</span><div class="flex items-center"><span class="rounded-l-lg border border-r-0 border-line bg-black/20 px-3 py-2 text-xs text-slate-500">/store/</span><input class="input rounded-l-none" name="slug" required value="<?= e($store['slug']) ?>"></div></label>
        <label class="md:col-span-2"><span class="label">Frase corta</span><input class="input" name="tagline" maxlength="160" value="<?= e($store['tagline']) ?>"></label>
        <label class="md:col-span-2"><span class="label">Título principal</span><input class="input" name="hero_title" maxlength="160" value="<?= e($store['hero_title']) ?>"></label>
        <label class="md:col-span-2"><span class="label">Descripción de portada</span><textarea class="input min-h-24" name="hero_text" maxlength="500"><?= e($store['hero_text']) ?></textarea></label>
        <label class="md:col-span-2"><span class="label">URL del logo</span><input class="input" type="url" name="logo_url" value="<?= e($store['logo_url']) ?>" placeholder="https://..."></label>
        <div class="md:col-span-2 mt-2 rounded-xl border border-line bg-black/10 p-4"><h4 class="font-semibold text-white">Fondo de la portada</h4><p class="mt-1 text-xs text-slate-500">Usa un color, una imagen o hasta 8 imágenes como carrusel.</p><div class="mt-4 grid gap-4 md:grid-cols-2">
            <label><span class="label">Tipo de fondo</span><select class="input" name="hero_background_mode"><?php foreach(['gradient'=>'Degradado actual','color'=>'Color sólido','image'=>'Una imagen','carousel'=>'Carrusel de imágenes'] as $value=>$label): ?><option value="<?= $value ?>" <?= ($store['hero_background_mode']??'gradient')===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
            <label><span class="label">Color de fondo / respaldo</span><div class="flex gap-2"><input class="input h-11 w-16 p-1" type="color" name="hero_background_color" value="<?= e($store['hero_background_color']??'#0b1324') ?>"><input class="input min-w-0 flex-1 uppercase" value="<?= e($store['hero_background_color']??'#0b1324') ?>" readonly></div></label>
            <label class="md:col-span-2"><span class="label">Imágenes del hero (una URL o UID fil_ por línea)</span><textarea class="input min-h-28 font-mono text-xs" name="hero_images" placeholder="https://.../portada-1.jpg&#10;https://.../portada-2.jpg"><?= e(implode("\n", is_array($store['hero_images']??null)?$store['hero_images']:[])) ?></textarea><small class="text-slate-500">En modo imagen se usa la primera. En carrusel cambian automáticamente.</small></label>
            <label><span class="label">Oscurecer imagen</span><input class="input" type="range" min="0" max="85" name="hero_overlay_opacity" value="<?= (int)($store['hero_overlay_opacity']??55) ?>" oninput="this.nextElementSibling.textContent=this.value+'%'"><small class="text-slate-500"><?= (int)($store['hero_overlay_opacity']??55) ?>%</small></label>
            <label><span class="label">Velocidad del carrusel</span><select class="input" name="hero_carousel_interval"><?php foreach([3000=>'3 segundos',5000=>'5 segundos',7000=>'7 segundos',10000=>'10 segundos'] as $value=>$label): ?><option value="<?= $value ?>" <?= (int)($store['hero_carousel_interval']??6000)===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        </div></div>
    </div>
</section>
<section class="card p-6">
    <h3 class="font-semibold text-white">Portada comercial</h3>
    <p class="mt-1 text-sm text-slate-500">Configura las secciones que ayudan a descubrir y vender productos.</p>
    <div class="mt-5 grid gap-4 md:grid-cols-2">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="show_featured" value="1" <?= ($store['show_featured']??1)?'checked':'' ?>> Mostrar productos destacados</label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="show_new_arrivals" value="1" <?= ($store['show_new_arrivals']??1)?'checked':'' ?>> Mostrar recién agregados</label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="show_brands" value="1" <?= ($store['show_brands']??1)?'checked':'' ?>> Mostrar carrusel de marcas</label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="promo_enabled" value="1" <?= ($store['promo_enabled']??1)?'checked':'' ?>> Mostrar banner promocional</label>
        <label class="md:col-span-2"><span class="label">Título promocional</span><input class="input" name="promo_title" maxlength="120" value="<?= e($store['promo_title']??'') ?>"></label>
        <label class="md:col-span-2"><span class="label">Texto promocional</span><textarea class="input min-h-20" name="promo_text" maxlength="280"><?= e($store['promo_text']??'') ?></textarea></label>
        <label><span class="label">Imagen promocional (URL o UID fil_)</span><input class="input" name="promo_image" value="<?= e($store['promo_image']??'') ?>" placeholder="fil_... o https://..."></label>
        <label><span class="label">Enlace del banner</span><input class="input" name="promo_link" value="<?= e($store['promo_link']??'') ?>" placeholder="/store/mi-tienda/categories"></label>
    </div>
</section>
<section class="card p-6">
    <h3 class="font-semibold text-white">Sistema visual</h3><p class="mt-1 text-sm text-slate-500">Los colores se aplican al catálogo, productos, carrito y checkout.</p>
    <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach (['primary_color'=>'Principal','accent_color'=>'Acento','background_color'=>'Fondo','surface_color'=>'Superficie','text_color'=>'Texto','muted_color'=>'Texto secundario','header_color'=>'Encabezado','footer_color'=>'Pie de página'] as $field=>$label): ?>
        <label><span class="label"><?= e($label) ?></span><div class="flex gap-2"><input class="input h-11 w-16 p-1" type="color" name="<?= e($field) ?>" value="<?= e($store[$field]) ?>"><input class="input min-w-0 flex-1 uppercase" value="<?= e($store[$field]) ?>" readonly></div></label>
        <?php endforeach; ?>
        <label><span class="label">Tipografía</span><select class="input" name="font_family"><?php foreach(['inter'=>'Inter / moderna','system'=>'Sistema / rápida','poppins'=>'Poppins / geométrica','serif'=>'Serif / elegante'] as $value=>$label): ?><option value="<?= $value ?>" <?= $store['font_family']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label><span class="label">Portada</span><select class="input" name="hero_style"><?php foreach(['gradient'=>'Gradiente','minimal'=>'Minimalista','split'=>'Dividida'] as $value=>$label): ?><option value="<?= $value ?>" <?= $store['hero_style']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label><span class="label">Tarjetas</span><select class="input" name="card_style"><?php foreach(['elevated'=>'Elevadas','outlined'=>'Contorno','minimal'=>'Minimalistas'] as $value=>$label): ?><option value="<?= $value ?>" <?= $store['card_style']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label><span class="label">Redondeado (px)</span><input class="input" type="range" min="8" max="32" name="border_radius" value="<?= (int)$store['border_radius'] ?>" oninput="this.nextElementSibling.textContent=this.value+' px'"><small class="text-slate-500"><?= (int)$store['border_radius'] ?> px</small></label>
    </div>
    <div class="mt-5 grid gap-4 md:grid-cols-2"><label class="md:col-span-2"><span class="label">Texto del anuncio superior</span><input class="input" name="announcement_text" maxlength="200" value="<?= e($store['announcement_text']) ?>" placeholder="Envío gratis en compras mayores de..."></label><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="announcement_enabled" value="1" <?= $store['announcement_enabled']?'checked':'' ?>> Mostrar anuncio</label><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="show_stock" value="1" <?= $store['show_stock']?'checked':'' ?>> Mostrar existencias</label><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="show_sku" value="1" <?= $store['show_sku']?'checked':'' ?>> Mostrar código/SKU</label></div>
</section>
</div>
<aside class="space-y-5">
    <section class="card p-5"><h3 class="font-semibold text-white">Catálogo</h3><label class="mt-4 block"><span class="label">Tipo de negocio</span><select class="input" name="business_type"><?php foreach(['auto'=>'Detectar automáticamente','electronics'=>'Electrónica y celulares','restaurant'=>'Restaurante y comida','general'=>'Tienda general'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= ($store['business_type']??'auto')===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select><small class="text-slate-500">La detección automática usa las tablas y el nombre del proyecto.</small></label><label class="mt-4 block"><span class="label">Tabla de productos</span><select class="input" name="catalog_table"><option value="">Detectar automáticamente</option><?php foreach($tables as $table): ?><option value="<?= e($table) ?>" <?= $store['catalog_table']===$table?'selected':'' ?>><?= e($table) ?></option><?php endforeach; ?></select></label><label class="mt-4 block"><span class="label">Moneda</span><select class="input" name="currency"><?php foreach(['DOP'=>'Peso dominicano','USD'=>'Dólar estadounidense','EUR'=>'Euro'] as $code=>$label): ?><option value="<?= $code ?>" <?= $store['currency']===$code?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label></section>
    <section class="card p-5"><h3 class="font-semibold text-white">Contacto y redes</h3><div class="mt-4 space-y-4"><label><span class="label">Teléfono</span><input class="input" name="phone" value="<?= e($store['phone']) ?>"></label><label><span class="label">WhatsApp</span><input class="input" name="whatsapp" value="<?= e($store['whatsapp']) ?>" placeholder="18095550101"></label><label><span class="label">Correo</span><input class="input" type="email" name="email" value="<?= e($store['email']) ?>"></label><label><span class="label">Dirección</span><textarea class="input min-h-20" name="address"><?= e($store['address']) ?></textarea></label><label><span class="label">Instagram</span><input class="input" type="url" name="instagram_url" value="<?= e($store['instagram_url']) ?>"></label><label><span class="label">Facebook</span><input class="input" type="url" name="facebook_url" value="<?= e($store['facebook_url']) ?>"></label><label><span class="label">Texto del pie</span><textarea class="input min-h-20" name="footer_text"><?= e($store['footer_text']) ?></textarea></label></div></section>
    <section id="checkout" class="card scroll-mt-5 p-5"><h3 class="font-semibold text-white">Entrega y checkout</h3><div class="mt-4 space-y-3"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="pickup_enabled" value="1" <?= $store['pickup_enabled']?'checked':'' ?>> Retiro en tienda</label><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="delivery_enabled" value="1" <?= $store['delivery_enabled']?'checked':'' ?>> Entrega local</label><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="shipping_enabled" value="1" <?= $store['shipping_enabled']?'checked':'' ?>> Envío nacional</label><label><span class="label">Costo fijo de envío</span><input class="input" type="number" min="0" step=".01" name="flat_shipping_cost" value="<?= e($store['flat_shipping_cost']) ?>"></label><label><span class="label">Envío gratis desde</span><input class="input" type="number" min="0" step=".01" name="free_shipping_threshold" value="<?= e($store['free_shipping_threshold']) ?>"></label><label><span class="label">URL de términos</span><input class="input" type="url" name="checkout_terms_url" value="<?= e($store['checkout_terms_url']) ?>"></label></div></section>
    <button class="btn-primary w-full">Guardar diseño y checkout</button>
</aside>
</form>

<section id="pagos" class="card mt-6 scroll-mt-5 p-6">
<div><h3 class="font-semibold text-white">Métodos de pago</h3><p class="mt-1 text-sm text-slate-500">Activa solo los que usarás. Los secretos se cifran y nunca se muestran en la tienda.</p></div>
<form method="post" action="/projects/<?= e($project['uid']) ?>/storefront/payments" class="mt-6">
<input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
<div class="grid gap-4 xl:grid-cols-2">
<?php foreach ($paymentsByProvider as $provider=>$method): $cfg=$method['public_config']; ?>
<article class="rounded-xl border border-line bg-black/10 p-5">
    <div class="flex items-start justify-between gap-3"><div><h4 class="font-semibold text-white"><?= e($method['display_name']) ?></h4><p class="mt-1 text-xs text-slate-500"><?= e($providerHelp[$provider] ?? '') ?></p></div><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="payments[<?= e($provider) ?>][enabled]" value="1" <?= $method['enabled']?'checked':'' ?>> Activo</label></div>
    <div class="mt-4 grid gap-3 md:grid-cols-2">
        <label><span class="label">Nombre visible</span><input class="input" name="payments[<?= e($provider) ?>][display_name]" value="<?= e($method['display_name']) ?>"></label>
        <?php if(in_array($provider,['stripe','paypal','azul'],true)): ?><label class="flex items-end gap-2 pb-3 text-sm"><input type="checkbox" name="payments[<?= e($provider) ?>][test_mode]" value="1" <?= $method['test_mode']?'checked':'' ?>> Modo de prueba</label><?php endif; ?>
        <?php if($provider==='stripe'): ?><label><span class="label">Publishable key</span><input class="input" autocomplete="off" name="payments[stripe][publishable_key]" value="<?= e($cfg['publishable_key']??'') ?>" placeholder="pk_..."></label><label><span class="label">Secret key <?= $method['credentials_configured']?'(guardada)':'' ?></span><input class="input" type="password" autocomplete="new-password" name="payments[stripe][secret_key]" placeholder="<?= $method['credentials_configured']?'Dejar vacío para conservar':'sk_...' ?>"></label><?php endif; ?>
        <?php if($provider==='paypal'): ?><label><span class="label">Client ID</span><input class="input" autocomplete="off" name="payments[paypal][client_id]" value="<?= e($cfg['client_id']??'') ?>"></label><label><span class="label">Client secret <?= $method['credentials_configured']?'(guardado)':'' ?></span><input class="input" type="password" autocomplete="new-password" name="payments[paypal][client_secret]" placeholder="<?= $method['credentials_configured']?'Dejar vacío para conservar':'' ?>"></label><?php endif; ?>
        <?php if($provider==='azul'): ?><label><span class="label">Merchant ID</span><input class="input" name="payments[azul][merchant_id]" value="<?= e($cfg['merchant_id']??'') ?>"></label><label><span class="label">Enlace de pago alojado</span><input class="input" type="url" name="payments[azul][payment_url]" value="<?= e($cfg['payment_url']??'') ?>" placeholder="https://..."></label><?php endif; ?>
        <label class="md:col-span-2"><span class="label">Instrucciones para el cliente</span><textarea class="input min-h-20" name="payments[<?= e($provider) ?>][instructions]" placeholder="Detalles que verá al seleccionar este método"><?= e($method['instructions']) ?></textarea></label>
        <?php if($method['credentials_configured']): ?><label class="md:col-span-2 flex items-center gap-2 text-xs text-rose-300"><input type="checkbox" name="payments[<?= e($provider) ?>][clear_credentials]" value="1"> Borrar credenciales guardadas</label><?php endif; ?>
    </div>
</article>
<?php endforeach; ?>
</div>
<div class="mt-5 flex justify-end"><button class="btn-primary">Guardar métodos de pago</button></div>
</form>
</section>

<section id="administradores" class="card mt-6 scroll-mt-5 p-6">
<div class="flex flex-wrap items-start justify-between gap-4"><div><h3 class="font-semibold text-white">Panel administrativo del comercio</h3><p class="mt-1 max-w-2xl text-sm text-slate-500">Crea accesos independientes para propietarios, gerentes, cajeros o cocina. Este proyecto entra por <strong class="text-slate-300">/<?= e($store['slug']) ?>/admin/</strong> y no puede acceder a la consola privada de soporte.</p></div><a class="btn-secondary" target="_blank" href="/<?= e(rawurlencode($store['slug'])) ?>/admin/">Abrir panel del proyecto</a></div>
<form method="post" action="/projects/<?= e($project['uid']) ?>/storefront/admins" class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
<input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
<label><span class="label">Nombre completo</span><input class="input" name="name" required maxlength="100" placeholder="Administrador"></label>
<label><span class="label">Correo electrónico</span><input class="input" type="email" name="email" required autocomplete="off" placeholder="admin@empresa.com"></label>
<label><span class="label">Contraseña inicial</span><input class="input" type="password" name="password" required minlength="10" autocomplete="new-password" placeholder="Mínimo 10 caracteres"></label>
<label><span class="label">Rol</span><select class="input" name="role"><option value="owner">Propietario</option><option value="manager">Gerente</option><option value="cashier">Cajero</option><option value="kitchen">Cocina</option></select></label>
<div class="md:col-span-2 xl:col-span-4 flex justify-end"><button class="btn-primary">Crear o actualizar acceso</button></div>
</form>
</section>

<section id="pedidos" class="card mt-6 scroll-mt-5 overflow-hidden">
<div class="border-b border-line p-6"><h3 class="font-semibold text-white">Pedidos recientes</h3><p class="mt-1 text-sm text-slate-500">Órdenes creadas desde el nuevo checkout.</p></div>
<div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-black/20 text-xs uppercase text-slate-500"><tr><th class="px-6 py-3">Pedido</th><th class="px-6 py-3">Cliente</th><th class="px-6 py-3">Pago</th><th class="px-6 py-3">Estado</th><th class="px-6 py-3 text-right">Total</th><th class="px-6 py-3">Fecha</th></tr></thead><tbody class="divide-y divide-line"><?php if(!$orders): ?><tr><td colspan="6" class="px-6 py-10 text-center text-slate-500">Todavía no hay pedidos web.</td></tr><?php endif; ?><?php foreach($orders as $order): ?><tr><td class="px-6 py-4 font-semibold text-white"><?= e($order['order_number']) ?></td><td class="px-6 py-4"><?= e($order['customer_name']) ?><small class="block text-slate-500"><?= e($order['customer_phone']) ?></small></td><td class="px-6 py-4"><?= e(ucfirst(str_replace('_',' ',$order['payment_provider']))) ?></td><td class="px-6 py-4"><span class="rounded-full bg-white/5 px-2 py-1 text-xs"><?= e($order['payment_status']) ?></span></td><td class="px-6 py-4 text-right font-semibold"><?= e($order['currency']) ?> <?= number_format((float)$order['total'],2) ?></td><td class="px-6 py-4 text-slate-500"><?= e($order['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div>
</section>

<script>
document.querySelectorAll('input[type="color"]').forEach(input=>input.addEventListener('input',()=>{input.nextElementSibling.value=input.value.toUpperCase()}));
</script>
