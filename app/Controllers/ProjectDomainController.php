<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Auth, Csrf, Http, View};
use App\Services\{ProjectDomainService, ProjectService, LogService};
use Flight;

final class ProjectDomainController
{
    public function __construct(private ProjectDomainService $domains, private ProjectService $projects, private LogService $logs, private array $config) {}

    private function authorized(): bool
    {
        if (!Auth::check()) { Flight::redirect('/'); return false; }
        if (!Auth::isAdmin()) { http_response_code(403); echo 'Solo un administrador puede gestionar dominios.'; return false; }
        return true;
    }

    public function register(): void
    {
        Flight::route('GET /projects/@uid/domains', function (string $uid): void {
            if (!$this->authorized()) return;
            try {
                View::render('project-domains', [
                    'title' => 'Dominios del proyecto', 'project' => $this->projects->find($uid),
                    'domains' => $this->domains->all($uid), 'routing' => $this->domains->routingStatus(),
                    'ips' => $this->config['domains']['ips'] ?? [], 'flashes' => Http::flashes(),
                ]);
            } catch (\Throwable $e) { Http::flash('error', $e->getMessage()); Flight::redirect('/dashboard'); }
        });
        Flight::route('POST /projects/@uid/domains', function (string $uid): void {
            if (!$this->authorized()) return;
            try {
                Csrf::verify($_POST['_csrf'] ?? null);
                $this->projects->findActive($uid);
                $host = (string) ($_POST['store_domain'] ?? '');
                $system = !empty($_POST['enable_system']) ? trim((string) ($_POST['system_domain'] ?? '')) : null;
                if ($system === '') $system = 'sistema.' . ProjectDomainService::hostname($host);
                $this->domains->save($uid, $host, $system);
                $this->logs->write('project.domains_saved', $uid, null, null, null, ['store' => $host, 'system' => $system]);
                Http::flash('success', 'Dominios guardados. Añade los registros DNS y verifica cada dirección para activarla.');
            } catch (\Throwable $e) { Http::flash('error', $e->getMessage()); }
            Flight::redirect('/projects/' . rawurlencode($uid) . '/domains');
        });
        Flight::route('POST /projects/@uid/domains/@id/@operation', function (string $uid, string $id, string $operation): void {
            if (!$this->authorized()) return;
            try {
                Csrf::verify($_POST['_csrf'] ?? null);
                $this->projects->find($uid);
                if (in_array($operation, ['verify', 'https'], true)) $this->projects->findActive($uid);
                $message = match ($operation) {
                    'verify' => 'DNS verificado. La dirección se activará en unos segundos y se solicitará su certificado HTTPS.',
                    'https' => 'Certificado HTTPS válido.',
                    'disable' => 'Dominio desactivado.',
                    'remove' => 'Dominio desvinculado. Puedes asignarlo nuevamente con una nueva verificación.',
                    default => throw new \InvalidArgumentException('Operación no válida.'),
                };
                match ($operation) {
                    'verify' => $this->domains->verify($uid, (int) $id),
                    'https' => $this->domains->checkHttps($uid, (int) $id),
                    'disable' => $this->domains->disable($uid, (int) $id),
                    'remove' => $this->domains->remove($uid, (int) $id),
                };
                $this->logs->write('project.domain_' . $operation, $uid, 'project_domains', $id);
                Http::flash('success', $message);
            } catch (\Throwable $e) { Http::flash('error', $e->getMessage()); }
            Flight::redirect('/projects/' . rawurlencode($uid) . '/domains');
        });
    }
}
