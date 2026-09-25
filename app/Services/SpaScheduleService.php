<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/** Shared project-wide capacity for the public links, landing and cloud writes. */
final class SpaScheduleService
{
    public function defaults(): array
    {
        return ['version' => 1, 'enabled' => false, 'timezone' => 'America/Santo_Domingo',
            'days' => [1, 2, 3, 4, 5, 6], 'shifts' => [
                ['id' => 'turno_1', 'label' => '09:00–12:00', 'start' => '09:00', 'end' => '12:00', 'capacity' => 2],
                ['id' => 'turno_2', 'label' => '14:00–18:00', 'start' => '14:00', 'end' => '18:00', 'capacity' => 3],
            ]];
    }

    private function connection(array $project): PDO
    {
        $db = Database::connect($project['database_path']);
        $db->exec('CREATE TABLE IF NOT EXISTS _spa_schedule (id INTEGER PRIMARY KEY CHECK(id=1), settings TEXT NOT NULL)');
        return $db;
    }

    public function get(array $project): array
    {
        $json = $this->connection($project)->query('SELECT settings FROM _spa_schedule WHERE id=1')->fetchColumn();
        $settings = $json ? json_decode($json, true, 512, JSON_THROW_ON_ERROR) : $this->defaults();
        // Preserve identifiers in existing links while presenting only time ranges.
        foreach ($settings['shifts'] as &$shift) $shift['label'] = $shift['start'] . '–' . $shift['end'];
        unset($shift);
        usort($settings['shifts'], static fn ($a, $b) => strcmp($a['start'], $b['start']));
        return $settings;
    }

    public function save(array $project, array $input): array
    {
        $settings = $this->defaults();
        $settings['enabled'] = ($input['enabled'] ?? false) === true;
        $timezone = (string) ($input['timezone'] ?? '');
        if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) throw new InvalidArgumentException('Seleccione una zona horaria válida.');
        $settings['timezone'] = $timezone;
        $days = $input['days'] ?? [];
        if (!is_array($days) || !$days || count($days) !== count(array_unique($days))) throw new InvalidArgumentException('Seleccione los días de atención.');
        foreach ($days as $day) if (!is_int($day) || $day < 0 || $day > 6) throw new InvalidArgumentException('Día de atención inválido.');
        $settings['days'] = array_values($days);
        $shifts = $input['shifts'] ?? [];
        if (!is_array($shifts) || !array_is_list($shifts) || !$shifts) throw new InvalidArgumentException('Agregue al menos un turno.');
        $settings['shifts'] = [];
        $ids = [];
        foreach ($shifts as $value) {
            if (!is_array($value)) throw new InvalidArgumentException('Turno inválido.');
            $id = (string) ($value['id'] ?? '');
            if (!preg_match('/^[A-Za-z0-9_-]{1,80}$/D', $id) || isset($ids[$id])) throw new InvalidArgumentException('Cada turno debe tener un identificador único válido.');
            $ids[$id] = true;
            $shift = ['id' => $id];
            foreach (['start', 'end'] as $key) {
                if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', (string) ($value[$key] ?? ''))) throw new InvalidArgumentException('Indique horas válidas.');
                $shift[$key] = $value[$key];
            }
            if ($shift['start'] >= $shift['end']) throw new InvalidArgumentException('El inicio debe ser anterior al final del turno.');
            $capacity = $value['capacity'] ?? null;
            if (!is_int($capacity) || $capacity < 1 || $capacity > 100) throw new InvalidArgumentException('Los cupos deben ser un entero entre 1 y 100.');
            $shift['capacity'] = $capacity;
            $shift['label'] = $shift['start'] . '–' . $shift['end'];
            $settings['shifts'][] = $shift;
        }
        usort($settings['shifts'], static fn ($a, $b) => strcmp($a['start'], $b['start']));
        for ($index = 1; $index < count($settings['shifts']); $index++) {
            if ($settings['shifts'][$index - 1]['end'] > $settings['shifts'][$index]['start']) throw new InvalidArgumentException('Los horarios de los turnos no pueden superponerse.');
        }

