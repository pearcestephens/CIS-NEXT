<?php
declare(strict_types=1);

namespace CIS\Shared\Mail;

use CIS\Shared\Config\Env;
use CIS\Shared\Logging\Logger;

final class Mailer {
    private string $driver;
    private string $fromEmail;
    private string $fromName;
    private ?string $sgApiKey;

    public function __construct() {
        $this->driver    = strtolower((string)Env::get('MAIL_DRIVER', 'sendgrid_api'));
        $this->fromEmail = (string)Env::get('MAIL_FROM_ADDRESS', 'noreply@example.com');
        $this->fromName  = (string)Env::get('MAIL_FROM_NAME',   'CIS System');
        $this->sgApiKey  = Env::get('SENDGRID_API_KEY') ?: null;
    }

    public function send(MailMessage $msg): array {
        $t0 = microtime(true);
        try {
            switch ($this->driver) {
                case 'sendgrid_api':
                    $res = $this->sendViaSendgridApi($msg);
                    break;
                case 'php_mail':
                    $res = $this->sendViaPhpMail($msg);
                    break;
                default:
                    throw new \RuntimeException("Unknown MAIL_DRIVER '{$this->driver}'");
            }
            $latency = (int)round((microtime(true) - $t0) * 1000);
            Logger::info('mail.sent', [
                'driver'   => $this->driver,
                'to'       => $this->maskEmail($msg->toEmail),
                'subject'  => $msg->subject,
                'latency_ms' => $latency
            ]);
            return ['ok' => true, 'driver' => $this->driver, 'latency_ms' => $latency] + $res;
        } catch (\Throwable $e) {
            Logger::error('mail.error', [
                'driver'  => $this->driver,
                'to'      => $this->maskEmail($msg->toEmail),
                'error'   => $e->getMessage()
            ]);
            return ['ok' => false, 'error' => $e->getMessage(), 'driver' => $this->driver];
        }
    }

    private function sendViaSendgridApi(MailMessage $m): array {
        if (!$this->sgApiKey) {
            throw new \RuntimeException('SENDGRID_API_KEY missing');
        }
        $payload = [
            'personalizations' => [[
                'to'      => [['email' => $m->toEmail, 'name' => $m->toName]],
                'subject' => $m->subject
            ]],
            'from' => ['email' => $this->fromEmail, 'name' => $this->fromName],
            'content' => [
                ['type' => 'text/plain', 'value' => $m->text],
                ['type' => 'text/html',  'value' => $m->html],
            ]
        ];
        $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->sgApiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HEADER         => true,
        ]);
        $raw = curl_exec($ch);
        $info = curl_getinfo($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        $status = (int)($info['http_code'] ?? 0);
        if ($raw !== false && isset($info['header_size'])) {
            $headers = substr($raw, 0, $info['header_size']);
            $body    = substr($raw, $info['header_size']);
        } else {
            $headers = ''; $body = '';
        }

        if ($err) {
            throw new \RuntimeException("SendGrid cURL error: {$err}");
        }
        // Success codes: 202 Accepted (queued)
        if ($status !== 202) {
            throw new \RuntimeException("SendGrid HTTP {$status}: {$body}");
        }
        return ['http_code' => $status];
    }

    private function sendViaPhpMail(MailMessage $m): array {
        // Basic MIME email using built-in mail() as a fallback
        $boundary = 'b_' . bin2hex(random_bytes(8));
        $headers  = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'From: ' . $this->fromName . ' <' . $this->fromEmail . '>';
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $body .= $m->text . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $body .= $m->html . "\r\n";
        $body .= "--{$boundary}--";

        $ok = mail($m->toEmail, '=?UTF-8?B?' . base64_encode($m->subject) . '?=',
                   $body, implode("\r\n", $headers));
        if (!$ok) {
            throw new \RuntimeException('php mail() returned false');
        }
        return ['transport' => 'php_mail'];
    }

    private function maskEmail(string $email): string {
        $parts = explode('@', $email, 2);
        if (count($parts) < 2) return '***';
        $local = $parts[0];
        $masked = substr($local, 0, 1) . str_repeat('*', max(0, strlen($local) - 2)) . substr($local, -1);
        return $masked . '@' . $parts[1];
    }
}
