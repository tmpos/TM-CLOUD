<?php if (!(int) ($store['show_prices'] ?? 1)): ?>
<style>
[data-add-uid],[data-quick-add],[data-add-product],[data-add],[data-quantity],
[data-cart-open],a[href$="/cart"],a[href$="/checkout"],.drawer-foot{display:none!important}
.store-catalog-notice{padding:14px 20px;background:var(--soft,#f5f7fb);color:var(--ink,#14213d);text-align:center;font:inherit}
.store-catalog-notice a{text-decoration:underline;font-weight:700}
</style>
<?php endif; ?>
