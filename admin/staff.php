<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pdo = getDB();

// Handle toggle active status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_staff_id'], $_POST['csrf_token'])) {
    if (validateCSRFToken($_POST['csrf_token'])) {
        $staffId = (int)$_POST['toggle_staff_id'];
        $stmt = $pdo->prepare("UPDATE it_staff SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$staffId]);
        setFlash('success', 'Staff status updated.');
    } else {
        setFlash('error', 'Invalid request.');
    }
    header('Location: ' . APP_URL . '/admin/staff');
    exit;
}

// Fetch all staff
$staff = $pdo->query("SELECT * FROM it_staff ORDER BY role, name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>IT Staff Management — <?= APP_NAME ?></title>
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
    <a class="nav-link active" href="<?= APP_URL ?>/admin/staff"><i class="bi bi-person-badge"></i> IT Staff Management</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/users"><i class="bi bi-people"></i> User Management</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/reports"><i class="bi bi-bar-chart-line-fill"></i> System Reports</a>
  </nav>
</div>

<!-- Main Content -->
<div class="admin-main">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 fw-bold">IT Staff Directory</h4>
    <a href="<?= APP_URL ?>/admin/staff_form" class="btn btn-primary shadow-sm"><i class="bi bi-plus-lg me-1"></i> Add New Staff</a>
  </div>

  <?php renderFlash(); ?>

  <div class="admin-table-container">
    <div class="table-responsive">
      <table class="table admin-table table-hover align-middle">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Designation</th>
            <th>System Role</th>
            <th>Contact</th>
            <th>Status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($staff as $s): ?>
          <tr class="<?= $s['is_active'] ? '' : 'table-secondary opacity-75' ?>">
            <td>
              <div class="fw-bold text-dark"><?= h($s['name']) ?></div>
            </td>
            <td><?= h($s['email']) ?></td>
            <td><span class="text-muted small"><?= h($s['designation']) ?></span></td>
            <td><?= roleBadge($s['role']) ?></td>
            <td><?= h($s['contact']) ?></td>
            <td>
              <?php if ($s['is_active']): ?>
                <span class="badge bg-success rounded-pill px-3">Active</span>
              <?php else: ?>
                <span class="badge bg-danger rounded-pill px-3">Inactive</span>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <a href="<?= APP_URL ?>/admin/staff_detail?staff_id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Performance & Tickets">
                <i class="bi bi-graph-up"></i>
              </a>
              <a href="<?= APP_URL ?>/admin/staff_form?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                <i class="bi bi-pencil-square"></i>
              </a>

              <form method="post" class="d-inline" onsubmit="return confirm('Toggle active status for this staff member?');">
                <input type="hidden" name="csrf_token" value="<?= h(generateCSRFToken()) ?>">
                <input type="hidden" name="toggle_staff_id" value="<?= $s['id'] ?>">
                <?php if ($s['is_active']): ?>
                  <button type="submit" class="btn btn-sm btn-outline-warning" title="Deactivate"><i class="bi bi-pause-circle"></i></button>
                <?php else: ?>
                  <button type="submit" class="btn btn-sm btn-outline-success" title="Activate"><i class="bi bi-play-circle"></i></button>
                <?php endif; ?>
              </form>

              <form method="post" action="staff_delete" class="d-inline" onsubmit="return confirm('WARNING: Are you sure you want to permanently delete this staff member? This will reassign their tickets to unassigned / system admin.');">
                <input type="hidden" name="csrf_token" value="<?= h(generateCSRFToken()) ?>">
                <input type="hidden" name="staff_id" value="<?= $s['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($staff)): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">No staff members found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
