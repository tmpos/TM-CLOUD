<?php

declare(strict_types=1);
namespace App\Services;
use PDO;
use RuntimeException;

/** Executes the same accounting engine as desktop, with PDO transactions owned here. */
final class ExpenseAccountingService
{
    public function handle(PDO $db, string $channel, array $payload, array $actor): array
    {
        $script = dirname(__DIR__, 2) . '/bin/expense-runtime.cjs';
        if (!is_file($script)) throw new RuntimeException('El motor contable no esta instalado.');
        // API-key actors arrive only through the authenticated project Secret transport.
        if (($actor['email'] ?? '') === 'api-key') {
            $actor = ['usuario'=>'api-key', 'estado'=>'ACTIVO', 'rol'=>'admin'];
        }
        if (!function_exists('proc_open')) throw new RuntimeException('El servidor no permite ejecutar el motor contable (proc_open deshabilitado).');
        $process = proc_open([$this->nodeBinary(), $script], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('No se pudo iniciar el motor contable.');
        stream_set_timeout($pipes[1], 35);
        stream_set_blocking($pipes[2], false);
        $inTransaction = false;
        try {
            fwrite($pipes[0], json_encode(['channel'=>$channel,'payload'=>$payload,'user'=>$actor], JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
            while (($line = fgets($pipes[1], 33554432)) !== false) {
                $message = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                if (($message['type'] ?? '') === 'result') {
                    if ($inTransaction) { $db->exec('ROLLBACK'); $inTransaction=false; throw new RuntimeException('El motor contable no completo la transaccion.'); }
                    return ['success'=>($message['success'] ?? false)===true, ...(isset($message['data']) ? ['data'=>$message['data']] : ['error'=>$message['error'] ?? 'No se pudo procesar el gasto'])];
                }
                if (($message['type'] ?? '') !== 'sql') throw new RuntimeException('Respuesta contable no valida.');
                // Statements are emitted only by the deployed trusted engine, not browser input.
                try {
                    $sql = (string) $message['sql'];
                    $statement = $db->prepare($sql);
                    $statement->execute($message['params'] ?? []);
                    if ($sql === 'BEGIN IMMEDIATE') $inTransaction=true;
                    if ($sql === 'COMMIT' || $sql === 'ROLLBACK') $inTransaction=false;
                    $reply = ['rows'=>($message['method'] ?? '') === 'all' ? $statement->fetchAll(PDO::FETCH_ASSOC) : []];
                } catch (\Throwable $e) { $reply=['error'=>$e->getMessage()]; }
                fwrite($pipes[0], json_encode($reply, JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
            }
            $detail = trim((string) stream_get_contents($pipes[2]));
            throw new RuntimeException('El motor contable no respondio. No se confirmo el registro.' . ($detail !== '' ? ' ' . substr($detail, 0, 300) : ''));
        } finally {
            if ($inTransaction) $db->exec('ROLLBACK');
            foreach ($pipes as $pipe) fclose($pipe);
            proc_terminate($process);
            proc_close($process);
        }
    }

    /** NODE_BINARY overrides; otherwise the Docker image path, then common install paths. */
    private function nodeBinary(): string
    {
        $configured = trim((string) (getenv('NODE_BINARY') ?: ''));
        if ($configured !== '') return $configured;
        foreach (['/usr/bin/node', '/usr/local/bin/node'] as $candidate) {
            if (is_executable($candidate)) return $candidate;
        }
        throw new RuntimeException('Node.js no esta instalado en el servidor; el motor contable no puede ejecutarse.');
    }
}
