<?php
// ============================================================
// PHPMailer Factory
// ============================================================
require_once __DIR__ . '/constants.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once VENDOR_PATH . '/phpmailer/src/Exception.php';
require_once VENDOR_PATH . '/phpmailer/src/PHPMailer.php';
require_once VENDOR_PATH . '/phpmailer/src/SMTP.php';

/**
 * Returns a pre-configured PHPMailer instance.
 * Caller sets: addAddress(), Subject, Body, AltBody then calls send().
 */
function getMailer(): PHPMailer {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = MAIL_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = MAIL_USERNAME;
    $mail->Password   = MAIL_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = MAIL_PORT;
    $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    return $mail;
}

/**
 * Send a simple HTML email.
 */
function sendEmail(string $toEmail, string $toName, string $subject, string $htmlBody, string $plainBody = ''): bool {
    try {
        $mail = getMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $plainBody ?: strip_tags($htmlBody);
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mailer error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Build a branded HTML email wrapper.
 */
function emailTemplate(string $title, string $content): string {
    $appName = APP_NAME;
    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>{$title}</title></head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:30px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1);">
      <tr><td style="background:#1a3a5c;padding:20px 30px;">
        <h1 style="margin:0;color:#fff;font-size:20px;">{$appName}</h1>
        <p style="margin:4px 0 0;color:#adc8e8;font-size:13px;">IT Support Ticket System</p>
      </td></tr>
      <tr><td style="padding:30px;">
        {$content}
      </td></tr>
      <tr><td style="background:#f4f6f9;padding:15px 30px;text-align:center;">
        <p style="margin:0;color:#999;font-size:12px;">This is an automated message from {$appName}.<br>Please do not reply to this email.</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}
