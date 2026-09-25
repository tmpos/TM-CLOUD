<?php
declare(strict_types=1);
namespace App\Services;

use InvalidArgumentException;

final class CrmRegistrationService
{
    public function __construct(private SchemaService $schema, private RecordService $records, private LogService $logs, private WebhookService $webhooks) {}

    public function seller(array $project, string $key): array
    {
        foreach ($this->records->all($project, 'usuarios') as $user) {
            $uid = (string) ($user['uid'] ?? '');
            if ($uid === '') $uid = 'usuario:' . strtolower(trim((string) ($user['usuario'] ?? '')));
            if ($uid !== $key || in_array(strtoupper((string) ($user['estado'] ?? '')), ['DESACTIVADO','INACTIVO','ELIMINADO','BLOQUEADO'], true)) continue;
            return ['vendedor_uid' => $uid, 'vendedor_nombre' => (string) ($user['nombre'] ?? $user['usuario'] ?? 'Usuario')];
        }
        throw new InvalidArgumentException('El vendedor asignado no esta disponible. Solicite un nuevo enlace.');
    }

    public function prepare(array $project, array $input): array
    {
        $seller = $this->seller($project, trim((string) ($input['vendedor_uid'] ?? '')));
        $tables = array_column($this->schema->tables($project), 'name');
        $fields = array_map(fn ($name) => ['name' => $name, 'type' => 'TEXT'], ['cliente_uid','nombre','telefono','email','direccion','rnc','necesidad','producto_interes','productos_interes','origen','prioridad','vendedor_uid','vendedor_nombre','creado_por']);
        $fields[] = ['name' => 'presupuesto', 'type' => 'REAL'];
        if (!in_array('crm_prospectos', $tables, true)) $this->schema->createTable($project, 'crm_prospectos', $fields);
        else {
            $columns = array_column($this->schema->columns($project, 'crm_prospectos'), 'name');
            foreach ($fields as $field) if (!in_array($field['name'], $columns, true)) $this->schema->addColumn($project, 'crm_prospectos', $field);
        }
        if (!in_array('clientes', $tables, true)) $this->schema->createTable($project, 'clientes', array_map(fn ($name) => ['name'=>$name,'type'=>'TEXT'], ['nombre','telefono','email','direccion','rnc','tipo_cliente','almacen_uid']));
        return $seller;
    }

    public function validate(array $input): void
    {
        foreach (['producto_interes'=>500, 'necesidad'=>2000] as $key=>$max) {
            $value = trim((string) ($input[$key] ?? ''));
            if ($value === '' || mb_strlen($value) > $max) throw new InvalidArgumentException('Indique el producto de interes y que necesita (maximo 500 y 2000 caracteres).');
        }
        if (!empty($input['website'])) throw new InvalidArgumentException('Solicitud no valida.');
        if (($input['consentimiento'] ?? '') !== '1') throw new InvalidArgumentException('Autorice que le contactemos para atender su consulta.');
    }

    public function complete(array $project, array $request, array $candidate, array $input): array
    {
        $this->validate($input);
        $config = json_decode((string) $request['crm_json'], true, 512, JSON_THROW_ON_ERROR);
        $seller = $this->seller($project, $config['vendedor_uid']);
        $db = $this->schema->connection($project);
        $leadUid = 'crm_web_' . $request['uid'];
        $customerUid = 'crm_cliente_' . $request['uid'];
        $db->beginTransaction();
        try {
            $existing = $db->prepare('SELECT cliente_uid FROM crm_prospectos WHERE uid=?');
            $existing->execute([$leadUid]);
            $linked = $existing->fetchColumn();
            if ($linked) { $customer = $this->records->find($project, 'clientes', (string) $linked); $db->commit(); return $customer; }
            // Both records commit together; deterministic UIDs make retries safe.
            $customer = $this->records->create($project, 'clientes', ['uid'=>$customerUid, ...$candidate], false);
            $lead = $this->records->create($project, 'crm_prospectos', [
                'uid'=>$leadUid, 'cliente_uid'=>$customer['uid'], 'nombre'=>$candidate['nombre'],
                'telefono'=>$candidate['telefono'], 'email'=>$candidate['email'] ?? '',
                'direccion'=>$candidate['direccion'] ?? '', 'rnc'=>$candidate['rnc'] ?? '',
                'necesidad'=>trim((string)$input['necesidad']), 'producto_interes'=>trim((string)$input['producto_interes']),
                'productos_interes'=>'[]', 'presupuesto'=>0, 'origen'=>'Formulario web', 'prioridad'=>'NORMAL',
                ...$seller, 'creado_por'=>'formulario_publico',
            ], false);
            $db->commit();
        } catch (\Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
        foreach (['clientes'=>$customer, 'crm_prospectos'=>$lead] as $table=>$record) {
            try { $this->logs->write('record.created', $project['uid'], $table, $record['uid'], null, $record); } catch (\Throwable) {}
            if ($table === 'crm_prospectos') {
                try { $this->webhooks->dispatch('record.created', $project, $table, $record); } catch (\Throwable) {}
            }
        }
        return $customer;
    }
}
