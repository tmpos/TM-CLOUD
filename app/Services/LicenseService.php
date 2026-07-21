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
        'email', 'direccion', 'rnc', 'usuario', 'identificadordb', 'role_key',
        'equipos_no_autorizados',
    ];

    public function __construct(private PDO $db, private LogService $logs)
    {
    }

    public function all(string $projectUid): array
    {
        $stmt = $this->db->prepare(
            'SELECT l.*, p.name AS project_name, p.public_key AS public_key, p.secret_key AS secret_key
             FROM licenses l JOIN projects p ON p.uid = l.project_uid
             WHERE l.project_uid = ? ORDER BY l.id DESC'
        );
        $stmt->execute([$projectUid]);
        return $stmt->fetchAll();
    }

    public function allGlobal(): array
    {
        return $this->db->query(
            'SELECT l.*, p.name AS project_name, p.public_key AS public_key, p.secret_key AS secret_key
             FROM licenses l
             JOIN projects p ON p.uid = l.project_uid
             ORDER BY l.id DESC'
        )->fetchAll();
    }

    public function find(string $uid): array
    {
        $stmt = $this->db->prepare(
            'SELECT l.*, p.name AS project_name, p.public_key AS public_key, p.secret_key AS secret_key
             FROM licenses l JOIN projects p ON p.uid = l.project_uid WHERE l.uid = ? LIMIT 1'
        );
        $stmt->execute([$uid]);
        return $stmt->fetch() ?: throw new RuntimeException('License not found.');
    }

    public function findForProject(string $uid, string $projectUid): array
    {
        $stmt = $this->db->prepare(
            'SELECT l.*, p.name AS project_name, p.public_key AS public_key, p.secret_key AS secret_key
             FROM licenses l JOIN projects p ON p.uid = l.project_uid
             WHERE l.uid = ? AND l.project_uid = ? LIMIT 1'
        );
        $stmt->execute([$uid, $projectUid]);
        return $stmt->fetch() ?: throw new RuntimeException('License not found.');
    }

    public function findByKey(string $licenseKey): array
    {
        $stmt = $this->db->prepare(
            'SELECT l.*, p.name AS project_name, p.public_key AS public_key, p.secret_key AS secret_key
             FROM licenses l JOIN projects p ON p.uid = l.project_uid WHERE l.license_key = ? LIMIT 1'
        );
        $stmt->execute([$licenseKey]);
        return $stmt->fetch() ?: throw new RuntimeException('License not found.');
    }

    public function apiData(array $license, bool $includeLicenseKey = false): array
    {
        $allowed = [
            'uid', 'project_uid', 'project_name', 'system_name', 'status', 'max_uses',
            'current_uses', 'expires_at', 'project_url', 'public_key', 'nombre', 'link',
            'tipo', 'proximopago', 'telefono', 'email', 'direccion', 'rnc',
            'dispositivos', 'equipos_no_autorizados',
            'created_at', 'updated_at',
        ];
        if ($includeLicenseKey) {
            $allowed[] = 'license_key';
        }

        $safe = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $license)) {
                $safe[$field] = $license[$field];
            }
        }
        $safe['authorized_devices'] = $license['dispositivos']
            ? (json_decode((string) $license['dispositivos'], true) ?? [])
            : [];
        $safe['unauthorized_devices'] = $license['equipos_no_autorizados']
            ? (json_decode((string) $license['equipos_no_autorizados'], true) ?? [])
            : [];
        unset($safe['dispositivos'], $safe['equipos_no_autorizados']);
        return $safe;
    }

    public function companyData(string $projectUid): array
    {
        foreach ($this->all($projectUid) as $license) {
            $metadata = json_decode((string) ($license['metadata'] ?? ''), true);
            $metadata = is_array($metadata) ? $metadata : [];
            $data = [
                'nombre' => $license['nombre'] ?: ($metadata['nombre'] ?? $metadata['company_name'] ?? $license['project_name'] ?? ''),
                'rnc' => $license['rnc'] ?: ($metadata['rnc'] ?? $metadata['tax_id'] ?? ''),
                'telefono' => $license['telefono'] ?: ($metadata['telefono'] ?? $metadata['phone'] ?? ''),
                'email' => $license['email'] ?: ($metadata['email'] ?? ''),
                'direccion' => $license['direccion'] ?: ($metadata['direccion'] ?? $metadata['address'] ?? ''),
                'logo' => $metadata['logo'] ?? $metadata['company_logo'] ?? '',
                'moneda' => $metadata['moneda'] ?? $metadata['currency'] ?? '',
            ];
            if (array_filter($data, static fn (mixed $value): bool => trim((string) $value) !== '')) {
                return $data;
            }
        }
        return [];
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
        $vals = [$uid, $projectUid, $systemName, $licenseKey, 'active', $maxUses, 0, $expiresAt, $metadata, (string) ($data['project_url'] ?? ''), '', '', ...array_values($extra), $now, $now];
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
        $stmt = $this->db->prepare('SELECT l.*, p.name AS project_name, p.status AS project_status, p.public_key AS public_key, p.secret_key AS secret_key FROM licenses l JOIN projects p ON p.uid = l.project_uid WHERE l.license_key = ? LIMIT 1');
        $stmt->execute([$licenseKey]);
        $license = $stmt->fetch();

        if (!$license) {
            return ['valid' => false, 'error' => 'License key not found.'];
        }

        if ($license['status'] === 'blocked') {
            return ['valid' => false, 'error' => 'License is blocked.', 'license' => $this->apiData($license)];
        }

        if ($license['status'] === 'expired') {
            return ['valid' => false, 'error' => 'License has expired.', 'license' => $this->apiData($license)];
        }

        if ($license['expires_at'] && $license['expires_at'] < Support::now()) {
            $this->setStatus($license['uid'], 'expired');
            return ['valid' => false, 'error' => 'License has expired.', 'license' => $this->apiData($this->find($license['uid']))];
        }

        if ($systemName && $license['system_name'] !== $systemName) {
            return ['valid' => false, 'error' => 'License is not valid for this system.', 'license' => $this->apiData($license)];
        }

        return ['valid' => true, 'license' => $this->apiData($license), 'project_url' => $license['project_url'], 'public_key' => $license['public_key']];
    }

    public function use(string $licenseKey): array
    {
        return $this->validate($licenseKey);
    }

    public function devicesForLicense(string $licenseUid): array
    {
        $stmt = $this->db->prepare('SELECT device_id,status,requested_at,authorized_at,revoked_at,last_seen_at,app_version FROM license_devices WHERE license_uid = ? ORDER BY created_at ASC');
        $stmt->execute([$licenseUid]);
        $rows = $stmt->fetchAll();
        $byStatus = ['pending' => [], 'authorized' => [], 'blocked' => [], 'revoked' => []];
        foreach ($rows as $row) $byStatus[$row['status']][] = $row;
        return [
            'records' => $rows,
            'pending_devices' => array_column($byStatus['pending'], 'device_id'),
            'authorized_devices' => array_column($byStatus['authorized'], 'device_id'),
            'blocked_devices' => array_column($byStatus['blocked'], 'device_id'),
            'revoked_devices' => array_column($byStatus['revoked'], 'device_id'),
        ];
    }

    public function isDeviceAuthorized(string $licenseUid, string $deviceId): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM license_devices WHERE license_uid = ? AND device_id = ? AND status = 'authorized' LIMIT 1");
        $stmt->execute([$licenseUid, strtoupper(trim($deviceId))]);
        return (bool) $stmt->fetchColumn();
    }

    public function registerDevice(string $licenseKey, string $deviceId, array $context = []): array
    {
        $license = $this->findByKey($licenseKey);
        $deviceId = strtoupper(trim($deviceId));

        if ($license['status'] !== 'active') {
            return ['success' => false, 'error' => 'License is not active.'];
        }
        if ($license['expires_at'] && $license['expires_at'] < Support::now()) {
            return ['success' => false, 'error' => 'License has expired.'];
        }

        $maxDevices = max(0, (int) $license['max_uses']);
        $now = Support::now();
        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare('UPDATE licenses SET updated_at = updated_at WHERE uid = ?');
            $lock->execute([$license['uid']]);
            $find = $this->db->prepare('SELECT * FROM license_devices WHERE license_uid = ? AND device_id = ? LIMIT 1');
            $find->execute([$license['uid'], $deviceId]);
            $device = $find->fetch();
            if ($device && $device['status'] === 'blocked') {
                $this->db->commit();
                return ['success' => false, 'error' => 'Device is blocked.', 'device_blocked' => true, 'device_id' => $deviceId];
            }
            if (!$device) {
                $stmt = $this->db->prepare('INSERT INTO license_devices (uid,license_uid,device_id,status,requested_at,last_seen_at,ip_address,app_version,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute([Support::uid('dev_'), $license['uid'], $deviceId, 'pending', $now, $now, $context['ip_address'] ?? null, $context['app_version'] ?? null, $now, $now]);
                $status = 'pending';
            } else {
                $status = $device['status'] === 'revoked' ? 'pending' : $device['status'];
                $stmt = $this->db->prepare('UPDATE license_devices SET status = ?, requested_at = CASE WHEN ? = \'pending\' THEN ? ELSE requested_at END, last_seen_at = ?, ip_address = ?, app_version = COALESCE(?, app_version), updated_at = ? WHERE id = ?');
                $stmt->execute([$status, $status, $now, $now, $context['ip_address'] ?? null, $context['app_version'] ?? null, $now, $device['id']]);
            }
            $this->syncLegacyDevices($license['uid']);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
        $authorized = $status === 'authorized';
        $this->logs->write($authorized ? 'license.device_seen' : 'license.device_pending', $license['project_uid'], null, $license['uid'], null, ['device_id' => $deviceId]);

        return [
            'success' => true,
            'device_registered' => true,
            'authorized' => $authorized,
            'pending_authorization' => !$authorized,
            'device_id' => $deviceId,
            'devices_count' => count($this->devicesForLicense($license['uid'])['authorized_devices']),
            'max_devices' => $maxDevices,
            'license' => $this->find($license['uid']),
        ];
    }

    public function authorizeDevice(string $uid, string $deviceId): array
    {
        $license = $this->find($uid);
        $deviceId = strtoupper(trim($deviceId));
        $now = Support::now();
        $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE licenses SET updated_at = updated_at WHERE uid = ?')->execute([$uid]);
            $check = $this->db->prepare("SELECT COUNT(*) FROM license_devices WHERE license_uid = ? AND status = 'authorized' AND device_id <> ?");
            $check->execute([$uid, $deviceId]);
            $authorizedCount = (int) $check->fetchColumn();
            $maxDevices = max(0, (int) $license['max_uses']);
            if ($maxDevices > 0 && $authorizedCount >= $maxDevices) {
                throw new RuntimeException('License usage limit reached.');
            }
            $stmt = $this->db->prepare("INSERT INTO license_devices (uid,license_uid,device_id,status,requested_at,authorized_at,last_seen_at,created_at,updated_at) VALUES (?,?,?,'authorized',?,?,?,?,?) ON CONFLICT(license_uid,device_id) DO UPDATE SET status='authorized', authorized_at=excluded.authorized_at, revoked_at=NULL, updated_at=excluded.updated_at");
            $stmt->execute([Support::uid('dev_'), $uid, $deviceId, $now, $now, $now, $now, $now]);
            $this->syncLegacyDevices($uid);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
        $this->logs->write('license.device_authorized', $license['project_uid'], null, $uid, null, ['device_id' => $deviceId]);
        return $this->find($uid);
    }

    public function blockDevice(string $uid, string $deviceId): array
    {
        $license = $this->find($uid);
        $this->setDeviceStatus($uid, $deviceId, 'blocked');
        $this->logs->write('license.device_blocked', $license['project_uid'], null, $uid, null, ['device_id' => $deviceId]);
        return $this->find($uid);
    }

    public function revokeDevice(string $uid, string $deviceId): array
    {
        $license = $this->find($uid);
        $this->setDeviceStatus($uid, $deviceId, 'revoked');
        $this->logs->write('license.device_revoked', $license['project_uid'], null, $uid, null, ['device_id' => $deviceId]);
        return $this->find($uid);
    }

    public function resetUses(string $uid): array
    {
        $license = $this->find($uid);
        $now = Support::now();
        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE license_devices SET status = 'revoked', revoked_at = ?, updated_at = ? WHERE license_uid = ? AND status = 'authorized'")->execute([$now, $now, $uid]);
            $this->syncLegacyDevices($uid);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
        $this->logs->write('license.uses_reset', $license['project_uid'], null, $uid);
        return $this->find($uid);
    }

    private function setDeviceStatus(string $uid, string $deviceId, string $status): void
    {
        $deviceId = strtoupper(trim($deviceId));
        $now = Support::now();
        $revokedAt = in_array($status, ['blocked', 'revoked'], true) ? $now : null;
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('INSERT INTO license_devices (uid,license_uid,device_id,status,requested_at,revoked_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?) ON CONFLICT(license_uid,device_id) DO UPDATE SET status=excluded.status, revoked_at=excluded.revoked_at, updated_at=excluded.updated_at');
            $stmt->execute([Support::uid('dev_'), $uid, $deviceId, $status, $now, $revokedAt, $now, $now]);
            $this->syncLegacyDevices($uid);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    private function syncLegacyDevices(string $uid): void
    {
        $groups = $this->devicesForLicense($uid);
        $unauthorized = array_values(array_unique([...$groups['pending_devices'], ...$groups['blocked_devices']]));
        $stmt = $this->db->prepare('UPDATE licenses SET dispositivos = ?, equipos_no_autorizados = ?, current_uses = ?, updated_at = ? WHERE uid = ?');
        $stmt->execute([
            json_encode($groups['authorized_devices']), json_encode($unauthorized),
            count($groups['authorized_devices']), Support::now(), $uid,
        ]);
    }
}
