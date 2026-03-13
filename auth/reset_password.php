<?php
// ============================================================
// Reset Password - Step 3
// ============================================================
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
redirectIfLoggedIn();

// Guard
if (empty($_SESSION['otp_verified']) || empty($_SESSION['pwd_reset_user_id'])) {
    header('Location: ' . APP_URL . '/auth/forgot_password.php');
    exit;
}

$error   = '';
$userId   = (int) $_SESSION['pwd_reset_user_id'];
$userType = $_SESSION['pwd_reset_user_type'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
        $password    = $_POST['password'] ?? '';
        $confirmPwd  = $_POST['confirm_password'] ?? '';

        if (!isValidPassword($password)) {
            $error = 'Password must be at least 8 characters and include uppercase, number, and special character.';
        } elseif ($password !== $confirmPwd) {
            $error = 'Passwords do not match.';
        } else {
            $pdo = getDB();
            // Check password history (last N passwords)
            $stmt = $pdo->prepare("
                SELECT password_hash FROM password_history
                WHERE user_id = ? AND user_type = ?
                ORDER BY changed_at DESC
                LIMIT " . PASSWORD_HISTORY_DEPTH
            );
            $stmt->execute([$userId, $userType]);
            $history = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $reused = false;
            foreach ($history as $oldHash) {
                if (password_verify($password, $oldHash)) {
                    $reused = true;
                    break;
                }
            }

            if ($reused) {
                $error = 'You cannot reuse one of your last ' . PASSWORD_HISTORY_DEPTH . ' passwords. Please choose a different password.';
            } else {
                $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $table   = ($userType === 'staff') ? 'it_staff' : 'users';

                $pdo->beginTransaction();
                try {
                    $pdo->prepare("UPDATE {$table} SET password_hash = ? WHERE id = ?")->execute([$newHash, $userId]);
                    $pdo->prepare("
                        INSERT INTO password_history (user_id, user_type, password_hash) VALUES (?, ?, ?)
                    ")->execute([$userId, $userType, $newHash]);
                    $pdo->commit();

                    // Clear reset session vars
                    unset($_SESSION['otp_verified'], $_SESSION['pwd_reset_user_id'],
                          $_SESSION['pwd_reset_user_type'], $_SESSION['pwd_reset_email']);

                    setFlash('success', 'Password changed successfully! Please log in with your new password.');
                    header('Location: ' . APP_URL . '/auth/login.php');
                    exit;
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    error_log('Reset password error: ' . $e->getMessage());
                    $error = 'Something went wrong. Please try again.';
                }
            }
        }
    }
}
$csrf = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password — <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/auth.css">
</head>
<body class="auth-page">
<div class="auth-card">
    <div class="auth-header">
        <img src="<?= APP_URL ?>/assets/images/apollo_logo.png" alt="Apollo University Logo">
        <h1>Set New Password</h1>
        <p>Choose a strong password for your account</p>
    </div>
    <div class="auth-body">
        <?php if ($error): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?= h($error) ?></div>
        <?php endif; ?>

        <div class="alert alert-info mb-3" style="font-size:.82rem;">
            <i class="bi bi-info-circle me-1"></i>
            Password must be <strong>8+ characters</strong> with at least one
            <strong>uppercase letter</strong>, <strong>number</strong>, and <strong>special character</strong>.
            Cannot match your last <?= PASSWORD_HISTORY_DEPTH ?> passwords.
        </div>

        <form method="POST" action="reset_password.php">
            <?= csrfField() ?>
            <div class="mb-3">
                <label class="form-label fw-semibold">New Password</label>
                <div class="input-group">
                    <input type="password" name="password" id="pwdInput" class="form-control"
                           placeholder="Enter new password" required>
                    <button type="button" class="btn btn-outline-secondary"
                            onclick="const i=document.getElementById('pwdInput');i.type=i.type==='password'?'text':'password'">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <div class="mt-1">
                    <div class="pwd-strength-bar" id="strengthBar" style="width:0;"></div>
                    <span id="strengthText" class="pwd-strength-text"></span>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold">Confirm New Password</label>
                <input type="password" name="confirm_password" id="confirmPwd" class="form-control"
                       placeholder="Re-enter new password" required>
                <small id="pwdMatch"></small>
            </div>
            <button type="submit" class="btn-login">
                <i class="bi bi-check-circle me-2"></i>Update Password
            </button>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script>
updateStrengthMeter('pwdInput', 'strengthBar', 'strengthText');
document.getElementById('confirmPwd').addEventListener('input', function() {
    const m = document.getElementById('pwdMatch');
    if (this.value === document.getElementById('pwdInput').value) {
        m.textContent = '✓ Passwords match'; m.className = 'text-success';
    } else {
        m.textContent = '✗ Does not match'; m.className = 'text-danger';
    }
});
</script>
</body>
</html>
