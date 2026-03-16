<?php
// ============================================================
// Notification Helpers — with deferred email queue
// ============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/mailer.php';
require_once __DIR__ . '/../includes/functions.php';

// ── Deferred Email Queue ───────────────────────────────────
// Emails are queued in memory during request processing.
// After the ticket is created and the redirect is sent,
// sendResponseAndFlushEmails() closes the HTTP connection
// and sends emails in the background.
//
// NOTE: register_shutdown_function() does NOT work for this
// on Apache mod_php (XAMPP/LAMPP) — Apache buffers the
// entire response until PHP dies, including shutdown handlers.
// We must explicitly close the connection using HTTP headers.

/** @var array[] Queued emails to send after response */
$_EMAIL_QUEUE = [];

/**
 * Queue an email for deferred sending.
 */
function queueEmail(string $toEmail, string $toName, string $subject, string $htmlBody): void {
    global $_EMAIL_QUEUE;
    $_EMAIL_QUEUE[] = compact('toEmail', 'toName', 'subject', 'htmlBody');
}

/**
 * Flush the email queue — sends all queued emails.
 */
function flushEmailQueue(): void {
    global $_EMAIL_QUEUE;
    if (empty($_EMAIL_QUEUE)) return;

    foreach ($_EMAIL_QUEUE as $mail) {
        try {
            sendEmail($mail['toEmail'], $mail['toName'], $mail['subject'], $mail['htmlBody']);
        } catch (Throwable $e) {
            error_log('Deferred email error: ' . $e->getMessage());
        }
    }
    $_EMAIL_QUEUE = [];
}

/**
 * Send the HTTP response (redirect) to the browser immediately,
 * then flush the email queue in the background.
 *
 * Works on Apache mod_php (XAMPP/LAMPP), PHP-FPM, LiteSpeed, etc.
 *
 * @param string $redirectUrl  The URL to redirect the browser to
 */
function sendResponseAndFlushEmails(string $redirectUrl): void {
    global $_EMAIL_QUEUE;

    // If no emails queued, just redirect normally
    if (empty($_EMAIL_QUEUE)) {
        header('Location: ' . $redirectUrl);
        exit;
    }

    // ── Close the HTTP connection BEFORE sending emails ──
    // This tells the browser "we're done, go to the redirect URL"
    // while PHP continues running in the background to send emails.

    ignore_user_abort(true);
    set_time_limit(120); // Allow up to 2 minutes for emails

    // Write a minimal redirect body
    $body = '<html><head><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($redirectUrl, ENT_QUOTES) . '"></head>'
          . '<body>Redirecting...</body></html>';

    // 1. Send redirect + connection close headers
    header('Location: ' . $redirectUrl);
    header('Connection: close');
    header('Content-Length: ' . strlen($body));

    // 2. Flush all output buffers
    while (ob_get_level() > 0) {
        ob_end_flush();
    }

    // 3. Write body and flush to Apache
    echo $body;
    flush();

    // 4. If PHP-FPM or LiteSpeed, use their native finish functions
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } elseif (function_exists('litespeed_finish_request')) {
        litespeed_finish_request();
    }

    // ── Browser has now moved on — send emails in background ──
    // Close the session so it doesn't block other requests
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    flushEmailQueue();
    exit;
}


/**
 * Core notification dispatcher.
 * Saves to DB immediately. Emails are QUEUED (not sent inline).
 *
 * @param array $params {
 *   recipient_id   int
 *   recipient_type 'user'|'staff'
 *   message        string   (in-app message)
 *   ticket_id      int|null
 *   email          string   (recipient email)
 *   name           string   (recipient name)
 *   subject        string   (email subject)
 *   email_body     string   (HTML email body)
 * }
 */
function dispatchNotification(array $params): void {
    $pdo = getDB();

    // 1. Save in-app notification (instant)
    try {
        $pdo->prepare("
            INSERT INTO notifications (recipient_id, recipient_type, message, ticket_id)
            VALUES (?, ?, ?, ?)
        ")->execute([
            $params['recipient_id'],
            $params['recipient_type'],
            $params['message'],
            $params['ticket_id'] ?? null,
        ]);
    } catch (Throwable $e) {
        error_log('Notification DB insert error: ' . $e->getMessage());
    }

    // 2. Queue email for deferred sending (non-blocking)
    if (!empty($params['email']) && !empty($params['subject'])) {
        $body = $params['email_body'] ?? emailTemplate($params['subject'], '<p>' . nl2br(h($params['message'])) . '</p>');
        queueEmail($params['email'], $params['name'] ?? '', $params['subject'], $body);
    }

}

/**
 * Notify all leadership (ICT Head, Assistant Manager, Assistant ICT) about a new ticket.
 */
