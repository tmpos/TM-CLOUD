<?php foreach (\App\Services\StorefrontEditorService::activeCampaigns($store) as $campaign):
    $campaignImage = $campaign['image'];
    if (str_starts_with($campaignImage, 'fil_')) $campaignImage = substr($store['url'], 0, strpos($store['url'], '/store/')) . '/api/' . rawurlencode($store['project_uid']) . '/storage/' . rawurlencode($campaignImage);
?>
<section class="promo-band" data-campaign="<?= e($campaign['id']) ?>"><div class="container"><article class="promo-card">
<?php if ($campaignImage): ?><img src="<?= e($campaignImage) ?>" alt="" loading="lazy" style="width:100%;height:100%;max-height:350px;object-fit:cover"><?php endif; ?>
<div class="promo-copy"><small>Promoción</small><h2><?= e($campaign['title']) ?></h2><p><?= e($campaign['text']) ?></p><a href="<?= e($campaign['link'] ?: '/store/' . rawurlencode($store['slug']) . '/categories') ?>">Ver promoción →</a></div>
</article></div></section>
<?php endforeach; ?>
