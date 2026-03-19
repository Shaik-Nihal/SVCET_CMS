<?php
// AJAX: Validate email domain during registration
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

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

if (!isAllowedEmailDomain($email)) {
    echo json_encode(['valid' => false, 'message' => 'Must be a valid ' . allowedEmailDomainsLabel() . ' email.']);
    exit;
}

echo json_encode(['valid' => true, 'message' => 'Email format is valid.']);
