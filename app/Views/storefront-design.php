<?php
$websiteDesign = $store['web_editor']['design'] ?? \App\Services\StorefrontDesignService::defaults();
if ($websiteDesign['preset'] === 'classic') return;
$designRatio = ['square'=>'1 / 1','portrait'=>'3 / 4','wide'=>'16 / 9'][$websiteDesign['image_ratio']];
?>
<style data-website-design="<?= e($websiteDesign['preset']) ?>">
body .container{width:min(<?= (int)$websiteDesign['width'] ?>px,calc(100% - 32px))}
body .product-grid{grid-template-columns:repeat(<?= (int)$websiteDesign['columns'] ?>,minmax(0,1fr))}
body .product-media{aspect-ratio:<?= $designRatio ?>;height:auto}
body .product-media img{width:100%;height:100%;object-fit:contain}
body main>[data-website-section],body main>[data-website-block]{margin-bottom:<?= (int)$websiteDesign['spacing'] ?>px}
body .hero-inner{grid-template-columns:<?= $websiteDesign['hero']==='split'?'1.4fr 1fr':'1fr' ?>}
<?php if($websiteDesign['hero']!=='split'): ?>
body .hero-card{display:none}
body .hero-inner{max-width:960px;text-align:<?= $websiteDesign['hero']==='center'?'center':'left' ?>}
body .hero-actions,body .hero-metrics{justify-content:<?= $websiteDesign['hero']==='center'?'center':'flex-start' ?>}
<?php endif; ?>
<?php if($websiteDesign['hero']==='compact'): ?>body .hero{padding:28px 0}body .hero h1{font-size:clamp(28px,4vw,44px)}body .hero-metrics{display:none}<?php endif; ?>
<?php if($websiteDesign['header']==='center'): ?>
@media(min-width:801px){body .header{height:auto;min-height:100px}body .header-inner{flex-wrap:wrap;justify-content:center;padding-block:18px}body .header-inner .brand{flex-basis:100%;justify-content:center;text-align:center}body .website-custom-nav{justify-content:center}}
<?php endif; ?>
.website-builder-content{padding:clamp(20px,4vw,48px);border-radius:var(--radius,12px);overflow-wrap:anywhere}.website-builder-content h2{font-size:clamp(24px,4vw,42px);margin:0 0 16px}.website-builder-content p{white-space:pre-line;line-height:1.8}.website-builder-content img{max-width:100%;height:auto;border-radius:var(--radius,12px)}.website-builder-content a{display:inline-block;padding:14px 24px;border-radius:var(--radius,12px);background:var(--brand);color:#fff;text-decoration:none;font-weight:700}.website-builder-spacer{height:<?= (int)$websiteDesign['spacing'] ?>px}
body .hero-actions .primary{background:var(--brand);color:#fff}
/* Shared editorial rhythm; stored colors, fonts and dimensions remain editable. */
body .hero{padding-block:clamp(48px,7vw,112px)}body .hero h1{font-size:clamp(34px,5.5vw,76px);line-height:1.06;letter-spacing:-.045em;max-width:18ch;text-wrap:balance;margin-bottom:24px}
body .hero p{max-width:58ch;line-height:1.75;font-size:clamp(15px,1.3vw,18px)}body .eyebrow{letter-spacing:.14em;text-transform:uppercase;font-size:11px;font-weight:600}
body .hero-actions{gap:14px;margin-top:28px}body .hero-actions a{padding:14px 24px;font-weight:600}body .hero-metrics{gap:28px;margin-top:40px;padding-top:24px;border-top:1px solid #ffffff26}body .hero-metric strong{font-size:26px;letter-spacing:-.04em}
body .hero-card{padding:32px;border:1px solid #ffffff26;box-shadow:0 24px 64px #00000014;backdrop-filter:blur(12px)}body .hero-card-grid{gap:14px}body .benefit{padding:18px}body .product-grid{gap:24px}body .product{overflow:hidden;transition:transform .2s,box-shadow .2s}body .product-media{padding:12px}body .product:hover{transform:translateY(-3px);box-shadow:0 16px 36px #13213212}
body .website-custom-nav{gap:28px;padding-block:20px;font-size:13px;font-weight:500}body .website-custom-nav a{padding-block:4px}body a:focus-visible,body button:focus-visible{outline:3px solid var(--brand);outline-offset:4px}
<?php if($websiteDesign['hero']==='center'): ?>body .hero h1,body .hero p{margin-inline:auto}<?php endif; ?>
<?php switch($websiteDesign['preset']): case 'modern': ?>
body .hero-card{transform:rotate(2deg);border-radius:24px}body .hero-card-grid .benefit{background:#ffffff0d}body .hero-inner{gap:64px}body .product-media{background:linear-gradient(135deg,#8899aa08,#8899aa18)}
<?php break; case 'boutique': ?>
body .hero h1{font-weight:400;font-style:italic;font-size:clamp(38px,6vw,84px)}body .hero-metrics{display:none}body .product-grid{gap:36px}body .product-media{padding:0}body .website-custom-nav{text-transform:uppercase;letter-spacing:.12em;font-size:11px}body .hero-actions a{border-radius:0}
<?php break; case 'market': ?>
body .hero{padding-block:38px}body .hero h1{font-size:clamp(30px,4vw,48px);max-width:28ch}body .product-grid{gap:16px}body .hero-metrics{display:none}body .product-media{padding:8px}body .website-custom-nav{justify-content:flex-start;overflow-x:auto}
<?php break; case 'gourmet': ?>
body .hero h1{max-width:17ch;font-size:clamp(38px,5.5vw,72px)}body .hero-actions a{border-radius:40px}body .hero-metrics{display:none}body .product-media{border-radius:40px 40px 8px 8px}body .product-grid{gap:32px}body .hero-inner{padding-block:16px}
<?php break; case 'minimal': ?>
body .hero{padding-block:56px}body .hero h1{font-weight:400;max-width:24ch;font-size:clamp(32px,5vw,64px)}body .product-grid{gap:48px}body .product{box-shadow:none;border:0}body .product-media{padding:24px}body .hero-actions a{border-radius:0}body .website-custom-nav{border-top:1px solid #8882}
<?php break; case 'landing': ?>
body .hero h1{font-size:clamp(40px,6.5vw,88px);max-width:17ch}body .hero-metrics{display:none}body .website-builder-content{max-width:960px}body .website-builder-content p{max-width:65ch;margin-inline:auto}body .website-builder-content h2{letter-spacing:-.035em;text-wrap:balance}body .website-builder-content a{padding:18px 32px;box-shadow:0 8px 24px #17203318}
<?php break; case 'executive': ?>
body .hero-inner{gap:72px}body .hero h1{max-width:15ch;font-weight:600}body .hero-card{border-radius:0;border-left:4px solid var(--accent);box-shadow:none}body .hero-actions a{border-radius:4px}body .product{border-top:3px solid var(--brand)}body .website-custom-nav{justify-content:flex-start}body .hero-metric strong{font-weight:500}
<?php break; case 'atelier': ?>
body .hero h1{font-size:clamp(46px,8vw,108px);font-weight:400;font-style:italic;line-height:1;max-width:14ch}body .hero-metrics{display:none}body .hero-actions a{border-radius:0;letter-spacing:.1em;font-size:12px;text-transform:uppercase}body .product-grid{gap:56px}body .product-media{padding:0}body .product{border:0;box-shadow:none}body .website-custom-nav{letter-spacing:.18em;text-transform:uppercase;font-size:10px}
<?php break; case 'wellness': ?>
body .hero-card{border-radius:90px 24px 90px 24px;padding:44px 32px;box-shadow:none}body .hero h1{font-weight:500;letter-spacing:-.05em}body .hero-actions a{border-radius:40px}body .product-media{border-radius:120px 120px 16px 16px;padding:20px}body .product-grid{gap:32px}body .hero-metrics{border:0}body .website-custom-nav{font-weight:400}
<?php break; case 'urban': ?>
body .hero h1{font-weight:900;font-size:clamp(44px,7vw,96px);text-transform:uppercase;max-width:20ch;letter-spacing:-.06em}body .hero{padding-block:64px}body .hero-actions a,body .hero-card,body .product,body .product-media{border-radius:0}body .product-grid{gap:12px}body .website-custom-nav{text-transform:uppercase;font-weight:800;border-block:2px solid currentColor}body .product-media{padding:0}body .hero-actions .primary{box-shadow:5px 5px 0 #ffffff38}
<?php break; case 'panorama': ?>
body .hero{min-height:560px;display:grid;align-items:center;padding-block:100px}body .hero h1{font-size:clamp(42px,7vw,92px);font-weight:400;max-width:18ch}body .hero-metrics{display:none}body .product-grid{gap:40px}body .product-media{padding:0}body .hero-actions a{border-radius:6px}body .hero p{font-size:18px}body .hero-overlay{background:linear-gradient(180deg,#00000010,#00000099)}
<?php break; case 'restaurant': ?>
body .hero h1{font-weight:400;font-style:italic;font-size:clamp(42px,6vw,86px)}body .hero-metrics,body .hero-card{display:none}body .hero-actions a{border-radius:4px;text-transform:uppercase;font-size:12px;letter-spacing:.1em}body .product-media{padding:0}body .product-grid{gap:32px}body .website-builder-content h2{font-weight:400;font-style:italic}body .website-custom-nav{text-transform:uppercase;letter-spacing:.12em}body .website-builder-content{border-block:1px solid var(--line,#8883)}
<?php break; case 'gym': ?>
body .hero{background:var(--ink);color:#fff}body .hero h1{font-weight:900;text-transform:uppercase;max-width:16ch;letter-spacing:-.06em}body .hero-metrics,body .hero-card{display:none}body .hero-inner{grid-template-columns:1fr}body .hero-actions .primary{background:var(--accent);color:#172015}body .product-grid{gap:18px}body .product-media{padding:0;border-bottom:5px solid var(--accent)}body .website-builder-content{border-left:5px solid var(--accent);border-radius:0}body .website-builder-content h2{text-transform:uppercase;font-weight:900;letter-spacing:-.04em}body .website-custom-nav{text-transform:uppercase;font-weight:800}
<?php break; case 'spa': ?>
body .hero{background:var(--soft);color:var(--ink)}body .hero h1{font-size:clamp(42px,6vw,82px);font-weight:400;letter-spacing:-.035em}body .hero p,body .hero .eyebrow{color:var(--muted)}body .hero-metrics,body .hero-card{display:none}body .hero-actions a{border-radius:40px}body .hero-actions .secondary{color:var(--ink);border-color:var(--muted)}body .product-grid{gap:44px}body .product-media{padding:16px;border-radius:180px 180px 24px 24px}body .website-builder-content{max-width:980px;padding-block:56px}body .website-builder-content h2{font-weight:400;font-style:italic}body .website-builder-content p{max-width:58ch;margin-inline:auto}body .website-custom-nav{letter-spacing:.1em;font-weight:400}
<?php break; endswitch; ?>
@media(prefers-reduced-motion:reduce){body .product{transition:none}body .product:hover{transform:none}}
@media(max-width:700px){body .hero{padding-block:40px;min-height:0}body .hero h1{font-size:clamp(32px,9vw,48px)}body .product-grid{gap:14px}body .hero-actions{flex-wrap:wrap}body .hero-metrics{gap:16px}body .website-custom-nav{gap:16px}body .product-media{padding:8px}}
@media(max-width:1000px){body .product-grid{grid-template-columns:repeat(<?= min(3, (int)$websiteDesign['columns']) ?>,minmax(0,1fr))}}
@media(max-width:700px){body .product-grid{grid-template-columns:repeat(2,minmax(0,1fr))}body .hero-inner{grid-template-columns:1fr}body .hero-card{display:none}}
@media(max-width:360px){body .product-grid{grid-template-columns:1fr}}
</style>
