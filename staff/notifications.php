<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireStaff();

$pdo     = getDB();
$staffId = currentStaffId();
$role    = currentRole();

// Mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE recipient_id=? AND recipient_type='staff'")->execute([$staffId]);
    header('Location: notifications');
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE recipient_id=? AND recipient_type='staff'");
$stmt->execute([$staffId]);
$total = (int)$stmt->fetchColumn();
$pg = paginate($total, 25);

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE recipient_id=? AND recipient_type='staff' ORDER BY created_at DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}");
$stmt->execute([$staffId]);
$notifications = $stmt->fetchAll();

$unreadCount = count(array_filter($notifications, fn($n) => !$n['is_read']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="csrf-token" content="<?= generateCSRFToken() ?>">
<meta name="app-url" content="<?= APP_URL ?>">
<title>Notifications — <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body data-user-type="staff">

<nav class="navbar navbar-apollo fixed-top" style="z-index:200;">
  <div class="container-fluid">
    <button class="btn btn-sm text-white me-2 d-lg-none" id="sidebarToggle"><i class="bi bi-list" style="font-size:1.3rem;"></i></button>
    <a class="navbar-brand" href="<?= APP_URL ?>/staff/dashboard"><img src="<?= APP_URL ?>/assets/images/apollo_logo.png" alt="Logo"><?= APP_SHORT ?></a>
    <div class="ms-auto d-flex align-items-center gap-3">
      <a class="text-white position-relative" href="<?= APP_URL ?>/staff/notifications">
        <i class="bi bi-bell-fill" style="font-size:1.1rem;"></i>
        <span class="badge rounded-pill bg-danger position-absolute <?= $unreadCount ? '' : 'd-none' ?>"
              id="notif-badge" style="top:-6px;right:-8px;font-size:.6rem;"><?= $unreadCount ?: '' ?></span>
      </a>
      <div class="dropdown">
        <a class="text-white text-decoration-none dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="bi bi-person-circle"></i></a>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="<?= APP_URL ?>/staff/profile"><i class="bi bi-person me-2"></i>Profile</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/auth/logout"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>
<div style="padding-top:56px;"></div>

<div class="sidebar" id="sidebar">
  <div class="sidebar-section">Navigation</div>
  <nav class="nav flex-column">
    <a class="nav-link" href="<?= APP_URL ?>/staff/dashboard"><i class="bi bi-speedometer2"></i>Dashboard</a>
    <a class="nav-link" href="<?= APP_URL ?>/staff/tickets"><i class="bi bi-ticket-perforated"></i>Tickets</a>
    <a class="nav-link active" href="<?= APP_URL ?>/staff/notifications"><i class="bi bi-bell-fill"></i>Notifications<?php if ($unreadCount): ?><span class="badge bg-danger ms-auto"><?= $unreadCount ?></span><?php endif; ?></a>
    <?php if ($role === ROLE_ICT_HEAD): ?><a class="nav-link" href="<?= APP_URL ?>/staff/reports"><i class="bi bi-bar-chart-line"></i>Reports</a><?php endif; ?>
    <div class="sidebar-section">Account</div>
    <a class="nav-link" href="<?= APP_URL ?>/staff/profile"><i class="bi bi-person-gear"></i>Profile</a>
    <a class="nav-link" href="<?= APP_URL ?>/auth/logout"><i class="bi bi-box-arrow-right"></i>Logout</a>
  </nav>
</div>

<div class="main-content">
  <div class="page-title-bar">
    <h4><i class="bi bi-bell me-2"></i>Notifications <span class="badge bg-secondary ms-1"><?= $total ?></span></h4>
    <?php if ($unreadCount > 0): ?>
    <form method="POST" class="d-inline">
      <?= csrfField() ?>
      <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-check-all me-1"></i>Mark All Read</button>
    </form>
    <?php endif; ?>
  </div>

  <?php renderFlash(); ?>

  <div class="card">
    <div class="card-body p-0">
      <?php if (empty($notifications)): ?>
      <div class="empty-state"><i class="bi bi-bell-slash d-block"></i><p>No notifications yet.</p></div>
      <?php else: ?>
      <?php foreach ($notifications as $n): ?>
      <div class="p-3 d-flex align-items-start gap-3 border-bottom <?= !$n['is_read'] ? 'bg-light' : '' ?>"
           style="font-size:.88rem;">
        <div style="width:36px;height:36px;border-radius:50%;background:<?= !$n['is_read'] ? '#dbeafe' : '#f1f5f9' ?>;flex-shrink:0;display:flex;align-items:center;justify-content:center;">
          <i class="bi bi-bell<?= !$n['is_read'] ? '-fill text-primary' : ' text-muted' ?>"></i>
        </div>
        <div class="flex-grow-1">
          <?php if ($n['ticket_id']): ?>
          <a href="<?= APP_URL ?>/staff/ticket_detail?id=<?= $n['ticket_id'] ?>" class="text-decoration-none text-dark"><?= h($n['message']) ?></a>
          <?php else: ?>
          <span><?= h($n['message']) ?></span>
          <?php endif; ?>
          <div class="text-muted mt-1" style="font-size:.75rem;"><?= timeAgo($n['created_at']) ?></div>
        </div>
        <?php if (!$n['is_read']): ?><span class="badge rounded-pill bg-primary" style="font-size:.6rem;">New</span><?php endif; ?>
      </div>
      <?php endforeach; ?>
      <?php if ($pg['total'] > 1): ?><div class="py-3"><?= renderPagination($pg) ?></div><?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
