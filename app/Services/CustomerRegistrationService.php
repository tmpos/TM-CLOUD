<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Support;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class CustomerRegistrationService
{
    public function __construct(
        private PDO $db,
        private array $config,
        private LogService $logs,
        private SchemaService $schema,
        private RecordService $records,
        private WebhookService $webhooks,
    ) {}

    public function settings(array $project, ?array $input = null, array $request = []): array
    {
        $service = new CustomerRegistrationSettingsService($this->schema);
        $branding = $service->branding($project, $request);
        if (str_starts_with($branding['logo_url'], '/')) $branding['logo_url'] = rtrim((string) ($this->config['url'] ?? ''), '/') . $branding['logo_url'];
        return ['settings' => $input === null ? $service->get($project) : $service->save($project, $input), 'branding' => $branding];
    }

    public function create(array $project, array $input, string $createdBy = '', bool $allowEmptyPhone = false): array
    {
        $crm = !empty($input['crm']) ? (new CrmRegistrationService($this->schema, $this->records, $this->logs, $this->webhooks))->prepare($project, (array) $input['crm']) : null;
        $reusable = ($input['reusable'] ?? false) === true;
        $phone = self::phone((string) ($input['phone'] ?? $input['telefono'] ?? ''));
        if ($phone === '' && !$reusable && !$allowEmptyPhone) throw new InvalidArgumentException('Indique el numero de WhatsApp del cliente.');
        $baseUrl = rtrim((string) ($this->config['url'] ?? ''), '/');
        if (parse_url($baseUrl, PHP_URL_SCHEME) !== 'https' && !in_array(parse_url($baseUrl, PHP_URL_HOST), ['localhost', '127.0.0.1'], true)) {
            throw new RuntimeException('APP_URL debe usar HTTPS para crear enlaces de registro.');
        }

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $uid = Support::uid('crq_');
        $now = Support::now();
        $expiresAt = $reusable ? '9999-12-31 23:59:59' : gmdate('Y-m-d H:i:s', time() + 7 * 86400);
        $almacenId = max(0, (int) ($input['almacen_id'] ?? 0));
        $almacenUid = substr(trim((string) ($input['almacen_uid'] ?? '')), 0, 120);
        $this->db->prepare('INSERT INTO customer_registration_requests (uid,token_hash,project_uid,status,phone,almacen_id,almacen_uid,expires_at,created_by,created_at,reusable,crm_json) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$uid, hash('sha256', $token), $project['uid'], 'pending', $phone, $almacenId ?: null, $almacenUid ?: null, $expiresAt, substr(trim($createdBy), 0, 160), $now, (int) $reusable, $crm ? json_encode($crm, JSON_THROW_ON_ERROR) : null]);
        $this->logs->write('customer.registration_requested', (string) $project['uid'], 'clientes', $uid, null, ['phone' => $phone, 'almacen_id' => $almacenId, 'almacen_uid' => $almacenUid]);

        return [
            'uid' => $uid,
            'url' => $baseUrl . '/register/customer/' . $token,
            'phone' => $phone,
            'expires_at' => $reusable ? null : $expiresAt,
            'reusable' => $reusable,
            'crm' => $crm !== null,
            'status' => 'pending',
        ];
    }

    public function resolve(string $token): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{43}$/D', $token)) throw new RuntimeException('Enlace de registro no disponible.', 404);
        $stmt = $this->db->prepare('SELECT * FROM customer_registration_requests WHERE token_hash=? LIMIT 1');
        $stmt->execute([hash('sha256', $token)]);
        $request = $stmt->fetch();
        if (!$request || ($request['status'] !== 'used' && $request['expires_at'] <= gmdate('Y-m-d H:i:s'))) {
            throw new RuntimeException('Enlace de registro no disponible.', 404);
        }
        return $request;
    }

    public function complete(array $request, array $project, array $input): array
    {
        if (!empty($request['reusable'])) throw new RuntimeException('Abra el QR para iniciar un registro individual.', 409);
        if (($request['status'] ?? '') === 'used') throw new RuntimeException('Este enlace ya fue utilizado.', 409);
        $fields = (new CustomerRegistrationSettingsService($this->schema))->get($project)['fields'];
        // Hidden fields cannot be injected through a direct POST.
        foreach ($fields as $key => $field) {
            if (!$field['visible']) $input[$key] = '';
        }
        if (!$fields['documento']['visible']) unset($input['rnc']);
        if (!array_key_exists('documento', $input)) $input['documento'] = $input['rnc'] ?? '';
        if (!array_key_exists('telefono', $input)) $input['telefono'] = $request['phone'] ?? '';
        foreach (['nombre' => 'el nombre', 'telefono' => 'el teléfono', 'documento' => 'la cédula o RNC', 'email' => 'el correo electrónico', 'direccion' => 'la dirección', 'tipo_cliente' => 'el tipo de cliente'] as $key => $label) {
            if (!is_scalar($input[$key] ?? '')) throw new InvalidArgumentException('El campo ' . $label . ' no es válido.');
            if ($fields[$key]['required'] && trim((string) ($input[$key] ?? '')) === '') throw new InvalidArgumentException('Indique ' . $label . ' del cliente.');
        }
        $name = mb_strtoupper(trim((string) ($input['nombre'] ?? '')), 'UTF-8');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 160) throw new InvalidArgumentException('Indique un nombre valido (2 a 160 caracteres).');
        $phone = self::phone((string) ($input['telefono'] ?? $request['phone'] ?? ''));
        if ($phone === '' && ($fields['telefono']['required'] || trim((string) ($input['telefono'] ?? '')) !== '')) throw new InvalidArgumentException('Indique un telefono valido.');
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('El correo electronico no es valido.');
        $document = preg_replace('/\D+/', '', (string) ($input['documento'] ?? $input['rnc'] ?? '')) ?: '';
        if ($document === '' && ($fields['documento']['required'] || trim((string) ($input['documento'] ?? '')) !== '')) throw new InvalidArgumentException('Indique la cédula o RNC del cliente.');
        if (strlen($document) > 20) throw new InvalidArgumentException('El documento no es valido.');
        $address = mb_strtoupper(trim((string) ($input['direccion'] ?? '')), 'UTF-8');
        if (mb_strlen($address) > 300) throw new InvalidArgumentException('La direccion es demasiado larga.');
        $type = strtoupper(trim((string) ($input['tipo_cliente'] ?? 'NORMAL')));
        $allowedTypes = ['NORMAL', 'CONSUMO', 'FISCAL', 'GUBERNAMENTAL', 'REGIMEN_ESPECIAL', 'EXPORTACION'];
        if (!in_array($type, $allowedTypes, true)) $type = 'NORMAL';

        if (!empty($request['crm_json'])) (new CrmRegistrationService($this->schema, $this->records, $this->logs, $this->webhooks))->validate($input, $fields);
        $claim = $this->db->prepare("UPDATE customer_registration_requests SET status='processing' WHERE uid=? AND status='pending' AND expires_at>?");
        $claim->execute([$request['uid'], gmdate('Y-m-d H:i:s')]);
        if ($claim->rowCount() !== 1) throw new RuntimeException('El enlace ya fue utilizado o expiro.', 409);

        try {
            $available = array_column($this->schema->columns($project, 'clientes'), 'name');
            $candidate = [
                'nombre' => $name,
                'telefono' => $phone,
                'whatsapp' => $phone,
                'email' => $email,
                'direccion' => $address,
                'rnc' => $document,
                'cedula' => $document,
                'tipo_cliente' => $type,
                'activo' => 1,
                'estado' => 'ACTIVO',
                'almacen_id' => (int) ($request['almacen_id'] ?? 0),
                'almacen_uid' => (string) ($request['almacen_uid'] ?? ''),
            ];
            $data = array_intersect_key($candidate, array_flip($available));
            foreach ($data as $key => $value) {
                if ($value === '' && !in_array($key, ['email', 'direccion', 'rnc', 'cedula'], true)) unset($data[$key]);
            }
            $customer = !empty($request['crm_json'])
                ? (new CrmRegistrationService($this->schema, $this->records, $this->logs, $this->webhooks))->complete($project, $request, $data, $input, $fields)
                : $this->records->create($project, 'clientes', $data);
            $now = Support::now();
            $this->db->prepare("UPDATE customer_registration_requests SET status='used',customer_uid=?,used_at=? WHERE uid=? AND status='processing'")
                ->execute([(string) ($customer['uid'] ?? ''), $now, $request['uid']]);
        } catch (\Throwable $error) {
            $this->db->prepare("UPDATE customer_registration_requests SET status='pending' WHERE uid=? AND status='processing'")->execute([$request['uid']]);
            throw $error;
        }

        try { $this->logs->write('customer.registered', (string) $project['uid'], 'clientes', (string) ($customer['uid'] ?? ''), null, ['request_uid' => $request['uid'], 'customer' => $customer]); }
        catch (\Throwable) {}
        $eventErrors = [];
        try { $this->webhooks->dispatch('record.created', $project, 'clientes', $customer); }
        catch (\Throwable $eventError) { $eventErrors[] = 'record.created: ' . $eventError->getMessage(); }
        try {
            $this->webhooks->dispatch('customer.registered', $project, 'clientes', [
                ...$customer,
                'request_uid' => (string) $request['uid'],
                'registered_at' => $now,
            ]);
        } catch (\Throwable $eventError) { $eventErrors[] = 'customer.registered: ' . $eventError->getMessage(); }
        if ($eventErrors) {
            try { $this->logs->write('customer.registration_notification_failed', (string) $project['uid'], 'clientes', (string) ($customer['uid'] ?? ''), null, ['errors' => $eventErrors]); }
            catch (\Throwable) {}
        }
        return $customer;
    }

    private static function phone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?: '';
        if (strlen($digits) === 10) $digits = '1' . $digits;
        return strlen($digits) >= 10 && strlen($digits) <= 15 ? $digits : '';
    }
}
