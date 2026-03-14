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

$domainValid = false;
foreach (EMAIL_DOMAINS as $domain) {
    if (str_ends_with($email, '@' . $domain)) {
        $domainValid = true;
        break;
    }
}

if (!$domainValid) {
    echo json_encode(['valid' => false, 'message' => 'Must be a valid ' . implode(' or ', EMAIL_DOMAINS) . ' email.']);
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
