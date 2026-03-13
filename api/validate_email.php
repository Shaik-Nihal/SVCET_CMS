<?php
// AJAX: Validate email domain during registration
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$email = strtolower(trim($_GET['email'] ?? ''));

if (!$email) {
    echo json_encode(['valid' => false, 'message' => 'Email is required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['valid' => false, 'message' => 'Invalid email format.']);
    exit;
}

if (!str_ends_with($email, '@' . EMAIL_DOMAIN)) {
    echo json_encode(['valid' => false, 'message' => 'Must be a @' . EMAIL_DOMAIN . ' email.']);
    exit;
}

$pdo  = getDB();
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);

if ($stmt->fetch()) {
    echo json_encode(['valid' => false, 'message' => 'Email already registered.']);
    exit;
}

echo json_encode(['valid' => true, 'message' => 'Email is available.']);
