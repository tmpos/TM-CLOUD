<?php
declare(strict_types=1);
namespace App\Services;

use PDO;
use RuntimeException;
use InvalidArgumentException;

/** Capture only: an administrator must apply the received signature in settings. */
final class CompanySignatureService
{
    public const CONSENT = 'Autorizo a la empresa a guardar esta firma y utilizarla como firma de su representante en facturas y cotizaciones.';
    public function __construct(private SchemaService $schema, private array $config) {}

    private function exists(PDO $db): bool
    {
        return (bool) $db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='_company_signature_requests'")->fetchColumn();
    }

    public function create(array $project, array $input): array
    {
        $name = $input['representative_name'] ?? '';
        if (!is_string($name) || mb_strlen($name) > 100) throw new InvalidArgumentException('Nombre del representante no válido.');
        $base = rtrim((string) ($this->config['url'] ?? ''), '/');
        if (parse_url($base, PHP_URL_SCHEME) !== 'https' && !in_array(parse_url($base, PHP_URL_HOST), ['localhost', '127.0.0.1'], true)) throw new RuntimeException('Configure APP_URL con HTTPS.');
        $db = $this->schema->connection($project);
        $db->exec('CREATE TABLE IF NOT EXISTS _company_signature_requests (id INTEGER PRIMARY KEY AUTOINCREMENT, uid TEXT UNIQUE NOT NULL, token_hash TEXT UNIQUE NOT NULL, representative_name TEXT NOT NULL, expires_at TEXT NOT NULL, signed_at TEXT, revoked_at TEXT, signature TEXT, consent_text TEXT)');
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $uid = 'csig_' . bin2hex(random_bytes(16));
        $expires = gmdate('Y-m-d H:i:s', time() + 86400);
        $db->exec('BEGIN IMMEDIATE');
        try {
            $db->prepare('UPDATE _company_signature_requests SET revoked_at=? WHERE signed_at IS NULL AND revoked_at IS NULL')->execute([gmdate('Y-m-d H:i:s')]);
            $db->prepare('INSERT INTO _company_signature_requests(uid,token_hash,representative_name,expires_at) VALUES(?,?,?,?)')->execute([$uid, hash('sha256', $token), trim($name), $expires]);
            $db->exec('COMMIT');
        } catch (\Throwable $e) { $db->exec('ROLLBACK'); throw $e; }
        return ['uid' => $uid, 'status' => 'pending', 'expires_at' => $expires, 'url' => $base . '/sign/company/' . rawurlencode($project['uid']) . '/' . $token];
    }

    public function resolve(array $project, string $token): array
    {
        $db = $this->schema->connection($project);
        if (!preg_match('/^[A-Za-z0-9_-]{43}$/D', $token) || !$this->exists($db)) throw new RuntimeException('Enlace de firma no disponible.', 404);
        $stmt = $db->prepare('SELECT * FROM _company_signature_requests WHERE token_hash=?');
        $stmt->execute([hash('sha256', $token)]);
        $request = $stmt->fetch();
        if (!$request || $request['revoked_at'] || $request['expires_at'] <= gmdate('Y-m-d H:i:s')) throw new RuntimeException('El enlace venció o fue reemplazado. Solicita uno nuevo a la empresa.', 404);
        return $request;
    }

    public function sign(array $project, string $token, array $input): array
    {
        $request = $this->resolve($project, $token);
        if ($request['signed_at']) throw new RuntimeException('La firma ya fue recibida.', 409);
        if (!in_array($input['consent'] ?? null, [true, 1, '1', 'on'], true)) throw new InvalidArgumentException('Acepta el uso de la firma antes de continuar.');
        $name = $input['signer_name'] ?? '';
        if (!is_string($name) || mb_strlen(trim($name)) < 2 || mb_strlen($name) > 100) throw new InvalidArgumentException('Indica el nombre completo del representante (2 a 100 caracteres).');
        $image = $input['signature'] ?? '';
        if (!is_string($image)) throw new InvalidArgumentException('Firma no válida.');
        $png = InvoiceSignatureService::decodePng($image);
        $image = 'data:image/png;base64,' . base64_encode($png);
        (new DocumentSettingsService($this->schema))->normalize(['show_company_signature' => true, 'representative_name' => trim($name), 'representative_signature' => $image]);
        $stmt = $this->schema->connection($project)->prepare('UPDATE _company_signature_requests SET representative_name=?,signature=?,consent_text=?,signed_at=? WHERE uid=? AND signed_at IS NULL AND revoked_at IS NULL AND expires_at>?');
        $now = gmdate('Y-m-d H:i:s');
        $stmt->execute([trim($name), $image, self::CONSENT, $now, $request['uid'], $now]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException('El enlace ya fue utilizado o venció.', 409);
        return ['status' => 'signed', 'signed_at' => $now];
    }

    public function status(array $project): array
    {
        $db = $this->schema->connection($project);
        if (!$this->exists($db)) return ['status' => 'none'];
        $row = $db->query('SELECT * FROM _company_signature_requests ORDER BY id DESC LIMIT 1')->fetch();
        if (!$row) return ['status' => 'none'];
        $status = $row['revoked_at'] ? 'revoked' : ($row['signed_at'] ? 'signed' : ($row['expires_at'] <= gmdate('Y-m-d H:i:s') ? 'expired' : 'pending'));
        $result = ['uid' => $row['uid'], 'status' => $status, 'expires_at' => $row['expires_at'], 'signed_at' => $row['signed_at']];
        if ($status === 'signed') $result += ['representative_name' => $row['representative_name'], 'representative_signature' => $row['signature']];
        return $result;
    }

    public function cancel(array $project): array
    {
        $db = $this->schema->connection($project);
        if ($this->exists($db)) $db->prepare('UPDATE _company_signature_requests SET revoked_at=? WHERE signed_at IS NULL AND revoked_at IS NULL')->execute([gmdate('Y-m-d H:i:s')]);
        return $this->status($project);
    }
}
