<?php
// ============================================================
// Forgot Password - Step 1: Submit email → send OTP
// ============================================================
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mailer.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
redirectIfLoggedIn();

$error   = '';
$success = '';

$resetMaxRequests = 5;
$resetWindowSecs = 900; // 15 minutes

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!isAllowedEmailDomain($email)) {
            $error = 'Only ' . allowedEmailDomainsLabel() . ' email addresses are allowed.';
        } else {
            $pdo = getDB();
            $ip = getClientIP();
            $throttleKey = 'pwd_reset|' . $email;

            $stmt = $pdo->prepare("\n                SELECT COUNT(*)\n                FROM login_attempts\n                WHERE ip_address = ?\n                  AND email = ?\n                  AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)\n            ");
            $stmt->execute([$ip, $throttleKey, $resetWindowSecs]);
            $requestCount = (int)$stmt->fetchColumn();

            if ($requestCount >= $resetMaxRequests) {
                // Keep response generic to avoid user enumeration.
                $success = 'If this email is registered, an OTP has been sent.';
                usleep(250000);
                goto forgot_password_done;
            }

            // Check users table
            $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            $userType = 'user';

            if (!$user) {
                // Check staff table
                $stmt = $pdo->prepare("SELECT id, name FROM it_staff WHERE email = ? AND is_active = 1 LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                $userType = 'staff';
            }

            if ($user) {
                $otp     = generateOTP();
                $otpHash = hashOTP($otp);
                $expires = date('Y-m-d H:i:s', time() + (OTP_EXPIRY_MINUTES * 60));

                // Delete old tokens for this user
                $pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id = ? AND user_type = ?")->execute([$user['id'], $userType]);

                // Insert new OTP
                $pdo->prepare("
                    INSERT INTO password_reset_tokens (user_id, user_type, token, expires_at)
                    VALUES (?, ?, ?, ?)
                ")->execute([$user['id'], $userType, $otpHash, $expires]);

                // Send email
                $subject = APP_NAME . ' — Password Reset OTP';
                $body = emailTemplate('Password Reset OTP', "
                    <p>Dear {$user['name']},</p>
                    <p>You requested a password reset. Use the OTP below to proceed.</p>
                    <div style='text-align:center;margin:25px 0;'>
                        <div style='background:#f0f4ff;border:2px dashed #2563a8;border-radius:12px;padding:20px;display:inline-block;'>
                            <div style='font-size:2.5rem;font-weight:900;letter-spacing:.4rem;color:#1a3a5c;font-family:Consolas,monospace;'>{$otp}</div>
                            <div style='font-size:.8rem;color:#64748b;margin-top:5px;'>Valid for " . OTP_EXPIRY_MINUTES . " minutes</div>
                        </div>
                    </div>
                    <p style='color:#dc2626;font-size:.85rem;'><strong>Do not share this OTP with anyone.</strong> If you did not request this, please ignore this email.</p>
                ");
                $mailSent = sendEmail($email, $user['name'], $subject, $body);
                if (!$mailSent) {
                    // Avoid stale OTPs when delivery fails.
                    $pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id = ? AND user_type = ?")
                        ->execute([$user['id'], $userType]);
                } else {
                    // Store in session for next step (don't expose user_id in URL)
                    $_SESSION['pwd_reset_user_id']   = $user['id'];
                    $_SESSION['pwd_reset_user_type'] = $userType;
                    $_SESSION['pwd_reset_email']     = $email;
                    $_SESSION['otp_attempts']        = 0;
                }
            }

            // Track every reset request attempt for throttling.
            $pdo->prepare("INSERT INTO login_attempts (ip_address, email) VALUES (?, ?)")
                ->execute([$ip, $throttleKey]);

            // Generic message for both existing and non-existing accounts.
            $success = 'If this email is registered, an OTP has been sent.';
            if ($user) {
                header('Refresh: 2; url=' . APP_URL . '/auth/verify_otp');
            }

            forgot_password_done:
            // Keep timing close between paths to reduce side-channel leakage.
            usleep(250000);
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
<title>Forgot Password — <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/auth.css">
</head>
<body class="auth-page">
<div class="auth-card">
    <div class="auth-header">
        <img src="<?= APP_LOGO_URL ?>" alt="<?= APP_LOGO_ALT ?>">
        <h1>Forgot Password</h1>
        <p>Enter your registered email to receive an OTP</p>
    </div>
    <div class="auth-body">
        <?php if ($error): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?= h($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?= $success ?></div>
        <?php endif; ?>

        <form method="POST" action="forgot_password">
            <?= csrfField() ?>
            <div class="mb-3">
                <label class="form-label fw-semibold">Registered Email Address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" name="email" class="form-control"
                           placeholder="yourname@<?= EMAIL_DOMAIN ?>"
                           value="<?= h($_POST['email'] ?? '') ?>" required>
                </div>
            </div>
            <button type="submit" class="btn-login">
                <i class="bi bi-send me-2"></i>Send OTP
            </button>
        </form>
        <div class="text-center mt-3">
            <a href="login" class="small text-primary"><i class="bi bi-arrow-left me-1"></i>Back to Login</a>
        </div>
    </div>
</div>
<script nonce="<?= cspNonce() ?>" src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