function notifyAllLeadership(int $ticketId, string $ticketNumber, string $userName, string $category, string $userDesignation = ''): void {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT id, name, email, contact FROM it_staff WHERE role IN ('ict_head','assistant_manager','assistant_ict') AND is_active = 1");
    $stmt->execute();
    $leaders = $stmt->fetchAll();

    $raisedByDisplay = h($userName);
    if ($userDesignation !== '') {
        $raisedByDisplay .= ' (' . h($userDesignation) . ')';
    }

    foreach ($leaders as $leader) {
        $msg  = "New ticket {$ticketNumber} raised by {$userName}. Problem: {$category}. Please review and assign.";
        $body = emailTemplate("New IT Support Ticket — {$ticketNumber}", "
            <p>Dear {$leader['name']},</p>
            <p>A new support ticket has been raised by <strong>{$raisedByDisplay}</strong>.</p>
            <table style='width:100%;border-collapse:collapse;'>
              <tr><td style='padding:8px;background:#f4f6f9;font-weight:bold;'>Ticket No</td><td style='padding:8px;'>{$ticketNumber}</td></tr>
              <tr><td style='padding:8px;background:#f4f6f9;font-weight:bold;'>Problem</td><td style='padding:8px;'>{$category}</td></tr>
            </table>
            <p style='margin-top:20px;'><a href='" . APP_URL . "/staff/ticket_detail?id={$ticketId}' style='background:#1a3a5c;color:#fff;padding:10px 20px;border-radius:5px;text-decoration:none;'>View Ticket</a></p>
        ");

        dispatchNotification([
            'recipient_id'   => $leader['id'],
            'recipient_type' => 'staff',
            'message'        => $msg,
            'ticket_id'      => $ticketId,
            'email'          => $leader['email'],
            'name'           => $leader['name'],
            'subject'        => "New Ticket: {$ticketNumber} — {$category}",
            'email_body'     => $body,
        ]);
    }
}

/**
 * Notify user about their ticket being received.
 */
function notifyUserTicketCreated(int $userId, int $ticketId, string $ticketNumber, string $staffName, ?array $userData = null): void {
    if ($userData) {
        $user = $userData;
    } else {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT name, email, phone FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
    }
    if (!$user) return;

    $msg  = "Your ticket {$ticketNumber} has been raised successfully and assigned to {$staffName}. We will get back to you shortly.";
    $body = emailTemplate("Ticket {$ticketNumber} Received", "
        <p>Dear {$user['name']},</p>
        <p>Your support ticket has been received and assigned to our IT team.</p>
        <table style='width:100%;border-collapse:collapse;'>
          <tr><td style='padding:8px;background:#f4f6f9;font-weight:bold;'>Ticket No</td><td style='padding:8px;'>{$ticketNumber}</td></tr>
          <tr><td style='padding:8px;background:#f4f6f9;font-weight:bold;'>Assigned To</td><td style='padding:8px;'>{$staffName}</td></tr>
          <tr><td style='padding:8px;background:#f4f6f9;font-weight:bold;'>Status</td><td style='padding:8px;'><span style='background:#17a2b8;color:#fff;padding:2px 8px;border-radius:3px;'>Notified</span></td></tr>
        </table>
        <p style='margin-top:20px;'><a href='" . APP_URL . "/user/ticket_detail?id={$ticketId}' style='background:#1a3a5c;color:#fff;padding:10px 20px;border-radius:5px;text-decoration:none;'>Track Ticket</a></p>
    ");

    dispatchNotification([
        'recipient_id'   => $userId,
        'recipient_type' => 'user',
        'message'        => $msg,
        'ticket_id'      => $ticketId,
        'email'          => $user['email'],
        'name'           => $user['name'],
        'subject'        => "Ticket {$ticketNumber} Received",
        'email_body'     => $body,
    ]);
}

/**
 * Notify assigned staff of new assignment.
 */
function notifyStaffAssigned(int $staffId, int $ticketId, string $ticketNumber, string $assignedByName, string $category, string $notes = ''): void {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT name, email, contact FROM it_staff WHERE id = ?");
    $stmt->execute([$staffId]);
    $staff = $stmt->fetch();
    if (!$staff) return;

    // Fetch user designation
    $reqStmt = $pdo->prepare("SELECT u.name, u.designation FROM tickets t JOIN users u ON t.user_id = u.id WHERE t.id = ?");
    $reqStmt->execute([$ticketId]);
    $requestor = $reqStmt->fetch();
    $reqName = $requestor['name'] ?? 'User';
    $reqDesigStr = !empty($requestor['designation']) ? " ({$requestor['designation']})" : "";
    $raisedByDisplay = h($reqName) . h($reqDesigStr);

    $msg  = "Ticket {$ticketNumber} ({$category}) has been assigned to you by {$assignedByName}. " . ($notes ? "Note: {$notes}" : "");
    $body = emailTemplate("Ticket Assigned: {$ticketNumber}", "
        <p>Dear {$staff['name']},</p>
        <p>A support ticket has been assigned to you by <strong>{$assignedByName}</strong>.</p>
        <table style='width:100%;border-collapse:collapse;'>
          <tr><td style='padding:8px;background:#f4f6f9;font-weight:bold;'>Ticket No</td><td style='padding:8px;'>{$ticketNumber}</td></tr>
          <tr><td style='padding:8px;background:#f4f6f9;font-weight:bold;'>Raised By</td><td style='padding:8px;'>{$raisedByDisplay}</td></tr>
          <tr><td style='padding:8px;background:#f4f6f9;font-weight:bold;'>Problem</td><td style='padding:8px;'>{$category}</td></tr>
          <tr><td style='padding:8px;background:#f4f6f9;font-weight:bold;'>Note</td><td style='padding:8px;'>" . h($notes ?: 'No additional notes') . "</td></tr>
        </table>
        <p style='margin-top:20px;'><a href='" . APP_URL . "/staff/ticket_detail?id={$ticketId}' style='background:#1a3a5c;color:#fff;padding:10px 20px;border-radius:5px;text-decoration:none;'>View & Update Ticket</a></p>
    ");

    dispatchNotification([
        'recipient_id'   => $staffId,
        'recipient_type' => 'staff',
        'message'        => $msg,
        'ticket_id'      => $ticketId,
        'email'          => $staff['email'],
        'name'           => $staff['name'],
        'subject'        => "Ticket Assigned: {$ticketNumber}",
        'email_body'     => $body,
    ]);
}

/**
 * Notify user about status change.
 */
function notifyUserStatusChange(int $userId, int $ticketId, string $ticketNumber, string $newStatus): void {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT name, email, phone FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) return;

    $statusText = statusLabel($newStatus);
    $msg  = "Your ticket {$ticketNumber} status has been updated to: {$statusText}.";
    if ($newStatus === STATUS_SOLVED) {
        $msg .= " Please submit your feedback.";
    }

    $feedbackLink = ($newStatus === STATUS_SOLVED)
        ? "<p style='margin-top:15px;'><a href='" . APP_URL . "/user/feedback?ticket_id={$ticketId}' style='background:#28a745;color:#fff;padding:10px 20px;border-radius:5px;text-decoration:none;'>Submit Feedback</a></p>"
        : "<p style='margin-top:15px;'><a href='" . APP_URL . "/user/ticket_detail?id={$ticketId}' style='background:#1a3a5c;color:#fff;padding:10px 20px;border-radius:5px;text-decoration:none;'>Track Ticket</a></p>";

    $body = emailTemplate("Ticket {$ticketNumber} — Status Updated", "
        <p>Dear {$user['name']},</p>
        <p>The status of your support ticket has been updated.</p>
        <table style='width:100%;border-collapse:collapse;'>
          <tr><td style='padding:8px;background:#f4f6f9;font-weight:bold;'>Ticket No</td><td style='padding:8px;'>{$ticketNumber}</td></tr>
          <tr><td style='padding:8px;background:#f4f6f9;font-weight:bold;'>New Status</td><td style='padding:8px;'>{$statusText}</td></tr>
        </table>
        {$feedbackLink}
    ");

    dispatchNotification([
        'recipient_id'   => $userId,
        'recipient_type' => 'user',
        'message'        => $msg,
        'ticket_id'      => $ticketId,
        'email'          => $user['email'],
        'name'           => $user['name'],
        'subject'        => "Ticket {$ticketNumber} — Status: {$statusText}",
        'email_body'     => $body,
    ]);
}

/**
 * Notify ICT Heads + Asst Managers of a status update (for awareness).
 */
function notifyManagementStatusChange(int $ticketId, string $ticketNumber, string $newStatus, int $updatedByStaffId): void {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT id, name, email, contact FROM it_staff WHERE role IN ('ict_head','assistant_manager','assistant_ict') AND is_active = 1 AND id != ?");
    $stmt->execute([$updatedByStaffId]);
    $managers = $stmt->fetchAll();

    $statusText = statusLabel($newStatus);
    foreach ($managers as $mgr) {
        dispatchNotification([
            'recipient_id'   => $mgr['id'],
            'recipient_type' => 'staff',
            'message'        => "Ticket {$ticketNumber} status updated to {$statusText}.",
            'ticket_id'      => $ticketId,
            'email'          => $mgr['email'],
            'name'           => $mgr['name'],
            'subject'        => '',  // No email for minor status updates
        ]);
    }
}
