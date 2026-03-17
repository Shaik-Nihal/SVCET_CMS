<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('users.manage');

$pdo    = getDB();
$userId = (int)($_GET['user_id'] ?? 0);

$stmt = $pdo->prepare("SELECT id, name, email, phone, department, roll_no, email_verified, created_at FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    setFlash('error', 'User not found.');
    header('Location: ' . APP_URL . '/admin/users');
    exit;
}

// Ticket aggregates
$statsStmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'notified')   AS notified,
        SUM(status = 'processing') AS processing,
        SUM(status = 'solving')    AS solving,
        SUM(status = 'solved')     AS solved,
        SUM(status != 'solved')    AS open
    FROM tickets
    WHERE user_id = ?
");
$statsStmt->execute([$userId]);
$stats = $statsStmt->fetch();

// Average resolution time (minutes)
$avgResStmt = $pdo->prepare("SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, solved_at)) FROM tickets WHERE user_id = ? AND solved_at IS NOT NULL");
$avgResStmt->execute([$userId]);
$avgResolution = $avgResStmt->fetchColumn();

// Average feedback rating
$avgRatingStmt = $pdo->prepare("SELECT AVG(rating) FROM feedback WHERE user_id = ?");
$avgRatingStmt->execute([$userId]);
$avgRating = $avgRatingStmt->fetchColumn();

