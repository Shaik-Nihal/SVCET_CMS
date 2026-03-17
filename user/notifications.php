<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireUser();

$pdo    = getDB();
$userId = currentUserId();

// Mark all as read (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE recipient_id=? AND recipient_type='user'")->execute([$userId]);
    header('Location: notifications');
    exit;
}

// Paginate
$stmt  = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE recipient_id=? AND recipient_type='user'");
$stmt->execute([$userId]);
$total = (int)$stmt->fetchColumn();
$pg    = paginate($total, 20);

$stmt = $pdo->prepare("
    SELECT * FROM notifications WHERE recipient_id=? AND recipient_type='user'
    ORDER BY created_at DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}
");
$stmt->execute([$userId]);
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
<body data-user-type="user">

<div class="user-shell">
  <aside class="user-sidebar" id="sidebar">
    <div class="user-sidebar-brand">
      <img src="<?= APP_LOGO_URL ?>" alt="<?= APP_LOGO_ALT ?>">
      <div>
        <strong><?= APP_SHORT ?></strong>
        <small>Complaint Portal</small>
      </div>
    </div>

    <nav class="user-sidebar-nav">
      <a href="<?= APP_URL ?>/user/dashboard"><i class="bi bi-grid"></i>Overview</a>
      <button class="user-nav-group" type="button" data-bs-toggle="collapse" data-bs-target="#ticketsNav" aria-expanded="true">
        <span><i class="bi bi-ticket-perforated"></i>Tickets</span><i class="bi bi-chevron-down"></i>
      </button>
      <div class="collapse show" id="ticketsNav">
        <a class="sub-link" href="<?= APP_URL ?>/user/raise_ticket"><i class="bi bi-plus-circle"></i>Raise Ticket</a>
        <a class="sub-link" href="<?= APP_URL ?>/user/my_tickets"><i class="bi bi-list-check"></i>My Tickets</a>
      </div>
      <a class="active" href="<?= APP_URL ?>/user/notifications"><i class="bi bi-bell"></i>Notifications</a>
      <a href="<?= APP_URL ?>/user/profile"><i class="bi bi-person"></i>Profile</a>
    </nav>
  </aside>

  <div class="user-main">
    <header class="user-topbar">
      <button class="btn btn-outline-secondary btn-sm" id="sidebarToggle" type="button">
        <i class="bi bi-list"></i>
      </button>
      <a class="topbar-brand" href="<?= APP_URL ?>/user/dashboard"><?= SUPPORT_PORTAL_NAME ?></a>
      <div class="ms-auto d-flex align-items-center gap-2">
        <a class="btn btn-light btn-sm position-relative" href="<?= APP_URL ?>/user/notifications">
          <i class="bi bi-bell"></i>
          <span class="notif-badge badge rounded-pill bg-danger <?= $unreadCount ? '' : 'd-none' ?>" id="notif-badge"><?= $unreadCount ?: '' ?></span>
        </a>
        <div class="dropdown">
          <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle me-1"></i><?= h($_SESSION['user_name']) ?>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="<?= APP_URL ?>/user/profile"><i class="bi bi-person me-2"></i>My Profile</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/auth/logout"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
          </ul>
        </div>
      </div>
    </header>

    <main class="user-content container-fluid px-4 py-3">
  <div class="page-title-bar">
    <h4><i class="bi bi-bell me-2"></i>Notifications <span class="badge bg-secondary ms-1"><?= $total ?></span></h4>
    <?php if ($unreadCount > 0): ?>
    <form method="POST" action="notifications" class="d-inline">
      <?= csrfField() ?>
      <button type="submit" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-check-all me-1"></i>Mark All as Read
      </button>
    </form>
    <?php endif; ?>
  </div>

  <?php renderFlash(); ?>

  <div class="card">
    <div class="card-body p-0">
      <?php if (empty($notifications)): ?>
      <div class="empty-state">
        <i class="bi bi-bell-slash"></i>
        <p>No notifications yet.</p>
      </div>
      <?php else: ?>
      <?php foreach ($notifications as $n): ?>
      <div class="p-3 d-flex align-items-start gap-3 border-bottom <?= !$n['is_read'] ? 'bg-light' : '' ?>"
           style="font-size:.88rem;">
        <div style="width:36px;height:36px;border-radius:50%;background:<?= !$n['is_read'] ? '#dbeafe' : '#f1f5f9' ?>;flex-shrink:0;display:flex;align-items:center;justify-content:center;">
          <i class="bi bi-bell<?= !$n['is_read'] ? '-fill text-primary' : ' text-muted' ?>"></i>
        </div>
        <div class="flex-grow-1">
          <?php if ($n['ticket_id']): ?>
          <a href="<?= APP_URL ?>/user/ticket_detail?id=<?= $n['ticket_id'] ?>"
             class="text-decoration-none text-dark">
            <?= h($n['message']) ?>
          </a>
          <?php else: ?>
          <span><?= h($n['message']) ?></span>
          <?php endif; ?>
          <div class="text-muted mt-1" style="font-size:.75rem;"><?= timeAgo($n['created_at']) ?></div>
        </div>
        <?php if (!$n['is_read']): ?>
        <span class="badge rounded-pill bg-primary" style="font-size:.65rem;">New</span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      <?php if ($pg['total'] > 1): ?>
      <div class="py-3"><?= renderPagination($pg) ?></div>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
    </main>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
