<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Support;
use PDO;
use RuntimeException;

final class MailService
{
    private const TEMPLATES = ['test', 'otp', 'invoice', 'weekly_summary', 'cash_closing', 'license', 'system_event', 'storefront_order'];

    public function __construct(private PDO $db, private array $config, private LogService $logs, private string $storage)
    {
    }

    public function settings(bool $withPassword = false): array
    {
        $settings = $this->config;
        $row = $this->db->query('SELECT * FROM mail_settings WHERE id = 1')->fetch();
        if ($row) {
            $settings = array_merge($settings, [
                'enabled' => (int) $row['enabled'] === 1,
                'host' => (string) $row['host'],
                'port' => (int) $row['port'],
                'encryption' => (string) $row['encryption'],
                'username' => (string) $row['username'],
                'from_email' => (string) $row['from_email'],
                'from_name' => (string) $row['from_name'],
                'reply_to' => (string) $row['reply_to'],
            ]);
            if ((string) $row['password_encrypted'] !== '') {
                $settings['password'] = (new CredentialCipher($this->storage))->decrypt((string) $row['password_encrypted']);
            }
        }
        $settings['password_configured'] = trim((string) ($settings['password'] ?? '')) !== '';
        if (!$withPassword) unset($settings['password']);
        return $settings;
    }

    public function saveSettings(array $input): array
    {
        $enabled = isset($input['enabled']) && in_array(strtolower((string) $input['enabled']), ['1', 'true', 'on', 'yes'], true);
        $host = trim((string) ($input['host'] ?? 'smtp.gmail.com'));
        $port = (int) ($input['port'] ?? 587);
        $encryption = strtolower(trim((string) ($input['encryption'] ?? 'tls')));
        $username = strtolower(trim((string) ($input['username'] ?? '')));
        $fromEmail = strtolower(trim((string) ($input['from_email'] ?? $username)));
        $fromName = trim((string) ($input['from_name'] ?? 'TMPBase'));
        $replyTo = strtolower(trim((string) ($input['reply_to'] ?? '')));
        if ($host === '' || $port < 1 || $port > 65535 || !in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            throw new \InvalidArgumentException('Invalid SMTP server configuration.');
        }
        if ($enabled && (!filter_var($username, FILTER_VALIDATE_EMAIL) || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL))) {
            throw new \InvalidArgumentException('A valid Google account and sender email are required.');
        }
        if ($replyTo !== '' && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Reply-to email is invalid.');

        $current = $this->db->query('SELECT password_encrypted FROM mail_settings WHERE id = 1')->fetch();
        $encrypted = (string) ($current['password_encrypted'] ?? '');
        $password = preg_replace('/\s+/', '', (string) ($input['password'] ?? '')) ?? '';
        if ($password !== '') $encrypted = (new CredentialCipher($this->storage))->encrypt($password);
        if ($enabled && $encrypted === '' && trim((string) ($this->config['password'] ?? '')) === '') {
            throw new \InvalidArgumentException('Google app password is required.');
        }
        $now = Support::now();
        $stmt = $this->db->prepare('INSERT INTO mail_settings (id,enabled,host,port,encryption,username,password_encrypted,from_email,from_name,reply_to,updated_at) VALUES (1,?,?,?,?,?,?,?,?,?,?) ON CONFLICT(id) DO UPDATE SET enabled=excluded.enabled,host=excluded.host,port=excluded.port,encryption=excluded.encryption,username=excluded.username,password_encrypted=excluded.password_encrypted,from_email=excluded.from_email,from_name=excluded.from_name,reply_to=excluded.reply_to,updated_at=excluded.updated_at');
        $stmt->execute([$enabled ? 1 : 0, $host, $port, $encryption, $username, $encrypted, $fromEmail, $fromName ?: 'TMPBase', $replyTo, $now]);
        $this->logs->write('mail.settings.updated', null, 'mail_settings', '1', null, ['enabled' => $enabled, 'host' => $host, 'username' => $username]);
        return $this->settings();
    }

