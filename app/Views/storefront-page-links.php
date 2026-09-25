<?php $pageBase = '/store/' . rawurlencode((string) $store['slug']); ?>
<nav aria-label="Información de la tienda" style="display:flex;justify-content:center;flex-wrap:wrap;gap:12px 24px;padding:20px 16px;font-size:14px;line-height:1.6">
<?php foreach (['nosotros'=>'Nosotros','contacto'=>'Contacto','preguntas-frecuentes'=>'Preguntas frecuentes','politicas'=>'Políticas','privacidad'=>'Privacidad','terminos'=>'Términos'] as $linkPage=>$linkLabel): ?>
  <a href="<?= e($pageBase . '/pages/' . $linkPage) ?>" style="display:inline;margin:0;color:inherit;text-decoration:underline;text-underline-offset:4px"><?= e($linkLabel) ?></a>
<?php endforeach; ?>
</nav>
