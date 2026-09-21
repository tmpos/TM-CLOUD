<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Http;
use App\Core\Support;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/** Signing tokens are separate from read-only invoice share tokens. */
final class InvoiceSignatureService
{
    public const CONSENT = 'He revisado esta factura y acepto firmarla electrónicamente como constancia de recepción y conformidad.';

    public function __construct(
        private PDO $db,
        private array $config,
        private LogService $logs,
        private SchemaService $schema,
        private ?RealtimeService $realtime = null,
    ) {}

    public function create(array $project, array $invoice, ?string $expiresAt = null): array
    {
        $projectUid = (string) ($project['uid'] ?? '');
        $recordUid = trim((string) ($invoice['uid'] ?? ''));
        if ($projectUid === '' || $recordUid === '') throw new InvalidArgumentException('La factura debe estar sincronizada antes de solicitar la firma.');
        self::ensureSignable($invoice);
        $baseUrl = rtrim((string) $this->config['url'], '/');
        if (parse_url($baseUrl, PHP_URL_SCHEME) !== 'https' && !in_array(parse_url($baseUrl, PHP_URL_HOST), ['localhost', '127.0.0.1'], true)) {
            throw new RuntimeException('APP_URL debe usar HTTPS para crear enlaces de firma.');
        }
        $now = Support::now();
        $expiresAt = $this->expiration($expiresAt);
        $snapshot = $this->snapshot($project, $invoice);
        $hash = hash('sha256', $snapshot);
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $uid = Support::uid('sig_');
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $stmt = $this->db->prepare('SELECT 1 FROM invoice_signature_requests WHERE project_uid=? AND record_uid=? AND invoice_hash=? AND signed_at IS NOT NULL LIMIT 1');
            $stmt->execute([$projectUid, $recordUid, $hash]);
            if ($stmt->fetchColumn()) throw new RuntimeException('Esta versión de la factura ya fue firmada.', 409);
            $this->db->prepare('UPDATE invoice_signature_requests SET revoked_at=?,updated_at=? WHERE project_uid=? AND record_uid=? AND signed_at IS NULL AND revoked_at IS NULL')
                ->execute([$now, $now, $projectUid, $recordUid]);
            $this->db->prepare('INSERT INTO invoice_signature_requests (uid,token_hash,project_uid,record_uid,invoice_snapshot,invoice_hash,expires_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$uid, hash('sha256', $token), $projectUid, $recordUid, $snapshot, $hash, $expiresAt, $now, $now]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        $this->logs->write('invoice.signature_requested', $projectUid, 'facturas', $recordUid, null, ['request_uid' => $uid, 'invoice_hash' => $hash]);
        return ['uid' => $uid, 'url' => rtrim((string) $this->config['url'], '/') . '/sign/invoice/' . $token, 'expires_at' => $expiresAt, 'status' => 'pending'];
    }

    public function resolve(string $token): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{43}$/', $token)) throw new RuntimeException('Enlace de firma no disponible.', 404);
        $stmt = $this->db->prepare('SELECT * FROM invoice_signature_requests WHERE token_hash=? LIMIT 1');
        $stmt->execute([hash('sha256', $token)]);
        $request = $stmt->fetch();
        if (!$request || $request['revoked_at'] || $request['expires_at'] <= gmdate('Y-m-d H:i:s')) throw new RuntimeException('Enlace de firma no disponible.', 404);
        return $request;
    }