// Recent tickets
$ticketsStmt = $pdo->prepare("
    SELECT t.id, t.ticket_number, t.status, t.priority, t.created_at, t.solved_at,
           s.name AS assigned_name,
           f.rating AS feedback_rating
    FROM tickets t
    LEFT JOIN it_staff s ON t.assigned_to = s.id
    LEFT JOIN feedback f ON f.ticket_id = t.id
    WHERE t.user_id = ?
    ORDER BY t.created_at DESC
    LIMIT 100
");
$ticketsStmt->execute([$userId]);
$tickets = $ticketsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>User Profile — <?= APP_NAME ?></title>
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
  </nav>
  <div class="p-3 text-uppercase text-secondary small fw-bold">Account</div>
  <nav class="nav flex-column">
    <a class="nav-link" href="<?= APP_URL ?>/admin/profile"><i class="bi bi-person-gear"></i> My Profile</a>
  </nav>
</div>

<!-- Main Content -->
<div class="admin-main">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div class="d-flex align-items-center">
      <a href="<?= APP_URL ?>/admin/users" class="btn btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i> Back</a>
      <div>
        <h4 class="mb-0 fw-bold">User Profile</h4>
        <div class="text-muted">Registered <?= formatDate($user['created_at']) ?></div>
      </div>
    </div>
    <div>
      <a href="<?= APP_URL ?>/admin/reset_password?user_id=<?= $user['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-key"></i> Reset Password</a>
    </div>
  </div>

  <?php renderFlash(); ?>

  <div class="row g-4 mb-4">
    <div class="col-lg-4">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body">
          <h6 class="text-uppercase text-muted fw-bold mb-3">Account</h6>
          <p class="mb-2"><span class="fw-semibold">Name:</span> <?= h($user['name']) ?></p>
          <p class="mb-2"><span class="fw-semibold">Email:</span> <?= h($user['email']) ?></p>
          <p class="mb-2"><span class="fw-semibold">Phone:</span> <?= h($user['phone'] ?: 'N/A') ?></p>
          <p class="mb-2"><span class="fw-semibold">Department:</span> <?= h($user['department'] ?: 'N/A') ?></p>
          <p class="mb-2"><span class="fw-semibold">Roll No:</span> <?= h($user['roll_no'] ?: 'N/A') ?></p>
          <p class="mb-0"><span class="fw-semibold">Verification:</span>
            <?php if ($user['email_verified']): ?>
              <span class="badge bg-success">Verified</span>
            <?php else: ?>
              <span class="badge bg-secondary">Unverified</span>
            <?php endif; ?>
          </p>
        </div>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body">
          <h6 class="text-uppercase text-muted fw-bold mb-3">Ticket Overview</h6>
          <div class="row g-3">
            <div class="col-sm-6 col-md-4">
              <div class="admin-stat-card" style="border-top-color:#2563a8;">
                <div class="admin-stat-icon" style="background:#dbeafe;color:#2563a8;">
                  <i class="bi bi-collection"></i>
                </div>
                <div class="admin-stat-info">
                  <p>Total Tickets</p>
                  <h3><?= (int)$stats['total'] ?></h3>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-md-4">
              <div class="admin-stat-card" style="border-top-color:#f59e0b;">
                <div class="admin-stat-icon" style="background:#fef3c7;color:#d97706;">
                  <i class="bi bi-hourglass-split"></i>
                </div>
                <div class="admin-stat-info">
                  <p>Open Tickets</p>
                  <h3><?= (int)$stats['open'] ?></h3>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-md-4">
              <div class="admin-stat-card" style="border-top-color:#10b981;">
                <div class="admin-stat-icon" style="background:#d1fae5;color:#059669;">
                  <i class="bi bi-check2-circle"></i>
                </div>
                <div class="admin-stat-info">
                  <p>Solved Tickets</p>
                  <h3><?= (int)$stats['solved'] ?></h3>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-md-4">
              <div class="admin-stat-card" style="border-top-color:#8b5cf6;">
                <div class="admin-stat-icon" style="background:#ede9fe;color:#7c3aed;">
                  <i class="bi bi-stopwatch"></i>
                </div>
                <div class="admin-stat-info">
                  <p>Avg Resolution</p>
                  <h3><?= formatMinutes($avgResolution !== false ? (float)$avgResolution : null) ?></h3>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-md-4">
              <div class="admin-stat-card" style="border-top-color:#0ea5e9;">
                <div class="admin-stat-icon" style="background:#e0f2fe;color:#0284c7;">
                  <i class="bi bi-star-half"></i>
                </div>
                <div class="admin-stat-info">
                  <p>Avg Rating</p>
                  <h3><?= $avgRating ? number_format($avgRating, 1) : '—' ?></h3>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-md-4">
              <div class="admin-stat-card" style="border-top-color:#ef4444;">
                <div class="admin-stat-icon" style="background:#fee2e2;color:#dc2626;">
                  <i class="bi bi-graph-up"></i>
                </div>
                <div class="admin-stat-info">
                  <p>Status Split</p>
                  <h3><?= (int)$stats['processing'] ?> proc / <?= (int)$stats['solving'] ?> solving</h3>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between">
      <h5 class="mb-0 fw-bold text-secondary"><i class="bi bi-list-ul me-2"></i>Recent Tickets</h5>
      <span class="text-muted small">Showing latest 100</span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table admin-table mb-0 align-middle">
          <thead>
            <tr>
              <th>Ticket #</th>
              <th>Status</th>
              <th>Priority</th>
              <th>Assigned To</th>
              <th>Created</th>
              <th>Solved</th>
              <th>Feedback</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($tickets as $t): ?>
            <tr>
              <td class="fw-semibold font-monospace"><?= h($t['ticket_number']) ?></td>
              <td><span class="badge <?= statusBadge($t['status']) ?>"><?= statusLabel($t['status']) ?></span></td>
              <td><span class="badge <?= priorityBadge($t['priority']) ?>"><?= ucfirst(h($t['priority'])) ?></span></td>
              <td><?= $t['assigned_name'] ? h($t['assigned_name']) : '<span class="text-muted">Unassigned</span>' ?></td>
              <td class="text-muted small"><?= timeAgo($t['created_at']) ?></td>
              <td class="text-muted small"><?= $t['solved_at'] ? timeAgo($t['solved_at']) : '—' ?></td>
              <td>
                <?php if ($t['feedback_rating']): ?>
                  <?= renderStars((int)$t['feedback_rating']) ?>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($tickets)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No tickets found for this user.</td></tr>
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
