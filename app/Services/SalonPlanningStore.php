<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use RuntimeException;

final class SalonPlanningStore
{
    public static function save(PDO $db, array $input, bool $admin): array
    {
        if (!$admin) throw new RuntimeException('Solo Administrador o Soporte puede configurar la agenda.',403);
        $warehouse=(array)$input['warehouse'];
        $key='salon_planning_v1_'.($warehouse['uid']?:$warehouse['id']);
        $data=(array)($input['settings']??[]);
        if (!isset($data['services'],$data['breaks']) || !is_array($data['services']) || !is_array($data['breaks']) || count($data['services'])>2000 || count($data['breaks'])>10000) throw new RuntimeException('Planificacion invalida.');
        $db->exec('BEGIN IMMEDIATE');
        try {
            $q=$db->prepare('SELECT valor FROM configuracion WHERE clave=?');$q->execute([$key]);$old=json_decode((string)($q->fetchColumn()?:'{}'),true)?:[];
            if ((int)($old['revision']??0)!==(int)($input['expectedRevision']??-1)) throw new RuntimeException('La agenda fue modificada en otro equipo. Actualice antes de guardar.',409);
            $q->execute(['salon_staff_v1_'.($warehouse['uid']?:$warehouse['id'])]);$staff=json_decode((string)($q->fetchColumn()?:'[]'),true)?:[];
            $people=array_column($staff,null,'uid'); $ids=[];
            foreach ($data['services'] as $plan) {
                if (!is_int($plan['serviceId']??null) || $plan['serviceId']<=0 || isset($ids[$plan['serviceId']]) || !is_int($plan['duration']??null) || $plan['duration']<5 || $plan['duration']>720 || !in_array($plan['commissionType']??'', ['DEFAULT','PERCENTAGE','FIXED'],true) || !is_numeric($plan['commissionValue']??null) || !is_finite((float)$plan['commissionValue']) || $plan['commissionValue']<0 || ($plan['commissionType']==='PERCENTAGE' && $plan['commissionValue']>100) || !is_array($plan['stylistUids']??null)) throw new RuntimeException('Duracion, profesional o comision de servicio invalida.');
                foreach ($plan['stylistUids'] as $person) if (!isset($people[$person])) throw new RuntimeException('Profesional no encontrado en esta sucursal.');
                $ids[$plan['serviceId']]=true;
            }
            $ids=[];
            $q=$db->query("SELECT uid,fecha,hora,nota FROM citas_spa WHERE COALESCE(estado,'PENDIENTE')<>'CANCELADA'");$appointments=$q->fetchAll(PDO::FETCH_ASSOC);
            foreach ($data['breaks'] as $rest) {
                $person=$people[$rest['stylistUid']??'']??null;
                if (!$person || empty($rest['uid']) || isset($ids[$rest['uid']]) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',(string)($rest['date']??'')) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D',(string)($rest['start']??'')) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D',(string)($rest['end']??'')) || $rest['start']>=$rest['end'] || mb_strlen((string)($rest['reason']??''))>120) throw new RuntimeException('Descanso invalido.');
                $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$rest['date']);
                if (!$date || $date->format('Y-m-d')!==$rest['date']) throw new RuntimeException('Fecha de descanso invalida.');
                foreach ($appointments as $appointment) {
                    $booking=json_decode((string)$appointment['nota'],true)['tmpos_salon']??null;
                    if ($booking && (int)($booking['u']??0)===(int)$person['userId'] && $appointment['fecha']===$rest['date'] && $appointment['hora']<$rest['end'] && ($booking['e']??'')>$rest['start']) throw new RuntimeException('El descanso se cruza con una cita. Reprograme la cita primero.');
                }
                $ids[$rest['uid']]=true;
            }
            $data['revision']=(int)($old['revision']??0)+1;
            $now=gmdate('Y-m-d H:i:s');$json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            $q=$db->prepare('UPDATE configuracion SET valor=?,updated_at=? WHERE clave=?');$q->execute([$json,$now,$key]);
            if (!$q->rowCount()) $db->prepare('INSERT INTO configuracion(clave,valor,categoria,uid,created_at,updated_at) VALUES(?,?,?,?,?,?)')->execute([$key,$json,'salon','salon_'.bin2hex(random_bytes(16)),$now,$now]);
            $db->exec('COMMIT'); return ['success'=>true,'data'=>$data];
        } catch (\Throwable $e) { $db->exec('ROLLBACK'); throw $e; }
    }
}