    public function sendOtp(string $projectUid, string $recipient, string $otp, array $data = []): array
    {
        $recipient = strtolower(trim($recipient));
        $otp = preg_replace('/\D/', '', $otp) ?? '';
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || strlen($recipient) > 254) throw new \InvalidArgumentException('A valid recipient email is required.');
        if (!preg_match('/^\d{4,8}$/', $otp)) throw new \InvalidArgumentException('OTP must contain between 4 and 8 digits.');
        $purpose = trim((string) ($data['purpose'] ?? 'confirmar la operación'));
        if (strlen($purpose) > 120) throw new \InvalidArgumentException('OTP purpose is too long.');
        $minutes = max(1, min(30, (int) ($data['expires_minutes'] ?? 10)));
        $company = trim((string) ($data['company_name'] ?? 'TMPOS')) ?: 'TMPOS';
        $safe = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $subject = 'Código OTP - ' . $company;
        $html = '<!doctype html><html><body style="margin:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827;padding:28px"><div style="max-width:560px;margin:auto;background:#fff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden"><div style="background:#0f172a;color:#fff;padding:24px"><div style="font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#5eead4">' . $safe($company) . '</div><h1 style="margin:8px 0 0;font-size:22px">Código de verificación</h1></div><div style="padding:28px"><p style="color:#374151;line-height:1.6">Usa este código para ' . $safe($purpose) . '.</p><div style="margin:24px 0;padding:24px;text-align:center;background:#f0fdfa;border:1px solid #99f6e4;border-radius:12px"><div style="font-size:38px;font-weight:800;letter-spacing:12px">' . $safe($otp) . '</div></div><p style="font-size:13px;color:#64748b">El código vence en ' . $minutes . ' minuto(s). Si no lo solicitaste, ignora este correo.</p></div></div></body></html>';
        $text = "Código OTP: $otp\nVence en $minutes minuto(s).\nMotivo: $purpose";
        $messageId = $this->sendMessage($recipient, $subject, $html, $text);
        $this->logs->write('otp.sent', $projectUid, 'mail', null, null, ['recipient' => $recipient, 'purpose' => $purpose, 'message_id' => $messageId]);
        return ['sent' => true, 'recipient' => $recipient, 'message_id' => $messageId];
    }

    public function sendTest(string $recipient): array
    {
        return $this->sendOtp('system', $recipient, '123456', ['company_name' => 'TMPBase', 'purpose' => 'probar la configuración SMTP', 'expires_minutes' => 10]);
    }

    public function sendDocument(
        string $recipient,
        string $subject,
        string $html,
        string $text,
        string $document,
        string $filename,
    ): array {
        $recipient = strtolower(trim($recipient));
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || strlen($recipient) > 254) {
            throw new \InvalidArgumentException('Escribe un correo electrónico válido.');
        }
        if ($document === '' || strlen($document) > 12 * 1024 * 1024) {
            throw new \InvalidArgumentException('El documento está vacío o supera el límite permitido.');
        }
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?: 'documento.pdf';
        $messageId = $this->sendMessage(
            $recipient,
            mb_substr(trim($subject), 0, 180),
            $html,
            $text,
            [['content' => $document, 'filename' => $filename, 'mime' => 'application/pdf']]
        );
        return ['sent' => true, 'recipient' => $recipient, 'message_id' => $messageId];
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
        $hourlyLimit = $template === 'storefront_order' && ($payload['recipient_role'] ?? '') === 'store' ? 500 : 30;
        if ((int) $recent->fetchColumn() >= $hourlyLimit) {
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

    public function deliver(string $projectUid, string $uid): array
    {
        $stmt = $this->db->prepare('SELECT * FROM mail_queue WHERE uid = ? AND project_uid = ? LIMIT 1');
        $stmt->execute([$uid, $projectUid]);
        $job = $stmt->fetch() ?: throw new RuntimeException('Mail job not found.');
        if ((string) $job['status'] === 'sent') return $this->status($projectUid, $uid);

        $claim = $this->db->prepare("UPDATE mail_queue SET status='sending', attempts=attempts+1, last_error=NULL, updated_at=? WHERE id=? AND status IN ('pending','failed')");
        $claim->execute([Support::now(), $job['id']]);
        if ($claim->rowCount() !== 1) return $this->status($projectUid, $uid);

        try {
            $messageId = $this->send($job);
            $now = Support::now();
            $this->db->prepare("UPDATE mail_queue SET status='sent', message_id=?, sent_at=?, updated_at=? WHERE id=?")->execute([$messageId, $now, $now, $job['id']]);
            $this->logs->write('mail.sent', $projectUid, 'mail_queue', $uid, null, ['message_id' => $messageId, 'immediate' => true]);
        } catch (\Throwable $e) {
            $attempt = (int) $job['attempts'] + 1;
            $delayMinutes = min(60, 2 ** min(5, $attempt));
            $next = gmdate('Y-m-d H:i:s', time() + ($delayMinutes * 60));
            $error = substr($e->getMessage(), 0, 500);
            $this->db->prepare("UPDATE mail_queue SET status='failed', last_error=?, next_attempt_at=?, updated_at=? WHERE id=?")->execute([$error, $next, Support::now(), $job['id']]);
            $this->logs->write('mail.failed', $projectUid, 'mail_queue', $uid, null, ['attempt' => $attempt, 'error' => $error, 'immediate' => true]);
        }
        return $this->status($projectUid, $uid);
    }

    public function process(int $limit = 20): array
    {
        $settings = $this->settings(true);
        if (!($settings['enabled'] ?? false)) {
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
        return $this->sendMessage((string) $job['recipient'], $subject, $html, $text);
    }

    private function sendMessage(string $recipient, string $subject, string $html, string $text, array $attachments = []): string
    {
        $settings = $this->settings(true);
        if (!($settings['enabled'] ?? false)) throw new RuntimeException('Server mail is disabled.');
        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) throw new RuntimeException('PHPMailer is not installed. Run composer install.');
        if (trim((string) ($settings['host'] ?? '')) === '' || trim((string) ($settings['username'] ?? '')) === '' || trim((string) ($settings['password'] ?? '')) === '') {
            throw new RuntimeException('SMTP configuration is incomplete.');
        }
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = (string) ($settings['host'] ?? 'smtp.gmail.com');
        $mail->Port = (int) ($settings['port'] ?? 587);
        $mail->SMTPAuth = true;
        $mail->Username = (string) ($settings['username'] ?? '');
        $mail->Password = (string) ($settings['password'] ?? '');
        $encryption = strtolower((string) ($settings['encryption'] ?? 'tls'));
        if ($encryption === 'tls') $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        elseif (in_array($encryption, ['ssl', 'smtps'], true)) $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 20;
        $mail->setFrom((string) ($settings['from_email'] ?? $settings['username']), (string) ($settings['from_name'] ?? 'TMPBase'));
        if (!empty($settings['reply_to'])) $mail->addReplyTo((string) $settings['reply_to']);
        $mail->addAddress($recipient);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $html;
        $mail->AltBody = $text;
        foreach ($attachments as $attachment) {
            if (!is_array($attachment) || !is_string($attachment['content'] ?? null)) {
                continue;
            }
            $mail->addStringAttachment(
                $attachment['content'],
                (string) ($attachment['filename'] ?? 'documento.pdf'),
                \PHPMailer\PHPMailer\PHPMailer::ENCODING_BASE64,
                (string) ($attachment['mime'] ?? 'application/octet-stream')
            );
        }
        $mail->send();
        return trim($mail->getLastMessageID(), '<>');
    }

    private function render(string $template, array $data): array
    {
        if ($template === 'cash_closing') {
            return $this->renderCashClosing($data);
        }
        if ($template === 'storefront_order') {
            return $this->renderStorefrontOrder($data);
        }

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

    private function renderStorefrontOrder(array $data): array
    {
        $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $role = ($data['recipient_role'] ?? 'customer') === 'store' ? 'store' : 'customer';
        $company = trim((string) ($data['company_name'] ?? 'Tienda')) ?: 'Tienda';
        $number = trim((string) ($data['order_number'] ?? 'Pedido')) ?: 'Pedido';
        $currency = strtoupper(trim((string) ($data['currency'] ?? 'DOP')));
        $symbol = match ($currency) { 'USD' => 'US$', 'EUR' => '€', default => 'RD$' };
        $money = static fn (mixed $value): string => $symbol . ' ' . number_format((float) $value, 2, '.', ',');
        $color = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($data['primary_color'] ?? ''))
            ? (string) $data['primary_color']
            : '#0f766e';
        $delivery = match ((string) ($data['delivery_method'] ?? '')) {
            'pickup' => 'Retiro en tienda',
            'delivery' => 'Entrega local',
            'shipping' => 'Envío nacional',
            default => ucfirst((string) ($data['delivery_method'] ?? 'Por coordinar')),
        };
        $payment = match ((string) ($data['payment_provider'] ?? '')) {
            'cash' => 'Efectivo',
            'bank_transfer' => 'Transferencia bancaria',
            'card_on_delivery' => 'Tarjeta al recibir',
            'stripe', 'azul' => 'Tarjeta',
            'paypal' => 'PayPal',
            default => ucfirst((string) ($data['payment_provider'] ?? 'Por confirmar')),
        };
        $subject = $role === 'store'
            ? 'Nueva orden comprada ' . $number . ' - ' . $company
            : 'Confirmación de tu compra ' . $number . ' - ' . $company;

        $itemRows = '';
        $textItems = [];
        foreach (array_slice(is_array($data['items'] ?? null) ? $data['items'] : [], 0, 50) as $item) {
            if (!is_array($item)) continue;
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $name = trim((string) ($item['name'] ?? 'Producto')) ?: 'Producto';
            $sku = trim((string) ($item['sku'] ?? ''));
            $itemRows .= '<tr><td style="padding:15px 0;border-bottom:1px solid #e5e7eb"><strong style="display:block;color:#0f172a">' . $escape($name) . '</strong>'
                . ($sku !== '' ? '<span style="font-size:12px;color:#94a3b8">Código: ' . $escape($sku) . '</span>' : '')
                . '</td><td style="padding:15px 8px;border-bottom:1px solid #e5e7eb;text-align:center;color:#475569">' . $quantity . '</td>'
                . '<td style="padding:15px 0;border-bottom:1px solid #e5e7eb;text-align:right;font-weight:700;color:#0f172a">' . $escape($money($item['line_total'] ?? 0)) . '</td></tr>';
            $textItems[] = $quantity . ' x ' . $name . ' — ' . $money($item['line_total'] ?? 0);
        }
        if ($itemRows === '') {
            $itemRows = '<tr><td colspan="3" style="padding:20px;text-align:center;color:#64748b">Productos incluidos en la orden</td></tr>';
        }

        $actionUrl = trim((string) ($role === 'store' ? ($data['admin_url'] ?? '') : ($data['customer_url'] ?? '')));
        $actionLabel = $role === 'store' ? 'Gestionar orden' : 'Ver mis pedidos';
        $action = filter_var($actionUrl, FILTER_VALIDATE_URL)
            ? '<a href="' . $escape($actionUrl) . '" style="display:inline-block;background:' . $escape($color) . ';color:#fff;text-decoration:none;font-weight:700;padding:13px 22px;border-radius:10px">' . $actionLabel . '</a>'
            : '';
        $headline = $role === 'store' ? 'Tienes una nueva orden para procesar' : '¡Gracias por tu compra!';
        $intro = $role === 'store'
            ? 'La orden fue registrada en la tienda web. Revisa los productos y contacta al cliente para darle seguimiento.'
            : 'Recibimos tu pedido correctamente. Nuestro equipo lo revisará y se pondrá en contacto contigo para coordinar los próximos pasos.';
        $address = trim(implode(', ', array_filter([
            trim((string) ($data['delivery_address'] ?? '')),
            trim((string) ($data['delivery_city'] ?? '')),
        ])));
        $customerDetails = $role === 'store'
            ? '<div style="margin-top:22px;padding:18px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px"><strong style="display:block;margin-bottom:10px;color:#0f172a">Datos del cliente</strong><div style="color:#475569;line-height:1.8">'
                . $escape($data['customer_name'] ?? '') . '<br>' . $escape($data['customer_phone'] ?? '') . '<br>' . $escape($data['customer_email'] ?? '')
                . (trim((string) ($data['customer_document'] ?? '')) !== '' ? '<br>Cédula/RNC: ' . $escape($data['customer_document']) : '')
                . ($address !== '' ? '<br>Dirección: ' . $escape($address) : '') . '</div></div>'
            : '';
        $notes = trim((string) ($data['customer_notes'] ?? ''));
        $notesHtml = $notes === '' ? '' : '<div style="margin-top:16px;padding:14px 16px;background:#fffbeb;border-left:4px solid #f59e0b;border-radius:8px;color:#78350f"><strong>Nota del pedido:</strong> ' . $escape($notes) . '</div>';

        $html = '<!doctype html><html><body style="margin:0;padding:24px;background:#f1f5f9;font-family:Arial,sans-serif;color:#0f172a"><div style="max-width:680px;margin:auto;background:#fff;border:1px solid #e2e8f0;border-radius:18px;overflow:hidden;box-shadow:0 18px 45px rgba(15,23,42,.08)">'
            . '<div style="padding:32px;background:linear-gradient(135deg,#0f172a,' . $escape($color) . ');color:#fff"><div style="font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#d1fae5">' . $escape($company) . '</div><h1 style="margin:9px 0 7px;font-size:28px">' . $headline . '</h1><div style="color:#e2e8f0">Orden <strong>' . $escape($number) . '</strong></div></div>'
            . '<div style="padding:30px"><p style="margin:0 0 24px;color:#475569;line-height:1.7">' . $intro . '</p>'
            . '<table style="width:100%;border-collapse:collapse"><thead><tr><th style="padding:10px 0;text-align:left;font-size:11px;color:#64748b;text-transform:uppercase">Producto</th><th style="padding:10px 8px;text-align:center;font-size:11px;color:#64748b;text-transform:uppercase">Cant.</th><th style="padding:10px 0;text-align:right;font-size:11px;color:#64748b;text-transform:uppercase">Importe</th></tr></thead><tbody>' . $itemRows . '</tbody></table>'
            . '<table style="width:100%;margin-top:18px;border-collapse:collapse;color:#475569"><tr><td style="padding:6px 0">Subtotal</td><td style="padding:6px 0;text-align:right">' . $escape($money($data['subtotal'] ?? 0)) . '</td></tr><tr><td style="padding:6px 0">Envío</td><td style="padding:6px 0;text-align:right">' . $escape($money($data['shipping_total'] ?? 0)) . '</td></tr><tr><td style="padding:14px 0 6px;border-top:2px solid #e2e8f0;font-size:18px;font-weight:800;color:#0f172a">Total</td><td style="padding:14px 0 6px;border-top:2px solid #e2e8f0;text-align:right;font-size:20px;font-weight:800;color:' . $escape($color) . '">' . $escape($money($data['total'] ?? 0)) . '</td></tr></table>'
            . '<div style="display:flex;margin-top:20px;padding:16px;background:#f8fafc;border-radius:12px;color:#475569;line-height:1.7"><div><strong style="color:#0f172a">Entrega:</strong> ' . $escape($delivery) . '<br><strong style="color:#0f172a">Pago:</strong> ' . $escape($payment) . '</div></div>'
            . $customerDetails . $notesHtml . '<div style="margin-top:26px;text-align:center">' . $action . '</div>'
            . '<p style="margin:26px 0 0;padding-top:20px;border-top:1px solid #e2e8f0;text-align:center;color:#94a3b8;font-size:12px">Mensaje automático y seguro de ' . $escape($company) . '.</p></div></div></body></html>';

        $text = $subject . "\n\n" . $intro . "\n\n" . implode("\n", $textItems)
            . "\n\nSubtotal: " . $money($data['subtotal'] ?? 0)
            . "\nEnvío: " . $money($data['shipping_total'] ?? 0)
            . "\nTotal: " . $money($data['total'] ?? 0)
            . "\nEntrega: " . $delivery . "\nPago: " . $payment
            . ($actionUrl !== '' ? "\n\n" . $actionUrl : '');
        return [$subject, $html, $text];
    }

    private function renderCashClosing(array $data): array
    {
        $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $money = static fn (mixed $value): string => 'RD$ ' . number_format((float) $value, 2, '.', ',');
        $date = static function (mixed $value) use ($escape): string {
            $raw = trim((string) $value);
            if ($raw === '') return '-';
            $time = strtotime($raw);
            return $time === false ? $escape($raw) : date('d/m/Y h:i A', $time);
        };
        $items = static fn (mixed $value): array => is_array($value) ? array_slice($value, 0, 100) : [];
        $company = trim((string) ($data['company_name'] ?? 'TMPOS')) ?: 'TMPOS';
        $shiftId = (int) ($data['shift_id'] ?? 0);
        $subject = 'Cierre de caja #' . $shiftId . ' - ' . $company;
        $difference = (float) ($data['difference'] ?? 0);
        $differenceColor = abs($difference) < 0.01 ? '#047857' : '#b91c1c';

        $salesRows = '';
        foreach ($items($data['sales'] ?? []) as $sale) {
            if (!is_array($sale)) continue;
            $salesRows .= '<tr><td style="padding:9px;border-bottom:1px solid #e5e7eb">' . $escape($sale['numero'] ?? '-') . '</td><td style="padding:9px;border-bottom:1px solid #e5e7eb">' . $escape($sale['cliente'] ?? 'Cliente General') . '</td><td style="padding:9px;border-bottom:1px solid #e5e7eb">' . $escape($sale['metodo_pago'] ?? 'EFECTIVO') . '</td><td style="padding:9px;border-bottom:1px solid #e5e7eb;text-align:right;font-weight:600">' . $money($sale['total'] ?? 0) . '</td></tr>';
        }
        if ($salesRows === '') $salesRows = '<tr><td colspan="4" style="padding:18px;text-align:center;color:#64748b">Sin ventas registradas</td></tr>';

        $expenseRows = '';
        foreach ($items($data['expenses'] ?? []) as $expense) {
            if (!is_array($expense)) continue;
            $expenseRows .= '<tr><td style="padding:9px;border-bottom:1px solid #e5e7eb">' . $date($expense['fecha'] ?? '') . '</td><td style="padding:9px;border-bottom:1px solid #e5e7eb">' . $escape($expense['descripcion'] ?? 'Gasto') . '</td><td style="padding:9px;border-bottom:1px solid #e5e7eb">' . $escape($expense['metodo_pago'] ?? 'EFECTIVO') . '</td><td style="padding:9px;border-bottom:1px solid #e5e7eb;text-align:right;color:#b91c1c;font-weight:600">-' . $money($expense['monto'] ?? 0) . '</td></tr>';
        }
        if ($expenseRows === '') $expenseRows = '<tr><td colspan="4" style="padding:18px;text-align:center;color:#64748b">Sin gastos registrados</td></tr>';

        $movementRows = '';
        foreach ($items($data['movements'] ?? []) as $movement) {
            if (!is_array($movement)) continue;
            $movementRows .= '<tr><td style="padding:9px;border-bottom:1px solid #e5e7eb">' . $date($movement['fecha'] ?? '') . '</td><td style="padding:9px;border-bottom:1px solid #e5e7eb">' . $escape($movement['tipo'] ?? '-') . '</td><td style="padding:9px;border-bottom:1px solid #e5e7eb">' . $escape($movement['descripcion'] ?? '-') . '</td><td style="padding:9px;border-bottom:1px solid #e5e7eb;text-align:right;font-weight:600">' . $money($movement['monto'] ?? 0) . '</td></tr>';
        }
        if ($movementRows === '') $movementRows = '<tr><td colspan="4" style="padding:18px;text-align:center;color:#64748b">Sin entradas ni retiros</td></tr>';

        $workshopOrders = $items(
            $data['workshop_orders'] ?? $data['ordenes_taller'] ?? $data['taller'] ?? $data['workshop'] ?? []
        );
        $workshopRows = '';
        $workshopCalculatedTotal = 0.0;
        $workshopCalculatedPaid = 0.0;
        $workshopCalculatedPending = 0.0;
        foreach ($workshopOrders as $order) {
            if (!is_array($order)) continue;
            $total = (float) ($order['total'] ?? $order['monto_total'] ?? $order['total_pagar'] ?? $order['monto'] ?? 0);
            $paid = (float) ($order['abono'] ?? $order['abonado'] ?? $order['total_abonado'] ?? $order['paid'] ?? $order['cobrado'] ?? 0);
            $pending = isset($order['pendiente']) || isset($order['saldo']) || isset($order['balance']) || isset($order['saldo_pendiente'])
                ? (float) ($order['pendiente'] ?? $order['saldo_pendiente'] ?? $order['saldo'] ?? $order['balance'] ?? 0)
                : max(0, $total - $paid);
            $workshopCalculatedTotal += $total;
            $workshopCalculatedPaid += $paid;
            $workshopCalculatedPending += $pending;
            $workshopRows .= '<tr><td style="padding:9px;border-bottom:1px solid #e5e7eb">' . $escape($order['no_orden'] ?? $order['numero'] ?? $order['orden'] ?? '-') . '</td>'
                . '<td style="padding:9px;border-bottom:1px solid #e5e7eb">' . $escape($order['cliente'] ?? $order['nombre'] ?? 'Cliente') . '</td>'
                . '<td style="padding:9px;border-bottom:1px solid #e5e7eb">' . $escape($order['equipo'] ?? $order['marca_modelo'] ?? $order['dispositivo'] ?? $order['descripcion'] ?? '-') . '</td>'
                . '<td style="padding:9px;border-bottom:1px solid #e5e7eb">' . $escape($order['estado'] ?? $order['status'] ?? '-') . '</td>'
                . '<td style="padding:9px;border-bottom:1px solid #e5e7eb;text-align:right">' . $money($paid) . '</td>'
                . '<td style="padding:9px;border-bottom:1px solid #e5e7eb;text-align:right;font-weight:700;color:#b45309">' . $money($pending) . '</td></tr>';
        }
        if ($workshopRows === '') $workshopRows = '<tr><td colspan="6" style="padding:18px;text-align:center;color:#64748b">Sin órdenes de taller en el cuadre</td></tr>';
        $workshopTotal = (float) ($data['workshop_total'] ?? $data['taller_total'] ?? $workshopCalculatedTotal);
        $workshopPaid = (float) ($data['workshop_collected'] ?? $data['taller_cobrado'] ?? $data['workshop_paid'] ?? $workshopCalculatedPaid);
        $workshopPending = (float) ($data['workshop_pending'] ?? $data['taller_pendiente'] ?? $workshopCalculatedPending);

        $receivables = $items(
            $data['accounts_receivable'] ?? $data['cuentas_por_cobrar'] ?? $data['cuentas_cobrar'] ?? $data['receivables'] ?? []
        );
        $receivableRows = '';
        $receivableCalculatedTotal = 0.0;
        $receivableCalculatedPaid = 0.0;
        $receivableCalculatedPending = 0.0;
        foreach ($receivables as $account) {
            if (!is_array($account)) continue;
            $total = (float) ($account['total'] ?? $account['monto_total'] ?? $account['total_factura'] ?? $account['monto'] ?? $account['amount'] ?? 0);
            $paid = (float) ($account['abono'] ?? $account['abonado'] ?? $account['total_abonado'] ?? $account['monto_pagado'] ?? $account['paid'] ?? $account['cobrado'] ?? 0);
            $pending = isset($account['pendiente']) || isset($account['saldo']) || isset($account['balance']) || isset($account['saldo_pendiente'])
                ? (float) ($account['pendiente'] ?? $account['saldo_pendiente'] ?? $account['saldo'] ?? $account['balance'] ?? 0)
                : max(0, $total - $paid);
            $receivableCalculatedTotal += $total;
            $receivableCalculatedPaid += $paid;
            $receivableCalculatedPending += $pending;
            $receivableRows .= '<tr><td style="padding:9px;border-bottom:1px solid #e5e7eb">' . $escape($account['no_factura'] ?? $account['factura'] ?? $account['numero'] ?? '-') . '</td>'
                . '<td style="padding:9px;border-bottom:1px solid #e5e7eb">' . $escape($account['cliente'] ?? $account['nombre'] ?? 'Cliente') . '</td>'
                . '<td style="padding:9px;border-bottom:1px solid #e5e7eb">' . $date($account['fecha_vencimiento'] ?? $account['fecha_limite'] ?? $account['proximo_pago'] ?? $account['vencimiento'] ?? $account['due_date'] ?? '') . '</td>'
                . '<td style="padding:9px;border-bottom:1px solid #e5e7eb;text-align:right">' . $money($total) . '</td>'
                . '<td style="padding:9px;border-bottom:1px solid #e5e7eb;text-align:right">' . $money($paid) . '</td>'
                . '<td style="padding:9px;border-bottom:1px solid #e5e7eb;text-align:right;font-weight:700;color:#b91c1c">' . $money($pending) . '</td></tr>';
        }
        if ($receivableRows === '') $receivableRows = '<tr><td colspan="6" style="padding:18px;text-align:center;color:#64748b">Sin cuentas por cobrar en el cuadre</td></tr>';
        $receivableTotal = (float) ($data['accounts_receivable_total'] ?? $data['cuentas_cobrar_total'] ?? $data['receivables_total'] ?? $receivableCalculatedTotal);
        $receivablePaid = (float) ($data['accounts_receivable_collected'] ?? $data['cuentas_cobrar_cobrado'] ?? $data['receivables_collected'] ?? $receivableCalculatedPaid);
        $receivablePending = (float) ($data['accounts_receivable_pending'] ?? $data['cuentas_cobrar_pendiente'] ?? $data['receivables_pending'] ?? $receivableCalculatedPending);

        $observation = trim((string) ($data['observation'] ?? ''));
        $observationHtml = $observation === '' ? '' : '<div style="margin-top:22px;padding:14px 16px;background:#fffbeb;border-left:4px solid #f59e0b;border-radius:6px"><strong>Observacion:</strong> ' . $escape($observation) . '</div>';
        $truncatedHtml = empty($data['details_truncated']) ? '' : '<div style="margin-top:14px;padding:12px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:7px;color:#475569;font-size:12px">El correo muestra hasta 50 registros por seccion. Los totales incluyen todas las operaciones del turno.</div>';
        $html = '<!doctype html><html><body style="margin:0;background:#f1f5f9;font-family:Arial,sans-serif;color:#0f172a;padding:24px"><div style="max-width:780px;margin:auto;background:#fff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 12px 30px rgba(15,23,42,.08)">'
            . '<div style="padding:28px;background:linear-gradient(135deg,#064e3b,#047857);color:#fff"><div style="font-size:12px;letter-spacing:2px;color:#a7f3d0">REPORTE OFICIAL</div><h1 style="margin:7px 0 5px;font-size:28px">Cierre de caja #' . $shiftId . '</h1><div>' . $escape($company) . ' &bull; ' . $date($data['closed_at'] ?? '') . '</div></div>'
            . '<div style="padding:26px"><table style="width:100%;margin-bottom:20px"><tr><td><div style="font-size:11px;color:#64748b">CAJERO</div><strong>' . $escape($data['cashier'] ?? 'Usuario') . '</strong></td><td><div style="font-size:11px;color:#64748b">APERTURA</div><strong>' . $date($data['opened_at'] ?? '') . '</strong></td><td><div style="font-size:11px;color:#64748b">DURACION</div><strong>' . $escape($data['duration'] ?? '-') . '</strong></td></tr></table>'
            . '<table style="width:100%;border-spacing:8px"><tr><td style="padding:17px;background:#ecfdf5;border-radius:10px"><div style="font-size:11px;color:#047857">VENTAS (' . (int) ($data['sales_count'] ?? 0) . ')</div><strong style="font-size:21px">' . $money($data['sales_total'] ?? 0) . '</strong></td><td style="padding:17px;background:#eff6ff;border-radius:10px"><div style="font-size:11px;color:#1d4ed8">EFECTIVO ESPERADO</div><strong style="font-size:21px">' . $money($data['expected_cash'] ?? 0) . '</strong></td><td style="padding:17px;background:#f8fafc;border-radius:10px"><div style="font-size:11px;color:#475569">DIFERENCIA</div><strong style="font-size:21px;color:' . $differenceColor . '">' . $money($difference) . '</strong></td></tr></table>'
            . '<table style="width:100%;border-spacing:8px"><tr><td style="padding:15px;background:#fff7ed;border-radius:10px"><div style="font-size:11px;color:#c2410c">TALLER (' . count($workshopOrders) . ')</div><strong style="font-size:18px">' . $money($workshopPaid) . ' cobrado</strong><div style="font-size:12px;color:#9a3412">Pendiente: ' . $money($workshopPending) . '</div></td><td style="padding:15px;background:#fef2f2;border-radius:10px"><div style="font-size:11px;color:#b91c1c">CUENTAS POR COBRAR (' . count($receivables) . ')</div><strong style="font-size:18px">' . $money($receivablePaid) . ' cobrado</strong><div style="font-size:12px;color:#991b1b">Saldo: ' . $money($receivablePending) . '</div></td></tr></table>'
            . '<h2 style="margin-top:25px;font-size:17px;border-bottom:2px solid #e2e8f0;padding-bottom:8px">Resumen financiero</h2><table style="width:100%;border-collapse:collapse"><tr><td style="padding:7px">Fondo inicial</td><td style="text-align:right">' . $money($data['opening_amount'] ?? 0) . '</td><td style="padding:7px">Ventas efectivo</td><td style="text-align:right">' . $money($data['cash_sales'] ?? 0) . '</td></tr><tr><td style="padding:7px">Tarjeta</td><td style="text-align:right">' . $money($data['card_sales'] ?? 0) . '</td><td style="padding:7px">Transferencia</td><td style="text-align:right">' . $money($data['transfer_sales'] ?? 0) . '</td></tr><tr><td style="padding:7px">Entradas</td><td style="text-align:right;color:#047857">+' . $money($data['entries_total'] ?? 0) . '</td><td style="padding:7px">Gastos</td><td style="text-align:right;color:#b91c1c">-' . $money($data['expenses_total'] ?? 0) . '</td></tr><tr><td style="padding:7px">Retiros</td><td style="text-align:right;color:#b91c1c">-' . $money($data['withdrawals_total'] ?? 0) . '</td><td style="padding:7px;font-weight:700">Efectivo contado</td><td style="text-align:right;font-weight:700">' . $money($data['counted_cash'] ?? 0) . '</td></tr></table>'
            . '<h2 style="margin-top:25px;font-size:17px;border-bottom:2px solid #e2e8f0;padding-bottom:8px">Ventas del turno</h2><table style="width:100%;border-collapse:collapse;font-size:13px"><thead><tr style="background:#f8fafc"><th style="padding:9px;text-align:left">Factura</th><th style="text-align:left">Cliente</th><th style="text-align:left">Metodo</th><th style="text-align:right">Total</th></tr></thead><tbody>' . $salesRows . '</tbody></table>'
            . '<h2 style="margin-top:25px;font-size:17px;border-bottom:2px solid #e2e8f0;padding-bottom:8px">Órdenes de taller</h2><div style="margin-bottom:10px;color:#64748b;font-size:12px">Total: ' . $money($workshopTotal) . ' &bull; Cobrado: ' . $money($workshopPaid) . ' &bull; Pendiente: ' . $money($workshopPending) . '</div><table style="width:100%;border-collapse:collapse;font-size:12px"><thead><tr style="background:#fff7ed"><th style="padding:9px;text-align:left">Orden</th><th style="text-align:left">Cliente</th><th style="text-align:left">Equipo</th><th style="text-align:left">Estado</th><th style="text-align:right">Abonado</th><th style="text-align:right">Pendiente</th></tr></thead><tbody>' . $workshopRows . '</tbody></table>'
            . '<h2 style="margin-top:25px;font-size:17px;border-bottom:2px solid #e2e8f0;padding-bottom:8px">Cuentas por cobrar</h2><div style="margin-bottom:10px;color:#64748b;font-size:12px">Total: ' . $money($receivableTotal) . ' &bull; Cobrado: ' . $money($receivablePaid) . ' &bull; Saldo pendiente: ' . $money($receivablePending) . '</div><table style="width:100%;border-collapse:collapse;font-size:12px"><thead><tr style="background:#fef2f2"><th style="padding:9px;text-align:left">Factura</th><th style="text-align:left">Cliente</th><th style="text-align:left">Vencimiento</th><th style="text-align:right">Total</th><th style="text-align:right">Abonado</th><th style="text-align:right">Saldo</th></tr></thead><tbody>' . $receivableRows . '</tbody></table>'
            . '<h2 style="margin-top:25px;font-size:17px;border-bottom:2px solid #e2e8f0;padding-bottom:8px">Gastos</h2><table style="width:100%;border-collapse:collapse;font-size:13px"><tbody>' . $expenseRows . '</tbody></table>'
            . '<h2 style="margin-top:25px;font-size:17px;border-bottom:2px solid #e2e8f0;padding-bottom:8px">Entradas y retiros</h2><table style="width:100%;border-collapse:collapse;font-size:13px"><tbody>' . $movementRows . '</tbody></table>'
            . $observationHtml . $truncatedHtml . '<div style="margin-top:28px;padding-top:18px;border-top:1px solid #e2e8f0;color:#64748b;font-size:12px;text-align:center">Reporte generado automaticamente por TMPOS y enviado de forma segura por TMPBASE.</div></div></div></body></html>';

        $text = $subject
            . "\nCajero: " . (string) ($data['cashier'] ?? 'Usuario')
            . "\nApertura: " . (string) ($data['opened_at'] ?? '-')
            . "\nCierre: " . (string) ($data['closed_at'] ?? '-')
            . "\nVentas: " . $money($data['sales_total'] ?? 0)
            . "\nTaller total: " . $money($workshopTotal)
            . "\nTaller cobrado: " . $money($workshopPaid)
            . "\nTaller pendiente: " . $money($workshopPending)
            . "\nCuentas por cobrar total: " . $money($receivableTotal)
            . "\nCuentas por cobrar cobrado: " . $money($receivablePaid)
            . "\nCuentas por cobrar pendiente: " . $money($receivablePending)
            . "\nEfectivo esperado: " . $money($data['expected_cash'] ?? 0)
            . "\nEfectivo contado: " . $money($data['counted_cash'] ?? 0)
            . "\nDiferencia: " . $money($difference);
        return [$subject, $html, $text];
    }
}
