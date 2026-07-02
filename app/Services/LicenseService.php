<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Support;
use PDO;
use RuntimeException;

final class LicenseService
{
    private const EXTRA_FIELDS = [
        'almacen', 'nombre', 'link', 'token', 'tipo', 'dispositivos',
        'ultimopago', 'proximopago', 'precio', 'encargado', 'telefono',
        'email', 'direccion', 'usuario', 'identificadordb', 'role_key',
        'equipos_no_autorizados',
    ];

    public function __construct(private PDO $db, private LogService $logs)
    {
    }

    public function all(string $projectUid): array
    {
        $stmt = $this->db->prepare('SELECT * FROM licenses WHERE project_uid = ? ORDER BY id DESC');
        $stmt->execute([$projectUid]);
        return $stmt->fetchAll();
    }

    public function allGlobal(): array
    {
        return $this->db->query(
            'SELECT l.*, p.name AS project_name
             FROM licenses l
             JOIN projects p ON p.uid = l.project_uid
             ORDER BY l.id DESC'
        )->fetchAll();
    }

    public function find(string $uid): array
    {
        $stmt = $this->db->prepare('SELECT * FROM licenses WHERE uid = ? LIMIT 1');
        $stmt->execute([$uid]);
        return $stmt->fetch() ?: throw new RuntimeException('License not found.');
    }

    public function findByKey(string $licenseKey): array
    {
        $stmt = $this->db->prepare(
            'SELECT l.*, p.name AS project_name FROM licenses l JOIN projects p ON p.uid = l.project_uid WHERE l.license_key = ? LIMIT 1'
        );
        $stmt->execute([$licenseKey]);
        return $stmt->fetch() ?: throw new RuntimeException('License not found.');
    }

