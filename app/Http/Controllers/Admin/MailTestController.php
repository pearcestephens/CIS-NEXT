<?php
declare(strict_types=1);

namespace CIS\Http\Controllers\Admin;

use CIS\Shared\Mail\Mailer;
use CIS\Shared\Mail\MailMessage;
use CIS\Shared\Logging\Logger;

final class MailTestController
{
    public function index(): void {
        require __DIR__ . '/../../Views/admin/tools/mail.php';
    }

    public function send(): void {
        header('Content-Type: application/json');
        try {
            $to    = $_POST['to']    ?? '';
            $name  = $_POST['name']  ?? 'Test User';
            $subj  = $_POST['subj']  ?? 'CIS Test Email';
            $html  = $_POST['html']  ?? '<p>Hello from CIS V2.</p>';

            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                http_response_code(422);
                echo json_encode(['ok'=>false,'error'=>'invalid_email']);
                return;
            }
            $mailer = new Mailer();
            $msg    = new MailMessage($to, $name, $subj, $html);
            $res    = $mailer->send($msg);
            echo json_encode(['ok'=>$res['ok'] ?? false, 'result'=>$res]);
        } catch (\Throwable $e) {
            Logger::error('mailtest.error', ['error'=>$e->getMessage()]);
            http_response_code(500);
            echo json_encode(['ok'=>false,'error'=>'server_error']);
        }
    }
}
