<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireStaff();

$pdo     = getDB();
$staffId = currentStaffId();
$role    = currentRole();

// Filters
$filterStatus   = $_GET['status'] ?? '';
$filterPriority = $_GET['priority'] ?? '';
$filterSearch   = trim($_GET['q'] ?? '');
$validStatuses  = ['notified','processing','solving','solved'];
$validPriorities = ['low','medium','high'];

// Build WHERE clause based on role
$where   = "WHERE 1=1";
$params  = [];

if (currentStaffHasPermission('tickets.view_all')) {
  // Leadership roles with global visibility.
} elseif (currentStaffHasPermission('ticket.assign.exec')) {
  // Sr IT can see tickets they are involved in:
  // - currently assigned to self
  // - previously assigned to self
  // - assigned/reassigned by self
  $where  .= " AND (t.assigned_to = ? OR EXISTS (SELECT 1 FROM ticket_assignments ta WHERE ta.ticket_id = t.id AND ta.assigned_to = ?) OR EXISTS (SELECT 1 FROM ticket_assignments ta2 WHERE ta2.ticket_id = t.id AND ta2.assigned_by = ?))";
  $params[] = $staffId;
  $params[] = $staffId;
  $params[] = $staffId;
} elseif (currentStaffHasPermission('tickets.view_involved')) {
  // Assistant IT can see currently assigned tickets + tickets previously assigned to them (read-only).
  $where  .= " AND (t.assigned_to = ? OR EXISTS (SELECT 1 FROM ticket_assignments ta WHERE ta.ticket_id = t.id AND ta.assigned_to = ?))";
  $params[] = $staffId;
  $params[] = $staffId;
}

if ($filterStatus && in_array($filterStatus, $validStatuses)) {
    $where  .= " AND t.status = ?";
    $params[] = $filterStatus;
}
if ($filterPriority && in_array($filterPriority, $validPriorities)) {
    $where  .= " AND t.priority = ?";
    $params[] = $filterPriority;
}
if ($filterSearch) {
    $where  .= " AND (t.ticket_number LIKE ? OR u.name LIKE ?)";
    $params[] = "%{$filterSearch}%";
    $params[] = "%{$filterSearch}%";
}

// Count
$countStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT t.id)
    FROM tickets t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN problem_categories pc ON t.problem_category_id = pc.id
    $where
");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pg    = paginate($total, 20);

// Fetch
$stmt = $pdo->prepare("
    SELECT t.id, t.ticket_number, t.status, t.priority, t.created_at, t.solved_at,
           u.name AS user_name, u.department,
           COALESCE(pc.name,'Custom') AS category_name, pc.icon AS category_icon,
           s.name AS assigned_name, s.role AS assigned_role, s.designation
    FROM tickets t
    LEFT JOIN users u  ON t.user_id = u.id
    LEFT JOIN problem_categories pc ON t.problem_category_id = pc.id
    LEFT JOIN it_staff s ON t.assigned_to = s.id
    $where
    ORDER BY t.created_at DESC
    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}
");
$stmt->execute($params);
$tickets = $stmt->fetchAll();

// Unread
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE recipient_id=? AND recipient_type='staff' AND is_read=0");
$stmt->execute([$staffId]);
$unreadCount = (int)$stmt->fetchColumn();

$showAssignedColumn = currentStaffHasPermission('tickets.view_all')
  || currentStaffHasPermission('ticket.assign.lead')
  || currentStaffHasPermission('ticket.assign.exec');

