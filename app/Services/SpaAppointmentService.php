<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Support;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class SpaAppointmentService
{
    public function __construct(
        private PDO $db,
        private array $config,
        private LogService $logs,
        private SchemaService $schema,
        private RecordService $records,
        private WebhookService $webhooks,
    ) {}

    public function create(array $project, array $input, string $createdBy = '', bool $allowEmptyPhone = false): array
    {
        $reusable = ($input['reusable'] ?? false) === true;
        $phone = self::phone((string) ($input['phone'] ?? $input['telefono'] ?? ''));
        if ($phone === '' && !$reusable && !$allowEmptyPhone) throw new InvalidArgumentException('Indique el numero de WhatsApp del cliente.');
        $baseUrl = rtrim((string) ($this->config['url'] ?? ''), '/');
        if (parse_url($baseUrl, PHP_URL_SCHEME) !== 'https' && !in_array(parse_url($baseUrl, PHP_URL_HOST), ['localhost', '127.0.0.1'], true)) {
            throw new RuntimeException('APP_URL debe usar HTTPS para crear enlaces de registro.');
        }

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $uid = Support::uid('spq_');
        $now = Support::now();
        $expiresAt = $reusable ? '9999-12-31 23:59:59' : gmdate('Y-m-d H:i:s', time() + 7 * 86400);
        $almacenId = max(0, (int) ($input['almacen_id'] ?? 0));
        $almacenUid = substr(trim((string) ($input['almacen_uid'] ?? '')), 0, 120);
        $this->db->prepare('INSERT INTO spa_appointment_requests (uid,token_hash,project_uid,status,phone,almacen_id,almacen_uid,expires_at,created_by,created_at,reusable) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$uid, hash('sha256', $token), $project['uid'], 'pending', $phone, $almacenId ?: null, $almacenUid ?: null, $expiresAt, substr(trim($createdBy), 0, 160), $now, (int) $reusable]);
        $this->logs->write('spa.appointment_requested', (string) $project['uid'], 'citas_spa', $uid, null, ['phone' => $phone, 'almacen_id' => $almacenId, 'almacen_uid' => $almacenUid]);

        return [
            'uid' => $uid,
            'url' => $baseUrl . '/register/spa/' . $token,
            'phone' => $phone,
            'expires_at' => $reusable ? null : $expiresAt,
            'reusable' => $reusable,
            'status' => 'pending',
        ];
    }

    public function resolve(string $token): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{43}$/D', $token)) throw new RuntimeException('Enlace de registro no disponible.', 404);
        $stmt = $this->db->prepare('SELECT * FROM spa_appointment_requests WHERE token_hash=? LIMIT 1');
        $stmt->execute([hash('sha256', $token)]);
        $request = $stmt->fetch();
        if (!$request || ($request['status'] !== 'used' && $request['expires_at'] <= gmdate('Y-m-d H:i:s'))) {
            throw new RuntimeException('Enlace de registro no disponible.', 404);
        }
        return $request;
    }

    public function complete(array $request, array $project, array $input): array
    {
        if (($request['status'] ?? '') === 'used' && empty($request['reusable'])) throw new RuntimeException('Este enlace ya fue utilizado.', 409);
        $name = mb_strtoupper(trim((string) ($input['nombre'] ?? '')), 'UTF-8');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 160) throw new InvalidArgumentException('Indique un nombre valido (2 a 160 caracteres).');
        $phone = self::phone((string) ($input['telefono'] ?? $request['phone'] ?? ''));
        if ($phone === '') throw new InvalidArgumentException('Indique un telefono valido.');
        $fecha = trim((string) ($input['fecha'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) throw new InvalidArgumentException('Indique una fecha valida.');
        $hoy = gmdate('Y-m-d');
        if ($fecha < $hoy) throw new InvalidArgumentException('La fecha debe ser hoy o una fecha futura.');
        $hora = trim((string) ($input['hora'] ?? ''));
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora)) throw new InvalidArgumentException('Indique una hora valida.');
        $servicio = mb_strtoupper(trim((string) ($input['servicio'] ?? '')), 'UTF-8');
        if (mb_strlen($servicio) > 160) throw new InvalidArgumentException('El servicio solicitado es demasiado largo.');
        $nota = trim((string) ($input['nota'] ?? ''));
        if (mb_strlen($nota) > 500) throw new InvalidArgumentException('La nota es demasiado larga.');

        if (empty($request['reusable'])) {
            $claim = $this->db->prepare("UPDATE spa_appointment_requests SET status='processing' WHERE uid=? AND status='pending' AND expires_at>?");
            $claim->execute([$request['uid'], gmdate('Y-m-d H:i:s')]);
            if ($claim->rowCount() !== 1) throw new RuntimeException('El enlace ya fue utilizado o expiro.', 409);
        }

        try {
            $available = array_column($this->schema->columns($project, 'citas_spa'), 'name');
            $candidate = [
                'nombre_cliente' => $name,
                'telefono' => $phone,
                'fecha' => $fecha,
                'hora' => $hora,
                'servicio' => $servicio,
                'nota' => $nota,
                'estado' => 'PENDIENTE',
                'origen' => 'LINK',
                'almacen_id' => (int) ($request['almacen_id'] ?? 0),
                'almacen_uid' => (string) ($request['almacen_uid'] ?? ''),
            ];
            $data = array_intersect_key($candidate, array_flip($available));
            foreach ($data as $key => $value) {
                if ($value === '' && !in_array($key, ['nota'], true)) unset($data[$key]);
            }
            $appointment = $this->records->create($project, 'citas_spa', $data);
            $now = Support::now();
            if (empty($request['reusable'])) {
                $this->db->prepare("UPDATE spa_appointment_requests SET status='used',appointment_uid=?,used_at=? WHERE uid=? AND status='processing'")
                    ->execute([(string) ($appointment['uid'] ?? ''), $now, $request['uid']]);
            }
        } catch (\Throwable $error) {
            if (empty($request['reusable'])) {
                $this->db->prepare("UPDATE spa_appointment_requests SET status='pending' WHERE uid=? AND status='processing'")->execute([$request['uid']]);
            }
            throw $error;
        }

        try { $this->logs->write('spa.appointment_created', (string) $project['uid'], 'citas_spa', (string) ($appointment['uid'] ?? ''), null, ['request_uid' => $request['uid'], 'appointment' => $appointment]); }
        catch (\Throwable) {}
        try { $this->webhooks->dispatch('record.created', $project, 'citas_spa', $appointment); }
        catch (\Throwable $eventError) {
            try { $this->logs->write('spa.appointment_notification_failed', (string) $project['uid'], 'citas_spa', (string) ($appointment['uid'] ?? ''), null, ['error' => $eventError->getMessage()]); }
            catch (\Throwable) {}
        }
        return $appointment;
    }

    private static function phone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?: '';
        if (strlen($digits) === 10) $digits = '1' . $digits;
        return strlen($digits) >= 10 && strlen($digits) <= 15 ? $digits : '';
    }
}