    public function create(string $projectUid, array $data): array
    {
        $systemName = trim((string) ($data['system_name'] ?? ''));
        if ($systemName === '') {
            throw new \InvalidArgumentException('System name is required.');
        }

        $uid = Support::uid('lic_');
        $licenseKey = trim((string) ($data['license_key'] ?? ''));
        if ($licenseKey === '') {
            $raw = strtoupper(bin2hex(random_bytes(8)));
            $licenseKey = substr($raw, 0, 5) . '-' . substr($raw, 5, 5) . '-' . substr($raw, 10, 5);
        }
        $maxUses = max(0, (int) ($data['max_uses'] ?? 0));
        $expiresAt = trim((string) ($data['expires_at'] ?? '')) ?: null;
        $metadata = isset($data['metadata']) ? (is_string($data['metadata']) ? $data['metadata'] : Support::json($data['metadata'])) : null;
        $now = Support::now();

        $extra = [];
        foreach (self::EXTRA_FIELDS as $f) {
            $extra[$f] = (string) ($data[$f] ?? '');
        }

        $cols = ['uid', 'project_uid', 'system_name', 'license_key', 'status', 'max_uses', 'current_uses', 'expires_at', 'metadata', 'project_url', 'public_key', 'secret_key', ...self::EXTRA_FIELDS, 'created_at', 'updated_at'];
        $vals = [$uid, $projectUid, $systemName, $licenseKey, 'active', $maxUses, 0, $expiresAt, $metadata, (string) ($data['project_url'] ?? ''), (string) ($data['public_key'] ?? ''), (string) ($data['secret_key'] ?? ''), ...array_values($extra), $now, $now];
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));

        $stmt = $this->db->prepare("INSERT INTO licenses (" . implode(', ', $cols) . ") VALUES ($placeholders)");
        $stmt->execute($vals);

        $this->logs->write('license.created', $projectUid, null, $uid, null, [
            'system_name' => $systemName, 'license_key' => substr($licenseKey, 0, 8) . '...',
        ]);

        return $this->find($uid);
    }

    public function update(string $uid, array $data): array
    {
        $license = $this->find($uid);
        $fields = ['system_name', 'max_uses', 'expires_at', 'metadata', ...self::EXTRA_FIELDS];
        $set = [];
        $params = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $val = $data[$field];
                if ($field === 'max_uses') $val = max(0, (int) $val);
                if ($field === 'expires_at') $val = trim((string) $val) ?: null;
                if ($field === 'metadata') $val = is_string($val) ? $val : Support::json($val);
                $set[] = "$field = ?";
                $params[] = $val;
            }
        }
        if (!$set) return $license;
        $set[] = 'updated_at = ?';
        $params[] = Support::now();
        $params[] = $uid;
        $this->db->prepare('UPDATE licenses SET ' . implode(', ', $set) . ' WHERE uid = ?')->execute($params);
        $this->logs->write('license.updated', $license['project_uid'], null, $uid);
        return $this->find($uid);
    }

    public function setStatus(string $uid, string $status): array
    {
        if (!in_array($status, ['active', 'blocked', 'expired'], true)) {
            throw new \InvalidArgumentException('Invalid status. Use: active, blocked, expired.');
        }
        $license = $this->find($uid);
        $stmt = $this->db->prepare('UPDATE licenses SET status = ?, updated_at = ? WHERE uid = ?');
        $stmt->execute([$status, Support::now(), $uid]);
        $this->logs->write('license.' . $status, $license['project_uid'], null, $uid);
        return $this->find($uid);
    }

    public function delete(string $uid): void
    {
        $license = $this->find($uid);
        $stmt = $this->db->prepare('DELETE FROM licenses WHERE uid = ?');
        $stmt->execute([$uid]);
        $this->logs->write('license.deleted', $license['project_uid'], null, $uid);
    }

    public function validate(string $licenseKey, ?string $systemName = null): array
    {
        $stmt = $this->db->prepare('SELECT l.*, p.name AS project_name, p.status AS project_status FROM licenses l JOIN projects p ON p.uid = l.project_uid WHERE l.license_key = ? LIMIT 1');
        $stmt->execute([$licenseKey]);
        $license = $stmt->fetch();

        if (!$license) {
            return ['valid' => false, 'error' => 'License key not found.'];
        }

        if ($license['status'] === 'blocked') {
            return ['valid' => false, 'error' => 'License is blocked.', 'license' => $license];
        }

        if ($license['status'] === 'expired') {
            return ['valid' => false, 'error' => 'License has expired.', 'license' => $license];
        }

        if ($license['expires_at'] && $license['expires_at'] < Support::now()) {
            $this->setStatus($license['uid'], 'expired');
            return ['valid' => false, 'error' => 'License has expired.', 'license' => $this->find($license['uid'])];
        }

        if ($license['max_uses'] > 0 && $license['current_uses'] >= $license['max_uses']) {
            return ['valid' => false, 'error' => 'License usage limit reached.', 'license' => $license];
        }

        if ($systemName && $license['system_name'] !== $systemName) {
            return ['valid' => false, 'error' => 'License is not valid for this system.', 'license' => $license];
        }

        return ['valid' => true, 'license' => $license, 'project_url' => $license['project_url'], 'public_key' => $license['public_key'], 'secret_key' => $license['secret_key']];
    }

    public function use(string $licenseKey): array
    {
        $result = $this->validate($licenseKey);
        if (!$result['valid']) {
            return $result;
        }
        $stmt = $this->db->prepare('UPDATE licenses SET current_uses = current_uses + 1, updated_at = ? WHERE license_key = ?');
        $stmt->execute([Support::now(), $licenseKey]);
        $result['license'] = $this->find($result['license']['uid']);
        return $result;
    }

    public function registerDevice(string $licenseKey, string $deviceId): array
    {
        $license = $this->findByKey($licenseKey);

        if ($license['status'] !== 'active') {
            return ['success' => false, 'error' => 'License is not active.'];
        }
        if ($license['expires_at'] && $license['expires_at'] < Support::now()) {
            return ['success' => false, 'error' => 'License has expired.'];
        }

        $devices = $license['dispositivos'] ? (json_decode($license['dispositivos'], true) ?? []) : [];
        $blocked = $license['equipos_no_autorizados'] ? (json_decode($license['equipos_no_autorizados'], true) ?? []) : [];
        $maxDevices = max(0, (int) $license['max_uses']);

        if (in_array($deviceId, $blocked, true)) {
            return ['success' => false, 'error' => 'Device is not authorized.', 'device_blocked' => true];
        }

        if (in_array($deviceId, $devices, true)) {
            return ['success' => true, 'device_registered' => true, 'device_id' => $deviceId];
        }

        if ($maxDevices > 0 && count($devices) >= $maxDevices) {
            $blocked[] = $deviceId;
            $stmt = $this->db->prepare('UPDATE licenses SET equipos_no_autorizados = ?, updated_at = ? WHERE license_key = ?');
            $stmt->execute([json_encode($blocked), Support::now(), $licenseKey]);
            $this->logs->write('license.device_blocked', $license['project_uid'], null, $license['uid'], null, ['device_id' => $deviceId, 'reason' => 'max_devices_reached']);
            return ['success' => false, 'error' => 'Maximum devices reached. Device has been blocked.', 'device_blocked' => true];
        }

        $devices[] = $deviceId;
        $stmt = $this->db->prepare('UPDATE licenses SET dispositivos = ?, current_uses = current_uses + 1, updated_at = ? WHERE license_key = ?');
        $stmt->execute([json_encode($devices), Support::now(), $licenseKey]);
        $this->logs->write('license.device_registered', $license['project_uid'], null, $license['uid'], null, ['device_id' => $deviceId]);

        $updated = $this->find($license['uid']);
        return [
            'success' => true,
            'device_registered' => true,
            'device_id' => $deviceId,
            'devices_count' => count($devices),
            'max_devices' => $maxDevices,
            'license' => $updated,
        ];
    }

    public function authorizeDevice(string $uid, string $deviceId): array
    {
        $license = $this->find($uid);
        $blocked = $license['equipos_no_autorizados'] ? (json_decode($license['equipos_no_autorizados'], true) ?? []) : [];
        $devices = $license['dispositivos'] ? (json_decode($license['dispositivos'], true) ?? []) : [];

        $blocked = array_values(array_filter($blocked, fn ($d) => $d !== $deviceId));
        if (!in_array($deviceId, $devices, true)) {
            $devices[] = $deviceId;
        }

        $stmt = $this->db->prepare('UPDATE licenses SET dispositivos = ?, equipos_no_autorizados = ?, updated_at = ? WHERE uid = ?');
        $stmt->execute([json_encode($devices), json_encode($blocked), Support::now(), $uid]);
        $this->logs->write('license.device_authorized', $license['project_uid'], null, $uid, null, ['device_id' => $deviceId]);
        return $this->find($uid);
    }

    public function blockDevice(string $uid, string $deviceId): array
    {
        $license = $this->find($uid);
        $devices = $license['dispositivos'] ? (json_decode($license['dispositivos'], true) ?? []) : [];
        $blocked = $license['equipos_no_autorizados'] ? (json_decode($license['equipos_no_autorizados'], true) ?? []) : [];

        $devices = array_values(array_filter($devices, fn ($d) => $d !== $deviceId));
        if (!in_array($deviceId, $blocked, true)) {
            $blocked[] = $deviceId;
        }

        $stmt = $this->db->prepare('UPDATE licenses SET dispositivos = ?, equipos_no_autorizados = ?, updated_at = ? WHERE uid = ?');
        $stmt->execute([json_encode($devices), json_encode($blocked), Support::now(), $uid]);
        $this->logs->write('license.device_blocked', $license['project_uid'], null, $uid, null, ['device_id' => $deviceId]);
        return $this->find($uid);
    }

    public function resetUses(string $uid): array
    {
        $license = $this->find($uid);
        $stmt = $this->db->prepare('UPDATE licenses SET current_uses = 0, updated_at = ? WHERE uid = ?');
        $stmt->execute([Support::now(), $uid]);
        $this->logs->write('license.uses_reset', $license['project_uid'], null, $uid);
        return $this->find($uid);
    }
}
