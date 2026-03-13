<?php
// AJAX: Mark a notification as read
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

startSecureSession();
header('Content-Type: application/json');

if (empty($_SESSION['user_type'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$pdo = getDB();
$id  = (int)($_GET['id'] ?? 0);

if ($_SESSION['user_type'] === 'staff') {
    $recipientId   = currentStaffId();
    $recipientType = 'staff';
} else {
    $recipientId   = currentUserId();
    $recipientType = 'user';
}

if ($id > 0) {
    // Mark single notification as read (only if it belongs to this user)
    $stmt = $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND recipient_id=? AND recipient_type=?");
    $stmt->execute([$id, $recipientId, $recipientType]);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Missing notification ID']);
}
