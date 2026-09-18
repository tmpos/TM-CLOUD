<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Support;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Self-service client signup: a one-time link that, when filled out, creates
 * a project with the same table structure as the reference project below,
 * seeded with the submitted company info and a single Administrador user.
 */
final class ClientOnboardingService
{
    private const TEMPLATE_PROJECT_UID = 'prj_20dba616a24e37e34124cd9ab97088f0';

    public function __construct(
        private PDO $db,
        private array $config,
        private LogService $logs,
        private ProjectService $projects,
        private SchemaService $schema,
        private StorageService $storage,
        private LicenseService $licenses,
        private MailService $mail,
    ) {
    }

    public function all(): array
    {
        return $this->db->query('SELECT * FROM onboarding_links ORDER BY id DESC')->fetchAll();
    }

    public function create(string $createdBy): array
    {
        $uid = Support::uid('obl_');
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $now = Support::now();
        $this->db->prepare('INSERT INTO onboarding_links (uid, token_hash, status, created_by, created_at) VALUES (?,?,?,?,?)')
            ->execute([$uid, hash('sha256', $token), 'pending', $createdBy, $now]);
        $this->logs->write('onboarding.link_created', null, 'onboarding_links', $uid, null, ['created_by' => $createdBy]);
        return ['uid' => $uid, 'url' => rtrim((string) $this->config['url'], '/') . '/onboarding/' . $token, 'created_at' => $now];
    }

    public function delete(string $uid): void
    {
        $this->db->prepare("DELETE FROM onboarding_links WHERE uid = ? AND status = 'pending'")->execute([$uid]);
    }