        $db = $this->connection($project);
        // Same SQLite write lock as appointment INSERT/UPDATE; settings and guards change together.
        $db->exec('BEGIN IMMEDIATE');
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS citas_spa (id INTEGER PRIMARY KEY AUTOINCREMENT, uid TEXT UNIQUE NOT NULL, nombre_cliente TEXT, telefono TEXT, fecha TEXT, hora TEXT, servicio TEXT, nota TEXT, estado TEXT, origen TEXT, almacen_id INTEGER, almacen_uid TEXT, created_at TEXT, updated_at TEXT)");
            $db->prepare('INSERT INTO _spa_schedule(id,settings) VALUES(1,?) ON CONFLICT(id) DO UPDATE SET settings=excluded.settings')
                ->execute([json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
            $this->installGuards($db);
            if ($settings['enabled']) {
                $now = new DateTimeImmutable('now', new DateTimeZone($timezone));
                $today = $now->format('Y-m-d');
                $rows = $db->prepare("SELECT fecha,hora FROM citas_spa WHERE (fecha>? OR (fecha=? AND hora>=?)) AND COALESCE(estado,'PENDIENTE')<>'CANCELADA'");
                $rows->execute([$today, $today, $now->format('H:i')]);
                $counts = [];
                foreach ($rows->fetchAll() as $row) {
                    $date = $this->date((string) $row['fecha'], $settings);
                    $match = null;
                    foreach ($settings['shifts'] as $shift) if ($row['hora'] >= $shift['start'] && $row['hora'] < $shift['end']) $match = $shift;
                    if (!$match || !in_array((int) $date->format('w'), $days, true)) throw new InvalidArgumentException('Hay citas futuras fuera de estos horarios. Reprográmelas antes de guardar.');
                    $key = $row['fecha'] . ':' . $match['id'];
                    $counts[$key] = ($counts[$key] ?? 0) + 1;
                    if ($counts[$key] > $match['capacity']) throw new InvalidArgumentException('Los cupos no cubren las citas existentes del ' . $row['fecha'] . '.');
                }
            }
            $db->exec('COMMIT');
        } catch (\Throwable $e) { $db->exec('ROLLBACK'); throw $e; }
        return $settings;
    }

    private function installGuards(PDO $db): void
    {
        // SQLite serializes writers: counting and insertion happen in the same statement,
        // including generic APIs, synchronization and browser runtime writes.
        $checks = <<<'SQL'
SELECT CASE WHEN NOT EXISTS (
 SELECT 1 FROM _spa_schedule c, json_each(c.settings,'$.days') d
 WHERE CAST(d.value AS INTEGER)=CAST(strftime('%w',NEW.fecha) AS INTEGER)
) THEN RAISE(ABORT,'El spa no atiende en esa fecha.') END;
SELECT CASE WHEN NOT EXISTS (
 SELECT 1 FROM _spa_schedule c, json_each(c.settings,'$.shifts') s
 WHERE NEW.hora>=json_extract(s.value,'$.start') AND NEW.hora<json_extract(s.value,'$.end')
) THEN RAISE(ABORT,'Seleccione un horario de atención disponible.') END;
SELECT CASE WHEN EXISTS (
 SELECT 1 FROM _spa_schedule c, json_each(c.settings,'$.shifts') s
 WHERE NEW.hora>=json_extract(s.value,'$.start') AND NEW.hora<json_extract(s.value,'$.end')
 AND (SELECT COUNT(*) FROM citas_spa a WHERE a.fecha=NEW.fecha
      AND COALESCE(a.estado,'PENDIENTE')<>'CANCELADA' AND a.uid<>NEW.uid
      AND a.hora>=json_extract(s.value,'$.start') AND a.hora<json_extract(s.value,'$.end'))>=json_extract(s.value,'$.capacity')
) THEN RAISE(ABORT,'Este turno ya no tiene cupos disponibles. Seleccione otro turno o fecha.') END;
SQL;
        $enabled = "COALESCE((SELECT json_extract(settings,'$.enabled') FROM _spa_schedule WHERE id=1),0)=1 AND COALESCE(NEW.estado,'PENDIENTE')<>'CANCELADA'";
        $db->exec('DROP TRIGGER IF EXISTS spa_capacity_insert');
        $db->exec('DROP TRIGGER IF EXISTS spa_capacity_update');
        $db->exec("CREATE TRIGGER spa_capacity_insert BEFORE INSERT ON citas_spa WHEN $enabled BEGIN $checks END");
        $db->exec("CREATE TRIGGER spa_capacity_update BEFORE UPDATE ON citas_spa WHEN $enabled AND (NEW.fecha IS NOT OLD.fecha OR NEW.hora IS NOT OLD.hora OR OLD.estado='CANCELADA') BEGIN $checks END");
    }

