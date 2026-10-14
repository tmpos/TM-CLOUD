<?php
declare(strict_types=1);
namespace App\Services;

final class StorefrontSeoService
{
    public const PAGES = ['home' => 'Inicio', 'categories' => 'Catálogo', 'nosotros' => 'Nosotros', 'contacto' => 'Contacto', 'preguntas-frecuentes' => 'Preguntas frecuentes', 'politicas' => 'Políticas', 'privacidad' => 'Privacidad', 'terminos' => 'Términos', 'envios' => 'Envíos', 'devoluciones' => 'Devoluciones', 'garantias' => 'Garantías'];

    public static function defaults(): array
    {
        $fields = ['seo_title' => '', 'seo_description' => '', 'seo_image' => '', 'seo_image_alt' => '', 'seo_index' => '1', 'seo_follow' => '1', 'seo_sitemap' => '1', 'seo_schema' => '1', 'seo_google' => '', 'seo_bing' => '', 'seo_product_title' => '{producto} | {tienda}', 'seo_product_description' => '{descripcion}'];
        foreach (self::PAGES as $key => $_) {
            $fields['seo_' . $key . '_title'] = '';
            $fields['seo_' . $key . '_description'] = '';
            $fields['seo_' . $key . '_index'] = 'inherit';
        }
        return $fields;
    }

    public static function normalize(array $input, array $current = []): array
    {
        $result = [];
        foreach (self::defaults() as $key => $default) {
            $value = $input[$key] ?? $current[$key] ?? $default;
            if (!is_string($value)) throw new \InvalidArgumentException('Los campos SEO deben ser texto.');
            $value = trim($value);
            $max = str_ends_with($key, '_description') ? 500 : ($key === 'seo_image' ? 2000 : 200);
            if (mb_strlen($value) > $max) throw new \InvalidArgumentException('Campo SEO demasiado largo: ' . $key);
            if (in_array($key, ['seo_index', 'seo_follow', 'seo_sitemap', 'seo_schema'], true) && !in_array($value, ['0', '1'], true)) throw new \InvalidArgumentException('Opción SEO inválida.');
            if (str_ends_with($key, '_index') && $key !== 'seo_index' && !in_array($value, ['0', '1', 'inherit'], true)) throw new \InvalidArgumentException('Indexación SEO inválida.');
            if (in_array($key, ['seo_google', 'seo_bing'], true) && $value !== '' && !preg_match('/^[A-Za-z0-9_-]+$/D', $value)) throw new \InvalidArgumentException('Pega solo el código de verificación SEO.');
            if ($key === 'seo_image' && $value !== '' && !self::imageUrl($value)) throw new \InvalidArgumentException('La imagen SEO debe ser una URL HTTPS válida.');
            $result[$key] = $value;
        }
        return $result;
    }

    private static function imageUrl(string $value): bool
    {
        $url = parse_url($value);
        return filter_var($value, FILTER_VALIDATE_URL) && ($url['scheme'] ?? '') === 'https' && !isset($url['user']) && !isset($url['pass']);
    }

    public static function indexable(array $store, string $page, bool $filtered = false): bool
    {
        if ($filtered || (string) ($store['seo_index'] ?? '1') === '0' || (string) ($store['seo_' . $page . '_index'] ?? '') === '0') return false;
        $policies = ['privacidad' => 'page_privacy', 'terminos' => 'page_terms', 'envios' => 'page_shipping', 'devoluciones' => 'page_returns', 'garantias' => 'page_warranty'];
        return !isset($policies[$page]) || trim((string) ($store[$policies[$page]] ?? '')) !== '';
    }

    public static function url(array $store, string $page): string
    {
        return rtrim($store['url'], '/') . ($page === 'home' ? '' : ($page === 'categories' ? '/categories' : '/pages/' . rawurlencode($page)));
    }

