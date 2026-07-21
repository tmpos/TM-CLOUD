<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\PortalAuth;
use App\Services\ApiKeyService;
use App\Services\PdfService;
use App\Services\ProjectService;
use App\Services\RecordService;
use App\Services\SchemaService;
use App\Services\SharedDocumentService;
use Flight;

final class PortalController
{
    private const RESOURCES = ['facturas', 'clientes', 'productos', 'categorias'];

    public function __construct(
        private array $config,
        private PortalAuth $auth,
        private ProjectService $projects,
        private SchemaService $schema,
        private RecordService $records,
        private PdfService $pdf,
        private SharedDocumentService $shares,
        private ApiKeyService $keys,
    ) {}

    public function register(): void
    {
        Flight::route('GET /portal', fn () => Flight::redirect(PortalAuth::check() ? '/portal/dashboard' : '/portal/login'));
        Flight::route('GET /portal/login', fn () => $this->render('portal-login', ['title' => 'Acceso empresarial'], false));
        Flight::route('POST /portal/login', function (): void {
            try {
                Csrf::verify($_POST['_csrf'] ?? null);
                $email = strtolower(trim((string) ($_POST['email'] ?? '')));
                $this->keys->rateLimitPublic('portal-login:' . hash('sha256', $email), 10);
                if (!$this->auth->attempt($email, (string) ($_POST['password'] ?? ''))) throw new \RuntimeException('Correo o contraseña incorrectos.');
                Flight::redirect('/portal/dashboard');
            } catch (\Throwable $e) {
                $this->render('portal-login', ['title' => 'Acceso empresarial', 'error' => $e->getMessage()], false);
            }
        });
        Flight::route('POST /portal/logout', function (): void {
            Csrf::verify($_POST['_csrf'] ?? null);
            PortalAuth::logout();
            Flight::redirect('/portal/login');
        });
        Flight::route('GET /portal/dashboard', fn () => $this->protected(function (): void {
            $projects = $this->auth->projects(PortalAuth::user()['uid']);
            $stats = [];
            foreach ($projects as $project) {
                try {
                    $db = $this->schema->connection($project);
                    $stats[$project['uid']] = [
                        'invoices' => (int) $db->query('SELECT COUNT(*) FROM facturas')->fetchColumn(),
                        'sales' => (float) $db->query("SELECT COALESCE(SUM(total),0) FROM facturas WHERE LOWER(COALESCE(estado,'')) NOT IN ('anulada','cancelada')")->fetchColumn(),
                    ];
                } catch (\Throwable) { $stats[$project['uid']] = ['invoices' => 0, 'sales' => 0]; }
            }
            $this->render('portal-dashboard', ['title' => 'Mis sistemas', 'projects' => $projects, 'stats' => $stats]);
        }));
        Flight::route('GET /portal/@project/invoices/@uid/pdf', fn ($project, $uid) => $this->protected(function () use ($project, $uid): void {
            $p = $this->authorizedProject((string) $project);
            $invoice = $this->records->find($p, 'facturas', (string) $uid);
            $content = $this->pdf->invoice($p, 'facturas', $invoice);
            header('Content-Type: ' . (str_starts_with($content, '%PDF') ? 'application/pdf' : 'text/html; charset=UTF-8'));
            echo $content;
        }));
        Flight::route('GET /portal/@project/invoices/@uid', fn ($project, $uid) => $this->protected(function () use ($project, $uid): void {
            $p = $this->authorizedProject((string) $project);
            $invoice = $this->records->find($p, 'facturas', (string) $uid);
            echo $this->pdf->invoiceHtml($p, $invoice);
        }));
        Flight::route('POST /portal/@project/invoices/@uid/share', fn ($project, $uid) => $this->protected(function () use ($project, $uid): void {
            Csrf::verify($_POST['_csrf'] ?? null);
            $p = $this->authorizedProject((string) $project);
            $this->records->find($p, 'facturas', (string) $uid);
            $share = $this->shares->create($p['uid'], 'invoice', 'facturas', (string) $uid, gmdate('Y-m-d H:i:s', time() + 2592000));
            $this->render('portal-share', ['title' => 'Enlace de factura', 'project' => $p, 'share' => $share]);
        }));
        Flight::route('GET /portal/@project/@resource', fn ($project, $resource) => $this->protected(function () use ($project, $resource): void {
            $resource = (string) $resource;
            if (!in_array($resource, self::RESOURCES, true)) throw new \RuntimeException('Recurso no disponible.');
            $p = $this->authorizedProject((string) $project);
            $columns = $this->schema->columns($p, $resource);
            $result = $this->records->paginate($p, $resource, $_GET);
            $this->render('portal-table', ['title' => ucfirst($resource), 'project' => $p, 'resource' => $resource, 'columns' => $columns, 'rows' => $result['data'], 'meta' => $result['meta']]);
        }));
    }

    private function protected(callable $callback): void
    {
        if (!PortalAuth::check()) { Flight::redirect('/portal/login'); return; }
        try { $callback(); }
        catch (\Throwable $e) { http_response_code(in_array($e->getCode(), [403,404], true) ? $e->getCode() : 400); $this->render('portal-error', ['title' => 'No disponible', 'error' => $e->getMessage()]); }
    }

    private function authorizedProject(string $uid): array
    {
        $this->auth->membership(PortalAuth::user()['uid'], $uid);
        return $this->projects->findActive($uid);
    }

    private function render(string $template, array $data, bool $withLayout = true): void
    {
        $views = dirname(__DIR__) . '/Views';
        $file = $views . '/' . $template . '.php';
        extract($data, EXTR_SKIP);
        ob_start(); require $file; $content = (string) ob_get_clean();
        if (!$withLayout) { echo $content; return; }
        require $views . '/portal-layout.php';
    }
}