    private function date(string $value, array $settings): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone($settings['timezone']));
        if (!$date || $date->format('Y-m-d') !== $value) throw new InvalidArgumentException('Indique una fecha válida.');
        return $date;
    }

    public function availability(array $project, string $date, string $excludeUid = ''): array
    {
        $settings = $this->get($project);
        $day = $this->date($date, $settings);
        $now = new DateTimeImmutable('now', new DateTimeZone($settings['timezone']));
        $result = ['enabled' => $settings['enabled'], 'date' => $date, 'today' => $now->format('Y-m-d'), 'timezone' => $settings['timezone'], 'shifts' => []];
        if (!$settings['enabled']) return $result;
        $db = $this->connection($project);
        foreach ($settings['shifts'] as $shift) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM citas_spa WHERE fecha=? AND hora>=? AND hora<? AND COALESCE(estado,'PENDIENTE')<>'CANCELADA' AND uid<>?");
            $stmt->execute([$date, $shift['start'], $shift['end'], $excludeUid]);
            $remaining = max(0, $shift['capacity'] - (int) $stmt->fetchColumn());
            $reason = '';
            if ($date < $now->format('Y-m-d')) $reason = 'Fecha pasada';
            elseif (!in_array((int) $day->format('w'), $settings['days'], true)) $reason = 'Cerrado';
            elseif ($date === $now->format('Y-m-d') && $shift['start'] <= $now->format('H:i')) $reason = 'Turno iniciado';
            elseif (!$remaining) $reason = 'Sin cupos';
            $result['shifts'][] = [...$shift, 'remaining' => $remaining, 'available' => $reason === '', 'reason' => $reason];
        }
        return $result;
    }

    /** Never trust submitted hours when shifts are enabled. */
    public function bookingTime(array $project, array $input): string
    {
        $settings = $this->get($project);
        $date = (string) ($input['fecha'] ?? '');
        $this->date($date, $settings);
        $now = new DateTimeImmutable('now', new DateTimeZone($settings['timezone']));
        if ($date < $now->format('Y-m-d')) throw new InvalidArgumentException('La fecha debe ser hoy o una fecha futura.');
        if (!$settings['enabled']) {
            $time = (string) ($input['hora'] ?? '');
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', $time)) throw new InvalidArgumentException('Indique una hora válida.');
            return $time;
        }
        foreach ($this->availability($project, $date)['shifts'] as $shift) {
            if ($shift['id'] !== ($input['turno'] ?? '')) continue;
            if (!$shift['available']) throw new RuntimeException('El turno seleccionado no está disponible. Elija otro turno o fecha.', 409);
            return $shift['start'];
        }
        throw new InvalidArgumentException('Seleccione un turno disponible.');
    }

    public function handle(array $project, string $operation, array $input, RecordService $records, WebhookService $webhooks): mixed
    {
        if ($operation === 'get') return $this->get($project);
        if ($operation === 'save') return $this->save($project, $input);
        if ($operation === 'availability') return $this->availability($project, (string) ($input['fecha'] ?? ''), (string) ($input['exclude_uid'] ?? ''));
        if ($operation === 'appointments') return $records->all($project, 'citas_spa');
        $uid = trim((string) ($input['uid'] ?? ''));
        $old = $uid !== '' ? $records->find($project, 'citas_spa', $uid) : null;
        if ($operation === 'appointment-delete') {
            if (!$old) throw new InvalidArgumentException('La cita no existe.');
            $records->delete($project, 'citas_spa', $uid);
            try { $webhooks->dispatch('record.deleted', $project, 'citas_spa', $old); } catch (\Throwable) {}
            return ['deleted' => true];
        }
        if ($operation !== 'appointment-save') throw new InvalidArgumentException('Operación de spa no disponible.');
        $candidate = [];
        foreach (['nombre_cliente' => 160, 'telefono' => 30, 'servicio' => 160, 'nota' => 500] as $field => $max) {
            $candidate[$field] = trim((string) ($input[$field] ?? $old[$field] ?? ''));
            if (mb_strlen($candidate[$field]) > $max) throw new InvalidArgumentException('El campo ' . $field . ' es demasiado largo.');
        }
        if ($candidate['nombre_cliente'] === '') throw new InvalidArgumentException('Indique el nombre del cliente.');
        $candidate['estado'] = (string) ($input['estado'] ?? $old['estado'] ?? 'PENDIENTE');
        if (!in_array($candidate['estado'], ['PENDIENTE','CONFIRMADA','COMPLETADA','CANCELADA'], true)) throw new InvalidArgumentException('Estado inválido.');
        $candidate['fecha'] = (string) ($input['fecha'] ?? $old['fecha'] ?? '');
        $candidate['hora'] = (string) ($input['hora'] ?? $old['hora'] ?? '');
        $settings = $this->get($project);
        $this->date($candidate['fecha'], $settings);
        $sameSlot = $old && $old['fecha'] === $candidate['fecha'] && $old['hora'] === $candidate['hora'];
        if ($settings['enabled'] && !empty($input['turno'])) {
            $selected = null;
            foreach ($settings['shifts'] as $shift) if ($shift['id'] === $input['turno']) $selected = $shift;
            if (!$selected) throw new InvalidArgumentException('Seleccione un turno válido.');
            $sameSlot = $old && $old['fecha'] === $candidate['fecha'] && $old['hora'] >= $selected['start'] && $old['hora'] < $selected['end'];
            $candidate['hora'] = $sameSlot ? $old['hora'] : $selected['start'];
        }
        if ($candidate['estado'] !== 'CANCELADA' && (!$sameSlot || ($old['estado'] ?? '') === 'CANCELADA')) {
            $candidate['hora'] = $this->bookingTime($project, [...$input, 'fecha' => $candidate['fecha'], 'hora' => $candidate['hora']]);
        }
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/D', $candidate['hora'])) throw new InvalidArgumentException('Hora inválida.');
        if ($old) $row = $records->update($project, 'citas_spa', $uid, $candidate);
        else $row = $records->create($project, 'citas_spa', [...$candidate, 'origen' => 'MANUAL', 'almacen_id' => (int) ($input['almacen_id'] ?? 0), 'almacen_uid' => (string) ($input['almacen_uid'] ?? '')]);
        try { $webhooks->dispatch($old ? 'record.updated' : 'record.created', $project, 'citas_spa', $row); } catch (\Throwable) {}
        return $row;
    }
}
