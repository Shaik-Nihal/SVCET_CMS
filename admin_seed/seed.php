<?php
// ============================================================
// ONE-TIME DATABASE SEEDER
// Run once after importing schema.sql, then DELETE THIS FILE.
// Access: http://localhost/TMS/admin_seed/seed.php
// ============================================================

// Safety: only allow from localhost
$allowedIPs = ['127.0.0.1', '::1', 'localhost'];
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $allowedIPs)) {
    die('Access denied. This can only be run from localhost.');
}

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

$defaultPassword = 'Apollo@2026!';
$hash = password_hash($defaultPassword, PASSWORD_BCRYPT, ['cost' => 12]);

$pdo = getDB();

echo "<h2>Apollo TMS — Database Seeder</h2><pre>";

// ── Seed IT Staff ───────────────────────────────────────────
$staffData = [
    ['Dr G B Hima Bindu',  'dyd_ict@apollouniversity.edu.in',         'ict_head',          'Deputy Director ICT (DICT)',  '9876543210'],
    ['Dr Pakkairaha',      'pakkairaha@apollouniversity.edu.in',       'assistant_ict',     'Assistant Director of ICT',   '9876543211'],
    ['Mr Ashok Kumar',     'ashok.kumar@apollouniversity.edu.in',      'assistant_manager', 'Assistant Manager IT',        '9876543212'],
    ['Mr K Prasanna',      'k.prasanna@apollouniversity.edu.in',       'sr_it_executive',   'Sr. IT Executive',            '9876543213'],
    ['Mr K Jagadeesh',     'k.jagadeesh@apollouniversity.edu.in',      'sr_it_executive',   'Sr. IT Executive',            '9876543214'],
    ['Mr Mohan',           'mohan@apollouniversity.edu.in',            'assistant_it',      'Assistant IT Executive',      '9876543220'],
    ['Mr Bhargav',         'bhargav@apollouniversity.edu.in',          'assistant_it',      'Assistant IT Executive',      '9876543221'],
    ['Mr Gopi',            'gopi@apollouniversity.edu.in',             'assistant_it',      'Assistant IT Executive',      '9876543222'],
    ['Mr Vijay',           'vijay@apollouniversity.edu.in',            'assistant_it',      'Assistant IT Executive',      '9876543223'],
];

$stmt = $pdo->prepare("SELECT COUNT(*) FROM it_staff");
$stmt->execute();
$existingStaff = (int)$stmt->fetchColumn();

if ($existingStaff === 0) {
    $insert = $pdo->prepare("
        INSERT INTO it_staff (name, email, password_hash, role, designation, contact)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    foreach ($staffData as $s) {
        $insert->execute([$s[0], $s[1], $hash, $s[2], $s[3], $s[4]]);
        echo "✓ Staff added: {$s[0]} ({$s[3]})\n";

        // Add to password history
        $staffId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO password_history (user_id, user_type, password_hash) VALUES (?, 'staff', ?)")
            ->execute([$staffId, $hash]);
    }
    echo "\n";
} else {
    echo "⚠ IT staff already seeded ({$existingStaff} records found). Skipping.\n\n";
}

// ── Seed Problem Categories ─────────────────────────────────
$categories = [
    ['WiFi Signal Weak / Slow',          'bi-wifi',               'Poor or unstable wireless connectivity'],
    ['No Internet Connectivity',         'bi-wifi-off',           'Complete loss of internet access'],
    ['Computer / Laptop Not Working',    'bi-pc-display',         'Hardware or operating system level issues'],
    ['Printer Issue',                    'bi-printer',            'Printing errors, paper jams, driver issues'],
    ['Email / Account Login Problem',    'bi-envelope-slash',     'Cannot log in to email or university accounts'],
    ['Software Installation Required',   'bi-box-arrow-in-down',  'Need new or updated software installed'],
    ['Power / Electricity Issue',        'bi-plug',               'Power socket, UPS, or surge issues'],
    ['Projector / Display Problem',      'bi-projector',          'Projector not working, display errors'],
    ['Network / LAN Issue',              'bi-hdd-network',        'Wired network / LAN connectivity problems'],
    ['Other',                            'bi-question-circle',    'Any other issue not listed above'],
];

$stmt = $pdo->prepare("SELECT COUNT(*) FROM problem_categories");
$stmt->execute();
$existingCats = (int)$stmt->fetchColumn();

if ($existingCats === 0) {
    $insert = $pdo->prepare("INSERT INTO problem_categories (name, icon, description) VALUES (?, ?, ?)");
    foreach ($categories as $c) {
        $insert->execute($c);
        echo "✓ Category added: {$c[0]}\n";
    }
    echo "\n";
} else {
    echo "⚠ Categories already seeded ({$existingCats} records found). Skipping.\n\n";
}

// ── Create a test user ──────────────────────────────────────
$testEmail = 'test@apollouniversity.edu.in';
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$testEmail]);

if (!$stmt->fetch()) {
    $testHash = password_hash('Test@2026!', PASSWORD_BCRYPT, ['cost' => 12]);
    $pdo->prepare("INSERT INTO users (name, email, password_hash, phone, department, roll_no) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute(['Test User', $testEmail, $testHash, '9999999999', 'Computer Science', 'CS001']);
    $testId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO password_history (user_id, user_type, password_hash) VALUES (?, 'user', ?)")
        ->execute([$testId, $testHash]);
    echo "✓ Test user created: {$testEmail} / Test@2026!\n";
} else {
    echo "⚠ Test user already exists.\n";
}

echo "\n";
echo "═══════════════════════════════════════════════\n";
echo "  SEEDING COMPLETE!\n";
echo "═══════════════════════════════════════════════\n";
echo "\n";
echo "Login credentials:\n";
echo "─────────────────────────────────────────────\n";
echo "Test User:           test@apollouniversity.edu.in / Test@2026!\n";
echo "ICT Head (DICT):     dyd_ict@apollouniversity.edu.in / {$defaultPassword}\n";
echo "Assistant ICT:       pakkairaha@apollouniversity.edu.in / {$defaultPassword}\n";
echo "Asst Manager IT:     ashok.kumar@apollouniversity.edu.in / {$defaultPassword}\n";
echo "Sr IT Executive:     k.prasanna@apollouniversity.edu.in / {$defaultPassword}\n";
echo "Sr IT Executive:     k.jagadeesh@apollouniversity.edu.in / {$defaultPassword}\n";
echo "Asst IT Executive:   mohan@apollouniversity.edu.in / {$defaultPassword}\n";
echo "Asst IT Executive:   bhargav@apollouniversity.edu.in / {$defaultPassword}\n";
echo "Asst IT Executive:   gopi@apollouniversity.edu.in / {$defaultPassword}\n";
echo "Asst IT Executive:   vijay@apollouniversity.edu.in / {$defaultPassword}\n";
echo "\n";
echo "⚠ DELETE THIS FILE (admin_seed/seed.php) IMMEDIATELY!\n";
echo "</pre>";

echo '<hr><p style="color:red;font-weight:bold;font-size:16px;">⚠ SECURITY: Delete admin_seed/seed.php after use!</p>';
echo '<p><a href="' . APP_URL . '/auth/login.php">→ Go to Login Page</a></p>';
