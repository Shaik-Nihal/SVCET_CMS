<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ticket_helpers.php';

requireStaff();

$pdo      = getDB();
$staffId  = currentStaffId();
$role     = currentRole();
$ticketId = (int)($_GET['id'] ?? 0);

if (!$ticketId) {
    header('Location: ' . APP_URL . '/staff/tickets');
    exit;
}

// Role-based access control
$ticket = null;
$baseQuery = "
    SELECT t.*,
           u.name AS user_name, u.email AS user_email, u.phone AS user_phone,
           u.department AS user_dept, u.designation AS user_designation, u.roll_no,
           COALESCE(pc.name,'Custom') AS category_name, pc.icon AS category_icon,
           s.name AS assigned_name, s.role AS assigned_role,
           s.designation AS assigned_designation, s.contact AS assigned_contact,
           f.rating AS fb_rating, f.comment AS fb_comment
    FROM tickets t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN problem_categories pc ON t.problem_category_id = pc.id
    LEFT JOIN it_staff s ON t.assigned_to = s.id
    LEFT JOIN feedback f ON f.ticket_id = t.id
    WHERE t.id = ?
";

if (currentStaffHasPermission('tickets.view_all')) {
  // Leadership roles can view all tickets regardless of assignment
  $stmt = $pdo->prepare($baseQuery);
  $stmt->execute([$ticketId]);
} elseif (currentStaffHasPermission('ticket.assign.exec')) {
  // Sr IT can view tickets they are involved in:
  // current assignee, previous assignee, or assigner/reassigner.
  $stmt = $pdo->prepare($baseQuery . " AND (t.assigned_to = ? OR EXISTS (SELECT 1 FROM ticket_assignments ta WHERE ta.ticket_id = t.id AND ta.assigned_to = ?) OR EXISTS (SELECT 1 FROM ticket_assignments ta2 WHERE ta2.ticket_id = t.id AND ta2.assigned_by = ?))");
  $stmt->execute([$ticketId, $staffId, $staffId, $staffId]);
  } elseif (currentStaffHasPermission('tickets.view_involved')) {
    // Assistant IT can still view tickets previously assigned to them (read-only if no longer current assignee).
    $stmt = $pdo->prepare($baseQuery . " AND (t.assigned_to = ? OR EXISTS (SELECT 1 FROM ticket_assignments ta WHERE ta.ticket_id = t.id AND ta.assigned_to = ?))");
    $stmt->execute([$ticketId, $staffId, $staffId]);
} else {
    $stmt = $pdo->prepare($baseQuery . " AND t.assigned_to = ?");
    $stmt->execute([$ticketId, $staffId]);
}
$ticket = $stmt->fetch();

if (!$ticket) {
    setFlash('error', 'Ticket not found or you do not have access.');
    header('Location: ' . APP_URL . '/staff/tickets');
    exit;
}

// Status history
$histStmt = $pdo->prepare("\n    SELECT h.*,\n           changer.name AS changed_by_name,\n           (\n               SELECT assignee.name\n               FROM ticket_assignments ta\n               LEFT JOIN it_staff assignee ON ta.assigned_to = assignee.id\n               WHERE ta.ticket_id = h.ticket_id\n                 AND ta.assigned_at <= h.created_at\n               ORDER BY ta.assigned_at DESC, ta.id DESC\n               LIMIT 1\n           ) AS assigned_to_name\n    FROM ticket_status_history h\n    LEFT JOIN it_staff changer\n           ON h.changed_by_type = 'staff' AND h.changed_by = changer.id\n    WHERE h.ticket_id = ?\n    ORDER BY h.created_at ASC, h.id ASC\n");
$histStmt->execute([$ticketId]);
$history = $histStmt->fetchAll();

