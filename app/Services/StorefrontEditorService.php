<?php
declare(strict_types=1);
namespace App\Services;

final class StorefrontEditorService
{
    public const SECTIONS = ['hero', 'categories', 'trust', 'featured', 'campaigns', 'promo', 'brands', 'catalog', 'new', 'commerce', 'support'];

    public static function defaults(): array
    {
        return ['version' => 1, 'design' => StorefrontDesignService::defaults(), 'favicon' => '', 'menu' => [
            ['id' => 'home', 'label' => 'Inicio', 'path' => '', 'visible' => true],
            ['id' => 'catalog', 'label' => 'Productos', 'path' => '/categories', 'visible' => true],
            ['id' => 'about', 'label' => 'Nosotros', 'path' => '/pages/nosotros', 'visible' => true],
            ['id' => 'contact', 'label' => 'Contacto', 'path' => '/pages/contacto', 'visible' => true],
        ], 'sections' => array_map(static fn ($id) => ['id' => $id, 'visible' => true], self::SECTIONS),
            'featured_mode' => 'auto', 'featured' => [], 'timezone' => 'America/Santo_Domingo', 'campaigns' => [],
            'analytics' => ['enabled' => false, 'measurement_id' => '', 'consent_text' => '¿Permites medir las visitas y compras para mejorar nuestra tienda?'], 'products' => []];
    }

