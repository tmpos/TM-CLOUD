<?php
foreach ($store['web_editor']['design']['blocks'] as $websiteBlock):
    echo '<div data-website-block="' . e($websiteBlock['id']) . '">';
    if ($websiteBlock['type']==='section') {
        $websiteSection=['id'=>$websiteBlock['section'],'visible'=>true];
        if ($websiteSection['id']==='campaigns') require __DIR__.'/storefront-campaigns.php';
        elseif (in_array($websiteSection['id'],\App\Services\StorefrontEditorService::SECTIONS,true)) require __DIR__.'/storefront-sections/'.$websiteSection['id'].'.php';
    } elseif ($websiteBlock['type']==='spacer') echo '<div class="website-builder-spacer" aria-hidden="true"></div>';
    else {
        echo '<section class="container website-builder-content" style="text-align:'.e($websiteBlock['align']).';background:'.e($websiteBlock['background']).'">';
        if ($websiteBlock['type']==='text') {
            if ($websiteBlock['title']!=='') echo '<h2>'.e($websiteBlock['title']).'</h2>';
            echo '<p>'.e($websiteBlock['text']).'</p>';
        } elseif ($websiteBlock['type']==='image' && $websiteBlock['image']!=='') {
            $blockImage=$websiteBlock['image'];
            if(str_starts_with($blockImage,'fil_')) $blockImage=substr($store['url'],0,strpos($store['url'],'/store/')).'/api/'.rawurlencode($store['project_uid']).'/storage/'.rawurlencode($blockImage);
            echo '<img loading="lazy" src="'.e($blockImage).'" alt="'.e($websiteBlock['title']).'">';
        } elseif ($websiteBlock['type']==='button') echo '<a href="'.e($websiteBlock['link']?:'/store/'.rawurlencode($store['slug']).'/categories').'">'.e($websiteBlock['title']?:'Ver productos').'</a>';
        echo '</section>';
    }
    echo '</div>';
endforeach;
