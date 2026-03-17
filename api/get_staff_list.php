<?php
// AJAX: Get active IT staff list (for raise ticket form)
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rbac.php';

startSecureSession();
header('Content-Type: application/json');

if (empty($_SESSION['user_type']) || $_SESSION['user_type'] !== 'user') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$staff = getStaffByPermission('notify.management');

echo json_encode($staff);
