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
 * Check if Graph configuration is complete.
 */
function isGraphConfigured(): bool {
  return GRAPH_TENANT_ID !== '' && GRAPH_CLIENT_ID !== '' && GRAPH_CLIENT_SECRET !== '' && GRAPH_SENDER !== '';
}

/**
 * Acquire app-only access token for Microsoft Graph.
 */
function graphAccessToken(): ?string {
  if (!isGraphConfigured()) {
    return null;
  }

  if (!function_exists('curl_init')) {
    error_log('Graph mail error: cURL extension is not enabled.');
    return null;
  }

  $url = 'https://login.microsoftonline.com/' . rawurlencode(GRAPH_TENANT_ID) . '/oauth2/v2.0/token';
  $postFields = http_build_query([
    'client_id' => GRAPH_CLIENT_ID,
    'client_secret' => GRAPH_CLIENT_SECRET,
    'scope' => 'https://graph.microsoft.com/.default',
    'grant_type' => 'client_credentials',
  ]);

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postFields,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_TIMEOUT => 5,
    CURLOPT_CONNECTTIMEOUT => 3,
  ]);

  $response = curl_exec($ch);
  $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);

  if ($response === false || $status >= 400) {
    error_log('Graph token error: HTTP ' . $status . ' ' . $err . ' ' . (string)$response);
    return null;
  }

  $json = json_decode($response, true);
  return is_array($json) ? ($json['access_token'] ?? null) : null;
}

/**
 * Send email via Microsoft Graph API.
 */
function sendEmailViaGraph(string $toEmail, string $toName, string $subject, string $htmlBody, string $plainBody = ''): bool {
  $token = graphAccessToken();
  if (!$token) {
    return false;
  }

  if (!function_exists('curl_init')) {
    error_log('Graph mail error: cURL extension is not enabled.');
    return false;
  }

  $payload = [
    'message' => [
      'subject' => $subject,
      'body' => [
        'contentType' => 'HTML',
        'content' => $htmlBody,
      ],
      'toRecipients' => [[
        'emailAddress' => [
          'address' => $toEmail,
          'name' => $toName,
        ],
      ]],
    ],
    'saveToSentItems' => true,
  ];

  // Keep plain text in body for compatibility with existing call signature.
  if ($plainBody !== '') {
    $payload['message']['internetMessageHeaders'] = [[
      'name' => 'X-Alt-Text',
      'value' => $plainBody,
    ]];
  }

    $logoPath = BASE_PATH . '/assets/images/' . APP_LOGO_FILE;
    if (APP_EMBED_EMAIL_LOGO && strpos($htmlBody, 'cid:' . APP_EMAIL_LOGO_CID) !== false && file_exists($logoPath)) {
      $payload['message']['attachments'] = [
          [
             '@odata.type' => '#microsoft.graph.fileAttachment',
         'name' => basename($logoPath),
         'contentType' => 'image/svg+xml',
             'contentBytes' => base64_encode(file_get_contents($logoPath)),
             'isInline' => true,
         'contentId' => APP_EMAIL_LOGO_CID
          ]
      ];
  }

  $url = 'https://graph.microsoft.com/v1.0/users/' . rawurlencode(GRAPH_SENDER) . '/sendMail';
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
      'Authorization: Bearer ' . $token,
      'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    CURLOPT_TIMEOUT => 5,
    CURLOPT_CONNECTTIMEOUT => 3,
  ]);

  $response = curl_exec($ch);
  $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);

  if ($response === false || ($status !== 202 && $status !== 200)) {
    error_log('Graph sendMail error: HTTP ' . $status . ' ' . $err . ' ' . (string)$response);
    return false;
  }

  return true;
}

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
    $mail->Timeout = 5;          // SMTP timeout: 5 seconds (fail fast)
    $mail->SMTPKeepAlive = true; // Reuse connection for multiple emails
    return $mail;
}

/**
 * Send a simple HTML email.
 */
function sendEmail(string $toEmail, string $toName, string $subject, string $htmlBody, string $plainBody = ''): bool {
  if (MAIL_DRIVER === 'graph') {
    return sendEmailViaGraph($toEmail, $toName, $subject, $htmlBody, $plainBody);
  }

  if (empty(MAIL_PASSWORD)) {
      error_log('Mailer skipped: MAIL_PASSWORD is empty. Emails disabled for development.');
      return false;
  }

    try {
        $mail = getMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $plainBody ?: strip_tags($htmlBody);

        $logoPath = BASE_PATH . '/assets/images/' . APP_LOGO_FILE;
        if (APP_EMBED_EMAIL_LOGO && strpos($htmlBody, 'cid:' . APP_EMAIL_LOGO_CID) !== false && file_exists($logoPath)) {
          $mail->addEmbeddedImage($logoPath, APP_EMAIL_LOGO_CID, basename($logoPath));
        }

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
    $portalName = SUPPORT_PORTAL_NAME;
    $logoBlock = '';
    if (APP_EMBED_EMAIL_LOGO) {
        $logoUrl = 'cid:' . APP_EMAIL_LOGO_CID;
        $logoAlt = htmlspecialchars(APP_LOGO_ALT, ENT_QUOTES, 'UTF-8');
        $logoBlock = "<td width=\"60\" valign=\"middle\">" .
               "<img src=\"{$logoUrl}\" alt=\"{$logoAlt}\" width=\"45\" height=\"45\" style=\"display:block;border-radius:4px;\">" .
               "</td>";
    }
    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>{$title}</title></head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:30px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1);">
      <tr><td style="background:#1a3a5c;padding:20px 30px;">
        <table width="100%" cellpadding="0" cellspacing="0">
          <tr>
            {$logoBlock}
            <td valign="middle">
              <h1 style="margin:0;color:#fff;font-size:20px;">{$appName}</h1>
              <p style="margin:4px 0 0;color:#adc8e8;font-size:13px;">{$portalName}</p>
            </td>
          </tr>
        </table>
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
