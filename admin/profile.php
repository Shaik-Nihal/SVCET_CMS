<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pdo     = getDB();
$staffId = currentStaffId();

$stmt = $pdo->prepare("SELECT * FROM it_staff WHERE id = ?");
$stmt->execute([$staffId]);
$staff = $stmt->fetch();

$profileErrors = [];
$pwdErrors     = [];

// Update contact
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $profileErrors[] = 'Invalid request.';
    } else {
        $contact = trim($_POST['contact'] ?? '');
        if ($contact && !isValidPhone($contact)) $profileErrors[] = 'Contact must be a 10-digit mobile number.';
        if (empty($profileErrors)) {
            $pdo->prepare("UPDATE it_staff SET contact=? WHERE id=?")->execute([$contact ?: null, $staffId]);
            $staff['contact'] = $contact;
            setFlash('success', 'Profile updated.');
            rotateCSRFToken();
        }
    }
}

// Change password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $pwdErrors[] = 'Invalid request.';
    } else {
        $cur = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $cfm = $_POST['confirm_password'] ?? '';
        if (!password_verify($cur, $staff['password_hash'])) {
            $pwdErrors[] = 'Current password is incorrect.';
        } elseif (!isValidPassword($new)) {
            $pwdErrors[] = 'New password must be 8+ chars with uppercase, number, and special character.';
        } elseif ($new !== $cfm) {
            $pwdErrors[] = 'Passwords do not match.';
        } else {
            $stmt = $pdo->prepare("SELECT password_hash FROM password_history WHERE user_id=? AND user_type='staff' ORDER BY changed_at DESC LIMIT ?");
            $stmt->bindValue(1, $staffId, PDO::PARAM_INT);
            $stmt->bindValue(2, PASSWORD_HISTORY_DEPTH, PDO::PARAM_INT);
            $stmt->execute();
            $history = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $reused  = false;
            foreach ($history as $old) { if (password_verify($new, $old)) { $reused = true; break; } }
            if ($reused) {
                $pwdErrors[] = 'Cannot reuse last ' . PASSWORD_HISTORY_DEPTH . ' passwords.';
            } else {
                $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->prepare("UPDATE it_staff SET password_hash=? WHERE id=?")->execute([$hash, $staffId]);
                $pdo->prepare("INSERT INTO password_history (user_id, user_type, password_hash) VALUES (?,'staff',?)")->execute([$staffId, $hash]);
                $staff['password_hash'] = $hash;
                setFlash('success', 'Password changed successfully.');
                rotateCSRFToken();
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
<title>My Profile — <?= APP_NAME ?></title>
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
    <a class="nav-link" href="<?= APP_URL ?>/admin/users"><i class="bi bi-people"></i> User Management</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/reports"><i class="bi bi-bar-chart-line-fill"></i> System Reports</a>
  </nav>
  <div class="p-3 text-uppercase text-secondary small fw-bold">Account</div>
  <nav class="nav flex-column">
    <a class="nav-link active" href="<?= APP_URL ?>/admin/profile"><i class="bi bi-person-gear"></i> My Profile</a>
  </nav>
</div>

<!-- Main Content -->
<div class="admin-main">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 fw-bold"><i class="bi bi-person-circle me-2"></i>My Profile</h4>
  </div>

  <?php renderFlash(); ?>

  <div class="row g-4">
    <!-- Profile Info -->
    <div class="col-lg-5">
      <div class="admin-table-container p-4">
        <h6 class="fw-bold mb-3"><i class="bi bi-person-badge me-2"></i>Staff Details</h6>
        <?php $initials = substr(implode('',array_map(fn($w)=>strtoupper($w[0]),array_filter(explode(' ',$staff['name'])))),0,2); ?>
        <div class="text-center mb-3">
          <div style="width:72px;height:72px;background:#1a3a5c;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:700;color:#fff;margin:0 auto .75rem;"><?= h($initials) ?></div>
          <h5 class="mb-0"><?= h($staff['name']) ?></h5>
          <div class="text-muted"><?= h($staff['designation']) ?></div>
          <div class="mt-2"><?= roleBadge($staff['role']) ?></div>
        </div>
        <table class="table table-sm" style="font-size:.85rem;">
          <tr><td class="text-muted">Email</td><td><?= h($staff['email']) ?></td></tr>
          <tr><td class="text-muted">Contact</td><td><?= h($staff['contact'] ?? '—') ?></td></tr>
          <tr><td class="text-muted">Joined</td><td><?= formatDate($staff['created_at'],'d M Y') ?></td></tr>
        </table>
        <?php if ($profileErrors): ?><div class="alert alert-danger"><?php foreach ($profileErrors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?></div><?php endif; ?>
        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="update_profile" value="1">
          <div class="mb-3">
            <label class="form-label fw-semibold">Contact Number</label>
            <input type="tel" name="contact" class="form-control" value="<?= h($staff['contact'] ?? '') ?>" placeholder="10-digit mobile">
          </div>
          <button type="submit" class="btn btn-apollo"><i class="bi bi-check2 me-2"></i>Save</button>
        </form>
      </div>
    </div>

    <!-- Change Password -->
    <div class="col-lg-7">
      <div class="admin-table-container p-4">
        <h6 class="fw-bold mb-3"><i class="bi bi-lock me-2"></i>Change Password</h6>
        <?php if ($pwdErrors): ?><div class="alert alert-danger"><?php foreach ($pwdErrors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?></div><?php endif; ?>
        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="change_password" value="1">
          <div class="mb-3">
            <label class="form-label fw-semibold">Current Password</label>
            <input type="password" name="current_password" class="form-control" placeholder="Current password" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">New Password</label>
            <input type="password" name="new_password" id="newPwd" class="form-control" placeholder="Min 8 chars + uppercase + number + special" required>
            <div class="mt-1"><div class="pwd-strength-bar" id="strengthBar" style="width:0;"></div><span id="strengthText" class="pwd-strength-text"></span></div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Confirm Password</label>
            <input type="password" name="confirm_password" id="cfmPwd" class="form-control" required>
            <small id="pwdMatch"></small>
          </div>
          <div class="alert alert-info" style="font-size:.8rem;"><i class="bi bi-info-circle me-1"></i>Cannot reuse last <?= PASSWORD_HISTORY_DEPTH ?> passwords.</div>
          <button type="submit" class="btn btn-apollo"><i class="bi bi-lock me-2"></i>Update Password</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script>
updateStrengthMeter('newPwd','strengthBar','strengthText');
document.getElementById('cfmPwd').addEventListener('input', function() {
    const m = document.getElementById('pwdMatch');
    if (this.value === document.getElementById('newPwd').value) { m.textContent='✓ Match'; m.className='text-success'; }
    else { m.textContent='✗ No match'; m.className='text-danger'; }
});
</script>
</body>
</html>
