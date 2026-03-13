<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireUser();

$pdo    = getDB();
$userId = currentUserId();

// Load user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$profileErrors = [];
$pwdErrors     = [];

// Update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $profileErrors[] = 'Invalid request.';
    } else {
        $name       = trim($_POST['name'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $rollNo     = trim($_POST['roll_no'] ?? '');

        if (strlen($name) < 2) $profileErrors[] = 'Name must be at least 2 characters.';
        if ($phone && !isValidPhone($phone)) $profileErrors[] = 'Phone must be a 10-digit mobile number.';

        if (empty($profileErrors)) {
            $pdo->prepare("UPDATE users SET name=?, phone=?, department=?, roll_no=? WHERE id=?")
                ->execute([$name, $phone ?: null, $department ?: null, $rollNo ?: null, $userId]);
            $_SESSION['user_name'] = $name;
            $user['name']       = $name;
            $user['phone']      = $phone;
            $user['department'] = $department;
            $user['roll_no']    = $rollNo;
            setFlash('success', 'Profile updated successfully.');
        }
    }
}

// Change password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $pwdErrors[] = 'Invalid request.';
    } else {
        $currentPwd = $_POST['current_password'] ?? '';
        $newPwd     = $_POST['new_password'] ?? '';
        $confirmPwd = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPwd, $user['password_hash'])) {
            $pwdErrors[] = 'Current password is incorrect.';
        } elseif (!isValidPassword($newPwd)) {
            $pwdErrors[] = 'New password must be 8+ chars with uppercase, number, and special character.';
        } elseif ($newPwd !== $confirmPwd) {
            $pwdErrors[] = 'New passwords do not match.';
        } else {
            // Password history check
            $stmt = $pdo->prepare("SELECT password_hash FROM password_history WHERE user_id=? AND user_type='user' ORDER BY changed_at DESC LIMIT " . PASSWORD_HISTORY_DEPTH);
            $stmt->execute([$userId]);
            $history = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $reused  = false;
            foreach ($history as $old) {
                if (password_verify($newPwd, $old)) { $reused = true; break; }
            }
            if ($reused) {
                $pwdErrors[] = 'Cannot reuse one of your last ' . PASSWORD_HISTORY_DEPTH . ' passwords.';
            } else {
                $hash = password_hash($newPwd, PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash, $userId]);
                $pdo->prepare("INSERT INTO password_history (user_id, user_type, password_hash) VALUES (?, 'user', ?)")->execute([$userId, $hash]);
                $user['password_hash'] = $hash;
                setFlash('success', 'Password changed successfully.');
            }
        }
    }
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE recipient_id=? AND recipient_type='user' AND is_read=0");
$stmt->execute([$userId]);
$unreadCount = (int)$stmt->fetchColumn();

