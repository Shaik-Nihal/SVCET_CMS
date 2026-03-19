<?php
// ============================================================
// Login Page - User (Student/Faculty) + IT Staff
// ============================================================
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
redirectIfLoggedIn();

$type  = $_GET['type'] ?? 'user';  // 'user' or 'staff'
$error = '';

// Periodically clean up old login attempts (lightweight, runs on page load)
cleanupOldLoginAttempts();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $loginAs  = $_POST['login_as'] ?? 'user';
        $pdo      = getDB();

        // DB-backed brute force check
        $lockRemaining = checkLoginLockDB($email);
        if ($lockRemaining > 0) {
            $remaining = (int) ceil($lockRemaining / 60);
            $error = "Too many failed attempts. Please wait {$remaining} minute(s) before trying again.";
        } elseif ($loginAs === 'staff') {
            $isOwnerEmail = OWNER_ADMIN_EMAIL !== '' && hash_equals(OWNER_ADMIN_EMAIL, $email);

            // Owner admin auth is env-backed and never read from DB.
            if ($isOwnerEmail) {
                $ownerPassConfigured = OWNER_ADMIN_PASSWORD_HASH !== '';
                $ownerPassOk =
                    (OWNER_ADMIN_PASSWORD_HASH !== '' && password_verify($password, OWNER_ADMIN_PASSWORD_HASH));

                if ($ownerPassOk) {
                    session_regenerate_id(true);
                    clearLoginFailuresDB($email);
                    rotateCSRFToken();
                    $_SESSION['staff_id']        = 0;
                    $_SESSION['staff_name']      = OWNER_ADMIN_NAME;
                    $_SESSION['staff_role']      = ROLE_ADMIN;
                    $_SESSION['staff_email']     = OWNER_ADMIN_EMAIL;
                    $_SESSION['is_owner_admin']  = 1;
                    $_SESSION['user_type']       = 'staff';
                    $_SESSION['last_activity']   = time();
                    $_SESSION['session_created'] = time();

                    header('Location: ' . APP_URL . '/admin/dashboard');
                    exit;
                }

                recordLoginFailureDB($email);
                $error = $ownerPassConfigured
                    ? 'Invalid email or password.'
                    : 'Owner admin credentials are not configured. Please set OWNER_ADMIN_PASSWORD_HASH in local.env.php.';
            } else {
                // Regular staff authenticate from DB; DB-based admin accounts are blocked from login.
                $stmt = $pdo->prepare("SELECT id, name, email, password_hash, role FROM it_staff WHERE email = ? AND is_active = 1 AND role != 'admin' LIMIT 1");
                $stmt->execute([$email]);
                $row = $stmt->fetch();

                if ($row && password_verify($password, $row['password_hash'])) {
                session_regenerate_id(true);
                clearLoginFailuresDB($email);
                rotateCSRFToken();
                $_SESSION['staff_id']       = $row['id'];
                $_SESSION['staff_name']     = $row['name'];
                $_SESSION['staff_role']     = $row['role'];
                $_SESSION['staff_email']    = $row['email'];
                $_SESSION['is_owner_admin'] = 0;
                $_SESSION['user_type']      = 'staff';
                $_SESSION['last_activity']  = time();
                $_SESSION['session_created']= time();

                    header('Location: ' . APP_URL . '/staff/dashboard');
                    exit;
                }

                recordLoginFailureDB($email);
                $error = 'Invalid email or password.';
            }
        } else {
            $stmt = $pdo->prepare("SELECT id, name, password_hash FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $row = $stmt->fetch();

            if ($row && password_verify($password, $row['password_hash'])) {
                session_regenerate_id(true);
                clearLoginFailuresDB($email);
                rotateCSRFToken();
                $_SESSION['user_id']        = $row['id'];
                $_SESSION['user_name']      = $row['name'];
                $_SESSION['user_type']      = 'user';
                $_SESSION['last_activity']  = time();
                $_SESSION['session_created']= time();
                header('Location: ' . APP_URL . '/user/dashboard');
                exit;
            } else {
                recordLoginFailureDB($email);
                $error = 'Invalid email or password.';
            }
        }
    }
}
$csrf = generateCSRFToken();
$activeTab = $_POST['login_as'] ?? 'user';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/auth.css">
</head>
<body class="auth-page">