// Assignment history
$assnStmt = $pdo->prepare("
    SELECT ta.*, s1.name AS assigner_name, s1.designation AS assigner_desig,
         s2.id AS assignee_id, s2.role AS assignee_role, s2.name AS assignee_name, s2.designation AS assignee_desig
    FROM ticket_assignments ta
    LEFT JOIN it_staff s1 ON ta.assigned_by = s1.id
    LEFT JOIN it_staff s2 ON ta.assigned_to = s2.id
    WHERE ta.ticket_id = ? ORDER BY ta.assigned_at ASC
");
$assnStmt->execute([$ticketId]);
$assignments = $assnStmt->fetchAll();

    // Metric: count reassignment hops between execution-level assignees.
    // Use permissions instead of fixed slugs so renamed/custom roles still work.
    $executiveReassignCount = 0;
    $prevExecAssigneeId = 0;
    foreach ($assignments as $a) {
      $assigneeId = (int)($a['assignee_id'] ?? 0);
      $assigneeRole = (string)($a['assignee_role'] ?? '');

      $isExecutionRole = roleHasPermission($assigneeRole, 'ticket.update_status')
        && !roleHasPermission($assigneeRole, 'ticket.assign.exec')
        && !roleHasPermission($assigneeRole, 'ticket.assign.lead');

      if ($assigneeId > 0 && $isExecutionRole) {
        if ($prevExecAssigneeId > 0 && $assigneeId !== $prevExecAssigneeId) {
          $executiveReassignCount++;
        }
        $prevExecAssigneeId = $assigneeId;
      }
    }

// Eligible staff to assign (based on role & ticket domain)
$eligibleStaff = [];
$canAssign = canAssignForDomain($role, $ticket['user_email'] ?? '');

if ($canAssign && (currentStaffHasPermission('ticket.assign.lead') || currentStaffHasPermission('ticket.assign.exec'))) {
  $stmt = $pdo->prepare("SELECT id, name, role, designation, contact FROM it_staff WHERE is_active = 1 AND id != ? ORDER BY role, name");
  $stmt->execute([$staffId]);
  $candidates = $stmt->fetchAll();

  foreach ($candidates as $candidate) {
    if (canAssignForTicket($role, (string)$candidate['role'], $ticket['user_email'] ?? '')) {
      $eligibleStaff[] = $candidate;
    }
  }
}

$nextStatus    = getNextStatus($ticket['status']);
$unreadCount   = 0;
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE recipient_id=? AND recipient_type='staff' AND is_read=0");
$stmt->execute([$staffId]);
$unreadCount = (int)$stmt->fetchColumn();

$statusOrder = ['notified'=>0,'processing'=>1,'solving'=>2,'solved'=>3];
$currentStep = $statusOrder[$ticket['status']] ?? 0;

$ticketDomain = 'unknown';
$domainBadgeClass = 'bg-secondary';
$domainBadgeLabel = 'Domain: Unknown';
if (!empty($ticket['user_email'])) {
  $email = strtolower((string)$ticket['user_email']);
  if (str_ends_with($email, '@' . EMAIL_DOMAIN)) {
    $ticketDomain = 'primary';
    $domainBadgeClass = 'bg-primary';
    $domainBadgeLabel = 'Domain: ' . EMAIL_DOMAIN;
  }
}
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
        <a class="staff-user-toggle dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="bi bi-person-circle"></i><span class="d-none d-md-inline"><?= h($_SESSION['staff_name'] ?? 'Staff') ?></span></a>
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
    <a class="nav-link" href="<?= APP_URL ?>/staff/profile"><i class="bi bi-person-gear"></i>My Profile</a>
    <a class="nav-link" href="<?= APP_URL ?>/auth/logout"><i class="bi bi-box-arrow-right"></i>Logout</a>
  </nav>
</div>

<div class="main-content">
  <div class="staff-headline">
    <div>
      <h3><i class="bi bi-file-earmark-text me-2"></i>Case View</h3>
      <p>Inspect ticket lifecycle, timeline updates, and assignment history.</p>
    </div>
    <span class="pill"><?= h($ticket['ticket_number']) ?></span>
  </div>

  <div class="page-title-bar">
    <h4>
      <a href="<?= APP_URL ?>/staff/tickets" class="text-decoration-none text-muted me-2"><i class="bi bi-arrow-left"></i></a>
      Ticket: <span class="ticket-number"><?= h($ticket['ticket_number']) ?></span>
    </h4>
    <span class="badge <?= statusBadge($ticket['status']) ?> fs-6"><?= statusLabel($ticket['status']) ?></span>
  </div>

  <?php renderFlash(); ?>

  <div class="row g-3">
    <!-- Main -->
    <div class="col-lg-8">
      <!-- Status Progress -->
      <div class="card mb-3">
        <div class="card-body">
          <div class="status-progress">
            <?php foreach (['notified'=>'Notified','processing'=>'Processing','solving'=>'Solving','solved'=>'Solved'] as $s => $lbl):
              $step = $statusOrder[$s];
              $cls  = $step < $currentStep ? 'done' : ($step === $currentStep ? 'active' : '');
            ?>
            <div class="status-step <?= $cls ?>"><?= $lbl ?></div>
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
          <div class="bg-light rounded p-3 mb-3" style="font-size:.9rem;"><?= nl2br(h($ticket['custom_description'])) ?></div>
          <?php endif; ?>
          <div class="row g-2" style="font-size:.82rem;color:#64748b;">
            <div class="col-sm-6"><i class="bi bi-calendar3 me-1"></i>Raised: <?= formatDate($ticket['created_at']) ?></div>
            <?php if ($ticket['solved_at']): ?>
            <div class="col-sm-6"><i class="bi bi-check-circle me-1"></i>Solved: <?= formatDate($ticket['solved_at']) ?></div>
            <?php endif; ?>
            <div class="col-sm-6"><i class="bi bi-stopwatch me-1"></i>Time: <?= resolutionTime($ticket['created_at'], $ticket['solved_at']) ?></div>
          </div>
        </div>
      </div>

      <!-- ACTION: Update Status (Sr IT Executive) -->
      <?php if (currentStaffHasPermission('ticket.update_status') && (int)$ticket['assigned_to'] === $staffId && $nextStatus && $ticket['status'] !== 'solved'): ?>
      <div class="card mb-3 border-primary">
        <div class="card-header bg-primary text-white"><i class="bi bi-arrow-up-circle me-2"></i>Update Ticket Status</div>
        <div class="card-body">
          <p class="text-muted mb-3" style="font-size:.9rem;">
            Move ticket from <span class="badge <?= statusBadge($ticket['status']) ?>"><?= statusLabel($ticket['status']) ?></span>
            to <span class="badge <?= statusBadge($nextStatus) ?>"><?= statusLabel($nextStatus) ?></span>
          </p>
          <form method="POST" action="<?= APP_URL ?>/staff/update_status">
            <?= csrfField() ?>
            <input type="hidden" name="ticket_id" value="<?= $ticketId ?>">
            <input type="hidden" name="new_status" value="<?= h($nextStatus) ?>">
            <div class="mb-3">
              <label class="form-label fw-semibold">Update Notes <span class="text-muted">(optional)</span></label>
              <textarea name="notes" class="form-control" rows="2" placeholder="Any notes about the progress..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary" onclick="return confirm('Mark ticket as <?= statusLabel($nextStatus) ?>?')">
              <i class="bi bi-arrow-up-circle me-2"></i>Mark as <?= statusLabel($nextStatus) ?>
            </button>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <?php if (currentStaffHasPermission('ticket.update_status') && !currentStaffHasPermission('ticket.assign.exec') && (int)$ticket['assigned_to'] !== $staffId): ?>
      <div class="card mb-3 border-secondary">
        <div class="card-header bg-secondary text-white"><i class="bi bi-eye me-2"></i>Read-Only Access</div>
        <div class="card-body">
          This ticket was reassigned to another executive. You can view details and timeline, but you cannot change status.
        </div>
      </div>
      <?php endif; ?>

      <!-- ACTION: Assign Ticket -->
      <?php if ((currentStaffHasPermission('ticket.assign.lead') || currentStaffHasPermission('ticket.assign.exec')) && $ticket['status'] !== 'solved' && !empty($eligibleStaff)): ?>
      <div class="card mb-3 border-warning">
        <div class="card-header bg-warning text-dark"><i class="bi bi-person-fill-gear me-2"></i>Assign / Re-assign Ticket</div>
        <div class="card-body">
          <form method="POST" action="<?= APP_URL ?>/staff/assign_ticket">
            <?= csrfField() ?>
            <input type="hidden" name="ticket_id" value="<?= $ticketId ?>">
            <div class="mb-3">
              <label class="form-label fw-semibold">Assign To</label>
              <select name="assigned_to" class="form-select" required>
                <option value="">— Select Staff Member —</option>
                <?php foreach ($eligibleStaff as $es): ?>
                <option value="<?= $es['id'] ?>" <?= $ticket['assigned_to'] == $es['id'] ? 'selected' : '' ?>>
                  <?= h($es['name']) ?> — <?= h($es['designation']) ?> (<?= roleLabel($es['role']) ?>)
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Assignment Notes <span class="text-muted">(optional)</span></label>
              <textarea name="notes" class="form-control" rows="2" placeholder="Instructions or context for the assignee..."></textarea>
            </div>
            <button type="submit" class="btn btn-warning text-dark">
              <i class="bi bi-person-check me-2"></i>Assign Ticket
            </button>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <!-- Timeline -->
      <div class="card mb-3">
        <div class="card-header"><i class="bi bi-clock-history me-2"></i>Activity Timeline</div>
        <div class="card-body">
          <div class="mb-2 small text-muted"><i class="bi bi-arrow-left-right me-1"></i>Executive reassignments: <strong><?= (int)$executiveReassignCount ?></strong></div>
          <div class="timeline">
            <?php foreach ($history as $h_item): ?>
            <div class="timeline-item">
              <div class="timeline-dot <?= $h_item['new_status'] ?>"></div>
              <div class="timeline-date"><?= formatDate($h_item['created_at']) ?></div>
              <div class="timeline-text">
                <strong><?= statusLabel($h_item['new_status']) ?></strong>
                <?php if ($h_item['old_status']): ?><span class="text-muted"> ← <?= statusLabel($h_item['old_status']) ?></span><?php endif; ?>
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

      <!-- Feedback -->
      <?php if ($ticket['fb_rating']): ?>
      <div class="card">
        <div class="card-header"><i class="bi bi-star-fill text-warning me-2"></i>User Feedback</div>
        <div class="card-body">
          <div class="mb-1"><?= renderStars((int)$ticket['fb_rating']) ?> <strong class="ms-2"><?= $ticket['fb_rating'] ?>/5</strong></div>
          <?php if ($ticket['fb_comment']): ?><p class="text-muted mb-0">"<?= h($ticket['fb_comment']) ?>"</p><?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div class="col-lg-4">
      <!-- Raised By -->
      <div class="card mb-3">
        <div class="card-header"><i class="bi bi-person me-2"></i>Raised By</div>
        <div class="card-body" style="font-size:.85rem;">
          <div class="mb-2">
            <span class="badge <?= $domainBadgeClass ?>"><?= h($domainBadgeLabel) ?></span>
          </div>
          <div class="fw-semibold"><?= h($ticket['user_name']) ?></div>
          <?php if ($ticket['user_designation']): ?><div class="small fw-semibold text-primary"><?= h($ticket['user_designation']) ?></div><?php endif; ?>
          <div class="text-muted"><?= h($ticket['user_dept'] ?? '') ?></div>
          <?php if ($ticket['user_email']): ?><div><i class="bi bi-envelope me-1"></i><a href="mailto:<?= h($ticket['user_email']) ?>"><?= h($ticket['user_email']) ?></a></div><?php endif; ?>
          <?php if ($ticket['user_phone']): ?><div><i class="bi bi-telephone me-1"></i><?= h($ticket['user_phone']) ?></div><?php endif; ?>
          <?php if ($ticket['roll_no']): ?><div class="text-muted">Roll/Emp: <?= h($ticket['roll_no']) ?></div><?php endif; ?>
        </div>
      </div>

      <!-- Current Assignee -->
      <?php if ($ticket['assigned_name']): ?>
      <div class="card mb-3">
        <div class="card-header"><i class="bi bi-person-badge me-2"></i>Currently Assigned To</div>
        <div class="card-body text-center">
          <?php $initials = substr(implode('',array_map(fn($w)=>strtoupper($w[0]),array_filter(explode(' ',$ticket['assigned_name'])))),0,2); ?>
          <div style="width:52px;height:52px;background:#1a3a5c;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;margin:0 auto .5rem;"><?= h($initials) ?></div>
          <div class="fw-semibold"><?= h($ticket['assigned_name']) ?></div>
          <div class="text-muted small"><?= h($ticket['assigned_designation']) ?></div>
          <div class="mt-1"><?= roleBadge($ticket['assigned_role']) ?></div>
          <?php if ($ticket['assigned_contact']): ?><div class="small mt-1"><i class="bi bi-telephone me-1"></i><?= h($ticket['assigned_contact']) ?></div><?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Assignment History -->
      <?php if (!empty($assignments)): ?>
      <div class="card">
        <div class="card-header"><i class="bi bi-arrow-left-right me-2"></i>Assignment History</div>
        <div class="card-body p-0">
          <?php foreach ($assignments as $a): ?>
          <div class="p-3 border-bottom" style="font-size:.8rem;">
            <div><strong><?= h($a['assignee_name']) ?></strong> <span class="text-muted">(<?= h($a['assignee_desig']) ?>)</span></div>
            <?php if ($a['assigner_name']): ?><div class="text-muted">By: <?= h($a['assigner_name']) ?></div><?php endif; ?>
            <div class="text-muted"><?= timeAgo($a['assigned_at']) ?></div>
            <?php if ($a['notes']): ?><div class="mt-1 text-secondary"><?= h($a['notes']) ?></div><?php endif; ?>
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
