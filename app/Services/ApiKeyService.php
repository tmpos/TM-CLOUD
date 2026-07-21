<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Http;
use PDO;

final class ApiKeyService
{
    public function __construct(private PDO $db, private SchemaService $schema, private int $limit)
    {
    }

    public function authorize(array $project, string $table, string $method, ?string $key): string
    {
        $mode = $this->schema->accessMode($project, $table);
        if ($mode === 'blocked') {
            throw new \RuntimeException('This table API is blocked.', 403);
        }
        $role = match (true) {
            $key !== null && hash_equals($project['secret_key'], $key) => 'secret',
            $key !== null && hash_equals($project['public_key'], $key) => 'public',
            default => 'anonymous',
        };
        $read = in_array($method, ['GET', 'HEAD'], true);
        $allowed = match ($mode) {
            'public_read' => $read || $role === 'secret',
            'secret_only' => $role === 'secret',
            default => $role === 'secret' || ($read && $role === 'public'),
        };
        if (!$allowed) {
            throw new \RuntimeException('A valid API key with sufficient permissions is required.', 401);
        }
        $this->rateLimit($key ?? Http::clientIp());
        return $role;
    }

    public function authorizeProject(array $project, ?string $key, bool $secretOnly = false): string
    {
        $role = match (true) {
            $key !== null && hash_equals($project['secret_key'], $key) => 'secret',
            $key !== null && hash_equals($project['public_key'], $key) => 'public',
            default => 'anonymous',
        };
        if ($role === 'anonymous' || ($secretOnly && $role !== 'secret')) {
            throw new \RuntimeException($secretOnly ? 'Secret key required.' : 'A valid project API key is required.', 401);
        }
        $this->rateLimit($key);
        return $role;
    }

    public function rateLimitPublic(string $scope, int $maxHits = 30, int $windowSeconds = 60): void
    {
        $ip = trim(Http::clientIp()) ?: 'unknown';
        $this->rateLimit('public:' . $scope . ':' . $ip, max(1, $maxHits), max(60, $windowSeconds));
    }

    private function rateLimit(string $identity, ?int $customLimit = null, int $windowSeconds = 60): void
    {
        $hash = hash('sha256', $identity . ':window:' . $windowSeconds);
        $bucket = (string) (intdiv(time(), $windowSeconds) * $windowSeconds);
        $stmt = $this->db->prepare(
            'INSERT INTO rate_limits (api_key_hash,bucket,hits) VALUES (?,?,1)
             ON CONFLICT(api_key_hash,bucket) DO UPDATE SET hits = hits + 1'
        );
        $stmt->execute([$hash, $bucket]);
        $check = $this->db->prepare('SELECT hits FROM rate_limits WHERE api_key_hash = ? AND bucket = ?');
        $check->execute([$hash, $bucket]);
        if ((int) $check->fetchColumn() > ($customLimit ?? $this->limit)) {
            throw new \RuntimeException('Rate limit exceeded.', 429);
        }
        if (random_int(1, 100) === 1) {
            $cleanup = $this->db->prepare('DELETE FROM rate_limits WHERE length(bucket) <= 10 AND CAST(bucket AS INTEGER) < ?');
            $cleanup->execute([time() - 172800]);
        }
    }
}
