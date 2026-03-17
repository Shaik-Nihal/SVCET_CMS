<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('users.manage');

$pdo = getDB();

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'], $_POST['csrf_token'])) {
  if (validateCSRFToken($_POST['csrf_token'])) {
    $userId = (int)$_POST['delete_user_id'];
        
    try {
      $pdo->beginTransaction();

      // Clean up notifications and password history; tickets cascade via FK
      $pdo->prepare("DELETE FROM notifications WHERE recipient_id = ? AND recipient_type = 'user'")->execute([$userId]);
      $pdo->prepare("DELETE FROM password_history WHERE user_id = ? AND user_type = 'user'")->execute([$userId]);
      $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);

      $pdo->commit();
      setFlash('success', 'User and associated data deleted.');
    } catch (Exception $e) {
      $pdo->rollBack();
      error_log('User delete error: ' . $e->getMessage());
      setFlash('error', 'Error deleting user. Please try again.');
    }
  } else {
    setFlash('error', 'Invalid request.');
  }
  header('Location: ' . APP_URL . '/admin/users');
  exit;
}

// Fetch users
$users = $pdo->query("SELECT id, name, email, phone, designation, email_verified, created_at FROM users ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>User Management — <?= APP_NAME ?></title>
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
    <a class="nav-link" href="<?= APP_URL ?>/admin/roles"><i class="bi bi-diagram-3"></i> Roles & Permissions</a>
  </nav>
  <div class="p-3 text-uppercase text-secondary small fw-bold">Account</div>
  <nav class="nav flex-column">
    <a class="nav-link" href="<?= APP_URL ?>/admin/profile"><i class="bi bi-person-gear"></i> My Profile</a>
  </nav>
</div>

<!-- Main Content -->
<div class="admin-main">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 fw-bold">Registered Users</h4>
  </div>

  <?php renderFlash(); ?>

  <div class="admin-table-container">
    <div class="table-responsive">
      <table class="table admin-table align-middle">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Designation</th>
            <th>Registered On</th>
            <th>Verification</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td>
              <div class="fw-bold text-dark"><?= h($u['name']) ?></div>
            </td>
            <td><?= h($u['email']) ?></td>
            <td><?= h($u['phone'] ?? 'N/A') ?></td>
            <td><span class="badge bg-light text-dark border"><?= h($u['designation'] ?? 'N/A') ?></span></td>
            <td><span class="text-muted small"><?= formatDate($u['created_at']) ?></span></td>
            <td>
              <?php if ($u['email_verified']): ?>
                <span class="badge bg-success rounded-pill px-3">Verified</span>
              <?php else: ?>
                <span class="badge bg-secondary rounded-pill px-3">Unverified</span>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <a href="<?= APP_URL ?>/admin/user_detail?user_id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-secondary" title="View Profile & Tickets">
                <i class="bi bi-person-lines-fill"></i> View
              </a>
              <a href="<?= APP_URL ?>/admin/reset_password?user_id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary" title="Force Password Reset">
                <i class="bi bi-key"></i> Reset Password
              </a>
              <form method="post" class="d-inline" onsubmit="return confirm('WARNING: Are you sure you want to permanently delete this user AND ALL THEIR TICKETS?');">
                <input type="hidden" name="csrf_token" value="<?= h(generateCSRFToken()) ?>">
                <input type="hidden" name="delete_user_id" value="<?= $u['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete User and Tickets"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($users)): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">No users found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
