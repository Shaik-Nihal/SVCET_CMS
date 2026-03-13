<?php
// Quick password fix - run once then delete
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) {
    die('Access denied.');
}

require_once __DIR__ . '/../config/database.php';

$password = 'Apollo@2026!';
$hash     = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$pdo      = getDB();

echo "<h2>Apollo TMS — Password Fix</h2><pre>";

// Show current state
$rows = $pdo->query("SELECT id, name, email, LEFT(password_hash,20) as hash_preview FROM it_staff")->fetchAll();
echo "Current it_staff records:\n";
foreach ($rows as $r) {
    echo "  [{$r['id']}] {$r['name']} | {$r['email']} | hash: {$r['hash_preview']}...\n";
}
echo "\n";

if (empty($rows)) {
    // No staff at all — insert fresh
    echo "No staff found. Inserting fresh...\n\n";

    $staffData = [
        ['Dr. Ramesh Kumar', 'ramesh.kumar@apollouniversity.edu.in', 'ict_head',          'ICT Head',          '9876543210'],
        ['Ms. Priya Sharma', 'priya.sharma@apollouniversity.edu.in', 'ict_head',          'Assistant ICT',     '9876543211'],
        ['Mr. Arun Verma',   'arun.verma@apollouniversity.edu.in',   'assistant_manager', 'Assistant Manager', '9876543212'],
        ['Mr. Kiran Patel',  'kiran.patel@apollouniversity.edu.in',  'sr_it_executive',   'Sr. IT Executive',  '9876543213'],
        ['Mr. Suresh Reddy', 'suresh.reddy@apollouniversity.edu.in', 'sr_it_executive',   'Sr. IT Executive',  '9876543214'],
        ['Ms. Deepa Nair',   'deepa.nair@apollouniversity.edu.in',   'sr_it_executive',   'Sr. IT Executive',  '9876543215'],
    ];

    $pdo->exec("DELETE FROM password_history WHERE user_type = 'staff'");

    $ins = $pdo->prepare("INSERT INTO it_staff (name, email, password_hash, role, designation, contact) VALUES (?,?,?,?,?,?)");
    foreach ($staffData as $s) {
        $ins->execute([$s[0], $s[1], $hash, $s[2], $s[3], $s[4]]);
        $id = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO password_history (user_id, user_type, password_hash) VALUES (?, 'staff', ?)")
            ->execute([$id, $hash]);
        echo "  Inserted: {$s[0]}\n";
    }
} else {
    // Staff exist — just update all password hashes
    echo "Updating all staff password hashes...\n\n";

    $pdo->exec("DELETE FROM password_history WHERE user_type = 'staff'");
    $pdo->exec("UPDATE it_staff SET password_hash = '$hash'");

    $updated = $pdo->query("SELECT id, name FROM it_staff")->fetchAll();
    foreach ($updated as $r) {
        $pdo->prepare("INSERT INTO password_history (user_id, user_type, password_hash) VALUES (?, 'staff', ?)")
            ->execute([$r['id'], $hash]);
        echo "  Updated: {$r['name']}\n";
    }
}

// Verify hash works
$test = $pdo->query("SELECT password_hash FROM it_staff WHERE email = 'ramesh.kumar@apollouniversity.edu.in'")->fetchColumn();
$ok   = $test && password_verify($password, $test);

echo "\n";
echo "════════════════════════════════════════\n";
echo "  Hash verification: " . ($ok ? "PASS" : "FAIL") . "\n";
echo "════════════════════════════════════════\n\n";
echo "All staff login credentials:\n";
echo "  Password: Apollo@2026!\n\n";
echo "  ramesh.kumar@apollouniversity.edu.in   (ICT Head)\n";
echo "  priya.sharma@apollouniversity.edu.in   (Assistant ICT)\n";
echo "  arun.verma@apollouniversity.edu.in     (Assistant Manager)\n";
echo "  kiran.patel@apollouniversity.edu.in    (Sr. IT Executive)\n";
echo "  suresh.reddy@apollouniversity.edu.in   (Sr. IT Executive)\n";
echo "  deepa.nair@apollouniversity.edu.in     (Sr. IT Executive)\n";
echo "\n";
echo "DELETE THIS FILE after use!\n";
echo "</pre>";
echo '<hr>';
echo '<p style="color:red;font-weight:bold">DELETE: admin_seed/fix_passwords.php after use!</p>';
echo '<p><a href="http://localhost/TMS/auth/login.php">→ Go to Login Page</a></p>';
