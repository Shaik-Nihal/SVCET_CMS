<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$returnTo = ($_POST['return_to'] ?? '') === 'staff' ? '/staff/manage_staff' : '/admin/staff';

requirePermission('staff.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . $returnTo);
    exit;
}

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Invalid request token.');
    header('Location: ' . APP_URL . $returnTo);
    exit;
}

$pdo = getDB();
$staffId = (int)($_POST['staff_id'] ?? 0);

try {
    $pdo->beginTransaction();

    // Reassign tickets
    $stmt = $pdo->prepare("UPDATE tickets SET assigned_to = NULL WHERE assigned_to = ?");
    $stmt->execute([$staffId]);

    // Delete staff
    $stmt = $pdo->prepare("DELETE FROM it_staff WHERE id = ?");
    $stmt->execute([$staffId]);

    $pdo->commit();
    setFlash('success', 'Staff member deleted successfully. Their tickets are now unassigned.');
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Staff delete error: ' . $e->getMessage());
    setFlash('error', 'Failed to delete staff member. Please try again.');
}

header('Location: ' . APP_URL . $returnTo);
exit;
