<?php
require __DIR__ . '/storefront-design.php';
$editorFavicon = $store['web_editor']['favicon'] ?? '';
if ($editorFavicon !== '' && str_starts_with($editorFavicon, 'fil_')) {
    $editorFavicon = substr($store['url'], 0, strpos($store['url'], '/store/')) . '/api/' . rawurlencode($store['project_uid']) . '/storage/' . rawurlencode($editorFavicon);
}
if ($editorFavicon !== ''): ?>
<link rel="icon" href="<?= e($editorFavicon) ?>">
<?php endif; ?>
