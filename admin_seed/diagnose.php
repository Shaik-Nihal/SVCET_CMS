<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) {
    die('Access denied.');
}

echo "<h2>Apollo TMS — Login Diagnostic</h2><pre style='font-size:14px'>";

// Step 1: DB Connection
echo "STEP 1: Connecting to database...\n";
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=tms_apollo;charset=utf8mb4",
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    echo "  OK - Connected to tms_apollo\n\n";
} catch (Exception $e) {
    die("  FAILED: " . $e->getMessage() . "\n</pre>");
}

// Step 2: Check tables exist
echo "STEP 2: Checking tables...\n";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach (['it_staff', 'users', 'tickets', 'problem_categories', 'password_history'] as $t) {
    echo "  " . (in_array($t, $tables) ? "OK" : "MISSING") . " - $t\n";
}
echo "\n";

// Step 3: Show current it_staff
echo "STEP 3: Current it_staff table contents...\n";
$staff = $pdo->query("SELECT id, name, email, role, is_active, password_hash FROM it_staff")->fetchAll();
if (empty($staff)) {
    echo "  EMPTY - No staff records found\n\n";
} else {
    foreach ($staff as $s) {
        $hashType = strpos($s['password_hash'], 'placeholder') !== false ? 'PLACEHOLDER (BROKEN)' : 'REAL HASH (OK)';
        echo "  [{$s['id']}] {$s['name']}\n";
        echo "       Email:  {$s['email']}\n";
        echo "       Role:   {$s['role']}\n";
        echo "       Active: {$s['is_active']}\n";
        echo "       Hash:   {$hashType}: " . substr($s['password_hash'], 0, 30) . "...\n\n";
    }
}

// Step 4: Test password_verify
echo "STEP 4: Testing password_verify with Apollo\@2026!...\n";
foreach ($staff as $s) {
    $result = password_verify('Apollo@2026!', $s['password_hash']);
    echo "  " . ($result ? "PASS" : "FAIL") . " - {$s['email']}\n";
}
echo "\n";

// Step 5: Fix
echo "STEP 5: Applying fix...\n";
$newHash = password_hash('Apollo@2026!', PASSWORD_BCRYPT, ['cost' => 12]);

try {
    // Clear password history for staff
    $pdo->exec("DELETE FROM password_history WHERE user_type = 'staff'");

    if (empty($staff)) {
        // Insert all staff fresh
        $staffData = [
            ['Dr. Ramesh Kumar', 'ramesh.kumar@apollouniversity.edu.in', 'ict_head',          'ICT Head',          '9876543210'],
            ['Ms. Priya Sharma', 'priya.sharma@apollouniversity.edu.in', 'ict_head',          'Assistant ICT',     '9876543211'],
            ['Mr. Arun Verma',   'arun.verma@apollouniversity.edu.in',   'assistant_manager', 'Assistant Manager', '9876543212'],
            ['Mr. Kiran Patel',  'kiran.patel@apollouniversity.edu.in',  'sr_it_executive',   'Sr. IT Executive',  '9876543213'],
            ['Mr. Suresh Reddy', 'suresh.reddy@apollouniversity.edu.in', 'sr_it_executive',   'Sr. IT Executive',  '9876543214'],
            ['Ms. Deepa Nair',   'deepa.nair@apollouniversity.edu.in',   'sr_it_executive',   'Sr. IT Executive',  '9876543215'],
        ];
        $ins = $pdo->prepare("INSERT INTO it_staff (name, email, password_hash, role, designation, contact, is_active) VALUES (?,?,?,?,?,?,1)");
        foreach ($staffData as $s) {
            $ins->execute([$s[0], $s[1], $newHash, $s[2], $s[3], $s[4]]);
            $id = (int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO password_history (user_id, user_type, password_hash) VALUES (?, 'staff', ?)")
                ->execute([$id, $newHash]);
            echo "  Inserted: {$s[0]}\n";
        }
    } else {
        // Update existing
        $upd = $pdo->prepare("UPDATE it_staff SET password_hash = ?, is_active = 1 WHERE id = ?");
        foreach ($staff as $s) {
            $upd->execute([$newHash, $s['id']]);
            $pdo->prepare("INSERT INTO password_history (user_id, user_type, password_hash) VALUES (?, 'staff', ?)")
                ->execute([$s['id'], $newHash]);
            echo "  Updated: {$s['name']}\n";
        }
    }
    echo "\n";
} catch (Exception $e) {
    echo "  ERROR: " . $e->getMessage() . "\n\n";
}

// Step 6: Final verification
echo "STEP 6: Final verification...\n";
$check = $pdo->query("SELECT email, password_hash, is_active FROM it_staff")->fetchAll();
foreach ($check as $c) {
    $ok = password_verify('Apollo@2026!', $c['password_hash']);
    echo "  " . ($ok ? "PASS" : "FAIL") . " | active={$c['is_active']} | {$c['email']}\n";
}

echo "\n";
echo "════════════════════════════════════════════\n";
echo "  Done. All PASS = login will work.\n";
echo "  Login email:    ramesh.kumar\@apollouniversity.edu.in\n";
echo "  Login password: Apollo\@2026!\n";
echo "════════════════════════════════════════════\n";
echo "</pre>";
echo '<hr><p><a href="http://localhost/TMS/auth/login.php"><b>→ Go to Login Page</b></a></p>';
echo '<p style="color:red"><b>DELETE this file after login works: admin_seed/fix_passwords.php and admin_seed/diagnose.php</b></p>';