$departments = ['Computer Science','Information Technology','Electronics','Mechanical','Civil','MBA','MCA','BCA','Other'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="csrf-token" content="<?= generateCSRFToken() ?>">
<meta name="app-url" content="<?= APP_URL ?>">
<title>My Profile — <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body data-user-type="user">

<nav class="navbar navbar-expand-lg navbar-apollo fixed-top">
  <div class="container-fluid">
    <a class="navbar-brand" href="<?= APP_URL ?>/user/dashboard.php"><img src="<?= APP_URL ?>/assets/images/apollo_logo.png" alt="Logo"><?= APP_SHORT ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
      <span class="navbar-toggler-icon" style="filter:invert(1)"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMenu">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/user/dashboard.php"><i class="bi bi-house me-1"></i>Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/user/raise_ticket.php"><i class="bi bi-plus-circle me-1"></i>Raise Ticket</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/user/my_tickets.php"><i class="bi bi-ticket-perforated me-1"></i>My Tickets</a></li>
      </ul>
      <ul class="navbar-nav ms-auto align-items-center">
        <li class="nav-item me-2">
          <a class="nav-link notif-bell position-relative" href="<?= APP_URL ?>/user/notifications.php">
            <i class="bi bi-bell-fill" style="font-size:1.1rem;color:#fff;"></i>
            <span class="notif-badge badge rounded-pill bg-danger <?= $unreadCount ? '' : 'd-none' ?>" id="notif-badge"><?= $unreadCount ?: '' ?></span>
          </a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle active" href="#" data-bs-toggle="dropdown"><i class="bi bi-person-circle me-1"></i><?= h($_SESSION['user_name']) ?></a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="<?= APP_URL ?>/user/profile.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>
<div style="padding-top:70px;"></div>

<div class="container-fluid px-4 py-3">
  <div class="page-title-bar">
    <h4><i class="bi bi-person-circle me-2"></i>My Profile</h4>
  </div>

  <?php renderFlash(); ?>

  <div class="row g-3">
    <!-- Profile Info -->
    <div class="col-lg-6">
      <div class="card">
        <div class="card-header"><i class="bi bi-pencil me-2"></i>Update Profile</div>
        <div class="card-body">
          <?php if ($profileErrors): ?>
          <div class="alert alert-danger"><?php foreach ($profileErrors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?></div>
          <?php endif; ?>
          <form method="POST" action="profile.php">
            <?= csrfField() ?>
            <input type="hidden" name="update_profile" value="1">
            <div class="mb-3">
              <label class="form-label fw-semibold">Full Name</label>
              <input type="text" name="name" class="form-control" value="<?= h($user['name']) ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Email <span class="badge bg-secondary">Read-only</span></label>
              <input type="email" class="form-control bg-light" value="<?= h($user['email']) ?>" disabled>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Phone Number</label>
              <input type="tel" name="phone" class="form-control" value="<?= h($user['phone'] ?? '') ?>" placeholder="10-digit mobile">
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Department</label>
              <select name="department" class="form-select">
                <option value="">— Select —</option>
                <?php foreach ($departments as $d): ?>
                <option value="<?= h($d) ?>" <?= ($user['department'] ?? '') === $d ? 'selected' : '' ?>><?= h($d) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Roll / Emp Number</label>
              <input type="text" name="roll_no" class="form-control" value="<?= h($user['roll_no'] ?? '') ?>">
            </div>
            <button type="submit" class="btn btn-apollo"><i class="bi bi-check2 me-2"></i>Save Changes</button>
          </form>
        </div>
      </div>
    </div>

    <!-- Change Password -->
    <div class="col-lg-6">
      <div class="card">
        <div class="card-header"><i class="bi bi-lock me-2"></i>Change Password</div>
        <div class="card-body">
          <?php if ($pwdErrors): ?>
          <div class="alert alert-danger"><?php foreach ($pwdErrors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?></div>
          <?php endif; ?>
          <form method="POST" action="profile.php">
            <?= csrfField() ?>
            <input type="hidden" name="change_password" value="1">
            <div class="mb-3">
              <label class="form-label fw-semibold">Current Password</label>
              <input type="password" name="current_password" class="form-control" placeholder="Enter current password" required>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">New Password</label>
              <input type="password" name="new_password" id="newPwd" class="form-control" placeholder="Min 8 chars + uppercase + number + special" required>
              <div class="mt-1"><div class="pwd-strength-bar" id="strengthBar" style="width:0;"></div><span id="strengthText" class="pwd-strength-text"></span></div>
            </div>
            <div class="mb-4">
              <label class="form-label fw-semibold">Confirm New Password</label>
              <input type="password" name="confirm_password" id="confirmPwd" class="form-control" placeholder="Re-enter new password" required>
              <small id="pwdMatch"></small>
            </div>
            <div class="alert alert-info" style="font-size:.8rem;">
              <i class="bi bi-info-circle me-1"></i>Cannot reuse last <?= PASSWORD_HISTORY_DEPTH ?> passwords.
            </div>
            <button type="submit" class="btn btn-apollo"><i class="bi bi-lock me-2"></i>Update Password</button>
          </form>
        </div>
      </div>

      <!-- Account Info -->
      <div class="card mt-3">
        <div class="card-header"><i class="bi bi-info-circle me-2"></i>Account Info</div>
        <div class="card-body" style="font-size:.85rem;">
          <div class="row g-2">
            <div class="col-5 text-muted">Member Since</div>
            <div class="col-7"><?= formatDate($user['created_at'], 'd M Y') ?></div>
            <div class="col-5 text-muted">Email</div>
            <div class="col-7"><?= h($user['email']) ?></div>
            <div class="col-5 text-muted">Account Type</div>
            <div class="col-7"><span class="badge bg-primary">Student / Faculty</span></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script src="<?= APP_URL ?>/assets/js/notifications.js"></script>
<script>
updateStrengthMeter('newPwd','strengthBar','strengthText');
document.getElementById('confirmPwd').addEventListener('input', function() {
    const m = document.getElementById('pwdMatch');
    if (this.value === document.getElementById('newPwd').value) { m.textContent='✓ Match'; m.className='text-success'; }
    else { m.textContent='✗ No match'; m.className='text-danger'; }
});
</script>
</body>
</html>
