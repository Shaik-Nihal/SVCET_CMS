<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireStaff();
requirePermission('users.manage');

$pdo = getDB();
$staffId = currentStaffId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'], $_POST['csrf_token'])) {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        setFlash('error', 'Invalid request token.');
    } else {
        $userId = (int)$_POST['delete_user_id'];
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM notifications WHERE recipient_id = ? AND recipient_type = 'user'")->execute([$userId]);
            $pdo->prepare("DELETE FROM password_history WHERE user_id = ? AND user_type = 'user'")->execute([$userId]);
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
            $pdo->commit();
            setFlash('success', 'User deleted.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('staff/manage_users error: ' . $e->getMessage());
            setFlash('error', 'Failed to delete user.');
        }
    }

    header('Location: ' . APP_URL . '/staff/manage_users');
    exit;
}

$users = $pdo->query("SELECT id, name, email, phone, designation, email_verified, created_at FROM users ORDER BY created_at DESC")->fetchAll();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE recipient_id=? AND recipient_type='staff' AND is_read=0");
$stmt->execute([$staffId]);
$unreadCount = (int)$stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>User Directory - <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body data-user-type="staff">

<nav class="navbar navbar-svcet fixed-top staff-navbar" style="z-index:200;">
  <div class="container-fluid">
    <button class="btn btn-sm text-white me-2 d-lg-none" id="sidebarToggle"><i class="bi bi-list" style="font-size:1.3rem;"></i></button>
    <a class="navbar-brand" href="<?= APP_URL ?>/staff/dashboard"><img src="<?= APP_LOGO_URL ?>" alt="<?= APP_LOGO_ALT ?>"><?= APP_SHORT ?></a>
    <div class="ms-auto d-flex align-items-center gap-3">
      <a class="staff-nav-icon" href="<?= APP_URL ?>/staff/notifications" aria-label="Notifications">
        <i class="bi bi-bell-fill" style="font-size:1.1rem;"></i>
        <span class="badge rounded-pill bg-danger position-absolute <?= $unreadCount ? '' : 'd-none' ?>" id="notif-badge" style="top:-6px;right:-8px;font-size:.6rem;"><?= $unreadCount ?: '' ?></span>
      </a>
      <div class="dropdown">
        <a class="staff-user-toggle dropdown-toggle" href="#" data-bs-toggle="dropdown">
          <i class="bi bi-person-circle me-1"></i>
          <span class="d-none d-md-inline"><?= h($_SESSION['staff_name'] ?? 'Staff') ?></span>
        </a>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="<?= APP_URL ?>/staff/profile"><i class="bi bi-person me-2"></i>Profile</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/auth/logout"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>
<div class="staff-top-spacer"></div>

<div class="sidebar" id="sidebar">
  <div class="sidebar-section">Navigation</div>
  <nav class="nav flex-column">
    <a class="nav-link" href="<?= APP_URL ?>/staff/dashboard"><i class="bi bi-speedometer2"></i>Control Center</a>
    <a class="nav-link" href="<?= APP_URL ?>/staff/tickets"><i class="bi bi-ticket-perforated"></i>Work Queue</a>
    <a class="nav-link" href="<?= APP_URL ?>/staff/notifications"><i class="bi bi-bell"></i>Alerts<?php if ($unreadCount): ?><span class="badge bg-danger ms-auto"><?= $unreadCount ?></span><?php endif; ?></a>
    <?php if (currentStaffCanAccessReports()): ?><a class="nav-link" href="<?= APP_URL ?>/staff/reports"><i class="bi bi-bar-chart-line"></i>Insights Lab</a><?php endif; ?>
    <div class="sidebar-section">Admin Studio</div>
    <?php if (currentStaffHasPermission('staff.manage')): ?><a class="nav-link" href="<?= APP_URL ?>/staff/manage_staff"><i class="bi bi-person-badge"></i>Team Hub</a><?php endif; ?>
    <a class="nav-link active" href="<?= APP_URL ?>/staff/manage_users"><i class="bi bi-people"></i>User Directory</a>
    <?php if (currentStaffHasPermission('roles.manage')): ?><a class="nav-link" href="<?= APP_URL ?>/staff/roles"><i class="bi bi-diagram-3"></i>Roles & Permissions</a><?php endif; ?>
    <div class="sidebar-section">Account</div>
    <a class="nav-link" href="<?= APP_URL ?>/staff/profile"><i class="bi bi-person-gear"></i>Profile</a>
    <a class="nav-link" href="<?= APP_URL ?>/auth/logout"><i class="bi bi-box-arrow-right"></i>Logout</a>
  </nav>
</div>

<div class="main-content">
  <div class="staff-headline">
    <div>
      <h3><i class="bi bi-mortarboard-fill me-2"></i>User Directory</h3>
      <p>Review student accounts, contact info, and verification status.</p>
    </div>
    <span class="pill"><?= count($users) ?> account(s)</span>
  </div>

  <div class="page-title-bar">
    <h4><i class="bi bi-people me-2"></i>Student Accounts</h4>
  </div>

  <?php renderFlash(); ?>

  <div class="card">
    <div class="card-body p-0">
      <?php if (empty($users)): ?>
      <div class="empty-state"><i class="bi bi-inbox d-block"></i><p>No student accounts found.</p></div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-svcet mb-0">
          <thead><tr><th>Name</th><th>Institutional Email</th><th>Phone</th><th>Designation</th><th>Verification</th><th class="text-end">Controls</th></tr></thead>
          <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td class="fw-semibold"><?= h($u['name']) ?></td>
              <td><?= h($u['email']) ?></td>
              <td><?= h($u['phone'] ?? 'N/A') ?></td>
              <td><?= h($u['designation'] ?? 'N/A') ?></td>
              <td><?= $u['email_verified'] ? '<span class="badge bg-success">Verified</span>' : '<span class="badge bg-secondary">Unverified</span>' ?></td>
              <td class="text-end">
                <form method="post" class="d-inline" onsubmit="return confirm('Delete this user and all related tickets?');">
                  <?= csrfField() ?>
                  <input type="hidden" name="delete_user_id" value="<?= (int)$u['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