    public function resolve(string $token): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{43}$/', $token)) {
            throw new RuntimeException('Enlace no disponible.', 404);
        }
        $stmt = $this->db->prepare('SELECT * FROM onboarding_links WHERE token_hash = ? LIMIT 1');
        $stmt->execute([hash('sha256', $token)]);
        $link = $stmt->fetch();
        if (!$link || $link['status'] !== 'pending') {
            throw new RuntimeException('Este enlace ya no esta disponible.', 404);
        }
        return $link;
    }

    public function complete(string $token, array $company, ?array $logoFile): array
    {
        $link = $this->resolve($token);

        $name = trim((string) ($company['nombre'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            throw new InvalidArgumentException('Indique el nombre de la empresa (maximo 100 caracteres).');
        }
        $direccion = mb_substr(trim((string) ($company['direccion'] ?? '')), 0, 255);
        $telefono = mb_substr(trim((string) ($company['telefono'] ?? '')), 0, 50);
        $email = mb_substr(trim((string) ($company['email'] ?? '')), 0, 150);
        $encargado = mb_substr(trim((string) ($company['encargado'] ?? '')), 0, 100);
        $rnc = mb_substr(trim((string) ($company['rnc'] ?? '')), 0, 50);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Indique un correo valido; ahi se enviaran los datos de acceso.');
        }

        $claim = $this->db->prepare("UPDATE onboarding_links SET status='claimed' WHERE uid = ? AND status = 'pending'");
        $claim->execute([$link['uid']]);
        if ($claim->rowCount() !== 1) {
            throw new RuntimeException('Este enlace ya no esta disponible.', 409);
        }

        try {
            $project = $this->projects->create(['name' => $name, 'description' => 'Registrado por el cliente via enlace de firma']);
            $this->cloneTemplateSchema($project);
            $targetDb = $this->schema->connection($project);

            $logoUid = '';
            if ($logoFile && ($logoFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                try {
                    $file = $this->storage->upload($project, $logoFile);
                    $logoUid = (string) $file['uid'];
                } catch (\Throwable $e) {
                    $this->logs->write('onboarding.logo_failed', (string) $project['uid'], 'empresa', '', null, ['error' => $e->getMessage()]);
                }
            }

            $now = Support::now();
            $this->insertRow($targetDb, 'empresa', [
                'uid' => Support::uid('rec_'), 'nombre' => $name, 'legal' => $rnc, 'telefono' => $telefono,
                'email' => $email, 'direccion' => $direccion, 'encargado' => $encargado, 'moneda' => 'DOP',
                'impuesto' => 18, 'impuesto_incluido' => 1, 'logo' => $logoUid, 'estado' => 'ACTIVADO',
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->insertRow($targetDb, 'usuarios', [
                'uid' => Support::uid('rec_'), 'usuario' => 'ADMINISTRADOR', 'nombre' => 'Administrador',
                'pin' => '1234', 'nivel_seguridad' => 'administrador', 'rol' => 'administrador',
                'estado' => 'ACTIVADO', 'created_at' => $now, 'updated_at' => $now,
            ]);

            $license = $this->licenses->create((string) $project['uid'], [
                'system_name' => $name, 'nombre' => $name, 'rnc' => $rnc, 'telefono' => $telefono,
                'email' => $email, 'encargado' => $encargado, 'direccion' => $direccion, 'status' => 'active',
                'project_url' => rtrim((string) $this->config['url'], '/') . '/api/' . $project['uid'],
                'public_key' => $project['public_key'], 'secret_key' => $project['secret_key'],
            ]);

            $this->db->prepare("UPDATE onboarding_links SET status='used', project_uid=?, company_name=?, used_at=? WHERE uid=?")
                ->execute([$project['uid'], $name, $now, $link['uid']]);
        } catch (\Throwable $e) {
            $this->db->prepare("UPDATE onboarding_links SET status='pending' WHERE uid = ?")->execute([$link['uid']]);
            throw $e;
        }

        $this->logs->write('onboarding.completed', (string) $project['uid'], 'empresa', (string) $project['uid'], null, ['onboarding_uid' => $link['uid']]);
        $this->sendWelcomeEmail($project, $email, $name, $rnc, $encargado, (string) $license['license_key']);
        return ['project' => $project, 'license_key' => $license['license_key']];
    }

    /** Best-effort: the client already has a working project/license even if this fails (SMTP not configured, etc). */
    private function sendWelcomeEmail(array $project, string $email, string $name, string $rnc, string $encargado, string $licenseKey): void
    {
        try {
            $job = $this->mail->queue((string) $project['uid'], 'onboarding_welcome', $email, [
                'company_name' => $name,
                'rnc' => $rnc,
                'encargado' => $encargado,
                'license_key' => $licenseKey,
                'system_url' => rtrim((string) $this->config['url'], '/') . '/sistema/' . $project['slug'],
            ]);
            $this->mail->deliver((string) $project['uid'], (string) $job['uid']);
        } catch (\Throwable $e) {
            $this->logs->write('onboarding.welcome_mail_failed', (string) $project['uid'], 'empresa', (string) $project['uid'], null, ['error' => $e->getMessage()]);
        }
    }

    /** Copies every business table (and its indexes) from the reference project so a new client starts with the same structure, empty except for empresa/usuarios. */
    private function cloneTemplateSchema(array $project): void
    {
        $template = $this->projects->findActive(self::TEMPLATE_PROJECT_UID);
        $templateDb = $this->schema->connection($template);
        $targetDb = $this->schema->connection($project);
        $statements = $templateDb->query(
            "SELECT sql FROM sqlite_master WHERE sql IS NOT NULL AND type IN ('table','index') AND name NOT LIKE '\\_%' ESCAPE '\\' ORDER BY (type = 'index')"
        )->fetchAll(PDO::FETCH_COLUMN);
        foreach ($statements as $sql) {
            try {
                $targetDb->exec((string) $sql);
            } catch (\Throwable) {
                // A table the template already had columns changed on later, or an
                // index that depends on a table skipped above; not fatal on its own.
            }
        }
    }

    private function insertRow(PDO $db, string $table, array $data): void
    {
        $columns = array_column($db->query('PRAGMA table_info(' . Support::quoteIdentifier($table) . ')')->fetchAll(), 'name');
        $data = array_intersect_key($data, array_flip($columns));
        if (!$data) {
            return;
        }
        $cols = array_keys($data);
        $sql = 'INSERT INTO ' . Support::quoteIdentifier($table) . ' (' . implode(',', array_map([Support::class, 'quoteIdentifier'], $cols)) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
        $db->prepare($sql)->execute(array_values($data));
    }
}
