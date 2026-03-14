<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pdo     = getDB();
$staffId = currentStaffId();
$role    = currentRole();

// Report parameters
$reportType = $_GET['type'] ?? 'weekly';
$dateFrom   = $_GET['from'] ?? date('Y-m-d', strtotime('-7 days'));
$dateTo     = $_GET['to'] ?? date('Y-m-d');

if ($reportType === 'monthly') {
    $month = $_GET['month'] ?? date('m');
    $year  = $_GET['year'] ?? date('Y');
    $dateFrom = "{$year}-{$month}-01";
    $dateTo   = date('Y-m-t', strtotime($dateFrom));
}

// Summary stats
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'solved') AS solved,
        SUM(status != 'solved') AS open_count,
        ROUND(AVG(CASE WHEN solved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, created_at, solved_at) END),1) AS avg_hours
    FROM tickets
    WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
");
$stmt->execute([$dateFrom, $dateTo]);
$summary = $stmt->fetch();

// Staff performance
$stmt = $pdo->prepare("
    SELECT s.name, s.designation, s.role,
           COUNT(t.id) AS assigned_count,
           SUM(t.status = 'solved') AS solved_count,
           ROUND(AVG(CASE WHEN t.solved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, t.created_at, t.solved_at) END),1) AS avg_hours
    FROM it_staff s
    LEFT JOIN tickets t ON t.assigned_to = s.id
        AND t.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
    WHERE s.is_active = 1 AND s.role != 'admin'
    GROUP BY s.id
    ORDER BY solved_count DESC
");
$stmt->execute([$dateFrom, $dateTo]);
$staffPerf = $stmt->fetchAll();

