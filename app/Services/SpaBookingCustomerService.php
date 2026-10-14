<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

/** Save the public booking and its customer together under the project write lock. */
final class SpaBookingCustomerService
{
    public function create(array $project, array $data, RecordService $records, LogService $logs, WebhookService $webhooks): array
    {
        $db = Database::connect($project['database_path']);
        $created = null;
        $db->exec('BEGIN IMMEDIATE');
        try {
            $db->exec('CREATE TABLE IF NOT EXISTS clientes (id INTEGER PRIMARY KEY AUTOINCREMENT, uid TEXT UNIQUE NOT NULL, nombre TEXT, telefono TEXT, tipo_cliente TEXT, almacen_id INTEGER, almacen_uid TEXT, created_at TEXT, updated_at TEXT)');
            $columns = array_column($db->query('PRAGMA table_info(clientes)')->fetchAll(PDO::FETCH_ASSOC), 'name');
            foreach (['nombre'=>'TEXT','telefono'=>'TEXT','tipo_cliente'=>'TEXT','almacen_id'=>'INTEGER','almacen_uid'=>'TEXT'] as $column=>$type) {
                if (!in_array($column, $columns, true)) $db->exec('ALTER TABLE clientes ADD COLUMN "' . $column . '" ' . $type);
            }
            $normalizeName = static fn ($value) => preg_replace('/\s+/u', ' ', mb_strtoupper(trim((string) $value), 'UTF-8'));
            $normalizePhone = static function ($value): string {
                $digits = preg_replace('/\D+/', '', (string) $value);
                return strlen($digits) === 10 ? '1' . $digits : $digits;
            };
            $matches = [];
            foreach ($db->query('SELECT * FROM clientes') as $row) {
                if ($normalizeName($row['nombre']) === $normalizeName($data['nombre_cliente'])
                    && $normalizePhone($row['telefono']) === $normalizePhone($data['telefono'])) $matches[] = $row;
            }
            if (count($matches) > 1) throw new RuntimeException('Hay clientes duplicados con ese nombre y telefono. Contacte al establecimiento para reservar.');
            if (!$matches) {
                $created = $records->create($project, 'clientes', [
                    'nombre' => $normalizeName($data['nombre_cliente']), 'telefono' => $normalizePhone($data['telefono']),
                    'tipo_cliente' => 'NORMAL', 'almacen_id' => (int) ($data['almacen_id'] ?? 0),
                    'almacen_uid' => (string) ($data['almacen_uid'] ?? ''),
                ], false);
            }
            $appointment = $records->create($project, 'citas_spa', $data, false);
            $db->exec('COMMIT');
        } catch (\Throwable $error) {
            $db->exec('ROLLBACK');
            throw $error;
        }
        // Only committed records are announced; notification failures do not undo a booking.
        foreach (['clientes' => $created, 'citas_spa' => $appointment] as $table => $row) {
            if (!$row) continue;
            try { $logs->write('record.created', $project['uid'], $table, $row['uid'], null, $row); } catch (\Throwable) {}
        }
        if ($created) {
            try { $webhooks->dispatch('record.created', $project, 'clientes', $created); } catch (\Throwable) {}
        }
        return $appointment;
    }
}