    private static function text(mixed $value, int $max): string
    {
        if (!is_string($value) || mb_strlen($value) > $max) throw new \InvalidArgumentException('Texto del editor inválido o demasiado largo.');
        return trim($value);
    }
    private static function flag(mixed $value): bool
    {
        if (!is_bool($value)) throw new \InvalidArgumentException('Opción del editor inválida.');
        return $value;
    }
    private static function rows(mixed $value, int $max): array
    {
        if (!is_array($value) || !array_is_list($value) || count($value) > $max) throw new \InvalidArgumentException('La lista del editor supera su límite o tiene un formato inválido.');
        return $value;
    }
    private static function id(mixed $value): string
    {
        $id = self::text($value, 100);
        if (!preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $id)) throw new \InvalidArgumentException('Identificador del editor inválido.');
        return $id;
    }
    public static function image(mixed $value, bool $file = true): string
    {
        $value = self::text($value, 2000);
        if ($value === '' || ($file && preg_match('/^fil_[A-Za-z0-9]+$/D', $value))) return $value;
        $url = parse_url($value);
        if (!filter_var($value, FILTER_VALIDATE_URL) || ($url['scheme'] ?? '') !== 'https' || isset($url['user']) || isset($url['pass'])) throw new \InvalidArgumentException('Usa una imagen subida o una URL HTTPS válida.');
        return $value;
    }
    private static function date(mixed $value, \DateTimeZone $zone): string
    {
        $value = self::text($value, 16);
        if ($value === '') return '';
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, $zone);
        if (!$date || $date->format('Y-m-d\TH:i') !== $value) throw new \InvalidArgumentException('Fecha de campaña inválida.');
        return $value;
    }
    public static function normalize(mixed $input): array
    {
        if (!is_array($input) || ($input['version'] ?? 1) !== 1) throw new \InvalidArgumentException('Versión del editor no compatible.');
        $input = array_replace(self::defaults(), $input);
        $result = self::defaults();
        $result['design'] = StorefrontDesignService::normalize($input['design']);
        $result['favicon'] = self::image($input['favicon']);
        $result['timezone'] = self::text($input['timezone'], 100);
        if (!in_array($result['timezone'], \DateTimeZone::listIdentifiers(), true)) throw new \InvalidArgumentException('Zona horaria inválida.');
        $zone = new \DateTimeZone($result['timezone']);
        $result['menu'] = []; $seen = [];
        foreach (self::rows($input['menu'], 12) as $item) {
            if (!is_array($item)) throw new \InvalidArgumentException('Enlace inválido.');
            $id = self::id($item['id'] ?? '');
            $label = self::text($item['label'] ?? '', 60);
            $path = self::text($item['path'] ?? '', 120);
            if ($label === '' || isset($seen[$id]) || ($path !== '' && !preg_match('~^/(categories|invoices|cart|pages/(nosotros|contacto|preguntas-frecuentes|politicas|privacidad|terminos|envios|devoluciones|garantias))$~D', $path))) throw new \InvalidArgumentException('Nombre, destino o identificador del menú inválido.');
            $seen[$id] = true;
            $result['menu'][] = ['id' => $id, 'label' => $label, 'path' => $path, 'visible' => self::flag($item['visible'] ?? true)];
        }
        $result['sections'] = []; $seen = [];
        foreach (self::rows($input['sections'], count(self::SECTIONS)) as $item) {
            $id = $item['id'] ?? '';
            if (!in_array($id, self::SECTIONS, true) || isset($seen[$id])) throw new \InvalidArgumentException('Sección inválida o repetida.');
            $seen[$id] = true;
            $result['sections'][] = ['id' => $id, 'visible' => self::flag($item['visible'] ?? true)];
        }
        foreach (self::SECTIONS as $id) if (!isset($seen[$id])) $result['sections'][] = ['id' => $id, 'visible' => true];
        if (!in_array($input['featured_mode'], ['auto', 'manual'], true)) throw new \InvalidArgumentException('Modo de destacados inválido.');
        $result['featured_mode'] = $input['featured_mode'];
        $result['featured'] = array_values(array_unique(array_map([self::class, 'id'], self::rows($input['featured'], 24))));
        $result['campaigns'] = []; $seen = [];
        foreach (self::rows($input['campaigns'], 12) as $item) {
            $id = self::id($item['id'] ?? '');
            if (isset($seen[$id])) throw new \InvalidArgumentException('Campaña repetida.');
            $seen[$id] = true;
            $title = self::text($item['title'] ?? '', 120);
            if ($title === '') throw new \InvalidArgumentException('Cada campaña necesita un título.');
            $starts = self::date($item['starts'] ?? '', $zone); $ends = self::date($item['ends'] ?? '', $zone);
            if ($starts !== '' && $ends !== '' && $starts >= $ends) throw new \InvalidArgumentException('La campaña debe terminar después de comenzar.');
            $result['campaigns'][] = ['id' => $id, 'title' => $title, 'text' => self::text($item['text'] ?? '', 500), 'image' => self::image($item['image'] ?? ''), 'link' => self::image($item['link'] ?? '', false), 'starts' => $starts, 'ends' => $ends, 'enabled' => self::flag($item['enabled'] ?? false)];
        }
        $analytics = $input['analytics'];
        if (!is_array($analytics)) throw new \InvalidArgumentException('Analítica inválida.');
        $enabled = self::flag($analytics['enabled'] ?? false);
        $measurement = self::text($analytics['measurement_id'] ?? '', 22);
        if (($enabled || $measurement !== '') && !preg_match('/^G-[A-Z0-9]{4,20}$/D', $measurement)) throw new \InvalidArgumentException('Identificador de Google Analytics 4 inválido.');
        $consent = self::text($analytics['consent_text'] ?? '', 300);
        if ($consent === '') throw new \InvalidArgumentException('Completa el mensaje de consentimiento.');
        $result['analytics'] = ['enabled' => $enabled, 'measurement_id' => $measurement, 'consent_text' => $consent];
        $result['products'] = []; $seen = [];
        foreach (self::rows($input['products'], 1000) as $item) {
            $uid = self::id($item['uid'] ?? '');
            if (isset($seen[$uid])) throw new \InvalidArgumentException('Producto repetido.');
            $seen[$uid] = true;
            $result['products'][] = ['uid' => $uid, 'title' => self::text($item['title'] ?? '', 200), 'description' => self::text($item['description'] ?? '', 16000), 'images' => array_map([self::class, 'image'], self::rows($item['images'] ?? [], 8)), 'seo_title' => self::text($item['seo_title'] ?? '', 200), 'seo_description' => self::text($item['seo_description'] ?? '', 500)];
        }
        if (strlen(json_encode($result, JSON_THROW_ON_ERROR)) > 2000000) throw new \InvalidArgumentException('El contenido web supera 2 MB. Reduce textos o cantidad de productos personalizados.');
        return $result;
    }

    public static function applyProduct(array $product, array $store, callable $image): array
    {
        foreach (($store['web_editor']['products'] ?? []) as $content) {
            if ($content['uid'] !== $product['uid']) continue;
            if ($content['title'] !== '') $product['name'] = $content['title'];
            if ($content['description'] !== '') $product['description'] = $content['description'];
            if ($content['images'] !== []) {
                $product['images'] = array_values(array_filter(array_map($image, $content['images'])));
                $product['image'] = $product['images'][0] ?? '';
            }
            $product['seo_title'] = $content['seo_title']; $product['seo_description'] = $content['seo_description'];
            break;
        }
        return $product;
    }
    public static function featured(array $store, array $products): array
    {
        $config = $store['web_editor'] ?? self::defaults();
        if ($config['featured_mode'] === 'auto') return array_slice(array_values(array_filter($products, static fn ($p) => $p['available'] ?? false)), 0, 8);
        $indexed = array_column($products, null, 'uid');
        return array_values(array_filter(array_map(static fn ($uid) => $indexed[$uid] ?? null, $config['featured'])));
    }
    public static function activeCampaigns(array $store, ?\DateTimeImmutable $now = null): array
    {
        $config = $store['web_editor'] ?? self::defaults();
        $now = ($now ?? new \DateTimeImmutable())->setTimezone(new \DateTimeZone($config['timezone']))->format('Y-m-d\TH:i');
        return array_values(array_filter($config['campaigns'], static fn ($c) => $c['enabled'] && ($c['starts'] === '' || $c['starts'] <= $now) && ($c['ends'] === '' || $now < $c['ends'])));
    }
    public static function navigation(array $store): string
    {
        $html = '<nav class="website-custom-nav" aria-label="Menú de la tienda">';
        foreach (($store['web_editor']['menu'] ?? self::defaults()['menu']) as $item) {
            if (!$item['visible'] || ($item['path'] === '/cart' && empty($store['show_prices']))) continue;
            $html .= '<a href="' . self::escape('/store/' . rawurlencode($store['slug']) . $item['path']) . '">' . self::escape($item['label']) . '</a>';
        }
        return $html . '</nav><style>.website-custom-nav{display:flex;justify-content:center;gap:22px;flex-wrap:wrap;padding:16px 20px;background:var(--bg,#fff);border-bottom:1px solid #8882;font:600 14px system-ui}.website-custom-nav a{color:inherit;text-decoration:none}.website-custom-nav a:hover{text-decoration:underline}@media(max-width:600px){.website-custom-nav{gap:14px;font-size:13px}}</style>';
    }
    public static function escape(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