if (currentStaffHasPermission('tickets.view_all')) {
  $pageTitle = 'Complaint Backlog';
} elseif (currentStaffHasPermission('ticket.assign.exec')) {
  $pageTitle = 'My + Delegated Queue';
} else {
  $pageTitle = 'Assigned Work Queue';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="csrf-token" content="<?= generateCSRFToken() ?>">
<meta name="app-url" content="<?= APP_URL ?>">
<title><?= $pageTitle ?> — <?= APP_NAME ?></title>
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
        <span class="badge rounded-pill bg-danger position-absolute <?= $unreadCount ? '' : 'd-none' ?>"
              id="notif-badge" style="top:-6px;right:-8px;font-size:.6rem;"><?= $unreadCount ?: '' ?></span>
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
    <a class="nav-link active" href="<?= APP_URL ?>/staff/tickets"><i class="bi bi-ticket-perforated"></i>Work Queue</a>
    <a class="nav-link" href="<?= APP_URL ?>/staff/notifications"><i class="bi bi-bell"></i>Alerts<?php if ($unreadCount): ?><span class="badge bg-danger ms-auto"><?= $unreadCount ?></span><?php endif; ?></a>
    <?php if (currentStaffHasPermission('reports.view')): ?><a class="nav-link" href="<?= APP_URL ?>/staff/reports"><i class="bi bi-bar-chart-line"></i>Insights Lab</a><?php endif; ?>
    <?php if (currentStaffHasPermission('staff.manage') || currentStaffHasPermission('users.manage') || currentStaffHasPermission('roles.manage')): ?>
    <div class="sidebar-section">Admin Studio</div>
    <?php if (currentStaffHasPermission('staff.manage')): ?><a class="nav-link" href="<?= APP_URL ?>/staff/manage_staff"><i class="bi bi-person-badge"></i>Team Hub</a><?php endif; ?>
    <?php if (currentStaffHasPermission('users.manage')): ?><a class="nav-link" href="<?= APP_URL ?>/staff/manage_users"><i class="bi bi-people"></i>User Directory</a><?php endif; ?>
    <?php if (currentStaffHasPermission('roles.manage')): ?><a class="nav-link" href="<?= APP_URL ?>/admin/roles"><i class="bi bi-diagram-3"></i>Roles & Permissions</a><?php endif; ?>
    <?php endif; ?>
    <div class="sidebar-section">Account</div>
    <a class="nav-link" href="<?= APP_URL ?>/staff/profile"><i class="bi bi-person-gear"></i>Profile</a>
    <a class="nav-link" href="<?= APP_URL ?>/auth/logout"><i class="bi bi-box-arrow-right"></i>Logout</a>
  </nav>
</div>

<div class="main-content">
  <div class="staff-headline">
    <div>
      <h3><i class="bi bi-kanban me-2"></i>Queue Monitor</h3>
      <p>Filter, triage, and inspect complaints from a single operating panel.</p>
    </div>
    <span class="pill"><?= (int)$total ?> item(s)</span>
  </div>

  <div class="page-title-bar">
    <h4><i class="bi bi-ticket-perforated me-2"></i><?= $pageTitle ?> <span class="badge bg-secondary ms-1"><?= $total ?></span></h4>
  </div>

  <?php renderFlash(); ?>

  <!-- Filter Bar -->
  <div class="card mb-3">
    <div class="card-body py-2">
      <form method="GET" action="tickets" class="row g-2 align-items-center">
        <div class="col-auto">
          <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">Any Status</option>
            <?php foreach ($validStatuses as $s): ?>
            <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= statusLabel($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-auto">
          <select name="priority" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">Any Priority</option>
            <?php foreach ($validPriorities as $p): ?>
            <option value="<?= $p ?>" <?= $filterPriority === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-auto ms-auto">
          <div class="input-group input-group-sm">
            <input type="search" name="q" class="form-control" placeholder="Search ticket # or complainant..."
                   value="<?= h($filterSearch) ?>">
            <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Table -->
  <div class="card">
    <div class="card-body p-0">
      <?php if (empty($tickets)): ?>
      <div class="empty-state"><i class="bi bi-inbox d-block"></i><p>No tickets found.</p>
        <?php if ($filterStatus || $filterSearch || $filterPriority): ?>
        <a href="tickets" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-svcet mb-0">
          <thead><tr>
            <th>Ticket #</th><th>Raised By</th><th>Category</th>
            <?php if ($showAssignedColumn): ?><th>Assigned To</th><?php endif; ?>
            <th>Priority</th><th>Status</th><th>Date</th><th>Resolve Time</th><th></th>
          </tr></thead>
          <tbody>
            <?php foreach ($tickets as $t): ?>
            <tr>
              <td><span class="ticket-number"><?= h($t['ticket_number']) ?></span></td>
              <td><?= h($t['user_name']) ?><br><small class="text-muted"><?= h($t['department'] ?? '') ?></small></td>
              <td>
                <?php if ($t['category_icon']): ?><i class="bi <?= h($t['category_icon']) ?> me-1 text-muted"></i><?php endif; ?>
                <?= h($t['category_name']) ?>
              </td>
              <?php if ($showAssignedColumn): ?>
              <td><?= $t['assigned_name'] ? h($t['assigned_name']) . '<br><small class="text-muted">' . h($t['designation']) . '</small>' : '<span class="text-muted">Unassigned</span>' ?></td>
              <?php endif; ?>
              <td><span class="badge <?= priorityBadge($t['priority']) ?>"><?= ucfirst(h($t['priority'])) ?></span></td>
              <td><span class="badge <?= statusBadge($t['status']) ?>"><?= statusLabel($t['status']) ?></span></td>
              <td class="text-muted small"><?= formatDate($t['created_at'],'d M Y') ?><br><span style="font-size:.7rem;"><?= timeAgo($t['created_at']) ?></span></td>
              <td class="small"><?= resolutionTime($t['created_at'], $t['solved_at']) ?></td>
              <td><a href="<?= APP_URL ?>/staff/ticket_detail?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-secondary">View</a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($pg['total'] > 1): ?><div class="py-3"><?= renderPagination($pg) ?></div><?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script src="<?= APP_URL ?>/assets/js/notifications.js"></script>
</body>
</html>
