<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pdo = getDB();
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

$stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    setFlash('error', 'User not found.');
    header('Location: ' . APP_URL . '/admin/users');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid request token.";
    } else {
        $pass = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!isValidPassword($pass)) {
            $errors[] = "Password must be at least 8 characters with uppercase, number, and special character.";
        }
        if ($pass !== $confirm) {
            $errors[] = "Passwords do not match.";
        }

        if (empty($errors)) {
            $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
            
            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$hash, $userId]);
                
                $pdo->prepare("INSERT INTO password_history (user_id, user_type, password_hash) VALUES (?, 'user', ?)")
                    ->execute([$userId, $hash]);
                
                $pdo->commit();
                
                setFlash('success', "Password for {$user['name']} has been reset successfully.");
                header('Location: ' . APP_URL . '/admin/users');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log('Admin password reset error: ' . $e->getMessage());
                $errors[] = "An error occurred. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Reset User Password — <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/admin.css">
</head>
<body class="bg-light">

<!-- Navbar -->
<nav class="navbar navbar-dark admin-navbar fixed-top">
  <div class="container-fluid">
    <button class="btn btn-sm text-white me-2 d-lg-none" onclick="document.getElementById('adminSidebar').classList.toggle('show')">
      <i class="bi bi-list fs-4"></i>
    </button>
    <a class="navbar-brand" href="#"><i class="bi bi-shield-lock me-2"></i>TMS Admin Panel</a>
    <div class="ms-auto">
      <span class="text-white me-3 d-none d-sm-inline"><i class="bi bi-person-circle me-1"></i><?= h($_SESSION['staff_name'] ?? 'Admin') ?></span>
      <a href="<?= APP_URL ?>/auth/logout" class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
    </div>
  </div>
</nav>

<!-- Sidebar -->
<div class="admin-sidebar" id="adminSidebar">
  <div class="p-3 text-uppercase text-secondary small fw-bold mt-2">Core System</div>
  <nav class="nav flex-column">
    <a class="nav-link" href="<?= APP_URL ?>/admin/dashboard"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/staff"><i class="bi bi-person-badge"></i> IT Staff Management</a>
    <a class="nav-link active" href="<?= APP_URL ?>/admin/users"><i class="bi bi-people"></i> User Management</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/reports"><i class="bi bi-bar-chart-line-fill"></i> System Reports</a>
  </nav>
  <div class="p-3 text-uppercase text-secondary small fw-bold">Account</div>
  <nav class="nav flex-column">
    <a class="nav-link" href="<?= APP_URL ?>/admin/profile"><i class="bi bi-person-gear"></i> My Profile</a>
  </nav>
</div>

<!-- Main Content -->
<div class="admin-main">
  <div class="d-flex align-items-center mb-4">
    <a href="<?= APP_URL ?>/admin/users" class="btn btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i> Back</a>
    <h4 class="mb-0 fw-bold">Reset Password</h4>
  </div>

  <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
          <ul class="mb-0">
              <?php foreach ($errors as $err) echo "<li>" . h($err) . "</li>"; ?>
          </ul>
      </div>
  <?php endif; ?>

  <div class="card shadow-sm border-0" style="max-width: 600px;">
    <div class="card-body p-4">
      
      <div class="mb-4">
        <h6 class="text-muted text-uppercase small fw-bold mb-1">Target Account</h6>
        <div class="fs-5 fw-bold text-dark"><?= h($user['name']) ?></div>
        <div class="text-secondary"><?= h($user['email']) ?></div>
      </div>
      
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h(generateCSRFToken()) ?>">
        
        <div class="mb-3">
          <label class="form-label fw-semibold">New Password *</label>
          <input type="password" name="new_password" class="form-control" required minlength="8" placeholder="Must be at least 8 characters">
        </div>
        
        <div class="mb-4">
          <label class="form-label fw-semibold">Confirm New Password *</label>
          <input type="password" name="confirm_password" class="form-control" required minlength="8">
        </div>

        <div class="pt-3 border-top text-end">
          <button type="submit" class="btn btn-danger px-4" onclick="return confirm('Are you sure you want to enforce this password change?');">
            <i class="bi bi-key-fill me-1"></i> Force Password Reset
          </button>
        </div>
      </form>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
