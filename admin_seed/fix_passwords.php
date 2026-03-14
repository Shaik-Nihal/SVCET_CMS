<?php
// Reset seeded staff account passwords - run once, then delete this file.
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) {
    die('Access denied.');
}

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

$password = 'Apollo@2026!';
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$pdo = getDB();

echo "<h2>Apollo TMS — Staff Password Reset</h2><pre>";

$seedStaff = [
    ['Dr G B Hima Bindu', 'dyd_ict@apollouniversity.edu.in', 'ict_head', 'Deputy Director ICT (DICT)', '9876543210'],
    ['Dr Pakkairaha', 'pakkairaha@apollouniversity.edu.in', 'assistant_ict', 'Assistant Director of ICT', '9876543211'],
    ['Mr Ashok Kumar', 'ashok.kumar@apollouniversity.edu.in', 'assistant_manager', 'Assistant Manager IT', '9876543212'],
    ['Mr K Prasanna', 'k.prasanna@apollouniversity.edu.in', 'sr_it_executive', 'Sr. IT Executive', '9876543213'],
    ['Mr K Jagadeesh', 'k.jagadeesh@apollouniversity.edu.in', 'sr_it_executive', 'Sr. IT Executive', '9876543214'],
    ['Mr Mohan', 'mohan@apollouniversity.edu.in', 'assistant_it', 'Assistant IT Executive', '9876543220'],
    ['Mr Bhargav', 'bhargav@apollouniversity.edu.in', 'assistant_it', 'Assistant IT Executive', '9876543221'],
    ['Mr Gopi', 'gopi@apollouniversity.edu.in', 'assistant_it', 'Assistant IT Executive', '9876543222'],
    ['Mr Vijay', 'vijay@apollouniversity.edu.in', 'assistant_it', 'Assistant IT Executive', '9876543223'],
];

$findStmt = $pdo->prepare("SELECT id, name FROM it_staff WHERE email = ? LIMIT 1");
$updateStmt = $pdo->prepare("UPDATE it_staff SET password_hash = ?, is_active = 1 WHERE id = ?");
$insertStmt = $pdo->prepare("INSERT INTO it_staff (name, email, password_hash, role, designation, contact, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
$historyStmt = $pdo->prepare("INSERT INTO password_history (user_id, user_type, password_hash) VALUES (?, 'staff', ?)");

foreach ($seedStaff as $s) {
    [$name, $email, $role, $designation, $contact] = $s;
    $findStmt->execute([$email]);
    $existing = $findStmt->fetch();

    if ($existing) {
        $updateStmt->execute([$hash, (int)$existing['id']]);
        $historyStmt->execute([(int)$existing['id'], $hash]);
        echo "Updated: {$email} ({$existing['name']})\n";
    } else {
        $insertStmt->execute([$name, $email, $hash, $role, $designation, $contact]);
        $newId = (int)$pdo->lastInsertId();
        $historyStmt->execute([$newId, $hash]);
        echo "Inserted: {$email} ({$name})\n";
    }
}

echo "\nLogin password for all above staff: {$password}\n";
echo "\nImportant: delete admin_seed/fix_passwords.php after successful login.\n";
echo "</pre>";
echo '<hr>';
echo '<p><a href="' . APP_URL . '/auth/login.php">→ Go to Login Page</a></p>';
