<?php
$analyticsConfig = $store['web_editor']['analytics'] ?? [];
if (empty($analyticsConfig['enabled']) || !empty($store['_preview'])) return;
$analyticsPayload = ['id' => $analyticsConfig['measurement_id'], 'store' => $store['uid'], 'location' => $store['url'] . ($websiteAnalyticsPath ?? ''), 'title' => $store['store_name'], 'purchase' => null];
if (($websiteAnalyticsPath ?? '') === '/order-confirmation' && isset($order) && ($order['payment_status'] ?? '') === 'paid') {
    $analyticsPayload['purchase'] = ['transaction_id' => $order['uid'], 'currency' => $order['currency'], 'value' => max(0, (float) $order['total'] - (float) $order['shipping_total']), 'shipping' => (float) $order['shipping_total'],
        'items' => array_map(static fn ($item) => ['item_id' => $item['product_uid'], 'item_name' => $item['product_name'], 'quantity' => (int) $item['quantity'], 'price' => (float) ($item['unit_price'] ?? 0)], $order['items'])];
}
?>
<aside id="website-consent" hidden role="region" aria-label="Preferencias de analítica"><p><?= e($analyticsConfig['consent_text']) ?></p><div><button type="button" data-analytics-accept>Aceptar</button><button type="button" data-analytics-reject>Rechazar</button><a href="/store/<?= e($store['slug']) ?>/pages/privacidad">Privacidad</a></div></aside>
<button type="button" id="website-consent-open">Preferencias de analítica</button>
<style>#website-consent{position:fixed;bottom:100px;left:20px;width:min(430px,calc(100% - 40px));z-index:90;padding:20px;border:1px solid #dbe1e8;border-radius:14px;background:#fff;color:#17212f;box-shadow:0 10px 40px #0002;font:14px/1.5 system-ui}#website-consent[hidden]{display:none}#website-consent p{margin:0 0 12px}#website-consent div{display:flex;gap:10px;align-items:center;flex-wrap:wrap}#website-consent button{padding:10px 14px;cursor:pointer;background:#f1f5f9;color:#17212f;border:1px solid #cbd5e1;border-radius:8px}#website-consent-open{position:fixed;bottom:5px;left:8px;z-index:40;border:0;border-radius:5px;padding:5px;background:#fff;color:#334155;font:11px system-ui;cursor:pointer}</style>
<script>
(() => {
  const config = <?= json_encode($analyticsPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>;
  const panel = document.getElementById('website-consent');
  const key = 'website-analytics-' + config.store;
  let active = false;
  const read = () => { try { return localStorage.getItem(key); } catch { return null; } };
  const remember = value => { try { localStorage.setItem(key, value); } catch {} };
  function enable() {
    if (active) return;
    active = true;
    window['ga-disable-' + config.id] = false;
    window.dataLayer = window.dataLayer || [];
    const tag = window.gtag = window.gtag || function(){ window.dataLayer.push(arguments); };
    tag('js', new Date());
    tag('config', config.id, { send_page_view: false, page_location: config.location, page_referrer: '', allow_google_signals: false, allow_ad_personalization_signals: false, cookie_prefix: config.store, cookie_path: new URL(config.location).pathname.split('/').slice(0,3).join('/') });
    tag('event', 'page_view', { page_location: config.location, page_referrer: '', page_title: config.title, send_to: config.id });
    if (config.purchase) {
      const transactionKey = key + '-' + config.purchase.transaction_id;
      let sent = false;
      try { sent = localStorage.getItem(transactionKey) === 'sent'; } catch {}
      if (!sent) { tag('event', 'purchase', { ...config.purchase, send_to: config.id }); try { localStorage.setItem(transactionKey, 'sent'); } catch {} }
    }
    const script = document.createElement('script'); script.async = true; script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(config.id); document.head.appendChild(script);
  }
  panel.querySelector('[data-analytics-accept]').addEventListener('click', () => { remember('accepted'); panel.hidden = true; enable(); });
  panel.querySelector('[data-analytics-reject]').addEventListener('click', () => { remember('rejected'); panel.hidden = true; window['ga-disable-' + config.id] = true; if (active) location.reload(); });
  document.getElementById('website-consent-open').addEventListener('click', () => { panel.hidden = false; });
  if (read() === 'accepted') enable(); else if (read() !== 'rejected') panel.hidden = false;
})();
</script>
