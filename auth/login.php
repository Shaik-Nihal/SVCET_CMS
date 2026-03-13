<?php
// ============================================================
// Login Page - User (Student/Faculty) + IT Staff
// ============================================================
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
redirectIfLoggedIn();

$type  = $_GET['type'] ?? 'user';  // 'user' or 'staff'
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please refresh and try again.';
    } elseif (checkLoginLock()) {
        $remaining = ceil(($_SESSION['login_locked_until'] - time()) / 60);
        $error = "Too many failed attempts. Please wait {$remaining} minute(s) before trying again.";
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $loginAs  = $_POST['login_as'] ?? 'user';
        $pdo      = getDB();

        if ($loginAs === 'staff') {
            $stmt = $pdo->prepare("SELECT id, name, password_hash, role FROM it_staff WHERE email = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$email]);
            $row = $stmt->fetch();

            if ($row && password_verify($password, $row['password_hash'])) {
                session_regenerate_id(true);
                resetLoginFailures();
                $_SESSION['staff_id']       = $row['id'];
                $_SESSION['staff_name']     = $row['name'];
                $_SESSION['staff_role']     = $row['role'];
                $_SESSION['user_type']      = 'staff';
                $_SESSION['last_activity']  = time();
                $_SESSION['session_created']= time();
                header('Location: ' . APP_URL . '/staff/dashboard.php');
                exit;
            } else {
                recordLoginFailure();
                $error = 'Invalid email or password.';
            }
        } else {
            $stmt = $pdo->prepare("SELECT id, name, password_hash FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $row = $stmt->fetch();

            if ($row && password_verify($password, $row['password_hash'])) {
                session_regenerate_id(true);
                resetLoginFailures();
                $_SESSION['user_id']        = $row['id'];
                $_SESSION['user_name']      = $row['name'];
                $_SESSION['user_type']      = 'user';
                $_SESSION['last_activity']  = time();
                $_SESSION['session_created']= time();
                header('Location: ' . APP_URL . '/user/dashboard.php');
                exit;
            } else {
                recordLoginFailure();
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
        <img src="<?= APP_URL ?>/assets/images/apollo_logo.png" alt="Apollo University Logo">
        <h1><?= APP_SHORT ?></h1>
        <p>The Apollo University · IT Support Portal</p>
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
                        onclick="switchTab('user')">
                    <i class="bi bi-person-fill me-1"></i>Student / Faculty
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link <?= $activeTab === 'staff' ? 'active' : '' ?>" data-tab="staff"
                        onclick="switchTab('staff')">
                    <i class="bi bi-person-gear me-1"></i>IT Staff
                </button>
            </li>
        </ul>

        <form method="POST" action="login.php" id="loginForm">
            <?= csrfField() ?>
            <input type="hidden" name="login_as" id="login_as_field" value="<?= $activeTab === 'staff' ? 'staff' : 'user' ?>">

            <div class="mb-3">
                <label class="form-label fw-semibold">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" name="email" class="form-control"
                           placeholder="yourname@apollouniversity.edu.in"
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
                    <button type="button" class="btn btn-outline-secondary" onclick="togglePassword()">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="rememberMe">
                    <label class="form-check-label small" for="rememberMe">Remember me</label>
                </div>
                <a href="forgot_password.php" class="small text-primary">Forgot Password?</a>
            </div>

            <button type="submit" class="btn-login">
                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
            </button>
        </form>

        <div id="user-register-link" class="text-center mt-3 <?= $activeTab === 'staff' ? 'd-none' : '' ?>">
            <small class="text-muted">Don't have an account?
                <a href="register.php" class="text-primary fw-semibold">Register here</a>
            </small>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function switchTab(tab) {
    document.querySelectorAll('#loginTabs .nav-link').forEach(l => l.classList.remove('active'));
    document.querySelector(`[data-tab="${tab}"]`).classList.add('active');
    document.getElementById('login_as_field').value = tab;
    document.getElementById('user-register-link').classList.toggle('d-none', tab === 'staff');
}
function togglePassword() {
    const inp = document.getElementById('passwordInput');
    const ico = document.getElementById('eyeIcon');
    if (inp.type === 'password') {
        inp.type = 'text';
        ico.className = 'bi bi-eye-slash';
    } else {
        inp.type = 'password';
        ico.className = 'bi bi-eye';
    }
}
</script>
</body>
</html>
