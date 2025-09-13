<?php
declare(strict_types=1);

namespace CIS\Shared\Mail;

final class MailMessage {
    public string $toEmail;
    public string $toName;
    public string $subject;
    public string $html;
    public ?string $text;

    public function __construct(
        string $toEmail,
        string $toName,
        string $subject,
        string $html,
        ?string $text = null
    ) {
        $this->toEmail = $toEmail;
        $this->toName  = $toName;
        $this->subject = $subject;
        $this->html    = $html;
        $this->text    = $text ?? strip_tags($html);
    }
}
