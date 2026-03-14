<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireUser();

$pdo    = getDB();
$userId = currentUserId();

// Filters
$filterStatus = $_GET['status'] ?? '';
$filterSearch = trim($_GET['q'] ?? '');
$validStatuses = ['notified','processing','solving','solved'];

// Count for pagination
$where = "WHERE t.user_id = ?";
$params = [$userId];
if ($filterStatus && in_array($filterStatus, $validStatuses)) {
    $where .= " AND t.status = ?";
    $params[] = $filterStatus;
}
if ($filterSearch) {
    $where .= " AND (t.ticket_number LIKE ? OR pc.name LIKE ?)";
    $params[] = "%{$filterSearch}%";
    $params[] = "%{$filterSearch}%";
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM tickets t LEFT JOIN problem_categories pc ON t.problem_category_id = pc.id $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$pg = paginate($total, 15);

$stmt = $pdo->prepare("
    SELECT t.id, t.ticket_number, t.status, t.priority, t.created_at, t.solved_at,
           COALESCE(pc.name, 'Custom') AS category_name,
           pc.icon AS category_icon,
           s.name AS staff_name, s.designation,
           f.id AS has_feedback
    FROM tickets t
    LEFT JOIN problem_categories pc ON t.problem_category_id = pc.id
    LEFT JOIN it_staff s ON t.assigned_to = s.id
    LEFT JOIN feedback f ON f.ticket_id = t.id
    $where
    ORDER BY t.created_at DESC
    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}
");
$stmt->execute($params);
$tickets = $stmt->fetchAll();

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
<title>My Tickets — <?= APP_NAME ?></title>
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
        <li class="nav-item"><a class="nav-link active" href="<?= APP_URL ?>/user/my_tickets"><i class="bi bi-ticket-perforated me-1"></i>My Tickets</a></li>
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
    <h4><i class="bi bi-ticket-perforated me-2"></i>My Tickets <span class="badge bg-secondary ms-2"><?= $total ?></span></h4>
    <a href="<?= APP_URL ?>/user/raise_ticket" class="btn btn-sm btn-apollo"><i class="bi bi-plus me-1"></i>New Ticket</a>
  </div>

  <?php renderFlash(); ?>

  <!-- Filter Bar -->
  <div class="card mb-3">
    <div class="card-body py-2">
      <form method="GET" action="my_tickets" class="row g-2 align-items-center">
        <div class="col-auto">
          <label class="col-form-label small fw-semibold">Status:</label>
        </div>
        <?php foreach (array_merge([''=>'All'], array_combine($validStatuses, ['Notified','Processing','Solving','Solved'])) as $val => $label): ?>
        <div class="col-auto">
          <a href="?status=<?= $val ?>&q=<?= urlencode($filterSearch) ?>"
             class="btn btn-sm <?= $filterStatus === $val ? 'btn-apollo' : 'btn-outline-secondary' ?>">
            <?= $label ?>
          </a>
        </div>
        <?php endforeach; ?>
        <div class="col-auto ms-auto">
          <div class="input-group input-group-sm">
            <input type="search" name="q" class="form-control" placeholder="Search ticket #..."
                   value="<?= h($filterSearch) ?>">
            <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Tickets Table -->
  <div class="card">
    <div class="card-body p-0">
      <?php if (empty($tickets)): ?>
      <div class="empty-state">
        <i class="bi bi-inbox"></i>
        <p><?= $filterStatus || $filterSearch ? 'No tickets match your filter.' : 'You have not raised any tickets yet.' ?></p>
        <?php if (!$filterStatus && !$filterSearch): ?>
        <a href="<?= APP_URL ?>/user/raise_ticket" class="btn btn-apollo">Raise Your First Ticket</a>
        <?php else: ?>
        <a href="my_tickets" class="btn btn-outline-secondary btn-sm">Clear Filter</a>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-apollo mb-0">
          <thead>
            <tr>
              <th>Ticket #</th>
              <th>Problem Category</th>
              <th>Assigned To</th>
              <th>Priority</th>
              <th>Status</th>
              <th>Raised On</th>
              <th>Resolution</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($tickets as $t): ?>
            <tr>
              <td><span class="ticket-number"><?= h($t['ticket_number']) ?></span></td>
              <td>
                <?php if ($t['category_icon']): ?>
                <i class="bi <?= h($t['category_icon']) ?> me-1 text-muted"></i>
                <?php endif; ?>
                <?= h($t['category_name']) ?>
              </td>
              <td>
                <?php if ($t['staff_name']): ?>
                <div style="font-size:.85rem;"><?= h($t['staff_name']) ?></div>
                <small class="text-muted"><?= h($t['designation']) ?></small>
                <?php else: ?>
                <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td><span class="badge <?= priorityBadge($t['priority']) ?>"><?= ucfirst(h($t['priority'])) ?></span></td>
              <td>
                <span class="badge <?= statusBadge($t['status']) ?>"><i class="bi <?= statusIcon($t['status']) ?> me-1"></i><?= statusLabel($t['status']) ?></span>
                <?php if ($t['status'] === 'solved' && !$t['has_feedback']): ?>
                <br><a href="<?= APP_URL ?>/user/feedback?ticket_id=<?= $t['id'] ?>" class="badge bg-warning text-dark mt-1 text-decoration-none">
                  <i class="bi bi-star me-1"></i>Rate
                </a>
                <?php endif; ?>
              </td>
              <td class="text-muted small"><?= formatDate($t['created_at'], 'd M Y') ?><br><span style="font-size:.7rem;"><?= timeAgo($t['created_at']) ?></span></td>
              <td class="small"><?= resolutionTime($t['created_at'], $t['solved_at']) ?></td>
              <td>
                <a href="<?= APP_URL ?>/user/ticket_detail?id=<?= $t['id'] ?>"
                   class="btn btn-sm btn-outline-secondary">
                  <i class="bi bi-eye me-1"></i>View
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($pg['total'] > 1): ?>
      <div class="py-3"><?= renderPagination($pg) ?></div>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script src="<?= APP_URL ?>/assets/js/notifications.js"></script>
</body>
</html>