// Category breakdown
$stmt = $pdo->prepare("
    SELECT COALESCE(pc.name,'Other') AS category, COUNT(*) AS cnt
    FROM tickets t
    LEFT JOIN problem_categories pc ON t.problem_category_id = pc.id
    WHERE t.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
    GROUP BY pc.id
    ORDER BY cnt DESC
");
$stmt->execute([$dateFrom, $dateTo]);
$catBreakdown = $stmt->fetchAll();

// Ticket detail rows (for preview)
$stmt = $pdo->prepare("
    SELECT t.ticket_number, u.name AS user_name, u.department,
           COALESCE(pc.name,'Custom') AS category, t.custom_description,
           t.priority, t.status, s.name AS assigned_to,
           t.created_at, t.solved_at,
           TIMESTAMPDIFF(HOUR, t.created_at, t.solved_at) AS hours,
           f.rating
    FROM tickets t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN problem_categories pc ON t.problem_category_id = pc.id
    LEFT JOIN it_staff s ON t.assigned_to = s.id
    LEFT JOIN feedback f ON f.ticket_id = t.id
    WHERE t.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
    ORDER BY t.created_at DESC
");
$stmt->execute([$dateFrom, $dateTo]);
$ticketRows = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE recipient_id=? AND recipient_type='staff' AND is_read=0");
$stmt->execute([$staffId]);
$unreadCount = (int)$stmt->fetchColumn();

$periodLabel = ($reportType === 'monthly')
    ? date('F Y', strtotime($dateFrom))
    : formatDate($dateFrom, 'd M Y') . ' — ' . formatDate($dateTo, 'd M Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="csrf-token" content="<?= generateCSRFToken() ?>">
<meta name="app-url" content="<?= APP_URL ?>">
<title>Reports — <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/admin.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/dashboard.css">
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
    <a class="nav-link active" href="<?= APP_URL ?>/admin/reports"><i class="bi bi-bar-chart-line-fill"></i> System Reports</a>
  </nav>
</div>

<div class="admin-main">
  <div class="page-title-bar">
    <h4><i class="bi bi-bar-chart-line me-2"></i>Reports & Analytics</h4>
    <div>
      <a href="<?= APP_URL ?>/reports/generate_csv?from=<?= h($dateFrom) ?>&to=<?= h($dateTo) ?>" class="btn btn-sm btn-success me-1">
        <i class="bi bi-filetype-csv me-1"></i>Download CSV
      </a>
      <a href="<?= APP_URL ?>/reports/generate_pdf?from=<?= h($dateFrom) ?>&to=<?= h($dateTo) ?>" class="btn btn-sm btn-danger">
        <i class="bi bi-filetype-pdf me-1"></i>Download PDF
      </a>
    </div>
  </div>

  <?php renderFlash(); ?>

  <!-- Report Config -->
  <div class="card mb-3">
    <div class="card-body py-2">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-auto">
          <label class="form-label small fw-semibold mb-0">Report Type</label>
          <select name="type" class="form-select form-select-sm" onchange="toggleDateFields(this.value)">
            <option value="weekly" <?= $reportType === 'weekly' ? 'selected' : '' ?>>Weekly / Custom Range</option>
            <option value="monthly" <?= $reportType === 'monthly' ? 'selected' : '' ?>>Monthly</option>
          </select>
        </div>
        <div class="col-auto date-range" <?= $reportType === 'monthly' ? 'style="display:none;"' : '' ?>>
          <label class="form-label small fw-semibold mb-0">From</label>
          <input type="date" name="from" class="form-control form-control-sm" value="<?= h($dateFrom) ?>">
        </div>
        <div class="col-auto date-range" <?= $reportType === 'monthly' ? 'style="display:none;"' : '' ?>>
          <label class="form-label small fw-semibold mb-0">To</label>
          <input type="date" name="to" class="form-control form-control-sm" value="<?= h($dateTo) ?>">
        </div>
        <div class="col-auto month-select" <?= $reportType !== 'monthly' ? 'style="display:none;"' : '' ?>>
          <label class="form-label small fw-semibold mb-0">Month</label>
          <select name="month" class="form-select form-select-sm">
            <?php for ($m = 1; $m <= 12; $m++): ?>
            <option value="<?= str_pad($m,2,'0',STR_PAD_LEFT) ?>" <?= ($month ?? '') == str_pad($m,2,'0',STR_PAD_LEFT) ? 'selected' : '' ?>>
              <?= date('F', mktime(0,0,0,$m,1)) ?>
            </option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="col-auto month-select" <?= $reportType !== 'monthly' ? 'style="display:none;"' : '' ?>>
          <label class="form-label small fw-semibold mb-0">Year</label>
          <select name="year" class="form-select form-select-sm">
            <?php for ($y = (int)date('Y'); $y >= 2024; $y--): ?>
            <option value="<?= $y ?>" <?= ($year ?? '') == $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="col-auto">
          <button type="submit" class="btn btn-sm btn-apollo"><i class="bi bi-funnel me-1"></i>Generate</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Period Label -->
  <div class="alert alert-info py-2" style="font-size:.85rem;"><i class="bi bi-calendar3 me-2"></i>Report Period: <strong><?= h($periodLabel) ?></strong> — <?= (int)$summary['total'] ?> ticket(s)</div>

  <!-- Summary Stats -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card blue">
        <div class="stat-icon"><i class="bi bi-ticket-perforated-fill"></i></div>
        <div><div class="stat-num"><?= (int)$summary['total'] ?></div><div class="stat-label">Total Tickets</div></div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card green">
        <div class="stat-icon"><i class="bi bi-check-circle-fill"></i></div>
        <div><div class="stat-num"><?= (int)$summary['solved'] ?></div><div class="stat-label">Solved</div></div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card amber">
        <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
        <div><div class="stat-num"><?= (int)$summary['open_count'] ?></div><div class="stat-label">Open / Pending</div></div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card red">
        <div class="stat-icon"><i class="bi bi-clock-history"></i></div>
        <div><div class="stat-num"><?= $summary['avg_hours'] ?? '—' ?></div><div class="stat-label">Avg Hours to Solve</div></div>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <!-- Staff Performance -->
    <div class="col-lg-7">
      <div class="card">
        <div class="card-header"><i class="bi bi-people me-2"></i>Staff Performance</div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-apollo mb-0">
              <thead><tr><th>Staff</th><th>Role</th><th>Assigned</th><th>Solved</th><th>Avg Hrs</th></tr></thead>
              <tbody>
                <?php foreach ($staffPerf as $sp): ?>
                <tr>
                  <td><?= h($sp['name']) ?><br><small class="text-muted"><?= h($sp['designation']) ?></small></td>
                  <td><?= roleBadge($sp['role']) ?></td>
                  <td class="fw-semibold"><?= (int)$sp['assigned_count'] ?></td>
                  <td><span class="badge bg-success"><?= (int)$sp['solved_count'] ?></span></td>
                  <td><?= $sp['avg_hours'] ?? '—' ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Category Breakdown -->
    <div class="col-lg-5">
      <div class="card">
        <div class="card-header"><i class="bi bi-pie-chart me-2"></i>Category Breakdown</div>
        <div class="card-body p-0">
          <?php if (empty($catBreakdown)): ?>
          <div class="text-center text-muted py-3">No data</div>
          <?php else: ?>
          <?php $totalCat = array_sum(array_column($catBreakdown, 'cnt')); ?>
          <?php foreach ($catBreakdown as $cb):
            $pct = $totalCat > 0 ? round(($cb['cnt'] / $totalCat) * 100) : 0;
          ?>
          <div class="px-3 py-2 border-bottom d-flex align-items-center gap-2" style="font-size:.85rem;">
            <div class="flex-grow-1">
              <div class="d-flex justify-content-between mb-1">
                <span><?= h($cb['category']) ?></span>
                <span class="fw-semibold"><?= $cb['cnt'] ?> (<?= $pct ?>%)</span>
              </div>
              <div class="progress" style="height:5px;">
                <div class="progress-bar bg-primary" style="width:<?= $pct ?>%"></div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Ticket Detail Table -->
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="bi bi-table me-2"></i>Ticket Details (<?= count($ticketRows) ?>)</span>
    </div>
    <div class="card-body p-0">
      <?php if (empty($ticketRows)): ?>
      <div class="empty-state"><i class="bi bi-inbox d-block"></i><p>No tickets in this period.</p></div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-apollo table-sm mb-0" style="font-size:.8rem;">
          <thead><tr>
            <th>Ticket #</th><th>User</th><th>Dept</th><th>Category</th>
            <th>Priority</th><th>Status</th><th>Assigned To</th>
            <th>Created</th><th>Solved</th><th>Hrs</th><th>Rating</th>
          </tr></thead>
          <tbody>
            <?php foreach ($ticketRows as $r): ?>
            <tr>
              <td><span class="ticket-number"><?= h($r['ticket_number']) ?></span></td>
              <td><?= h($r['user_name']) ?></td>
              <td><?= h($r['department'] ?? '') ?></td>
              <td><?= h($r['category']) ?></td>
              <td><span class="badge <?= priorityBadge($r['priority']) ?>"><?= ucfirst(h($r['priority'])) ?></span></td>
              <td><span class="badge <?= statusBadge($r['status']) ?>"><?= statusLabel($r['status']) ?></span></td>
              <td><?= h($r['assigned_to'] ?? '—') ?></td>
              <td><?= formatDate($r['created_at'],'d M, H:i') ?></td>
              <td><?= $r['solved_at'] ? formatDate($r['solved_at'],'d M, H:i') : '—' ?></td>
              <td><?= $r['hours'] !== null ? $r['hours'] : '—' ?></td>
              <td><?= $r['rating'] ? renderStars((int)$r['rating']) : '—' ?></td>
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
<script>
function toggleDateFields(type) {
    document.querySelectorAll('.date-range').forEach(el => el.style.display = type === 'monthly' ? 'none' : '');
    document.querySelectorAll('.month-select').forEach(el => el.style.display = type === 'monthly' ? '' : 'none');
}
</script>
</body>
</html>
