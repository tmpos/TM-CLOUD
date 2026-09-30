<?php
declare(strict_types=1);
namespace App\Core;

use App\Services\ProjectDomainService;

final class ProjectDomainRequest
{
    public static function apply(ProjectDomainService $domains, array $config): void
    {
        $rawHost = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($rawHost === '') return; // CLI workers.
        $host = strtolower((string) parse_url('http://' . $rawHost, PHP_URL_HOST));
        $canonical = strtolower((string) parse_url($config['url'], PHP_URL_HOST));
        if ($host === $canonical || $host === 'localhost' || filter_var(trim($host, '[]'), FILTER_VALIDATE_IP)
            || in_array($host, $config['domains']['reserved'] ?? [], true)) return;
        $domain = $domains->resolve($host);
        $path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
        if (!$domain || !ProjectDomainService::allowedPath($domain, $path)) {
            http_response_code(404);
            header('Cache-Control: no-store');
            echo 'Dominio o página no disponible para este proyecto.';
            exit;
        }
        $_SERVER['TMPBASE_PROJECT_DOMAIN'] = $domain['hostname'];
        $_SERVER['TMPBASE_PROJECT_DOMAIN_UID'] = $domain['project_uid'];
        if ($path !== '/') return;
        $query = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_QUERY);
        $target = ($domain['destination'] === 'store' ? '/store/' . $domain['store_slug'] : '/sistema/' . $domain['project_slug']);
        if ($query !== '') $target .= '?' . $query;
        if ($domain['destination'] === 'system') {
            header('Location: ' . $target, true, 302);
            exit;
        }
        // Internal storefront alias: the domain root remains visible in the browser.
        $_SERVER['REQUEST_URI'] = $target;
        if (method_exists(\Flight::class, 'app')) \Flight::request()->url = $target;
    }
}
