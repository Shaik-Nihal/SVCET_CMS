<?php
// POST handler - Assign ticket to staff
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ticket_helpers.php';
require_once __DIR__ . '/../includes/notification_helpers.php';

requireStaff();
requireAnyPermission(['ticket.assign.lead', 'ticket.assign.exec']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/staff/tickets');
    exit;
}

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Invalid request.');
    header('Location: ' . APP_URL . '/staff/tickets');
    exit;
}

$pdo        = getDB();
$staffId    = currentStaffId();
$role       = currentRole();
$ticketId   = (int)($_POST['ticket_id'] ?? 0);
$assignedTo = (int)($_POST['assigned_to'] ?? 0);
$notes      = trim($_POST['notes'] ?? '');

// Validate ticket and get user email for domain check
if (currentStaffHasPermission('ticket.assign.exec') && !currentStaffHasPermission('ticket.assign.lead')) {
    // Sr IT can assign if ticket is on self OR currently with an Assistant IT previously delegated by this Sr IT.
    $stmt = $pdo->prepare("\n        SELECT t.id, t.ticket_number, t.status, COALESCE(pc.name,'Custom') AS category_name,\n               u.id AS user_id, u.email AS user_email\n        FROM tickets t\n        LEFT JOIN problem_categories pc ON t.problem_category_id = pc.id\n        LEFT JOIN users u ON t.user_id = u.id\n        WHERE t.id = ?\n          AND (\n            t.assigned_to = ?\n            OR (\n              t.assigned_to IN (\n                SELECT s.id\n                FROM it_staff s\n                INNER JOIN roles r ON r.slug = s.role\n                INNER JOIN role_permissions rp ON rp.role_id = r.id\n                INNER JOIN permissions p ON p.id = rp.permission_id\n                WHERE p.slug = 'ticket.update_status'\n              )\n              AND EXISTS (\n                SELECT 1\n                FROM ticket_assignments ta\n                WHERE ta.ticket_id = t.id AND ta.assigned_by = ? AND ta.assigned_to = t.assigned_to\n              )\n            )\n          )\n    ");
    $stmt->execute([$ticketId, $staffId, $staffId]);
} else {
    $stmt = $pdo->prepare("SELECT t.id, t.ticket_number, t.status, COALESCE(pc.name,'Custom') AS category_name, u.id AS user_id, u.email AS user_email FROM tickets t LEFT JOIN problem_categories pc ON t.problem_category_id = pc.id LEFT JOIN users u ON t.user_id = u.id WHERE t.id = ?");
    $stmt->execute([$ticketId]);
}
$ticket = $stmt->fetch();

if (!$ticket) {
    setFlash('error', 'Ticket not found or not assigned to you.');
    header('Location: ' . APP_URL . '/staff/tickets');
    exit;
}
if ($ticket['status'] === 'solved') {
    setFlash('error', 'Cannot reassign a solved ticket.');
    header('Location: ' . APP_URL . '/staff/ticket_detail?id=' . $ticketId);
    exit;
}

// Role-based access check
if (!canAssignForDomain($role, $ticket['user_email'] ?? '')) {
    setFlash('error', 'You do not have permission to assign tickets from this domain.');
    header('Location: ' . APP_URL . '/staff/ticket_detail?id=' . $ticketId);
    exit;
}

// Validate assignee
$stmt = $pdo->prepare("SELECT id, name, role, designation FROM it_staff WHERE id = ? AND is_active = 1");
$stmt->execute([$assignedTo]);
$assignee = $stmt->fetch();

if (!$assignee) {
    setFlash('error', 'Invalid staff selection.');
    header('Location: ' . APP_URL . '/staff/ticket_detail?id=' . $ticketId);
    exit;
}

// Permission check (role hierarchy + domain)
if (!canAssignForTicket($role, $assignee['role'], $ticket['user_email'] ?? '')) {
    setFlash('error', 'You do not have permission to assign to this role.');
    header('Location: ' . APP_URL . '/staff/ticket_detail?id=' . $ticketId);
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

    setFlash('success', "Ticket assigned to {$assignee['name']} successfully.");

    // If Sr IT Executive assigned to Assistant IT, ticket may no longer be visible on detail page.
    if (currentStaffHasPermission('ticket.assign.exec') && !currentStaffHasPermission('ticket.assign.lead') && $assignedTo !== $staffId) {
        sendResponseAndFlushEmails(APP_URL . '/staff/tickets');
    }
} catch (Throwable $e) {
    error_log('Assign ticket error: ' . $e->getMessage());
    setFlash('error', 'Assignment failed. Please try again.');
}

sendResponseAndFlushEmails(APP_URL . '/staff/ticket_detail?id=' . $ticketId);
