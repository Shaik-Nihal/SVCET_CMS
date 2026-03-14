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

<nav class="navbar navbar-expand-lg navbar-apollo fixed-top">
  <div class="container-fluid">
    <a class="navbar-brand" href="<?= APP_URL ?>/user/dashboard"><img src="<?= APP_URL ?>/assets/images/apollo_logo.png" alt="Logo"><?= APP_SHORT ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
      <span class="navbar-toggler-icon" style="filter:invert(1)"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMenu">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/user/dashboard"><i class="bi bi-house me-1"></i>Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/user/raise_ticket"><i class="bi bi-plus-circle me-1"></i>Raise Ticket</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/user/my_tickets"><i class="bi bi-ticket-perforated me-1"></i>My Tickets</a></li>
      </ul>
      <ul class="navbar-nav ms-auto align-items-center">
        <li class="nav-item me-2">
          <a class="nav-link notif-bell position-relative" href="<?= APP_URL ?>/user/notifications">
            <i class="bi bi-bell-fill" style="font-size:1.1rem;color:#fff;"></i>
            <span class="notif-badge badge rounded-pill bg-danger <?= $unreadCount ? '' : 'd-none' ?>" id="notif-badge"><?= $unreadCount ?: '' ?></span>
          </a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="bi bi-person-circle me-1"></i><?= h($_SESSION['user_name']) ?></a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="<?= APP_URL ?>/user/profile"><i class="bi bi-person me-2"></i>My Profile</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/auth/logout"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>
<div style="padding-top:70px;"></div>

<div class="container-fluid px-4 py-3">
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
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
