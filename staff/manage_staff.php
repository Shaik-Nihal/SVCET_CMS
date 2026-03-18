<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireStaff();
requirePermission('staff.manage');

$pdo = getDB();
$staffId = currentStaffId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        setFlash('error', 'Invalid request token.');
    } else {
        try {
            if (isset($_POST['toggle_staff_id'])) {
                $targetId = (int)$_POST['toggle_staff_id'];
                $stmt = $pdo->prepare("UPDATE it_staff SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$targetId]);
                setFlash('success', 'Staff status updated.');
            } elseif (isset($_POST['delete_staff_id'])) {
                $targetId = (int)$_POST['delete_staff_id'];

                $pdo->beginTransaction();
                $pdo->prepare("UPDATE tickets SET assigned_to = NULL WHERE assigned_to = ?")->execute([$targetId]);
                $pdo->prepare("DELETE FROM it_staff WHERE id = ?")->execute([$targetId]);
                $pdo->commit();

                setFlash('success', 'Staff member deleted. Assigned tickets are now unassigned.');
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('staff/manage_staff error: ' . $e->getMessage());
            setFlash('error', 'Action failed. Please try again.');
        }
    }

    header('Location: ' . APP_URL . '/staff/manage_staff');
    exit;
}

$staff = $pdo->query("SELECT id, name, email, designation, role, contact, is_active FROM it_staff ORDER BY role, name")->fetchAll();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE recipient_id=? AND recipient_type='staff' AND is_read=0");
$stmt->execute([$staffId]);
$unreadCount = (int)$stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Manage Staff - <?= APP_NAME ?></title>
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
    <?php if (currentStaffHasPermission('reports.view')): ?><a class="nav-link" href="<?= APP_URL ?>/staff/reports"><i class="bi bi-bar-chart-line"></i>Insights Lab</a><?php endif; ?>
    <div class="sidebar-section">Admin Studio</div>
    <a class="nav-link active" href="<?= APP_URL ?>/staff/manage_staff"><i class="bi bi-person-badge"></i>Team Hub</a>
    <?php if (currentStaffHasPermission('users.manage')): ?><a class="nav-link" href="<?= APP_URL ?>/staff/manage_users"><i class="bi bi-people"></i>User Directory</a><?php endif; ?>
    <?php if (currentStaffHasPermission('roles.manage')): ?><a class="nav-link" href="<?= APP_URL ?>/admin/roles"><i class="bi bi-diagram-3"></i>Roles & Permissions</a><?php endif; ?>
    <div class="sidebar-section">Account</div>
    <a class="nav-link" href="<?= APP_URL ?>/staff/profile"><i class="bi bi-person-gear"></i>Profile</a>
    <a class="nav-link" href="<?= APP_URL ?>/auth/logout"><i class="bi bi-box-arrow-right"></i>Logout</a>
  </nav>
</div>

<div class="main-content">
  <div class="staff-headline">
    <div>
      <h3><i class="bi bi-people-fill me-2"></i>Team Hub</h3>
      <p>Manage technicians, role coverage, and account activation from this panel.</p>
    </div>
    <span class="pill"><?= count($staff) ?> staff member(s)</span>
  </div>

  <div class="page-title-bar">
    <h4><i class="bi bi-person-badge me-2"></i>Team Directory</h4>
    <a href="<?= APP_URL ?>/admin/staff_form" class="btn btn-sm btn-svcet"><i class="bi bi-plus-lg me-1"></i>Invite Staff</a>
  </div>

  <?php renderFlash(); ?>

  <div class="card">
    <div class="card-body p-0">
      <?php if (empty($staff)): ?>
      <div class="empty-state"><i class="bi bi-inbox d-block"></i><p>No team members found.</p></div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-svcet mb-0">
          <thead><tr><th>Team Member</th><th>Work Email</th><th>Access Role</th><th>Function</th><th>Account State</th><th class="text-end">Controls</th></tr></thead>
          <tbody>
          <?php foreach ($staff as $s): ?>
            <tr class="<?= $s['is_active'] ? '' : 'table-secondary opacity-75' ?>">
              <td class="fw-semibold"><?= h($s['name']) ?></td>
              <td><?= h($s['email']) ?></td>
              <td><?= roleBadge($s['role']) ?></td>
              <td><?= h($s['designation'] ?? 'N/A') ?></td>
              <td><?= $s['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>' ?></td>
              <td class="text-end">
                <form method="post" class="d-inline" onsubmit="return confirm('Toggle active status?');">
                  <?= csrfField() ?>
                  <input type="hidden" name="toggle_staff_id" value="<?= (int)$s['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-warning"><i class="bi bi-power"></i></button>
                </form>
                <form method="post" class="d-inline" onsubmit="return confirm('Delete this staff member?');">
                  <?= csrfField() ?>
                  <input type="hidden" name="delete_staff_id" value="<?= (int)$s['id'] ?>">
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
