<?php
// POST handler - Assign ticket to staff
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ticket_helpers.php';
require_once __DIR__ . '/../includes/notification_helpers.php';

requireRole([ROLE_ICT_HEAD, ROLE_ASST_MANAGER]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/staff/tickets.php');
    exit;
}

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Invalid request.');
    header('Location: ' . APP_URL . '/staff/tickets.php');
    exit;
}

$pdo        = getDB();
$staffId    = currentStaffId();
$role       = currentRole();
$ticketId   = (int)($_POST['ticket_id'] ?? 0);
$assignedTo = (int)($_POST['assigned_to'] ?? 0);
$notes      = trim($_POST['notes'] ?? '');

// Validate ticket
$stmt = $pdo->prepare("SELECT t.id, t.ticket_number, t.status, COALESCE(pc.name,'Custom') AS category_name, u.id AS user_id FROM tickets t LEFT JOIN problem_categories pc ON t.problem_category_id = pc.id LEFT JOIN users u ON t.user_id = u.id WHERE t.id = ?");
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    setFlash('error', 'Ticket not found.');
    header('Location: ' . APP_URL . '/staff/tickets.php');
    exit;
}
if ($ticket['status'] === 'solved') {
    setFlash('error', 'Cannot reassign a solved ticket.');
    header('Location: ' . APP_URL . '/staff/ticket_detail.php?id=' . $ticketId);
    exit;
}

// Validate assignee
$stmt = $pdo->prepare("SELECT id, name, role, designation FROM it_staff WHERE id = ? AND is_active = 1");
$stmt->execute([$assignedTo]);
$assignee = $stmt->fetch();

if (!$assignee) {
    setFlash('error', 'Invalid staff selection.');
    header('Location: ' . APP_URL . '/staff/ticket_detail.php?id=' . $ticketId);
    exit;
}

// Permission check
if (!canAssignTo($role, $assignee['role'])) {
    setFlash('error', 'You do not have permission to assign to this role.');
    header('Location: ' . APP_URL . '/staff/ticket_detail.php?id=' . $ticketId);
    exit;
}

// Load current staff name
$stmt = $pdo->prepare("SELECT name FROM it_staff WHERE id = ?");
$stmt->execute([$staffId]);
$assigner = $stmt->fetch();

try {
    assignTicket($ticketId, $staffId, $assignedTo, $notes);

    // Notify assignee
    notifyStaffAssigned($assignedTo, $ticketId, $ticket['ticket_number'], $assigner['name'], $ticket['category_name'], $notes);

    // Notify the user
    $stmt = $pdo->prepare("SELECT name, email, phone FROM users WHERE id = ?");
    $stmt->execute([$ticket['user_id']]);
    $ticketUser = $stmt->fetch();
    if ($ticketUser) {
        dispatchNotification([
            'recipient_id'   => $ticket['user_id'],
            'recipient_type' => 'user',
            'message'        => "Your ticket {$ticket['ticket_number']} has been assigned to {$assignee['name']} ({$assignee['designation']}) and is now being processed.",
            'ticket_id'      => $ticketId,
            'email'          => $ticketUser['email'],
            'name'           => $ticketUser['name'],
            'phone'          => $ticketUser['phone'] ?? '',
            'subject'        => "Ticket {$ticket['ticket_number']} — Being Processed",
        ]);
    }

    setFlash('success', "Ticket assigned to <strong>{$assignee['name']}</strong> successfully.");
} catch (Throwable $e) {
    error_log('Assign ticket error: ' . $e->getMessage());
    setFlash('error', 'Assignment failed. Please try again.');
}

header('Location: ' . APP_URL . '/staff/ticket_detail.php?id=' . $ticketId);
exit;
