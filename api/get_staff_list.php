<?php
// AJAX: Get active IT staff list (for raise ticket form)
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

$pdo  = getDB();
$stmt = $pdo->query("SELECT id, name, role, designation, contact FROM it_staff WHERE is_active = 1 ORDER BY role, name");
$staff = $stmt->fetchAll();

echo json_encode($staff);
