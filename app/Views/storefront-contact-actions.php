<?php
$contactUrl = '/store/' . rawurlencode((string) $store['slug']) . '/pages/contacto';
$contactWhatsapp = preg_replace('/\D+/', '', (string) ($store['whatsapp'] ?? ''));
?>
<style>
.store-contact-actions{position:fixed;right:22px;bottom:22px;z-index:35;display:flex;align-items:center;gap:10px;font:600 14px/1.2 system-ui,sans-serif}
.store-contact-actions a{display:inline-flex;align-items:center;justify-content:center;gap:9px;margin:0;text-decoration:none;box-sizing:border-box;box-shadow:0 5px 22px #0002;transition:transform .15s}
.store-contact-actions a:hover{transform:translateY(-2px)}
.store-contact-actions a:focus-visible{outline:3px solid #2563eb;outline-offset:4px}
.store-contact-link{height:48px;padding:0 18px;border:1px solid #e2e8f0;border-radius:24px;background:#fff;color:#14213d}
.store-contact-whatsapp{width:56px;height:56px;border-radius:50%;background:#25d366;color:#fff}
.store-contact-whatsapp svg{width:29px;height:29px}
@media(max-width:760px){.store-contact-actions{right:14px;bottom:calc(18px + env(safe-area-inset-bottom))}body:has(.mobile-dock) .store-contact-actions{bottom:calc(82px + env(safe-area-inset-bottom))}}
@media print{.store-contact-actions{display:none}}
</style>
<nav class="store-contact-actions" aria-label="Contactar con la tienda">
  <a class="store-contact-link" href="<?= e($contactUrl) ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H4l-3 2 2-6a8.5 8.5 0 1 1 18-4.5Z"/></svg>Contacto</a>
  <?php if ($contactWhatsapp !== ''): ?><a class="store-contact-whatsapp" href="https://wa.me/<?= e($contactWhatsapp) ?>" target="_blank" rel="noopener noreferrer" aria-label="Hablar por WhatsApp" title="Hablar por WhatsApp"><?php require __DIR__ . '/storefront-whatsapp-icon.php'; ?></a><?php endif; ?>
</nav>
