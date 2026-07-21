<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Support;
use PDO;
use RuntimeException;

final class SharedDocumentService
{
    public function __construct(private PDO $db, private array $config, private LogService $logs)
    {
    }

    public function create(string $projectUid, string $type, string $table, string $recordUid, ?string $expiresAt = null): array
    {
        if ($type !== 'invoice' || $table !== 'facturas') {
            throw new \InvalidArgumentException('Unsupported shared document type.');
        }
        $rawToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $uid = Support::uid('shr_');
        $now = Support::now();
        $stmt = $this->db->prepare('INSERT INTO shared_documents (uid,token_hash,project_uid,document_type,table_name,record_uid,expires_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$uid, hash('sha256', $rawToken), $projectUid, $type, $table, $recordUid, $expiresAt, $now, $now]);
        $this->logs->write('document.shared', $projectUid, $table, $recordUid, null, ['share_uid' => $uid, 'expires_at' => $expiresAt]);
        return [
            'uid' => $uid,
            'url' => rtrim((string) $this->config['url'], '/') . '/share/invoice/' . $rawToken,
            'pdf_url' => rtrim((string) $this->config['url'], '/') . '/share/invoice/' . $rawToken . '.pdf',
            'expires_at' => $expiresAt,
        ];
    }

    public function resolve(string $rawToken, string $type = 'invoice'): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{40,60}$/', $rawToken)) {
            throw new RuntimeException('Shared document not found.');
        }
        $stmt = $this->db->prepare('SELECT * FROM shared_documents WHERE token_hash = ? AND document_type = ? LIMIT 1');
        $stmt->execute([hash('sha256', $rawToken), $type]);
        $share = $stmt->fetch();
        if (!$share || $share['revoked_at'] || ($share['expires_at'] && $share['expires_at'] < Support::now())) {
            throw new RuntimeException('Shared document is unavailable.');
        }
        return $share;
    }

    public function accessed(string $uid): void
    {
        $this->db->prepare('UPDATE shared_documents SET access_count = access_count + 1, last_access_at = ?, updated_at = ? WHERE uid = ?')
            ->execute([Support::now(), Support::now(), $uid]);
    }

    public function revoke(string $projectUid, string $uid): void
    {
        $now = Support::now();
        $stmt = $this->db->prepare('UPDATE shared_documents SET revoked_at = ?, updated_at = ? WHERE uid = ? AND project_uid = ? AND revoked_at IS NULL');
        $stmt->execute([$now, $now, $uid, $projectUid]);
        if ($stmt->rowCount() < 1) throw new RuntimeException('Shared document not found.');
        $this->logs->write('document.share_revoked', $projectUid, 'shared_documents', $uid);
    }
}