    public function sign(array $request, array $project, array $invoice, array $input, bool $useStoredSnapshot = false): array
    {
        self::ensureSignable($invoice);
        if ($request['signed_at'] !== null) throw new RuntimeException('Esta factura ya fue firmada.', 409);
        $snapshot = $useStoredSnapshot ? (string) ($request['invoice_snapshot'] ?? '') : $this->snapshot($project, $invoice);
        if (!hash_equals((string) $request['invoice_hash'], hash('sha256', $snapshot))) {
            throw new RuntimeException('La factura cambió desde que se generó el enlace. Solicite uno nuevo.', 409);
        }
        if (!in_array($input['consent'] ?? null, [true, 'true', '1', 1, 'on'], true)) throw new InvalidArgumentException('Debe aceptar expresamente la firma de la factura.');
        $name = trim((string) ($input['signer_name'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) throw new InvalidArgumentException('Indique el nombre de quien firma (2 a 120 caracteres).');
        $png = self::decodePng((string) ($input['signature'] ?? ''));
        $now = Support::now();
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $stmt = $this->db->prepare('UPDATE invoice_signature_requests SET signer_name=?,signature_png=?,signature_sha256=?,consent_text=?,consent_at=?,signer_ip=?,signer_user_agent=?,signed_at=?,updated_at=? WHERE uid=? AND signed_at IS NULL AND revoked_at IS NULL AND expires_at>?');
            $stmt->bindValue(1, $name);
            $stmt->bindValue(2, $png, PDO::PARAM_LOB);
            $stmt->bindValue(3, hash('sha256', $png));
            $stmt->bindValue(4, self::CONSENT);
            $stmt->bindValue(5, $now);
            $stmt->bindValue(6, substr(Http::clientIp(), 0, 64));
            $stmt->bindValue(7, substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512));
            $stmt->bindValue(8, $now);
            $stmt->bindValue(9, $now);
            $stmt->bindValue(10, $request['uid']);
            $stmt->bindValue(11, gmdate('Y-m-d H:i:s'));
            $stmt->execute();
            if ($stmt->rowCount() !== 1) throw new RuntimeException('El enlace ya fue utilizado o expiró.', 409);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        $this->logs->write('invoice.signed', (string) $request['project_uid'], 'facturas', (string) $request['record_uid'], null, ['request_uid' => $request['uid'], 'invoice_hash' => $request['invoice_hash'], 'signature_sha256' => hash('sha256', $png)]);
        $this->realtime?->broadcast('invoice.signed', $project, 'facturas', [
            'record_uid' => (string) $request['record_uid'],
            'request_uid' => (string) $request['uid'],
            'invoice_number' => (string) ($invoice['no_factura'] ?? $invoice['numero'] ?? $request['record_uid']),
            'signer_name' => $name,
            'signed_at' => $now,
        ]);
        return ['status' => 'signed', 'signed_at' => $now, 'signer_name' => $name];
    }

    public function status(array $project, array $invoice, bool $includeImage = false): array
    {
        $projectUid = (string) ($project['uid'] ?? '');
        $uid = trim((string) ($invoice['uid'] ?? ''));
        if ($uid === '') return ['status' => 'none'];
        try { self::ensureSignable($invoice); } catch (InvalidArgumentException) { return ['status' => 'unavailable']; }
        $hash = hash('sha256', $this->snapshot($project, $invoice));
        $stmt = $this->db->prepare('SELECT * FROM invoice_signature_requests WHERE project_uid=? AND record_uid=? ORDER BY (signed_at IS NOT NULL) DESC,created_at DESC,id DESC');
        $stmt->execute([$projectUid, $uid]);
        $requests = $stmt->fetchAll();
        foreach ($requests as $request) {
            if ($request['invoice_hash'] !== $hash || $request['revoked_at']) continue;
            if ($request['signed_at'] !== null) {
                $data = ['status' => 'signed', 'signed_at' => $request['signed_at'], 'signer_name' => $request['signer_name'], 'invoice_hash' => $hash];
                if ($includeImage) {
                    $png = $request['signature_png'];
                    if (is_resource($png)) $png = stream_get_contents($png);
                    $data['signature_data_uri'] = 'data:image/png;base64,' . base64_encode((string) $png);
                }
                return $data;
            }
            return ['status' => $request['expires_at'] > gmdate('Y-m-d H:i:s') ? 'pending' : 'expired', 'expires_at' => $request['expires_at']];
        }
        return ['status' => $requests ? 'stale' : 'none'];
    }

    public function snapshot(array $project, array $invoice): string
    {
        $details = $this->rows($project, 'factura_detalle', 'factura_id', [$invoice['id'] ?? null, $invoice['uid'] ?? null]);
        if (!$details && !empty($invoice['orden_id'])) $details = $this->rows($project, 'orden_detalle', 'orden_id', [$invoice['orden_id']]);
        $document = [
            'version' => 2,
            'project' => array_intersect_key($project, array_flip(['uid', 'name', 'rnc'])),
            'company' => $this->rows($project, 'empresa', null, []),
            'customer' => !empty($invoice['cliente_id']) ? $this->rows($project, 'clientes', 'id', [$invoice['cliente_id']]) : [],
            'invoice' => $invoice,
            'details' => $details,
            'payments' => $this->rows($project, 'factura_pagos', 'factura_id', [$invoice['id'] ?? null, $invoice['uid'] ?? null]),
            'consent' => self::CONSENT,
        ];
        return json_encode(self::canonicalize($document), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public function invoiceFromSnapshot(array $request): array
    {
        $snapshot = (string) ($request['invoice_snapshot'] ?? '');
        if ($snapshot === '' || !hash_equals((string) ($request['invoice_hash'] ?? ''), hash('sha256', $snapshot))) {
            throw new RuntimeException('La copia de la factura no es valida.', 409);
        }
        $document = json_decode($snapshot, true, 64, JSON_THROW_ON_ERROR);
        $invoice = $document['invoice'] ?? null;
        if (!is_array($invoice) || trim((string) ($invoice['uid'] ?? '')) !== trim((string) ($request['record_uid'] ?? ''))) {
            throw new RuntimeException('La copia de la factura no es valida.', 409);
        }
        return $invoice;
    }

    private function rows(array $project, string $table, ?string $column, array $values): array
    {
        try { return $this->filteredRows($project, $table, $column, $values); }
        catch (\Throwable) { return []; }
    }

    private function filteredRows(array $project, string $table, ?string $column, array $values): array
    {
        $columns = array_column($this->schema->columns($project, $table), 'name');
        if ($column === null) return $this->allRows($project, $table);
        if (!in_array($column, $columns, true)) return [];
        return $this->rowsWithValues($project, $table, $column, $values);
    }

    private function allRows(array $project, string $table): array
    {
        $sql = sprintf('SELECT * FROM %s ORDER BY id ASC', $table);
        return $this->schema->connection($project)->query($sql)->fetchAll() ?: [];
    }

    private function rowsWithValues(array $project, string $table, string $column, array $values): array
    {
        $values = array_values(array_unique(array_filter($values, fn ($v) => $v !== null && $v !== '')));
        if (!$values) return [];
        $marks = implode(',', array_fill(0, count($values), '?'));
        $sql = sprintf('SELECT * FROM %s WHERE %s IN (%s)', $table, $column, $marks);
        $stmt = $this->schema->connection($project)->prepare($sql);
        $stmt->execute($values);
        return $stmt->fetchAll() ?: [];
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) return $value;
        if (!array_is_list($value)) ksort($value, SORT_STRING);
        foreach ($value as $key => $item) $value[$key] = self::canonicalize($item);
        if (array_is_list($value)) usort($value, static fn (mixed $a, mixed $b): int => strcmp((string) json_encode($a), (string) json_encode($b)));
        return $value;
    }

    public static function decodePng(string $dataUri): string
    {
        if (!function_exists('imagecreatefromstring')) throw new RuntimeException('La extensión GD no está disponible.', 500);
        if (strlen($dataUri) > 360000 || !preg_match('#^data:image/png;base64,([A-Za-z0-9+/]+={0,2})$#D', $dataUri, $match)) {
            throw new InvalidArgumentException('La firma debe ser una imagen PNG válida.');
        }
        $png = base64_decode($match[1], true);
        if ($png === false || strlen($png) < 100 || strlen($png) > 256 * 1024 || !str_starts_with($png, "\x89PNG\r\n\x1a\n")) {
            throw new InvalidArgumentException('La imagen de firma no es válida o excede 256 KB.');
        }
        $size = @getimagesizefromstring($png);
        if (!$size || ($size['mime'] ?? '') !== 'image/png' || $size[0] < 100 || $size[1] < 40 || $size[0] > 2048 || $size[1] > 1024) {
            throw new InvalidArgumentException('La imagen de firma debe medir entre 100x40 y 2048x1024 píxeles.');
        }
        $image = @imagecreatefromstring($png);
        if (!$image) throw new InvalidArgumentException('No se pudo leer la imagen de firma.');
        $hasInk = false;
        for ($y = 0; $y < $size[1] && !$hasInk; $y += max(1, (int) floor($size[1] / 150))) {
            for ($x = 0; $x < $size[0]; $x += max(1, (int) floor($size[0] / 300))) {
                $color = imagecolorsforindex($image, imagecolorat($image, $x, $y));
                if ($color['alpha'] < 100 && $color['red'] + $color['green'] + $color['blue'] < 600) { $hasInk = true; break; }
            }
        }
        imagedestroy($image);
        if (!$hasInk) throw new InvalidArgumentException('Dibuje la firma antes de enviarla.');
        return $png;
    }

    private static function ensureSignable(array $invoice): void
    {
        foreach (['estado_factura', 'estado', 'tipo_documento', 'documento_titulo'] as $field) {
            $value = trim((string) ($invoice[$field] ?? ''));
            if ($value !== '' && preg_match('/anulad|cancelad|void|cotiz|presupuest|proforma/iu', $value)) {
                throw new InvalidArgumentException('No se puede firmar una factura anulada ni una cotización.');
            }
        }
    }

    private function expiration(?string $requested): string
    {
        if ($requested === null || trim($requested) === '') return gmdate('Y-m-d H:i:s', time() + 7 * 86400);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', trim($requested), new DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d H:i:s') !== trim($requested) || $date->getTimestamp() <= time() || $date->getTimestamp() > time() + 30 * 86400) {
            throw new InvalidArgumentException('expires_at debe ser una fecha UTC futura dentro de los próximos 30 días (YYYY-MM-DD HH:MM:SS).');
        }
        return $date->format('Y-m-d H:i:s');
    }
}
