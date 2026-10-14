<section class="trust-strip" aria-label="Beneficios de compra"><div class="container trust-grid">
    <div class="trust-item"><span class="trust-icon">✓</span><span><strong>Inventario sincronizado</strong><small>Disponibilidad actualizada desde la tienda</small></span></div>
    <div class="trust-item"><span class="trust-icon">▣</span><span><strong>Factura digital</strong><small>Consulta y descarga cuando la necesites</small></span></div>
    <div class="trust-item"><span class="trust-icon"><?= $store['delivery_enabled'] || $store['shipping_enabled'] ? '↗' : '⌂' ?></span><span><strong><?= $store['delivery_enabled'] || $store['shipping_enabled'] ? 'Opciones de entrega' : 'Retiro en tienda' ?></strong><small><?= $store['shipping_enabled'] ? 'Envíos configurados por el comercio' : ($store['delivery_enabled'] ? 'Entrega local disponible' : 'Compra en línea y recoge en tienda') ?></small></span></div>
    <div class="trust-item"><span class="trust-icon">◎</span><span><strong>Atención personalizada</strong><small>Asistencia antes y después de comprar</small></span></div>
</div></section>
