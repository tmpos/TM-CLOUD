<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Support;
use PDO;
use RuntimeException;

final class MailService
{
    private const TEMPLATES = ['test', 'invoice', 'weekly_summary', 'license', 'system_event'];

    public function __construct(private PDO $db, private array $config, private LogService $logs)
    {
    }

    public function queue(string $projectUid, string $template, string $recipient, array $payload = []): array
    {
        $template = strtolower(trim($template));
        $recipient = trim($recipient);
        if (!in_array($template, self::TEMPLATES, true)) {
            throw new \InvalidArgumentException('Unsupported mail template.');
        }
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || strlen($recipient) > 254) {
            throw new \InvalidArgumentException('A valid recipient email is required.');
        }
        if (strlen(json_encode($payload) ?: '') > 65535) {
            throw new \InvalidArgumentException('Mail data is too large.');
        }
        $recent = $this->db->prepare("SELECT COUNT(*) FROM mail_queue WHERE project_uid = ? AND recipient = ? AND created_at >= datetime('now','-1 hour')");
        $recent->execute([$projectUid, strtolower($recipient)]);
        if ((int) $recent->fetchColumn() >= 30) {
            throw new RuntimeException('Mail limit reached for this recipient.', 429);
        }

        $uid = Support::uid('mail_');
        $now = Support::now();
        $stmt = $this->db->prepare('INSERT INTO mail_queue (uid,project_uid,template,recipient,payload,status,max_attempts,next_attempt_at,created_at,updated_at) VALUES (?,?,?,?,?,\'pending\',?,?,?,?)');
        $stmt->execute([
            $uid, $projectUid, $template, strtolower($recipient), Support::json($payload),
            max(1, (int) ($this->config['max_attempts'] ?? 5)), $now, $now, $now,
        ]);
        $this->logs->write('mail.queued', $projectUid, 'mail_queue', $uid, null, ['template' => $template, 'recipient' => strtolower($recipient)]);
        return $this->status($projectUid, $uid);
    }

    public function status(string $projectUid, string $uid): array
    {
        $stmt = $this->db->prepare('SELECT uid,project_uid,template,recipient,status,attempts,max_attempts,message_id,last_error,next_attempt_at,sent_at,created_at,updated_at FROM mail_queue WHERE uid = ? AND project_uid = ? LIMIT 1');
        $stmt->execute([$uid, $projectUid]);
        return $stmt->fetch() ?: throw new RuntimeException('Mail job not found.');
    }

    public function process(int $limit = 20): array
    {
        if (!($this->config['enabled'] ?? false)) {
            throw new RuntimeException('Server mail is disabled.');
        }
        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            throw new RuntimeException('PHPMailer is not installed. Run composer install.');
        }
        $now = Support::now();
        $this->db->prepare("UPDATE mail_queue SET status='failed', last_error='Worker timeout; retrying.', next_attempt_at=?, updated_at=? WHERE status='sending' AND updated_at < datetime('now','-10 minutes')")
            ->execute([$now, $now]);
        $stmt = $this->db->prepare("SELECT * FROM mail_queue WHERE status IN ('pending','failed') AND attempts < max_attempts AND (next_attempt_at IS NULL OR next_attempt_at <= ?) ORDER BY created_at ASC LIMIT ?");
        $stmt->bindValue(1, $now);
        $stmt->bindValue(2, max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        $jobs = $stmt->fetchAll();
        $result = ['processed' => 0, 'sent' => 0, 'failed' => 0];
        foreach ($jobs as $job) {
            $result['processed']++;
            try {
                $claim = $this->db->prepare("UPDATE mail_queue SET status='sending', attempts=attempts+1, last_error=NULL, updated_at=? WHERE id=? AND status IN ('pending','failed')");
                $claim->execute([Support::now(), $job['id']]);
                if ($claim->rowCount() !== 1) {
                    $result['processed']--;
                    continue;
                }
                $messageId = $this->send($job);
                $now = Support::now();
                $this->db->prepare("UPDATE mail_queue SET status='sent', message_id=?, sent_at=?, updated_at=? WHERE id=?")->execute([$messageId, $now, $now, $job['id']]);
                $this->logs->write('mail.sent', $job['project_uid'], 'mail_queue', $job['uid'], null, ['message_id' => $messageId]);
                $result['sent']++;
            } catch (\Throwable $e) {
                $attempt = (int) $job['attempts'] + 1;
                $delayMinutes = min(60, 2 ** min(5, $attempt));
                $next = gmdate('Y-m-d H:i:s', time() + ($delayMinutes * 60));
                $error = substr($e->getMessage(), 0, 500);
                $this->db->prepare("UPDATE mail_queue SET status='failed', last_error=?, next_attempt_at=?, updated_at=? WHERE id=?")->execute([$error, $next, Support::now(), $job['id']]);
                $this->logs->write('mail.failed', $job['project_uid'], 'mail_queue', $job['uid'], null, ['attempt' => $attempt, 'error' => $error]);
                $result['failed']++;
            }
        }
        return $result;
    }

    private function send(array $job): string
    {
        [$subject, $html, $text] = $this->render((string) $job['template'], json_decode((string) ($job['payload'] ?? '{}'), true) ?: []);
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = (string) ($this->config['host'] ?? '');
        $mail->Port = (int) ($this->config['port'] ?? 587);
        $mail->SMTPAuth = (string) ($this->config['username'] ?? '') !== '';
        $mail->Username = (string) ($this->config['username'] ?? '');
        $mail->Password = (string) ($this->config['password'] ?? '');
        $encryption = strtolower((string) ($this->config['encryption'] ?? 'tls'));
        if ($encryption === 'tls') $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        elseif (in_array($encryption, ['ssl', 'smtps'], true)) $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 20;
        $mail->setFrom((string) $this->config['from_email'], (string) ($this->config['from_name'] ?? 'TMPBase'));
        if (!empty($this->config['reply_to'])) $mail->addReplyTo((string) $this->config['reply_to']);
        $mail->addAddress((string) $job['recipient']);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $html;
        $mail->AltBody = $text;
        $mail->send();
        return trim($mail->getLastMessageID(), '<>');
    }

    private function render(string $template, array $data): array
    {
        $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $company = $escape($data['company_name'] ?? 'TMPBase');
        $content = '';
        $subject = match ($template) {
            'invoice' => 'Factura ' . $escape($data['invoice_number'] ?? ''),
            'weekly_summary' => 'Resumen semanal - ' . $company,
            'license' => 'Información de licencia - ' . $company,
            'system_event' => $escape($data['event_title'] ?? 'Notificación del sistema') . ' - ' . $company,
            default => 'Prueba de correo - ' . $company,
        };
        foreach ($data as $key => $value) {
            if (is_scalar($value) && !in_array($key, ['share_url'], true)) {
                $content .= '<tr><th style="text-align:left;padding:8px;background:#f1f5f9">' . $escape(str_replace('_', ' ', $key)) . '</th><td style="padding:8px">' . $escape($value) . '</td></tr>';
            }
        }
        if (!empty($data['share_url']) && filter_var($data['share_url'], FILTER_VALIDATE_URL)) {
            $url = $escape($data['share_url']);
            $content .= '<tr><td colspan="2" style="padding:18px;text-align:center"><a href="' . $url . '" style="background:#0f766e;color:#fff;padding:12px 18px;text-decoration:none;border-radius:8px">Ver documento</a></td></tr>';
        }
        $html = '<!doctype html><html><body style="font-family:Arial,sans-serif;background:#f8fafc;padding:24px"><div style="max-width:640px;margin:auto;background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden"><div style="background:#0f172a;color:#fff;padding:22px"><h1 style="margin:0;font-size:22px">' . $subject . '</h1></div><table style="width:100%;border-collapse:collapse">' . $content . '</table><p style="padding:18px;color:#64748b;font-size:12px">Mensaje enviado de forma segura por TMPBase.</p></div></body></html>';
        $text = $subject . "\n\n" . implode("\n", array_map(static fn ($key, $value): string => is_scalar($value) ? str_replace('_', ' ', (string) $key) . ': ' . (string) $value : '', array_keys($data), $data));
        return [$subject, $html, $text];
    }
}
