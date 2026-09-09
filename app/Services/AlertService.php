<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Support;
use PDO;

final class AlertService
{
    public function __construct(
        private PDO $db,
        private array $config,
        private ProjectService $projects,
        private MetricsService $metrics,
        private BackupService $backups,
        private MailService $mail,
        private LogService $logs,
    ) {
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS _alerts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT NOT NULL,
            project_uid TEXT,
            sent_at TEXT NOT NULL
        )");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_alerts_lookup ON _alerts(type, project_uid, sent_at)");
    }

    public function checkAndNotify(): array
    {
        $thresholdPercent = (int) ($this->config['alert_storage_threshold_percent'] ?? 90);
        $staleDays = (int) ($this->config['alert_backup_stale_days'] ?? 2);
        $quotaBytes = (int) ($this->config['project_storage_max_bytes'] ?? 0);
        $storage = (string) ($this->config['storage'] ?? '');

        $projects = array_values(array_filter($this->projects->all(), static fn (array $p): bool => ($p['status'] ?? '') === 'active'));

        $result = ['checked' => count($projects), 'notified' => [], 'skipped' => []];

        $usage = $this->metrics->perProjectUsage($projects, $storage, $quotaBytes);
        $usageByUid = [];
        foreach ($usage['projects'] as $row) {
            $usageByUid[$row['uid']] = $row;
        }

        foreach ($projects as $project) {
            $uid = (string) $project['uid'];
            $name = (string) $project['name'];

            $row = $usageByUid[$uid] ?? null;
            if ($row !== null && $row['percent'] !== null && (float) $row['percent'] >= $thresholdPercent) {
                if ($this->notify('storage_quota', $uid, $name, sprintf(
                    'Project "%s" is using %s%% of its storage quota (%s of %s).',
                    $name,
                    $row['percent'],
                    $this->formatBytes((int) $row['total']),
                    $this->formatBytes((int) $row['quota'])
                ))) {
                    $result['notified'][] = ['type' => 'storage_quota', 'project_uid' => $uid];
                } else {
                    $result['skipped'][] = ['type' => 'storage_quota', 'project_uid' => $uid];
                }
            }

            $latestBackup = $this->latestBackup($uid);
            if ($latestBackup === null) {
                continue;
            }

            if ((string) ($latestBackup['status'] ?? 'valid') !== 'valid') {
                if ($this->notify('backup_failed', $uid, $name, sprintf(
                    'The latest backup for project "%s" has status "%s" (created at %s).',
                    $name,
                    (string) $latestBackup['status'],
                    (string) $latestBackup['created_at']
                ))) {
                    $result['notified'][] = ['type' => 'backup_failed', 'project_uid' => $uid];
                } else {
                    $result['skipped'][] = ['type' => 'backup_failed', 'project_uid' => $uid];
                }
                continue;
            }

            $createdAt = strtotime((string) $latestBackup['created_at']);
            if ($createdAt !== false && $createdAt < (time() - $staleDays * 86400)) {
                if ($this->notify('backup_stale', $uid, $name, sprintf(
                    'The latest backup for project "%s" is from %s, older than the %d day(s) threshold.',
                    $name,
                    (string) $latestBackup['created_at'],
                    $staleDays
                ))) {
                    $result['notified'][] = ['type' => 'backup_stale', 'project_uid' => $uid];
                } else {
                    $result['skipped'][] = ['type' => 'backup_stale', 'project_uid' => $uid];
                }
            }
        }

        return $result;
    }

    private function latestBackup(string $projectUid): ?array
    {
        $rows = $this->backups->all($projectUid);
        return $rows[0] ?? null;
    }

    private function notify(string $type, string $projectUid, string $projectName, string $message): bool
    {
        $cooldownHours = (int) ($this->config['alert_cooldown_hours'] ?? 24);
        $stmt = $this->db->prepare("SELECT 1 FROM _alerts WHERE type = ? AND project_uid = ? AND sent_at >= ? LIMIT 1");
        $stmt->execute([$type, $projectUid, gmdate('Y-m-d H:i:s', time() - $cooldownHours * 3600)]);
        if ($stmt->fetchColumn()) {
            return false;
        }

        $subject = match ($type) {
            'storage_quota' => 'Storage quota alert - ' . $projectName,
            'backup_failed' => 'Backup failure alert - ' . $projectName,
            'backup_stale' => 'Stale backup alert - ' . $projectName,
            default => 'TMPBase alert - ' . $projectName,
        };
        $safe = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<p>' . $safe . '</p>';

        $this->mail->notifyAdmin($subject, $html);

        $now = Support::now();
        $this->db->prepare('INSERT INTO _alerts (type, project_uid, sent_at) VALUES (?,?,?)')->execute([$type, $projectUid, $now]);
        $this->logs->write('alert.sent', $projectUid, '_alerts', null, null, ['type' => $type, 'message' => $message]);
        return true;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $index = 0;
        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }
        return number_format($value, 1) . ' ' . $units[$index];
    }
}
