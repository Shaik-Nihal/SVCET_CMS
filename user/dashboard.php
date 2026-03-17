<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireUser();

$pdo    = getDB();
$userId = currentUserId();
$name   = $_SESSION['user_name'];

// Stats
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(status != 'solved') AS open_count,
        SUM(status = 'solved')  AS solved_count,
        SUM(status = 'solved' AND NOT EXISTS (SELECT 1 FROM feedback f WHERE f.ticket_id = t.id)) AS pending_feedback
    FROM tickets t WHERE user_id = ?
");
$stmt->execute([$userId]);
$stats = $stmt->fetch();

// Recent tickets
$stmt = $pdo->prepare("
    SELECT t.id, t.ticket_number, t.status, t.priority, t.created_at,
           COALESCE(pc.name, 'Custom') AS category_name
    FROM tickets t
    LEFT JOIN problem_categories pc ON t.problem_category_id = pc.id
    WHERE t.user_id = ?
    ORDER BY t.created_at DESC LIMIT 5
");
$stmt->execute([$userId]);
$recentTickets = $stmt->fetchAll();

// Unread notifications count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE recipient_id=? AND recipient_type='user' AND is_read=0");
$stmt->execute([$userId]);
$unreadCount = (int)$stmt->fetchColumn();
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
      <a class="active" href="<?= APP_URL ?>/user/dashboard"><i class="bi bi-grid"></i>Overview</a>
      <button class="user-nav-group" type="button" data-bs-toggle="collapse" data-bs-target="#ticketsNav" aria-expanded="true">
        <span><i class="bi bi-ticket-perforated"></i>Tickets</span><i class="bi bi-chevron-down"></i>
      </button>
      <div class="collapse show" id="ticketsNav">
        <a class="sub-link" href="<?= APP_URL ?>/user/raise_ticket"><i class="bi bi-plus-circle"></i>Raise Ticket</a>
        <a class="sub-link" href="<?= APP_URL ?>/user/my_tickets"><i class="bi bi-list-check"></i>My Tickets</a>
      </div>
      <a href="<?= APP_URL ?>/user/notifications"><i class="bi bi-bell"></i>Notifications</a>
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
            <i class="bi bi-person-circle me-1"></i><?= h($name) ?>
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
  <?php renderFlash(); ?>

  <!-- Welcome Banner -->
  <div class="welcome-banner mb-4">
    <div>
      <h5>Welcome back, <?= h(explode(' ', $name)[0]) ?>! 👋</h5>
      <p>Today is <?= date('l, d F Y') ?> · <?= SUPPORT_PORTAL_NAME ?></p>
    </div>
    <a href="<?= APP_URL ?>/user/raise_ticket" class="btn btn-outline-light btn-sm">
      <i class="bi bi-plus-circle me-1"></i>Raise a Ticket
    </a>
  </div>

  <!-- Stat Cards -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card blue">
        <div class="stat-icon"><i class="bi bi-ticket-perforated-fill"></i></div>
        <div>
          <div class="stat-num"><?= (int)$stats['total'] ?></div>
          <div class="stat-label">Total Tickets</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card amber">
        <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
        <div>
          <div class="stat-num"><?= (int)$stats['open_count'] ?></div>
          <div class="stat-label">Open / In Progress</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card green">
        <div class="stat-icon"><i class="bi bi-check-circle-fill"></i></div>
        <div>
          <div class="stat-num"><?= (int)$stats['solved_count'] ?></div>
          <div class="stat-label">Solved</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card red">
        <div class="stat-icon"><i class="bi bi-star-half"></i></div>
        <div>
          <div class="stat-num"><?= (int)$stats['pending_feedback'] ?></div>
          <div class="stat-label">Pending Feedback</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <!-- Recent Tickets -->
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="bi bi-clock-history me-2"></i>Recent Tickets</span>
          <a href="<?= APP_URL ?>/user/my_tickets" class="btn btn-sm btn-svcet">View All</a>
        </div>
        <div class="card-body p-0">
          <?php if (empty($recentTickets)): ?>
          <div class="empty-state py-4">
            <i class="bi bi-inbox d-block"></i>
            <p class="mb-2">No tickets raised yet.</p>
            <a href="<?= APP_URL ?>/user/raise_ticket" class="btn btn-sm btn-svcet">Raise First Ticket</a>
          </div>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-svcet mb-0">
              <thead>
                <tr>
                  <th>Ticket #</th>
                  <th>Category</th>
                  <th>Priority</th>
                  <th>Status</th>
                  <th>Raised On</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentTickets as $t): ?>
                <tr>
                  <td><span class="ticket-number"><?= h($t['ticket_number']) ?></span></td>
                  <td><?= h($t['category_name']) ?></td>
                  <td><span class="badge <?= priorityBadge($t['priority']) ?>"><?= ucfirst(h($t['priority'])) ?></span></td>
                  <td><span class="badge <?= statusBadge($t['status']) ?>"><?= statusLabel($t['status']) ?></span></td>
                  <td class="text-muted small"><?= formatDate($t['created_at'], 'd M Y') ?></td>
                  <td><a href="<?= APP_URL ?>/user/ticket_detail?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-secondary">View</a></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="col-lg-4">
      <div class="card mb-3">
        <div class="card-header"><i class="bi bi-lightning-charge me-2"></i>Quick Actions</div>
        <div class="card-body">
          <div class="d-grid gap-2">
            <a href="<?= APP_URL ?>/user/raise_ticket" class="btn btn-svcet">
              <i class="bi bi-plus-circle me-2"></i>Raise New Ticket
            </a>
            <a href="<?= APP_URL ?>/user/my_tickets" class="btn btn-outline-secondary">
              <i class="bi bi-list-ul me-2"></i>Track My Tickets
            </a>
            <a href="<?= APP_URL ?>/user/notifications" class="btn btn-outline-secondary">
              <i class="bi bi-bell me-2"></i>View Notifications
              <?php if ($unreadCount): ?><span class="badge bg-danger ms-1"><?= $unreadCount ?></span><?php endif; ?>
            </a>
          </div>
        </div>
      </div>

      <!-- IT Contact Info Card -->
      <div class="card">
        <div class="card-header"><i class="bi bi-headset me-2"></i>Complaint Support Helpline</div>
        <div class="card-body" style="font-size:.85rem;">
          <div class="d-flex align-items-center gap-2 mb-2">
            <i class="bi bi-telephone-fill text-svcet"></i>
            <span><strong>ICT Help Desk:</strong> <?= SUPPORT_PHONE ?></span>
          </div>
          <div class="d-flex align-items-center gap-2 mb-2">
            <i class="bi bi-envelope-fill text-svcet"></i>
            <span><strong>Email:</strong> <?= SUPPORT_EMAIL ?></span>
          </div>
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-clock-fill text-svcet"></i>
            <span><strong>Hours:</strong> <?= SUPPORT_HOURS ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>
    </main>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script src="<?= APP_URL ?>/assets/js/notifications.js"></script>
</body>
</html>