    public static function metadata(array $store, string $page, array $product = [], bool $filtered = false): array
    {
        $store += self::defaults();
        $name = (string) $store['store_name'];
        $title = $store['seo_' . $page . '_title'] ?? '';
        $description = $store['seo_' . $page . '_description'] ?? '';
        $url = self::url($store, $page);
        $image = $store['seo_image'] ?: ($store['logo_url'] ?? '');
        if ($page === 'product') {
            $tokens = ['{producto}' => $product['name'], '{tienda}' => $name, '{categoria}' => $product['category'] ?? '', '{descripcion}' => $product['description'] ?: $product['name']];
            $title = strtr($store['seo_product_title'] ?: '{producto} | {tienda}', $tokens);
            $description = strtr($store['seo_product_description'] ?: '{descripcion}', $tokens);
            $title = ($product['seo_title'] ?? '') ?: $title;
            $description = ($product['seo_description'] ?? '') ?: $description;
            $url = rtrim($store['url'], '/') . '/products/' . rawurlencode($product['uid']);
            $image = $product['image'] ?: $image;
        }
        $title = $title ?: ($page === 'home' ? ($store['seo_title'] ?: $name) : ((self::PAGES[$page] ?? $name) . ' | ' . $name));
        $description = $description ?: ($store['seo_description'] ?: ($store['hero_text'] ?? '') ?: ($store['tagline'] ?? ''));
        return ['title' => mb_substr(strip_tags($title), 0, 200), 'description' => mb_substr(strip_tags($description), 0, 500), 'url' => $url, 'image' => self::imageUrl((string) $image) ? $image : '', 'index' => self::indexable($store, $page, $filtered), 'follow' => $store['seo_follow'] !== '0'];
    }

    public static function head(array $store, string $page, array $product = [], bool $filtered = false): string
    {
        $store += self::defaults();
        $m = self::metadata($store, $page, $product, $filtered);
        $e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<title>' . $e($m['title']) . '</title><link rel="canonical" href="' . $e($m['url']) . '">';
        $tags = ['description' => $m['description'], 'robots' => ($m['index'] ? 'index' : 'noindex') . ',' . ($m['follow'] ? 'follow' : 'nofollow'), 'twitter:card' => $m['image'] ? 'summary_large_image' : 'summary', 'twitter:title' => $m['title'], 'twitter:description' => $m['description']];
        if ($m['image']) $tags['twitter:image'] = $m['image'];
        if ($store['seo_google']) $tags['google-site-verification'] = $store['seo_google'];
        if ($store['seo_bing']) $tags['msvalidate.01'] = $store['seo_bing'];
        foreach ($tags as $key => $value) $html .= '<meta name="' . $key . '" content="' . $e($value) . '">';
        $og = ['title' => $m['title'], 'description' => $m['description'], 'url' => $m['url'], 'type' => 'website', 'site_name' => $store['store_name']];
        if ($m['image']) { $og['image'] = $m['image']; $og['image:alt'] = $page === 'product' ? $product['name'] : ($store['seo_image_alt'] ?: $store['store_name']); }
        foreach ($og as $key => $value) $html .= '<meta property="og:' . $key . '" content="' . $e($value) . '">';
        if ($store['seo_schema'] === '1' && $m['index']) {
            $schema = ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => $store['store_name'], 'url' => rtrim($store['url'], '/')];
            if (!empty($store['phone'])) $schema['telephone'] = $store['phone'];
            if (!empty($store['address'])) $schema['address'] = $store['address'];
            if (!empty($store['logo_url']) && self::imageUrl($store['logo_url'])) $schema['logo'] = $store['logo_url'];
            if ($page === 'product') {
                $schema = ['@context' => 'https://schema.org', '@type' => 'Product', 'name' => $product['name'], 'description' => $m['description'], 'url' => $m['url']];
                if ($m['image']) $schema['image'] = $m['image'];
                if (!empty($store['show_prices']) && isset($product['price']) && is_numeric($product['price'])) {
                    $schema['offers'] = ['@type' => 'Offer', 'url' => $m['url'], 'priceCurrency' => $store['currency'], 'price' => $product['price']];
                }
            }
            $html .= '<script type="application/ld+json">' . json_encode($schema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . '</script>';
        }
        return $html;
    }

    public static function sitemap(array $store, array $products): string
    {
        $urls = [];
        if (($store['seo_sitemap'] ?? '1') === '1' && ($store['seo_index'] ?? '1') === '1') {
            foreach (self::PAGES as $page => $_) if (self::indexable($store, $page)) $urls[] = self::url($store, $page);
            foreach ($products as $product) $urls[] = rtrim($store['url'], '/') . '/products/' . rawurlencode($product['uid']);
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach (array_unique($urls) as $url) $xml .= '<url><loc>' . htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc></url>';
        return $xml . '</urlset>';
    }
}
