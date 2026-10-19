<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use RuntimeException;

final class SalonPlanningStore
{
    public static function save(PDO $db, array $input, bool $admin, array $actor=[]): array
    {
        $type=(string)($input['stationOperation']['type']??'');
        $roles=['administrador'=>'administrador','admin'=>'administrador','ceo'=>'administrador','cajero'=>'cajero'];
        $level=strtolower(trim((string)($actor['nivel_seguridad']??'')));
        $role=strtolower(trim((string)($actor['rol']??'')));
        $effective=$roles[$level]??$roles[$role]??$role;
        if (!$admin && !($type==='assignOperator' && $effective==='cajero')) throw new RuntimeException('Solo Administrador o Soporte puede configurar la agenda.',403);
        $warehouse=(array)$input['warehouse'];
        $key='salon_planning_v1_'.($warehouse['uid']?:$warehouse['id']);
        $data=(array)($input['settings']??[]);
        if (!isset($input['stationOperation']) && (!isset($data['services'],$data['breaks']) || !is_array($data['services']) || !is_array($data['breaks']) || count($data['services'])>2000 || count($data['breaks'])>10000)) throw new RuntimeException('Planificacion invalida.');
        $db->exec('BEGIN IMMEDIATE');
        try {
            $q=$db->prepare('SELECT valor FROM configuracion WHERE clave=?');$q->execute([$key]);$old=json_decode((string)($q->fetchColumn()?:'{}'),true)?:[];
            if ((int)($old['revision']??0)!==(int)($input['expectedRevision']??-1)) throw new RuntimeException('La agenda fue modificada en otro equipo. Actualice antes de guardar.',409);
            $q->execute(['salon_staff_v1_'.($warehouse['uid']?:$warehouse['id'])]);$staff=json_decode((string)($q->fetchColumn()?:'[]'),true)?:[];
            $people=array_column($staff,null,'uid'); $ids=[];
            if (isset($input['stationOperation'])) {
                $data=self::stationOperation($db,$key,$old,(array)$input['stationOperation'],$people);
            } else {
                // Service/break editors, including older clients, cannot erase or
                // bypass validation of the daily workstation assignments.
                foreach (['stationVersion','stations','stationAssignments','deletedStations'] as $field) {
                    unset($data[$field]);
                    if (array_key_exists($field,$old)) $data[$field]=$old[$field];
                }
            }
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

    private static function stationOperation(PDO $db,string $key,array $old,array $operation,array $people): array
    {
        $data=$old+['services'=>[],'breaks'=>[],'stations'=>[],'stationAssignments'=>[]];
        $stations=$data['stations']; $assignments=$data['stationAssignments'];
        if (!is_array($stations) || !is_array($assignments)) throw new RuntimeException('La configuracion de puestos no es valida.');
        $type=$operation['type']??'';
        if ($type==='saveStation') {
            $station=(array)($operation['station']??[]);
            $uid=(string)($station['uid']??''); $name=trim((string)($station['name']??'')); $specialty=trim((string)($station['specialty']??''));
            foreach (($data['deletedStations']??[]) as $deleted) if ($deleted['station']['uid']===$uid) throw new RuntimeException('Esta mesa fue eliminada. Actualiza la jornada.');
            if (!preg_match('/^[\w-]{1,100}$/D',$uid) || $name==='' || mb_strlen($name)>60 || mb_strlen($specialty)>100 || !is_bool($station['active']??null)) throw new RuntimeException('Nombre, especialidad o estado del puesto invalido.');
            foreach ($stations as $row) {
                if ($row['uid']!==$uid && mb_strtolower(trim($row['name']))===mb_strtolower($name)) throw new RuntimeException('Ya existe un puesto con ese nombre.');
            }
            $stations=array_values(array_filter($stations,fn($row)=>$row['uid']!==$uid));
            $stations[]=['uid'=>$uid,'name'=>$name,'specialty'=>$specialty,'active'=>$station['active']];
            if (count($stations)>200) throw new RuntimeException('Se permiten hasta 200 puestos por sucursal.');
        } elseif ($type==='deleteStation') {
            $uid=(string)($operation['stationUid']??'');
            $station=array_column($stations,null,'uid')[$uid]??null;
            if (!$station) throw new RuntimeException('Mesa no encontrada. Actualiza la jornada.');
            $exists=$db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='_salon_station_accounts'")->fetchColumn();
            if ($exists) {
                $q=$db->prepare("SELECT 1 FROM _salon_station_accounts WHERE warehouse_uid=? AND station_uid=? AND status='OPEN' LIMIT 1");
                $q->execute([substr($key,strlen('salon_planning_v1_')),$uid]);
                if ($q->fetchColumn()) throw new RuntimeException('La mesa tiene una cuenta abierta. Factura y termina la atencion antes de eliminarla.');
            }
            $data['deletedStations'][]=['station'=>$station,'assignments'=>array_values(array_filter($assignments,fn($row)=>$row['stationUid']===$uid)),'deletedAt'=>gmdate('c')];
            $stations=array_values(array_filter($stations,fn($row)=>$row['uid']!==$uid));
            $assignments=array_values(array_filter($assignments,fn($row)=>$row['stationUid']!==$uid));
        } elseif ($type==='assignStation' || $type==='assignOperator') {
            $date=(string)($operation['date']??''); $stationUid=(string)($operation['stationUid']??''); $stylistUid=(string)($operation['stylistUid']??'');
            $parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
            if (!$parsed || $parsed->format('Y-m-d')!==$date) throw new RuntimeException('Fecha de jornada invalida.');
            $station=array_column($stations,null,'uid')[$stationUid]??null;
            if (!$station) throw new RuntimeException('Puesto no encontrado en esta sucursal.');
            if ($db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='_salon_station_accounts'")->fetchColumn()) {
                $q=$db->prepare("SELECT 1 FROM _salon_station_accounts WHERE warehouse_uid=? AND station_uid=? AND status='OPEN' LIMIT 1");
                $q->execute([substr($key,strlen('salon_planning_v1_')),$stationUid]);
                if ($q->fetchColumn()) throw new RuntimeException('La mesa tiene una cuenta abierta. Termina la atencion antes de cambiar su asignacion.');
            }
            $assignments=array_values(array_filter($assignments,fn($row)=>$row['stationUid']!==$stationUid || $row['date']!==$date));
            $direct=$type==='assignOperator';
            if ($direct && (!is_int($operation['userId']??null) || $operation['userId']<0)) throw new RuntimeException('Selecciona una operadora valida de Usuarios.');
            $person=$people[$stylistUid]??null;
            $userId=$direct ? $operation['userId'] : (int)($person['userId']??0);
            if ($userId>0 || (!$direct && $stylistUid!=='')) {
                if (!$station['active'] || (!$direct && (!$person || empty($person['active']) || $userId<1))) throw new RuntimeException('Selecciona un puesto y una operadora activos de esta sucursal.');
                $q=$db->prepare('SELECT * FROM usuarios WHERE id=?');$q->execute([$userId]);$user=$q->fetch(PDO::FETCH_ASSOC);
                $roles=['administrador'=>'administrador','admin'=>'administrador','ceo'=>'administrador','usuario'=>'vendedor','vendedor'=>'vendedor','cajero'=>'cajero','soporte'=>'soporte','taller'=>'taller','gerente'=>'gerente'];
                $level=strtolower(trim((string)($user['nivel_seguridad']??'')));$role=strtolower(trim((string)($user['rol']??'')));
                $effective=$roles[$level]??$roles[$role]??$role;
                $status=strtoupper(trim((string)($user['estado']??'ACTIVO')));
                if (!$user || $effective!=='vendedor' || !in_array($status,['ACTIVO','ACTIVA','ACTIVADO','ACTIVADA','1','TRUE'],true)) throw new RuntimeException('Solo se pueden asignar usuarios activos con rol Vendedor. Actualiza la lista de operadoras.');
                if ($direct) {
                    $stylistUid='salon_user_'.$userId;
                    foreach ($people as $profile) if ((int)($profile['userId']??0)===$userId && !empty($profile['active'])) { $stylistUid=(string)$profile['uid']; break; }
                }
                foreach ($assignments as $row) if ($row['date']===$date && (int)$row['userId']===$userId) throw new RuntimeException('La operadora ya tiene otro puesto asignado ese dia.');
                $q=$db->prepare("SELECT valor FROM configuracion WHERE clave LIKE 'salon_planning_v1_%' AND clave<>?");$q->execute([$key]);
                foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $json) {
                    $other=json_decode((string)$json,true)?:[];
                    foreach (($other['stationAssignments']??[]) as $row) if ($row['date']===$date && (int)$row['userId']===$userId) throw new RuntimeException('La operadora ya tiene un puesto asignado en otra sucursal ese dia.');
                }
                $assignments[]=['date'=>$date,'stationUid'=>$stationUid,'stationName'=>$station['name'],'stylistUid'=>$stylistUid,'stylistName'=>trim((string)(($user['nombre']??'')?:($user['usuario']??('Usuario '.$userId)))),'userId'=>$userId];
            }
            if (count($assignments)>30000) throw new RuntimeException('La jornada supera el limite de asignaciones almacenadas.');
        } else throw new RuntimeException('Operacion de puestos no disponible.');
        $data['stationVersion']=1; $data['stations']=$stations; $data['stationAssignments']=$assignments;
        return $data;
    }
}
