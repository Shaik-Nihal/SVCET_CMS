<?php
// AJAX: Mark a notification as read (POST only — state mutation)
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

startSecureSession();
header('Content-Type: application/json');

// Only allow POST for state-mutating operations
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (empty($_SESSION['user_type'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$pdo = getDB();

// Accept ID from POST body (JSON or form data)
$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? $_POST['id'] ?? 0);

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
