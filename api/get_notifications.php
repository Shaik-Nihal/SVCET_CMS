<?php
// AJAX: Get notifications (polled every 30s by notifications.js)
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
header('Content-Type: application/json');

if (empty($_SESSION['user_type'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$pdo   = getDB();
$limit = min((int)($_GET['limit'] ?? 5), 20);

if ($_SESSION['user_type'] === 'staff') {
    $recipientId   = currentStaffId();
    $recipientType = 'staff';
} else {
    $recipientId   = currentUserId();
    $recipientType = 'user';
}

// Unread count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE recipient_id=? AND recipient_type=? AND is_read=0");
$stmt->execute([$recipientId, $recipientType]);
$unreadCount = (int)$stmt->fetchColumn();

// Recent notifications
$stmt = $pdo->prepare("
    SELECT id, message, ticket_id, is_read, created_at
    FROM notifications
    WHERE recipient_id = ? AND recipient_type = ?
    ORDER BY created_at DESC
    LIMIT ?
");
$stmt->bindValue(1, $recipientId, PDO::PARAM_INT);
$stmt->bindValue(2, $recipientType, PDO::PARAM_STR);
$stmt->bindValue(3, $limit, PDO::PARAM_INT);
$stmt->execute();
$notifications = $stmt->fetchAll();

// Add time_ago field
foreach ($notifications as &$n) {
    $n['time_ago'] = timeAgo($n['created_at']);
    $n['is_read']  = (int)$n['is_read'];
}

echo json_encode([
    'unread_count'  => $unreadCount,
    'notifications' => $notifications,
]);