<div class="auth-card">
    <div class="auth-header">
        <img src="<?= APP_LOGO_URL ?>" alt="<?= APP_LOGO_ALT ?>">
        <h1><?= APP_SHORT ?></h1>
        <p><?= SUPPORT_PORTAL_NAME ?></p>
    </div>

    <div class="auth-body">
        <?php if (!empty($_GET['timeout'])): ?>
        <div class="alert alert-warning"><i class="bi bi-clock me-2"></i>Your session expired. Please log in again.</div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?= h($error) ?></div>
        <?php endif; ?>

        <?php $flash = getFlash(); if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : h($flash['type']) ?>">
            <?= h($flash['message']) ?>
        </div>
        <?php endif; ?>

        <!-- Tabs -->
        <ul class="nav auth-tabs mb-3" id="loginTabs">
            <li class="nav-item">
                <button class="nav-link <?= $activeTab !== 'staff' ? 'active' : '' ?>" data-tab="user"
                        data-switch-tab="user">
                    <i class="bi bi-person-fill me-1"></i>Student / Faculty
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link <?= $activeTab === 'staff' ? 'active' : '' ?>" data-tab="staff"
                        data-switch-tab="staff">
                    <i class="bi bi-person-gear me-1"></i>IT Staff
                </button>
            </li>
        </ul>

        <form method="POST" action="login" id="loginForm">
            <?= csrfField() ?>
            <input type="hidden" name="login_as" id="login_as_field" value="<?= $activeTab === 'staff' ? 'staff' : 'user' ?>">

            <div class="mb-3">
                <label class="form-label fw-semibold">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" name="email" class="form-control"
                           placeholder="yourname@<?= EMAIL_DOMAIN ?>"
                           value="<?= h($_POST['email'] ?? '') ?>"
                           required autocomplete="email">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" id="passwordInput" class="form-control"
                           placeholder="Enter your password" required autocomplete="current-password">
                    <button type="button" id="togglePasswordBtn" class="btn btn-outline-secondary" data-toggle-password-target="#passwordInput">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="rememberMe">
                    <label class="form-check-label small" for="rememberMe">Remember me</label>
                </div>
                <a href="forgot_password" class="small text-primary">Forgot Password?</a>
            </div>

            <button type="submit" class="btn-login">
                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
            </button>
        </form>

        <div id="user-register-link" class="text-center mt-3 <?= $activeTab === 'staff' ? 'd-none' : '' ?>">
            <small class="text-muted">Don't have an account?
                <a href="register" class="text-primary fw-semibold">Register here</a>
            </small>
        </div>
    </div>
</div>

<script nonce="<?= cspNonce() ?>" src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script nonce="<?= cspNonce() ?>" src="<?= APP_URL ?>/assets/js/main.js"></script>
<script nonce="<?= cspNonce() ?>">
function switchTab(tab) {
    document.querySelectorAll('#loginTabs .nav-link').forEach(l => l.classList.remove('active'));
    document.querySelector(`[data-tab="${tab}"]`).classList.add('active');
    document.getElementById('login_as_field').value = tab;
    document.getElementById('user-register-link').classList.toggle('d-none', tab === 'staff');
}

document.getElementById('passwordInput').addEventListener('input', () => {
    const inp = document.getElementById('passwordInput');
    const ico = document.getElementById('eyeIcon');
    ico.className = inp.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
});

document.getElementById('togglePasswordBtn').addEventListener('click', () => {
    const inp = document.getElementById('passwordInput');
    const ico = document.getElementById('eyeIcon');
    setTimeout(() => {
        ico.className = inp.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
    }, 0);
});
</script>
</body>
</html>
