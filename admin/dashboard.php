<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pdo = getDB();

// Fast counts for Dashboard
$stats = [
    'staff'   => (int) $pdo->query("SELECT COUNT(*) FROM it_staff")->fetchColumn(),
    'users'   => (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'tickets' => (int) $pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn(),
    'pending' => (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE status != 'solved'")->fetchColumn()
];

// Recent tickets overview
$recentTickets = $pdo->query("
    SELECT t.ticket_number, t.status, t.priority, t.created_at, u.name as user_name, s.name as assigned_name
    FROM tickets t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN it_staff s ON t.assigned_to = s.id
    ORDER BY t.created_at DESC LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Admin Dashboard — <?= APP_NAME ?></title>
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
      <span class="text-white me-3 d-none d-sm-inline"><i class="bi bi-person-circle me-1"></i>System Admin</span>
      <a href="<?= APP_URL ?>/auth/logout.php" class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
    </div>
  </div>
</nav>

<!-- Sidebar -->
<div class="admin-sidebar" id="adminSidebar">
  <div class="p-3 text-uppercase text-secondary small fw-bold mt-2">Core System</div>
  <nav class="nav flex-column">
    <a class="nav-link active" href="<?= APP_URL ?>/admin/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/staff.php"><i class="bi bi-person-badge"></i> IT Staff Management</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/users.php"><i class="bi bi-people"></i> User Management</a>
  </nav>
</div>

<!-- Main Content -->
<div class="admin-main">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 fw-bold">System Overview</h4>
  </div>

  <?php renderFlash(); ?>

  <!-- Stats Row -->
  <div class="row g-4 mb-4">
    <div class="col-sm-6 col-lg-3">
      <div class="admin-stat-card" style="border-top-color: #8b5cf6;">
        <div class="admin-stat-icon" style="background: #ede9fe; color: #7c3aed;">
          <i class="bi bi-person-badge-fill"></i>
        </div>
        <div class="admin-stat-info">
          <p>Total Staff</p>
          <h3><?= number_format($stats['staff']) ?></h3>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="admin-stat-card" style="border-top-color: #10b981;">
        <div class="admin-stat-icon" style="background: #d1fae5; color: #059669;">
          <i class="bi bi-people-fill"></i>
        </div>
        <div class="admin-stat-info">
          <p>Registered Users</p>
          <h3><?= number_format($stats['users']) ?></h3>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="admin-stat-card" style="border-top-color: #f59e0b;">
        <div class="admin-stat-icon" style="background: #fef3c7; color: #d97706;">
          <i class="bi bi-ticket-detailed-fill"></i>
        </div>
        <div class="admin-stat-info">
          <p>Total Tickets</p>
          <h3><?= number_format($stats['tickets']) ?></h3>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="admin-stat-card" style="border-top-color: #ef4444;">
        <div class="admin-stat-icon" style="background: #fee2e2; color: #dc2626;">
          <i class="bi bi-exclamation-octagon-fill"></i>
        </div>
        <div class="admin-stat-info">
          <p>Pending Issues</p>
          <h3><?= number_format($stats['pending']) ?></h3>
        </div>
      </div>
    </div>
  </div>

  <!-- Recent Tickets Preview -->
  <div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white py-3 border-0">
      <h5 class="mb-0 fw-bold text-secondary"><i class="bi bi-clock-history me-2"></i>Recent Ticket Activity</h5>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table admin-table mb-0">
          <thead>
            <tr>
              <th>Ticket #</th>
              <th>Raised By</th>
              <th>Assigned To</th>
              <th>Priority</th>
              <th>Status</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentTickets as $t): ?>
            <tr>
              <td><span class="fw-semibold font-monospace"><?= h($t['ticket_number']) ?></span></td>
              <td><?= h($t['user_name']) ?></td>
              <td><?= $t['assigned_name'] ? h($t['assigned_name']) : '<span class="text-muted">Unassigned</span>' ?></td>
              <td><span class="badge <?= priorityBadge($t['priority']) ?>"><?= ucfirst(h($t['priority'])) ?></span></td>
              <td><span class="badge <?= statusBadge($t['status']) ?>"><?= statusLabel($t['status']) ?></span></td>
              <td class="text-muted small"><?= formatDate($t['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recentTickets)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No tickets recorded yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
