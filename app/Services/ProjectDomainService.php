<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use App\Core\Support;

final class ProjectDomainService
{
    public function __construct(private PDO $db, private array $config, private ?\Closure $dns = null) {}

    public static function migrate(PDO $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS project_domains (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            project_uid TEXT NOT NULL REFERENCES projects(uid) ON DELETE CASCADE,
            hostname TEXT NOT NULL UNIQUE COLLATE NOCASE,
            destination TEXT NOT NULL CHECK(destination IN ('store','system')),
            verification_token TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','active','disabled')),
            verified_at TEXT, tls_checked_at TEXT, tls_ok INTEGER NOT NULL DEFAULT 0,
            last_error TEXT, created_at TEXT NOT NULL,
            UNIQUE(project_uid,destination)
        )");
    }

    public static function hostname(string $value): string
    {
        $host = strtolower(rtrim(trim($value), '.'));
        if (strlen($host) > 253 || filter_var($host, FILTER_VALIDATE_IP)
            || !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D', $host)) {
            throw new \InvalidArgumentException('Escribe solo el dominio, sin https://, rutas, puertos ni comodines.');
        }
        return $host;
    }

    public function all(string $project): array
    {
        $q = $this->db->prepare('SELECT * FROM project_domains WHERE project_uid=? ORDER BY destination');
        $q->execute([$project]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function save(string $project, string $store, ?string $system): void
    {
        $hosts = ['store' => self::hostname($store)];
        if ($system !== null && trim($system) !== '') $hosts['system'] = self::hostname($system);
        if (count(array_unique($hosts)) !== count($hosts)) throw new \InvalidArgumentException('La tienda y el sistema necesitan dominios diferentes.');
        $reserved = array_filter(array_merge([(string) parse_url($this->config['url'], PHP_URL_HOST)], $this->config['domains']['reserved'] ?? []));
        foreach ($hosts as $host) {
            if (in_array($host, $reserved, true)) throw new \InvalidArgumentException('Este dominio está reservado para la plataforma.');
        }
        $this->db->beginTransaction();
        try {
            foreach ($hosts as $destination => $host) {
                $q = $this->db->prepare('SELECT * FROM project_domains WHERE hostname=?');
                $q->execute([$host]);
                $existing = $q->fetch(PDO::FETCH_ASSOC);
                if ($existing && ($existing['project_uid'] !== $project || $existing['destination'] !== $destination)) {
                    throw new \InvalidArgumentException('El dominio ya está asignado. Desvincúlalo del otro proyecto primero.');
                }
                $this->db->prepare("INSERT INTO project_domains(project_uid,hostname,destination,verification_token,created_at)
                    VALUES(?,?,?,?,?) ON CONFLICT(project_uid,destination) DO UPDATE SET
                    hostname=excluded.hostname,verification_token=excluded.verification_token,status='pending',
                    verified_at=NULL,tls_checked_at=NULL,tls_ok=0,last_error=NULL
                    WHERE project_domains.hostname<>excluded.hostname")
                    ->execute([$project, $host, $destination, bin2hex(random_bytes(24)), Support::now()]);
            }
            $this->db->commit();
        } catch (\Throwable $e) { $this->db->rollBack(); throw $e; }
    }

    private function row(string $project, int $id): array
    {
        $q = $this->db->prepare('SELECT * FROM project_domains WHERE project_uid=? AND id=?');
        $q->execute([$project, $id]);
        return $q->fetch(PDO::FETCH_ASSOC) ?: throw new \RuntimeException('Dominio no encontrado.');
    }

    private function records(string $host, int $type): array
    {
        return $this->dns ? ($this->dns)($host, $type) : (@dns_get_record($host, $type) ?: []);
    }

    private function addresses(string $host, int $depth = 0): array
    {
        if ($depth > 5) throw new \RuntimeException('La cadena CNAME es demasiado larga.');
        $ips = [];
        foreach ($this->records($host, DNS_A | DNS_AAAA | DNS_CNAME) as $record) {
            if (isset($record['ip'])) $ips[] = $record['ip'];
            if (isset($record['ipv6'])) $ips[] = $record['ipv6'];
            if (isset($record['target'])) $ips = array_merge($ips, $this->addresses(self::hostname($record['target']), $depth + 1));
        }
        return array_values(array_unique($ips));
    }

    public function verify(string $project, int $id): void
    {
        $row = $this->row($project, $id);
        try {
            $expected = $this->config['domains']['ips'] ?? [];
            if (!$expected) throw new \RuntimeException('El servidor aún no tiene configurada la dirección DNS de destino.');
            $proof = 'tmpbase=' . $row['verification_token'];
            $valid = false;
            foreach ($this->records('_tmpbase.' . $row['hostname'], DNS_TXT) as $record) {
                if (hash_equals($proof, (string) ($record['txt'] ?? implode('', $record['entries'] ?? [])))) $valid = true;
            }
            if (!$valid) throw new \RuntimeException('Falta el TXT de verificación. Revisa el nombre y valor indicados y espera la propagación DNS.');
            $ips = $this->addresses($row['hostname']);
            $normalize = static fn ($ip) => @inet_pton($ip) ?: $ip;
            if (!$ips || array_diff(array_map($normalize, $ips), array_map($normalize, $expected))) {
                throw new \RuntimeException('El dominio debe apuntar únicamente a las IP indicadas. Revisa A/AAAA y desactiva el proxy DNS durante la activación.');
            }
            $update = $this->db->prepare("UPDATE project_domains SET status='active',verified_at=?,last_error=NULL,tls_ok=0,tls_checked_at=NULL WHERE id=? AND hostname=? AND verification_token=? AND status=?");
            $update->execute([Support::now(), $id, $row['hostname'], $row['verification_token'], $row['status']]);
            if ($update->rowCount() !== 1) throw new \RuntimeException('El dominio cambió durante la verificación. Recarga el apartado e inténtalo nuevamente.');
        } catch (\Throwable $e) {
            $this->db->prepare('UPDATE project_domains SET last_error=? WHERE id=? AND verification_token=?')->execute([$e->getMessage(), $id, $row['verification_token']]);
            throw $e;
        }
    }

    public function disable(string $project, int $id): void
    {
        $this->row($project, $id);
        $this->db->prepare("UPDATE project_domains SET status='disabled',tls_ok=0 WHERE id=?")->execute([$id]);
    }

    public function remove(string $project, int $id): void
    {
        $this->row($project, $id);
        $this->db->prepare('DELETE FROM project_domains WHERE id=? AND project_uid=?')->execute([$id, $project]);
    }

    public function checkHttps(string $project, int $id): void
    {
        $row = $this->row($project, $id);
        if ($row['status'] !== 'active') throw new \RuntimeException('Verifica y activa el DNS primero.');
        $ok = false;
        foreach ($this->config['domains']['ips'] ?? [] as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP)) continue;
            $context = stream_context_create(['ssl' => ['peer_name' => $row['hostname'], 'verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
            $address = str_contains($ip, ':') ? '[' . $ip . ']' : $ip;
            $socket = @stream_socket_client('ssl://' . $address . ':443', $errno, $error, 4, STREAM_CLIENT_CONNECT, $context);
            if ($socket) { fclose($socket); $ok = true; break; }
        }
        $message = $ok ? null : 'El certificado HTTPS aún no está listo. Espera un minuto y vuelve a comprobar; revisa DNS y los registros CAA si persiste.';
        $update = $this->db->prepare("UPDATE project_domains SET tls_ok=?,tls_checked_at=?,last_error=? WHERE id=? AND hostname=? AND verification_token=? AND status='active'");
        $update->execute([(int) $ok, Support::now(), $message, $id, $row['hostname'], $row['verification_token']]);
        if ($update->rowCount() !== 1) throw new \RuntimeException('El dominio cambió durante la comprobación. Recarga el apartado.');
        if (!$ok) throw new \RuntimeException($message);
    }

    public function routingStatus(): array
    {
        $data = json_decode((string) @file_get_contents($this->config['storage'] . '/domain-routing-status.json'), true);
        return is_array($data) ? $data : [];
    }

    public function resolve(string $hostname): ?array
    {
        $host = strtolower(explode(':', $hostname)[0]);
        $q = $this->db->prepare("SELECT d.*,p.slug AS project_slug,p.status AS project_status,s.slug AS store_slug
            FROM project_domains d JOIN projects p ON p.uid=d.project_uid
            LEFT JOIN storefronts s ON s.project_uid=p.uid WHERE d.hostname=?");
        $q->execute([$host]);
        return $q->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function allowedPath(array $domain, string $path): bool
    {
        if ($domain['status'] !== 'active' || $domain['project_status'] !== 'active') return false;
        $prefixes = ['/api/' . $domain['project_uid'] . '/storage', '/assets', '/sistema/app', '/system-apps'];
        if ($domain['destination'] === 'store') {
            $prefixes[] = '/store/' . $domain['store_slug'];
            $prefixes[] = '/api/storefront/' . $domain['store_slug'];
        } else {
            $prefixes[] = '/sistema/' . $domain['project_slug'];
            $prefixes[] = '/api/system/' . $domain['project_slug'];
        }
        if ($path === '/') return true;
        foreach ($prefixes as $prefix) if ($path === $prefix || str_starts_with($path, $prefix . '/')) return true;
        return false;
    }
}
