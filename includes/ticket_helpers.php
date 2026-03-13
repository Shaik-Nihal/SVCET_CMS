<?php
// ============================================================
// Ticket Helpers
// ============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

/**
 * Generate a unique ticket number: APL-YYYYMMDD-XXXX
 * Called within a transaction for safety.
 */
function generateTicketNumber(): string {
    $pdo  = getDB();
    $date = date('Ymd');

    // Count tickets created today
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $count = (int) $stmt->fetchColumn();

    return TICKET_PREFIX . '-' . $date . '-' . str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);
}

/**
 * Valid forward status transitions.
 */
function getNextStatus(string $current): ?string {
    $transitions = [
        STATUS_NOTIFIED   => STATUS_PROCESSING,
        STATUS_PROCESSING => STATUS_SOLVING,
        STATUS_SOLVING    => STATUS_SOLVED,
    ];
    return $transitions[$current] ?? null;
}

/**
 * Check if $actor (role) can assign a ticket TO $targetRole.
 */
function canAssignTo(string $actorRole, string $targetRole): bool {
    $allowed = [
        ROLE_ICT_HEAD     => [ROLE_ASST_MANAGER, ROLE_SR_IT_EXEC],
        ROLE_ASST_MANAGER => [ROLE_SR_IT_EXEC],
    ];
    return in_array($targetRole, $allowed[$actorRole] ?? [], true);
}

/**
 * Create a ticket (inside transaction).
 * Returns new ticket ID or throws on error.
 */
function createTicket(array $data): int {
    $pdo = getDB();
    $pdo->beginTransaction();

    try {
        $ticketNumber = generateTicketNumber();

        $stmt = $pdo->prepare("
            INSERT INTO tickets
                (ticket_number, user_id, problem_category_id, custom_description,
                 assigned_to, status, priority)
            VALUES (?, ?, ?, ?, ?, 'notified', ?)
        ");
        $stmt->execute([
            $ticketNumber,
            $data['user_id'],
            $data['category_id'] ?: null,
            $data['description'] ?: null,
            $data['assigned_to'],
            $data['priority'] ?? PRIORITY_MEDIUM,
        ]);
        $ticketId = (int) $pdo->lastInsertId();

        // Initial assignment record
        $pdo->prepare("
            INSERT INTO ticket_assignments (ticket_id, assigned_by, assigned_to, notes)
            VALUES (?, ?, ?, 'Ticket raised by user')
        ")->execute([$ticketId, $data['user_id'], $data['assigned_to']]);

        // Initial status history
        $pdo->prepare("
            INSERT INTO ticket_status_history
                (ticket_id, old_status, new_status, changed_by, changed_by_type, notes)
            VALUES (?, NULL, 'notified', ?, 'user', 'Ticket created')
        ")->execute([$ticketId, $data['user_id']]);

        $pdo->commit();
        return $ticketId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('createTicket error: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Assign ticket to a staff member (ICT Head or Asst Manager action).
 * Updates status to processing.
 */
function assignTicket(int $ticketId, int $assignedById, int $assignedToId, string $notes = ''): void {
    $pdo = getDB();
    $pdo->beginTransaction();

    try {
        // Get current status
        $stmt = $pdo->prepare("SELECT status FROM tickets WHERE id = ?");
        $stmt->execute([$ticketId]);
        $current = $stmt->fetchColumn();

        $newStatus = STATUS_PROCESSING;

        $pdo->prepare("
            UPDATE tickets SET assigned_to = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ")->execute([$assignedToId, $newStatus, $ticketId]);

        $pdo->prepare("
            INSERT INTO ticket_assignments (ticket_id, assigned_by, assigned_to, notes)
            VALUES (?, ?, ?, ?)
        ")->execute([$ticketId, $assignedById, $assignedToId, $notes]);

        $pdo->prepare("
            INSERT INTO ticket_status_history
                (ticket_id, old_status, new_status, changed_by, changed_by_type, notes)
            VALUES (?, ?, ?, ?, 'staff', ?)
        ")->execute([$ticketId, $current, $newStatus, $assignedById, "Assigned to staff — {$notes}"]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Update ticket status (Sr IT Executive action).
 * Only allows forward transitions.
 */
function updateTicketStatus(int $ticketId, int $staffId, string $newStatus, string $notes = ''): bool {
    $pdo = getDB();

    $stmt = $pdo->prepare("SELECT status FROM tickets WHERE id = ? AND assigned_to = ?");
    $stmt->execute([$ticketId, $staffId]);
    $current = $stmt->fetchColumn();

    if (!$current || getNextStatus($current) !== $newStatus) {
        return false;
    }

    $pdo->beginTransaction();
    try {
        if ($newStatus === STATUS_SOLVED) {
            $pdo->prepare("
                UPDATE tickets SET status = ?, updated_at = NOW(), solved_at = NOW() WHERE id = ?
            ")->execute([$newStatus, $ticketId]);
        } else {
            $pdo->prepare("
                UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?
            ")->execute([$newStatus, $ticketId]);
        }

        $pdo->prepare("
            INSERT INTO ticket_status_history
                (ticket_id, old_status, new_status, changed_by, changed_by_type, notes)
            VALUES (?, ?, ?, ?, 'staff', ?)
        ")->execute([$ticketId, $current, $newStatus, $staffId, $notes ?: 'Status updated by technician']);

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('updateTicketStatus error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Fetch full ticket data with related info for display.
 */
function getTicketById(int $ticketId): ?array {
    $pdo  = getDB();
    $stmt = $pdo->prepare("
        SELECT t.*,
               u.name       AS user_name,
               u.email      AS user_email,
               u.phone      AS user_phone,
               u.department AS user_department,
               u.roll_no    AS user_roll_no,
               pc.name      AS category_name,
               pc.icon      AS category_icon,
               s.name       AS assigned_name,
               s.role       AS assigned_role,
               s.designation AS assigned_designation,
               s.contact    AS assigned_contact,
               f.rating     AS feedback_rating,
               f.comment    AS feedback_comment
        FROM tickets t
        LEFT JOIN users u             ON t.user_id = u.id
        LEFT JOIN problem_categories pc ON t.problem_category_id = pc.id
        LEFT JOIN it_staff s          ON t.assigned_to = s.id
        LEFT JOIN feedback f          ON f.ticket_id = t.id
        WHERE t.id = ?
    ");
    $stmt->execute([$ticketId]);
    return $stmt->fetch() ?: null;
}
