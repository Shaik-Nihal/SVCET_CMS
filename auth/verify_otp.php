<?php
// ============================================================
// Verify OTP - Step 2
// ============================================================
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
redirectIfLoggedIn();

// Guard: must have gone through forgot_password.php
if (empty($_SESSION['pwd_reset_user_id'])) {
    header('Location: ' . APP_URL . '/auth/forgot_password');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
        // Check max attempts
        if (($_SESSION['otp_attempts'] ?? 0) >= OTP_MAX_ATTEMPTS) {
            unset($_SESSION['pwd_reset_user_id'], $_SESSION['pwd_reset_user_type'],
                  $_SESSION['pwd_reset_email'], $_SESSION['otp_attempts']);
            setFlash('error', 'Too many incorrect OTP attempts. Please start again.');
            header('Location: ' . APP_URL . '/auth/forgot_password');
            exit;
        }

        $otp      = trim($_POST['otp'] ?? '');
        $userId   = $_SESSION['pwd_reset_user_id'];
        $userType = $_SESSION['pwd_reset_user_type'];
        $pdo      = getDB();

        $stmt = $pdo->prepare("
            SELECT id, token FROM password_reset_tokens
            WHERE user_id = ? AND user_type = ? AND used_at IS NULL AND expires_at > NOW()
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$userId, $userType]);
        $row = $stmt->fetch();

        if ($row && hash_equals($row['token'], hashOTP($otp))) {
            // Mark token as used
            $pdo->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?")->execute([$row['id']]);
            $_SESSION['otp_verified'] = true;
            unset($_SESSION['otp_attempts']);
            header('Location: ' . APP_URL . '/auth/reset_password');
            exit;
        } else {
            $_SESSION['otp_attempts'] = ($_SESSION['otp_attempts'] ?? 0) + 1;
            $remaining = OTP_MAX_ATTEMPTS - $_SESSION['otp_attempts'];
            $error = "Incorrect OTP. You have {$remaining} attempt(s) remaining.";
        }
    }
}
$csrf = generateCSRFToken();
$maskedEmail = '';
if (!empty($_SESSION['pwd_reset_email'])) {
    $parts = explode('@', $_SESSION['pwd_reset_email']);
    $name  = $parts[0];
    $maskedEmail = substr($name, 0, 2) . str_repeat('*', max(2, strlen($name) - 2)) . '@' . $parts[1];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify OTP — <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/auth.css">
</head>
<body class="auth-page">
<div class="auth-card">
    <div class="auth-header">
        <img src="<?= APP_LOGO_URL ?>" alt="<?= APP_LOGO_ALT ?>">
        <h1>Enter OTP</h1>
        <p>We sent a 6-digit code to <strong><?= h($maskedEmail) ?></strong></p>
    </div>
    <div class="auth-body">
        <?php if ($error): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="verify_otp">
            <?= csrfField() ?>
            <div class="mb-3 text-center">
                <label class="form-label fw-semibold d-block">Enter OTP</label>
                <input type="text" name="otp" class="otp-input form-control"
                       maxlength="6" inputmode="numeric" pattern="[0-9]{6}"
                       placeholder="000000" autocomplete="one-time-code" required>
            </div>
            <div class="text-center mb-3">
                <small>OTP expires in: <span id="otp-timer" class="fw-bold"></span></small>
            </div>
            <button type="submit" class="btn-login">
                <i class="bi bi-check2-circle me-2"></i>Verify OTP
            </button>
        </form>
        <div class="text-center mt-3">
            <small><a href="forgot_password" class="text-primary">Resend OTP / Use different email</a></small>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Countdown timer (15 minutes)
let seconds = <?= OTP_EXPIRY_MINUTES * 60 ?>;
const timerEl = document.getElementById('otp-timer');
const interval = setInterval(() => {
    seconds--;
    if (seconds <= 0) {
        clearInterval(interval);
        timerEl.textContent = 'Expired';
        timerEl.className = 'text-danger fw-bold';
        document.querySelector('button[type=submit]').disabled = true;
        return;
    }
    const m = String(Math.floor(seconds / 60)).padStart(2, '0');
    const s = String(seconds % 60).padStart(2, '0');
    timerEl.textContent = `${m}:${s}`;
}, 1000);

// Auto-submit when 6 digits entered
document.querySelector('.otp-input').addEventListener('input', function() {
    if (this.value.length === 6) this.form.submit();
});
</script>
</body>
</html>
