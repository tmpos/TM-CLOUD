<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Project OTP ("OTP Local"): HMAC-SHA256 of the project's shared secret over a
 * time window, the same algorithm as electron/main.ts calculateVariableOtp, so
 * the desktop app, the browser, the dashboard and the server compute the same
 * code. The row lives in the project's otp_config table. The current code is
 * also the support access code for that project.
 */
final class ProjectOtpService
{
    public const CONFIG_UID = 'proyecto-otp-config';

    private function ensureTable(PDO $db): void
    {
        $exists = $db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='otp_config'")->fetchColumn();
        if ($exists) return;
        $db->exec('CREATE TABLE otp_config (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uid TEXT UNIQUE NOT NULL,
            mode TEXT NOT NULL DEFAULT \'variable\',
            fixed_code TEXT NOT NULL DEFAULT \'0000\',
            interval_seconds INTEGER NOT NULL DEFAULT 60,
            send_email INTEGER NOT NULL DEFAULT 0,
            secret TEXT NOT NULL DEFAULT \'\',
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');
    }

    public function row(PDO $db, string $now): array
    {
        $this->ensureTable($db);
        $stmt = $db->prepare('SELECT * FROM otp_config WHERE uid = ? LIMIT 1');
        $stmt->execute([self::CONFIG_UID]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $secret = bin2hex(random_bytes(32));
            $db->prepare('INSERT INTO otp_config (uid, mode, fixed_code, interval_seconds, send_email, secret, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([self::CONFIG_UID, 'variable', '0000', 60, 0, $secret, $now, $now]);
            $stmt->execute([self::CONFIG_UID]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } elseif (empty($row['secret'])) {
            $secret = bin2hex(random_bytes(32));
            $db->prepare('UPDATE otp_config SET secret = ?, updated_at = ? WHERE uid = ?')->execute([$secret, $now, self::CONFIG_UID]);
            $row['secret'] = $secret;
        }
        return $row;
    }

    public static function calculate(string $secret, int $intervalSeconds, ?int $timestampMs = null): string
    {
        $timestampMs ??= (int) round(microtime(true) * 1000);
        $windowNumber = (int) floor($timestampMs / 1000 / $intervalSeconds);
        $digest = hash_hmac('sha256', (string) $windowNumber, $secret, true);
        $num = unpack('N', substr($digest, 0, 4))[1];
        return str_pad((string) ($num % 10000), 4, '0', STR_PAD_LEFT);
    }

    /** @return array{mode:string, fixedCode:string, intervalSeconds:int, sendEmail:bool, code:string, secondsRemaining:int, networkUrl:string} */
    public function status(PDO $db, string $now): array
    {
        $row = $this->row($db, $now);
        $mode = $row['mode'] === 'fixed' ? 'fixed' : 'variable';
        $intervalSeconds = max(30, min(3600, (int) $row['interval_seconds']));
        $fixedCode = preg_match('/^\d{4}$/', (string) $row['fixed_code']) ? (string) $row['fixed_code'] : '0000';
        $code = $mode === 'fixed' ? $fixedCode : self::calculate((string) $row['secret'], $intervalSeconds);
        $nowSeconds = (int) floor(microtime(true));
        $secondsRemaining = $mode === 'fixed' ? 0 : $intervalSeconds - ($nowSeconds % $intervalSeconds);
        return [
            'mode' => $mode, 'fixedCode' => $fixedCode, 'intervalSeconds' => $intervalSeconds,
            'sendEmail' => (bool) $row['send_email'], 'code' => $code,
            'secondsRemaining' => $secondsRemaining, 'networkUrl' => '',
        ];
    }

    public function save(PDO $db, array $data, string $now): void
    {
        $row = $this->row($db, $now);
        $mode = ($data['mode'] ?? '') === 'fixed' ? 'fixed' : 'variable';
        $fixedCode = preg_match('/^\d{4}$/', (string) ($data['fixedCode'] ?? '')) ? (string) $data['fixedCode'] : (string) $row['fixed_code'];
        $intervalSeconds = max(30, min(3600, (int) ($data['intervalSeconds'] ?? $row['interval_seconds'])));
        $sendEmail = !empty($data['sendEmail']) ? 1 : 0;
        $secret = !empty($data['regenerateSecret']) ? bin2hex(random_bytes(32)) : (string) $row['secret'];
        $db->prepare('UPDATE otp_config SET mode=?, fixed_code=?, interval_seconds=?, send_email=?, secret=?, updated_at=? WHERE uid=?')
            ->execute([$mode, $fixedCode, $intervalSeconds, $sendEmail, $secret, $now, self::CONFIG_UID]);
    }

    /** Accepts the current and the previous window (clock drift); fixed mode compares the fixed code. */
    public function validate(PDO $db, string $code, string $now): bool
    {
        $code = trim($code);
        if (preg_match('/^\d{4}$/D', $code) !== 1) return false;
        $row = $this->row($db, $now);
        if ($row['mode'] === 'fixed') return hash_equals((string) $row['fixed_code'], $code);
        $intervalSeconds = max(30, min(3600, (int) $row['interval_seconds']));
        $nowMs = (int) round(microtime(true) * 1000);
        $current = self::calculate((string) $row['secret'], $intervalSeconds, $nowMs);
        $previous = self::calculate((string) $row['secret'], $intervalSeconds, $nowMs - $intervalSeconds * 1000);
        return hash_equals($current, $code) || hash_equals($previous, $code);
    }
}
