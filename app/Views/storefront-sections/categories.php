<?php if($navigationCatalog['categories']): ?>
<section class="category-showcase" aria-labelledby="product-category-title"><div class="container">
    <div class="category-showcase-head"><h2 id="product-category-title"><?= $isRestaurant?'Elige una categoría del menú':'Explora por categoría' ?></h2><a href="/store/<?= e($store['slug']) ?>/categories"><?= $isRestaurant?'Ver el menú completo':'Ver todas las categorías' ?> →</a></div>
    <nav class="category-cards" aria-label="Categorías de productos">
        <?php foreach($navigationCatalog['categories'] as $item): ?>
        <a class="category-card <?= mb_strtolower($category)===mb_strtolower($item)?'active':'' ?>" href="/store/<?= e($store['slug']) ?>/categories?category=<?= rawurlencode($item) ?>">
            <span class="category-icon"><?= e(mb_strtoupper(mb_substr($item,0,1))) ?></span>
            <span class="category-copy"><strong><?= e($item) ?></strong><small>Ver productos →</small></span>
        </a>
        <?php endforeach; ?>
    </nav>
</div></section>
<?php endif; ?>
