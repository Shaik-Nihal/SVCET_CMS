<?php
// POST handler - Update ticket status (Sr IT Executive / Assistant IT)
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ticket_helpers.php';
require_once __DIR__ . '/../includes/notification_helpers.php';

requireRole([ROLE_SR_IT_EXEC, ROLE_ASST_IT]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/staff/tickets');
    exit;
}

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Invalid request.');
    header('Location: ' . APP_URL . '/staff/tickets');
    exit;
}

$pdo       = getDB();
$staffId   = currentStaffId();
$ticketId  = (int)($_POST['ticket_id'] ?? 0);
$newStatus = $_POST['new_status'] ?? '';
$notes     = trim($_POST['notes'] ?? '');

$validStatuses = [STATUS_PROCESSING, STATUS_SOLVING, STATUS_SOLVED];
if (!$ticketId || !in_array($newStatus, $validStatuses)) {
    setFlash('error', 'Invalid request.');
    header('Location: ' . APP_URL . '/staff/tickets');
    exit;
}

// Load ticket
$stmt = $pdo->prepare("SELECT t.*, u.id AS user_id, t.ticket_number, COALESCE(pc.name,'Custom') AS category_name FROM tickets t LEFT JOIN problem_categories pc ON t.problem_category_id = pc.id LEFT JOIN users u ON t.user_id = u.id WHERE t.id = ? AND t.assigned_to = ?");
$stmt->execute([$ticketId, $staffId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    setFlash('error', 'Ticket not found or not assigned to you.');
    header('Location: ' . APP_URL . '/staff/tickets');
    exit;
}

$updated = updateTicketStatus($ticketId, $staffId, $newStatus, $notes);

if ($updated) {
    // Notify the ticket user
    notifyUserStatusChange($ticket['user_id'], $ticketId, $ticket['ticket_number'], $newStatus);

    // Notify management
    notifyManagementStatusChange($ticketId, $ticket['ticket_number'], $newStatus, $staffId);

    $label = statusLabel($newStatus);
    setFlash('success', "Ticket status updated to <strong>{$label}</strong>.");
} else {
    setFlash('error', 'Status update failed. Invalid transition or ticket not found.');
}

sendResponseAndFlushEmails(APP_URL . '/staff/ticket_detail?id=' . $ticketId);
