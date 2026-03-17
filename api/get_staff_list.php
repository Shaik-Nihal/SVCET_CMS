<?php
// AJAX: Get active IT staff list (for raise ticket form)
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

startSecureSession();
header('Content-Type: application/json');

if (empty($_SESSION['user_type']) || $_SESSION['user_type'] !== 'user') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$pdo    = getDB();
$userId = (int)($_SESSION['user_id'] ?? 0);
$stmt = $pdo->prepare("SELECT id, name, role, designation, contact FROM it_staff WHERE is_active = 1 AND role IN (?, ?, ?) ORDER BY role, name");
$stmt->execute([ROLE_ICT_HEAD, ROLE_ASST_MANAGER, ROLE_ASST_ICT]);
$staff = $stmt->fetchAll();

echo json_encode($staff);
