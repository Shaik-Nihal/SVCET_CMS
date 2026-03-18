<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireStaff();

$pdo     = getDB();
$staffId = currentStaffId();
$role    = currentRole();

// Load staff info
$stmt = $pdo->prepare("SELECT * FROM it_staff WHERE id = ?");
$stmt->execute([$staffId]);
$staffInfo = $stmt->fetch();

// Unread notifications
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE recipient_id=? AND recipient_type='staff' AND is_read=0");
$stmt->execute([$staffId]);
$unreadCount = (int)$stmt->fetchColumn();

// Role-based stats
if (currentStaffHasPermission('tickets.view_all')) {
    $stmt = $pdo->query("SELECT
        COUNT(*) AS total,
        SUM(status != 'solved') AS open_count,
        SUM(status = 'solved') AS solved_count,
        SUM(DATE(created_at) = CURDATE()) AS today_count,
        ROUND(AVG(TIMESTAMPDIFF(HOUR, created_at, solved_at)),1) AS avg_hours
        FROM tickets");
    $stats = $stmt->fetch();

    // By status counts
    $stmt = $pdo->query("SELECT status, COUNT(*) AS cnt FROM tickets GROUP BY status");
    $statusCounts = [];
    foreach ($stmt->fetchAll() as $r) $statusCounts[$r['status']] = $r['cnt'];

    // Recent tickets (all)
    $stmt = $pdo->query("
        SELECT t.id, t.ticket_number, t.status, t.priority, t.created_at,
               u.name AS user_name, u.department,
               COALESCE(pc.name,'Custom') AS category_name,
               s.name AS assigned_name
        FROM tickets t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN problem_categories pc ON t.problem_category_id = pc.id
        LEFT JOIN it_staff s ON t.assigned_to = s.id
        ORDER BY t.created_at DESC LIMIT 8
    ");
    $recentTickets = $stmt->fetchAll();
} elseif (currentStaffHasPermission('ticket.assign.exec')) {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total, SUM(status != 'solved') AS open_count, SUM(status = 'solved') AS solved_count, ROUND(AVG(CASE WHEN solved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, created_at, solved_at) END),1) AS avg_hours FROM tickets WHERE assigned_to = ?");
    $stmt->execute([$staffId]);
    $stats = $stmt->fetch();

    $stmt = $pdo->prepare("
        SELECT t.id, t.ticket_number, t.status, t.priority, t.created_at,
               u.name AS user_name,
               COALESCE(pc.name,'Custom') AS category_name,
               s.name AS assigned_name
        FROM tickets t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN problem_categories pc ON t.problem_category_id = pc.id
        LEFT JOIN it_staff s ON t.assigned_to = s.id
        WHERE t.assigned_to = ?
        ORDER BY t.created_at DESC LIMIT 8
    ");
    $stmt->execute([$staffId]);
    $recentTickets = $stmt->fetchAll();
    $statusCounts  = [];
} else {
  // Sr IT Executive / Assistant IT
    $stmt = $pdo->prepare("SELECT
        COUNT(*) AS total,
        SUM(status != 'solved') AS open_count,
        SUM(status = 'solved') AS solved_count,
        SUM(solved_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS solved_this_week
        FROM tickets WHERE assigned_to = ?");
    $stmt->execute([$staffId]);
    $stats = $stmt->fetch();

    $stmt = $pdo->prepare("
        SELECT t.id, t.ticket_number, t.status, t.priority, t.created_at,
               u.name AS user_name,
               COALESCE(pc.name,'Custom') AS category_name
        FROM tickets t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN problem_categories pc ON t.problem_category_id = pc.id
        WHERE t.assigned_to = ?
        ORDER BY t.created_at DESC LIMIT 8
    ");
    $stmt->execute([$staffId]);
    $recentTickets = $stmt->fetchAll();
    $statusCounts  = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="csrf-token" content="<?= generateCSRFToken() ?>">
<meta name="app-url" content="<?= APP_URL ?>">
<title>Dashboard — <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/dashboard.css">
</head>
<body data-user-type="staff">

<!-- Top Navbar -->
<nav class="navbar navbar-svcet fixed-top staff-navbar" style="z-index:200;">
  <div class="container-fluid">
    <button class="btn btn-sm text-white me-2 d-lg-none" id="sidebarToggle"><i class="bi bi-list" style="font-size:1.3rem;"></i></button>
    <a class="navbar-brand" href="<?= APP_URL ?>/staff/dashboard">
      <img src="<?= APP_LOGO_URL ?>" alt="<?= APP_LOGO_ALT ?>"><?= APP_SHORT ?>
    </a>
    <div class="ms-auto d-flex align-items-center gap-3">
      <a class="staff-nav-icon" href="<?= APP_URL ?>/staff/notifications" aria-label="Notifications">
        <i class="bi bi-bell-fill" style="font-size:1.1rem;"></i>
        <span class="badge rounded-pill bg-danger position-absolute <?= $unreadCount ? '' : 'd-none' ?>"
              id="notif-badge" style="top:-6px;right:-8px;font-size:.6rem;"><?= $unreadCount ?: '' ?></span>
      </a>
      <div class="dropdown">
        <a class="staff-user-toggle dropdown-toggle" href="#" data-bs-toggle="dropdown">
          <i class="bi bi-person-circle me-1"></i>
          <span class="d-none d-md-inline"><?= h($staffInfo['name']) ?></span>
        </a>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><h6 class="dropdown-header"><?= roleBadge($role) ?></h6></li>
          <li><a class="dropdown-item" href="<?= APP_URL ?>/staff/profile"><i class="bi bi-person me-2"></i>Profile</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/auth/logout"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>
<div class="staff-top-spacer"></div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
  <div class="p-3 border-bottom border-secondary" style="border-color:rgba(255,255,255,.1)!important;">
    <?= roleBadge($role) ?>
    <div class="text-white mt-1" style="font-size:.82rem;"><?= h($staffInfo['name']) ?></div>
    <div style="font-size:.72rem;color:rgba(255,255,255,.5);"><?= h($staffInfo['designation']) ?></div>
  </div>
  <div class="sidebar-section">Navigation</div>
  <nav class="nav flex-column">
    <a class="nav-link active" href="<?= APP_URL ?>/staff/dashboard"><i class="bi bi-speedometer2"></i>Control Center</a>
    <a class="nav-link" href="<?= APP_URL ?>/staff/tickets"><i class="bi bi-ticket-perforated"></i>Work Queue
      <?php if (!empty($stats['open_count']) && $stats['open_count'] > 0): ?>
      <span class="badge bg-warning text-dark ms-auto"><?= (int)$stats['open_count'] ?></span>
      <?php endif; ?>
    </a>
    <a class="nav-link" href="<?= APP_URL ?>/staff/notifications"><i class="bi bi-bell"></i>Alerts
      <?php if ($unreadCount): ?><span class="badge bg-danger ms-auto"><?= $unreadCount ?></span><?php endif; ?>
    </a>
    <?php if (currentStaffHasPermission('reports.view')): ?>
    <div class="sidebar-section">Management</div>
    <a class="nav-link" href="<?= APP_URL ?>/staff/reports"><i class="bi bi-bar-chart-line"></i>Insights Lab</a>
    <?php endif; ?>
    <?php if (currentStaffHasPermission('staff.manage') || currentStaffHasPermission('users.manage') || currentStaffHasPermission('roles.manage')): ?>
    <div class="sidebar-section">Admin Studio</div>
    <?php if (currentStaffHasPermission('staff.manage')): ?><a class="nav-link" href="<?= APP_URL ?>/staff/manage_staff"><i class="bi bi-person-badge"></i>Team Hub</a><?php endif; ?>
    <?php if (currentStaffHasPermission('users.manage')): ?><a class="nav-link" href="<?= APP_URL ?>/staff/manage_users"><i class="bi bi-people"></i>User Directory</a><?php endif; ?>
    <?php if (currentStaffHasPermission('roles.manage')): ?><a class="nav-link" href="<?= APP_URL ?>/admin/roles"><i class="bi bi-diagram-3"></i>Roles & Permissions</a><?php endif; ?>
    <?php endif; ?>
    <div class="sidebar-section">Account</div>
    <a class="nav-link" href="<?= APP_URL ?>/staff/profile"><i class="bi bi-person-gear"></i>My Profile</a>
    <a class="nav-link text-danger-emphasis" href="<?= APP_URL ?>/auth/logout"><i class="bi bi-box-arrow-right"></i>Logout</a>
  </nav>
</div>

<!-- Main Content -->
<div class="main-content">
  <?php renderFlash(); ?>

  <div class="staff-headline">
    <div>
      <h3><i class="bi bi-activity me-2"></i>Operations Pulse</h3>
      <p>Track complaint flow, response workload, and closure performance from one workspace.</p>
    </div>
    <span class="pill"><?= strtoupper(date('D')) ?> · <?= date('d M Y') ?></span>
  </div>

  <!-- Welcome Banner -->
  <div class="welcome-banner mb-4">
    <div>
      <h5>Hello, <?= h(explode(' ', $staffInfo['name'])[0]) ?>.</h5>
      <p><?= h($staffInfo['designation']) ?> · <?= date('l, d F Y') ?></p>
    </div>
    <span class="badge bg-light text-dark p-2" style="font-size:.85rem;"><?= roleLabel($role) ?></span>
  </div>

  <!-- Stat Cards -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card blue">
        <div class="stat-icon"><i class="bi bi-ticket-perforated-fill"></i></div>
        <div><div class="stat-num"><?= (int)($stats['total'] ?? 0) ?></div><div class="stat-label">Total Cases</div></div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card amber">
        <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
        <div><div class="stat-num"><?= (int)($stats['open_count'] ?? 0) ?></div><div class="stat-label">Awaiting Action</div></div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card green">
        <div class="stat-icon"><i class="bi bi-check-circle-fill"></i></div>
        <div><div class="stat-num"><?= (int)($stats['solved_count'] ?? 0) ?></div><div class="stat-label">Closed</div></div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <?php if (currentStaffHasPermission('tickets.view_all')): ?>
      <div class="stat-card red">
        <div class="stat-icon"><i class="bi bi-calendar-day"></i></div>
        <div><div class="stat-num"><?= (int)($stats['today_count'] ?? 0) ?></div><div class="stat-label">New Today</div></div>
      </div>
      <?php elseif (!currentStaffHasPermission('ticket.assign.exec') && currentStaffHasPermission('ticket.update_status')): ?>
      <div class="stat-card green">
        <div class="stat-icon"><i class="bi bi-trophy-fill"></i></div>
        <div><div class="stat-num"><?= (int)($stats['solved_this_week'] ?? 0) ?></div><div class="stat-label">Closed This Week</div></div>
      </div>
      <?php else: ?>
      <div class="stat-card blue">
        <div class="stat-icon"><i class="bi bi-clock-history"></i></div>
        <div><div class="stat-num"><?= $stats['avg_hours'] ?? '—' ?></div><div class="stat-label">Avg Resolution (hrs)</div></div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Recent Tickets -->
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="bi bi-clock-history me-2"></i>Live Complaint Queue</span>
      <a href="<?= APP_URL ?>/staff/tickets" class="btn btn-sm btn-svcet">Open Queue</a>
    </div>
    <div class="card-body p-0">
      <?php if (empty($recentTickets)): ?>
      <div class="empty-state py-4"><i class="bi bi-inbox d-block"></i><p>No tickets <?= !currentStaffHasPermission('tickets.view_all') ? 'assigned to you' : 'raised' ?> yet.</p></div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-svcet mb-0">
          <thead><tr>
            <th>Ticket #</th><th>Raised By</th><th>Category</th>
            <th>Priority</th><th>Status</th><th>Date</th><th></th>
          </tr></thead>
          <tbody>
            <?php foreach ($recentTickets as $t): ?>
            <tr>
              <td><span class="ticket-number"><?= h($t['ticket_number']) ?></span></td>
              <td><?= h($t['user_name'] ?? '—') ?><br><small class="text-muted"><?= h($t['department'] ?? '') ?></small></td>
              <td><?= h($t['category_name']) ?></td>
              <td><span class="badge <?= priorityBadge($t['priority']) ?>"><?= ucfirst(h($t['priority'])) ?></span></td>
              <td><span class="badge <?= statusBadge($t['status']) ?>"><?= statusLabel($t['status']) ?></span></td>
              <td class="text-muted small"><?= formatDate($t['created_at'], 'd M Y') ?></td>
              <td><a href="<?= APP_URL ?>/staff/ticket_detail?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-secondary">View</a></td>
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
<script src="<?= APP_URL ?>/assets/js/notifications.js"></script>
</body>
</html>
