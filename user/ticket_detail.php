<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ticket_helpers.php';

requireUser();

$pdo      = getDB();
$userId   = currentUserId();
$ticketId = (int)($_GET['id'] ?? 0);

// Security: ticket must belong to current user
$ticket = null;
if ($ticketId) {
    $stmt = $pdo->prepare("
        SELECT t.*,
               COALESCE(pc.name,'Custom') AS category_name, pc.icon AS category_icon,
               s.name AS staff_name, s.role AS staff_role, s.designation, s.contact AS staff_contact,
               f.rating AS fb_rating, f.comment AS fb_comment, f.created_at AS fb_date
        FROM tickets t
        LEFT JOIN problem_categories pc ON t.problem_category_id = pc.id
        LEFT JOIN it_staff s ON t.assigned_to = s.id
        LEFT JOIN feedback f ON f.ticket_id = t.id
        WHERE t.id = ? AND t.user_id = ?
    ");
    $stmt->execute([$ticketId, $userId]);
    $ticket = $stmt->fetch();
}
if (!$ticket) {
    setFlash('error', 'Ticket not found.');
    header('Location: ' . APP_URL . '/user/my_tickets');
    exit;
}

// Status history
$histStmt = $pdo->prepare("
    SELECT h.*,
           changer.name AS changed_by_name,
           (
               SELECT assignee.name
               FROM ticket_assignments ta
               LEFT JOIN it_staff assignee ON ta.assigned_to = assignee.id
               WHERE ta.ticket_id = h.ticket_id
                 AND ta.assigned_at <= h.created_at
               ORDER BY ta.assigned_at DESC, ta.id DESC
               LIMIT 1
           ) AS assigned_to_name
    FROM ticket_status_history h
    LEFT JOIN it_staff changer
           ON h.changed_by_type = 'staff' AND h.changed_by = changer.id
    WHERE h.ticket_id = ?
    ORDER BY h.created_at ASC, h.id ASC
");
$histStmt->execute([$ticketId]);
$history = $histStmt->fetchAll();

// Assignment history
$assnStmt = $pdo->prepare("
    SELECT ta.*, assigner.name AS assigner_name, assignee.name AS assignee_name, assignee.designation
    FROM ticket_assignments ta
    LEFT JOIN it_staff assigner ON ta.assigned_by = assigner.id
    LEFT JOIN it_staff assignee ON ta.assigned_to = assignee.id
    WHERE ta.ticket_id = ?
    ORDER BY ta.assigned_at ASC
");
$assnStmt->execute([$ticketId]);
$assignments = $assnStmt->fetchAll();

// Unread count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE recipient_id=? AND recipient_type='user' AND is_read=0");
$stmt->execute([$userId]);
$unreadCount = (int)$stmt->fetchColumn();

$statusOrder = ['notified'=>0,'processing'=>1,'solving'=>2,'solved'=>3];
$currentStep = $statusOrder[$ticket['status']] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="csrf-token" content="<?= generateCSRFToken() ?>">
<meta name="app-url" content="<?= APP_URL ?>">
<title><?= h($ticket['ticket_number']) ?> — <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body data-user-type="user">

<nav class="navbar navbar-expand-lg navbar-apollo fixed-top">
  <div class="container-fluid">
    <a class="navbar-brand" href="<?= APP_URL ?>/user/dashboard"><img src="<?= APP_LOGO_URL ?>" alt="<?= APP_LOGO_ALT ?>"><?= APP_SHORT ?></a>
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
    <h4>
      <a href="<?= APP_URL ?>/user/my_tickets" class="text-decoration-none text-muted me-2"><i class="bi bi-arrow-left"></i></a>
      Ticket: <span class="ticket-number"><?= h($ticket['ticket_number']) ?></span>
    </h4>
    <span class="badge <?= statusBadge($ticket['status']) ?> fs-6"><?= statusLabel($ticket['status']) ?></span>
  </div>

  <?php renderFlash(); ?>

  <!-- Feedback Prompt -->
  <?php if ($ticket['status'] === 'solved' && !$ticket['fb_rating']): ?>
  <div class="alert alert-warning d-flex align-items-center gap-3">
    <i class="bi bi-star-fill fs-4"></i>
    <div>
      <strong>Your issue has been resolved!</strong> Please share your feedback to help us improve.
      <a href="<?= APP_URL ?>/user/feedback?ticket_id=<?= $ticketId ?>" class="btn btn-sm btn-warning ms-3">
        <i class="bi bi-star me-1"></i>Submit Feedback
      </a>
    </div>
  </div>
  <?php endif; ?>

  <div class="row g-3">
    <!-- Main Column -->
    <div class="col-lg-8">
      <!-- Status Progress -->
      <div class="card mb-3">
        <div class="card-body">
          <div class="status-progress">
            <?php foreach (['notified'=>'Notified','processing'=>'Processing','solving'=>'Solving','solved'=>'Solved'] as $s => $lbl):
              $step = $statusOrder[$s];
              $cls = $step < $currentStep ? 'done' : ($step === $currentStep ? 'active' : '');
            ?>
            <div class="status-step <?= $cls ?>">
              <?= $lbl ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Problem Details -->
      <div class="card mb-3">
        <div class="card-header"><i class="bi bi-info-circle me-2"></i>Problem Details</div>
        <div class="card-body">
          <div class="d-flex align-items-center gap-3 mb-3">
            <?php if ($ticket['category_icon']): ?>
            <div style="width:48px;height:48px;background:#e8f0fa;border-radius:10px;display:flex;align-items:center;justify-content:center;">
              <i class="bi <?= h($ticket['category_icon']) ?>" style="font-size:1.4rem;color:#1a3a5c;"></i>
            </div>
            <?php endif; ?>
            <div>
              <div class="fw-semibold"><?= h($ticket['category_name']) ?></div>
              <span class="badge <?= priorityBadge($ticket['priority']) ?>"><?= ucfirst(h($ticket['priority'])) ?> Priority</span>
            </div>
          </div>
          <?php if ($ticket['custom_description']): ?>
          <div class="bg-light rounded p-3" style="font-size:.9rem;">
            <?= nl2br(h($ticket['custom_description'])) ?>
          </div>
          <?php endif; ?>
          <div class="row mt-3 g-2" style="font-size:.82rem;color:#64748b;">
            <div class="col-sm-6"><i class="bi bi-calendar3 me-1"></i>Raised: <?= formatDate($ticket['created_at']) ?></div>
            <?php if ($ticket['solved_at']): ?>
            <div class="col-sm-6"><i class="bi bi-check-circle me-1"></i>Solved: <?= formatDate($ticket['solved_at']) ?></div>
            <?php endif; ?>
            <div class="col-sm-6"><i class="bi bi-stopwatch me-1"></i>Resolution Time: <?= resolutionTime($ticket['created_at'], $ticket['solved_at']) ?></div>
          </div>
        </div>
      </div>

      <!-- Status Timeline -->
      <div class="card mb-3">
        <div class="card-header"><i class="bi bi-clock-history me-2"></i>Activity Timeline</div>
        <div class="card-body">
          <div class="timeline">
            <?php foreach ($history as $h_item): ?>
            <div class="timeline-item">
              <div class="timeline-dot <?= $h_item['new_status'] ?>"></div>
              <div class="timeline-date"><?= formatDate($h_item['created_at']) ?></div>
              <div class="timeline-text">
                <strong><?= statusLabel($h_item['new_status']) ?></strong>
                <?php if ($h_item['old_status']): ?>
                <span class="text-muted"> (was <?= statusLabel($h_item['old_status']) ?>)</span>
                <?php endif; ?>
                <?php
                  $timelineNote = trim((string)($h_item['notes'] ?? ''));
                  $looksGenericAssign = stripos($timelineNote, 'assigned to staff') === 0;
                  $looksGenericUpdate = strcasecmp($timelineNote, 'Status updated by technician') === 0;
                  if ($timelineNote === '' || $looksGenericAssign || $looksGenericUpdate) {
                    if (($h_item['new_status'] ?? '') === STATUS_PROCESSING && !empty($h_item['assigned_to_name'])) {
                      $timelineNote = 'Assigned to ' . $h_item['assigned_to_name'];
                    } elseif (!empty($h_item['changed_by_name'])) {
                      $timelineNote = 'Updated by ' . $h_item['changed_by_name'];
                    }
                  }
                ?>
                <?php if ($timelineNote !== ''): ?>
                <br><small class="text-muted"><?= h($timelineNote) ?></small>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Feedback (if submitted) -->
      <?php if ($ticket['fb_rating']): ?>
      <div class="card">
        <div class="card-header"><i class="bi bi-star-fill text-warning me-2"></i>Your Feedback</div>
        <div class="card-body">
          <div class="mb-2"><?= renderStars((int)$ticket['fb_rating']) ?> <strong class="ms-2"><?= $ticket['fb_rating'] ?>/5</strong></div>
          <?php if ($ticket['fb_comment']): ?>
          <p class="mb-0 text-muted" style="font-size:.9rem;">"<?= h($ticket['fb_comment']) ?>"</p>
          <?php endif; ?>
          <small class="text-muted">Submitted on <?= formatDate($ticket['fb_date']) ?></small>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div class="col-lg-4">
      <!-- Assigned Staff -->
      <?php if ($ticket['staff_name']): ?>
      <div class="card mb-3">
        <div class="card-header"><i class="bi bi-person-badge me-2"></i>Assigned To</div>
        <div class="card-body text-center">
          <?php
            $initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_filter(explode(' ', $ticket['staff_name']))));
            $initials = substr($initials, 0, 2);
          ?>
          <div style="width:64px;height:64px;background:#1a3a5c;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.3rem;font-weight:700;color:#fff;margin:0 auto .75rem;">
            <?= h($initials) ?>
          </div>
          <div class="fw-semibold"><?= h($ticket['staff_name']) ?></div>
          <div class="text-muted small"><?= h($ticket['designation']) ?></div>
          <div class="mt-2"><?= roleBadge($ticket['staff_role']) ?></div>
          <?php if ($ticket['staff_contact']): ?>
          <div class="mt-2 small"><i class="bi bi-telephone me-1"></i><?= h($ticket['staff_contact']) ?></div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Assignment History -->
      <?php if (!empty($assignments)): ?>
      <div class="card">
        <div class="card-header"><i class="bi bi-arrow-left-right me-2"></i>Assignment History</div>
        <div class="card-body p-0">
          <?php foreach ($assignments as $a): ?>
          <div class="p-3 border-bottom" style="font-size:.82rem;">
            <div class="fw-semibold"><?= h($a['assignee_name']) ?></div>
            <div class="text-muted"><?= h($a['designation'] ?? '') ?></div>
            <div class="text-muted mt-1"><?= timeAgo($a['assigned_at']) ?></div>
            <?php if ($a['notes']): ?>
            <div class="mt-1 text-secondary"><?= h($a['notes']) ?></div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
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
