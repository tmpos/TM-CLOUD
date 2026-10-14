<?php
declare(strict_types=1);
namespace App\Services;

use PDO;
use RuntimeException;

/** SQLite triggers serialize professional reservations even through generic record writes. */
final class SalonScheduleGuard
{
    public static function install(PDO $db): void
    {
        $fields = array_column($db->query('PRAGMA table_info(citas_spa)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (array_diff(['uid','nota','hora','fecha','estado','almacen_id','almacen_uid'], $fields)) return;
        $json = "CASE WHEN json_valid(NEW.nota) THEN NEW.nota ELSE '{}' END";
        $other = "CASE WHEN json_valid(a.nota) THEN a.nota ELSE '{}' END";
        $enabled = "COALESCE(NEW.estado,'PENDIENTE')<>'CANCELADA' AND json_type($json,'$.tmpos_salon')='object'";
        $checks = <<<SQL
SELECT CASE WHEN COALESCE(json_extract($json,'$.tmpos_salon.v'),0)<>1
 OR COALESCE(json_extract($json,'$.tmpos_salon.s'),'')=''
 OR COALESCE(CAST(json_extract($json,'$.tmpos_salon.u') AS INTEGER),0)<1
 OR COALESCE(NEW.hora,'') NOT GLOB '[0-2][0-9]:[0-5][0-9]'
 OR NEW.hora>'23:59'
 OR COALESCE(json_extract($json,'$.tmpos_salon.e'),'') NOT GLOB '[0-2][0-9]:[0-5][0-9]'
 OR json_extract($json,'$.tmpos_salon.e')>'23:59'
 OR NEW.hora>=json_extract($json,'$.tmpos_salon.e')
 THEN RAISE(ABORT,'Reserva de salon invalida: revise profesional y duracion.') END;
SELECT CASE WHEN EXISTS (
 SELECT 1 FROM citas_spa a WHERE a.uid<>NEW.uid AND a.fecha=NEW.fecha
 AND COALESCE(a.estado,'PENDIENTE')<>'CANCELADA'
 AND CAST(json_extract($other,'$.tmpos_salon.u') AS INTEGER)=CAST(json_extract($json,'$.tmpos_salon.u') AS INTEGER)
 AND a.hora<json_extract($json,'$.tmpos_salon.e')
 AND json_extract($other,'$.tmpos_salon.e')>NEW.hora
) THEN RAISE(ABORT,'El profesional ya tiene una cita en ese horario. Actualice la agenda.') END;
SQL;
        $hasConfig = $db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='configuracion'")->fetchColumn();
        if ($hasConfig) {
            $checks .= <<<SQL

SELECT CASE WHEN EXISTS (
 SELECT 1 FROM configuracion c, json_each(CASE WHEN json_valid(c.valor) THEN c.valor ELSE '{}' END,'$.breaks') b
 JOIN configuracion staff ON staff.clave='salon_staff_v1_' || substr(c.clave,19)
 JOIN json_each(CASE WHEN json_valid(staff.valor) THEN staff.valor ELSE '[]' END) person
 WHERE substr(c.clave,1,18)='salon_planning_v1_'
 AND json_extract(b.value,'$.stylistUid')=json_extract(person.value,'$.uid')
 AND CAST(json_extract(person.value,'$.userId') AS INTEGER)=CAST(json_extract($json,'$.tmpos_salon.u') AS INTEGER)
 AND json_extract(b.value,'$.date')=NEW.fecha
 AND json_extract(b.value,'$.start')<json_extract($json,'$.tmpos_salon.e')
 AND json_extract(b.value,'$.end')>NEW.hora
) THEN RAISE(ABORT,'El profesional tiene un descanso en ese horario.') END;
SQL;
        }
        foreach (['INSERT','UPDATE'] as $event) {
            $name = 'salon_professional_v1_' . strtolower($event) . ($hasConfig ? '_with_breaks' : '');
            // Versioned creation never leaves a concurrent write without its guard.
            $db->exec("CREATE TRIGGER IF NOT EXISTS $name BEFORE $event ON citas_spa WHEN $enabled BEGIN $checks END");
        }
    }

    public static function time(array $input, array $settings, array $availability): ?string
    {
        $meta=json_decode((string)($input['nota']??''),true)['tmpos_salon']??null;
        if (!$meta) return null;
        $start=(string)($input['hora']??''); $end=(string)($meta['e']??'');
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D',$start) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D',$end) || $start >= $end) throw new RuntimeException('Indique inicio y fin validos para el servicio.');
        $now=new \DateTimeImmutable('now',new \DateTimeZone($settings['timezone']));
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',(string)($input['fecha']??''));
        if (!$date || $date->format('Y-m-d')!==($input['fecha']??'') || !$settings['enabled'] || $input['fecha']<$now->format('Y-m-d')) throw new RuntimeException('Seleccione una fecha disponible.');
        foreach ($availability['shifts'] as $shift) {
            if ($shift['id'] !== ($input['turno']??'')) continue;
            if ($start<$shift['start'] || $end>$shift['end']) throw new RuntimeException('El servicio debe terminar dentro del turno.');
            if (!in_array((int)$date->format('w'),$settings['days'],true) || ($input['fecha']===$now->format('Y-m-d') && $start<=$now->format('H:i'))) throw new RuntimeException('Este horario ya no esta disponible.');
            return $start;
        }
        throw new RuntimeException('Seleccione un turno disponible.');
    }
}
