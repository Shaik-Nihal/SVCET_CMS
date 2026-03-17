<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ticket_helpers.php';
require_once __DIR__ . '/../includes/notification_helpers.php';

requireUser();

$pdo    = getDB();
$userId = currentUserId();
$errors = [];

// POST: Create ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please refresh and try again.';
    } else {
        $categoryId  = (int)($_POST['category_id'] ?? 0);
        $assignedTo  = (int)($_POST['assigned_to'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $priority    = in_array($_POST['priority'] ?? '', ['low','medium','high']) ? $_POST['priority'] : 'medium';

        // Check if category is "Other" (last one) or custom
        $stmt = $pdo->prepare("SELECT name FROM problem_categories WHERE id = ? AND is_active = 1");
        $stmt->execute([$categoryId]);
        $cat = $stmt->fetch();

        if (!$categoryId || !$cat) { $errors[] = 'Please select a problem category.'; }
        if (!$assignedTo) { $errors[] = 'Please select a staff member to assign the ticket to.'; }
        if ($cat && $cat['name'] === 'Other' && empty($description)) {
            $errors[] = 'Please describe your problem in the text box.';
        }

        // Validate assigned_to is real active staff and enforce role rules
        if ($assignedTo) {
          $stmt = $pdo->prepare("SELECT id, name, role, designation, email FROM it_staff WHERE id = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$assignedTo]);
            $staffRow = $stmt->fetch();
            if (!$staffRow) { $errors[] = 'Invalid staff selection.'; }

          if ($staffRow) {
            if (!in_array($staffRow['role'], [ROLE_ICT_HEAD, ROLE_ASST_MANAGER, ROLE_ASST_ICT], true)) {
              $errors[] = 'You can assign tickets only to ICT Head, Assistant Manager, or Assistant ICT.';
            }
          }
        }

        if (empty($errors)) {
            try {
                $result = createTicket([
                    'user_id'     => $userId,
                    'category_id' => $categoryId,
                    'description' => $description,
                    'assigned_to' => $assignedTo,
                    'priority'    => $priority,
                ]);

                $ticketId     = $result['id'];
                $ticketNumber = $result['ticket_number'];

                // Fetch user data once for notifications (avoids re-query)
                $stmt = $pdo->prepare("SELECT name, email, phone, designation FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$userId]);
                $userData = $stmt->fetch();

                notifyUserTicketCreated($userId, $ticketId, $ticketNumber, $staffRow['name'] . ' (' . $staffRow['designation'] . ')', $userData);
                notifyAllLeadership($ticketId, $ticketNumber, $_SESSION['user_name'], $cat['name'], $userData['designation'] ?? '');

                setFlash('success', "Ticket <strong>{$ticketNumber}</strong> raised successfully! You will be notified of updates.");

                // Redirect instantly — emails are sent AFTER the browser gets the response
                sendResponseAndFlushEmails(APP_URL . '/user/ticket_detail?id=' . $ticketId);
            } catch (Throwable $e) {
                $errors[] = 'Failed to raise ticket. Please try again.';
            }
        }
    }
}

// Load categories and staff
$categories = $pdo->query("SELECT * FROM problem_categories WHERE is_active = 1 ORDER BY id")->fetchAll();
$stmt = $pdo->prepare("SELECT id, name, role, designation, contact FROM it_staff WHERE is_active = 1 AND role IN (?, ?, ?) ORDER BY role, name");
$stmt->execute([ROLE_ICT_HEAD, ROLE_ASST_MANAGER, ROLE_ASST_ICT]);
$staffList = $stmt->fetchAll();

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
<title>Raise Ticket — <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body data-user-type="user">

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-apollo fixed-top">
  <div class="container-fluid">
    <a class="navbar-brand" href="<?= APP_URL ?>/user/dashboard"><img src="<?= APP_LOGO_URL ?>" alt="<?= APP_LOGO_ALT ?>"><?= APP_SHORT ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
      <span class="navbar-toggler-icon" style="filter:invert(1)"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMenu">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="<?= APP_URL ?>/user/dashboard"><i class="bi bi-house me-1"></i>Dashboard</a></li>
        <li class="nav-item"><a class="nav-link active" href="<?= APP_URL ?>/user/raise_ticket"><i class="bi bi-plus-circle me-1"></i>Raise Ticket</a></li>
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
    <h4><i class="bi bi-plus-circle me-2"></i>Raise a New Support Ticket</h4>
    <a href="<?= APP_URL ?>/user/my_tickets" class="btn btn-sm btn-outline-secondary"><i class="bi bi-list me-1"></i>My Tickets</a>
  </div>

  <?php if ($errors): ?>
  <div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
  </div>
  <?php endif; ?>

  <form method="POST" id="raise-ticket-form" action="raise_ticket">
    <?= csrfField() ?>
    <input type="hidden" id="category_id" name="category_id" value="<?= h($_POST['category_id'] ?? '') ?>">
    <input type="hidden" id="assigned_to" name="assigned_to" value="<?= h($_POST['assigned_to'] ?? '') ?>">

    <!-- Step 1: Category -->
    <div class="card mb-3">
      <div class="card-header">
        <span class="badge bg-primary me-2">Step 1</span>Select Problem Category
      </div>
      <div class="card-body">
        <div class="row g-2">
          <?php foreach ($categories as $cat): ?>
          <div class="col-6 col-sm-4 col-md-3 col-lg-2">
            <div class="category-card <?= ((int)($_POST['category_id'] ?? 0) === (int)$cat['id']) ? 'selected' : '' ?>"
                 data-id="<?= $cat['id'] ?>" data-name="<?= h($cat['name']) ?>" onclick="selectCategory(this)">
              <i class="bi <?= h($cat['icon']) ?>"></i>
              <div class="cat-name"><?= h($cat['name']) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Description (single textarea, always present, visibility controlled by JS) -->
        <div id="description-section" class="mt-3 d-none">
          <label class="form-label fw-semibold" id="desc-label">Additional Details <span class="text-muted" id="desc-required-tag">(optional)</span></label>
          <textarea name="description" id="description_field" class="form-control" rows="3" maxlength="1000"
                    placeholder="Please describe the issue in detail..."><?= h($_POST['description'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

    <!-- Step 2: Assign to Staff -->
    <div class="card mb-3">
      <div class="card-header">
        <span class="badge bg-primary me-2">Step 2</span>Select IT Staff to Assign Ticket
      </div>
      <div class="card-body">
        <div class="row g-2" id="staff-list">
          <?php foreach ($staffList as $staff):
            $initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_filter(explode(' ', $staff['name']))));
            $initials = substr($initials, 0, 2);
          ?>
          <div class="col-12 col-sm-6 col-lg-4">
            <div class="staff-card d-flex align-items-center gap-3
                        <?= ((int)($_POST['assigned_to'] ?? 0) === (int)$staff['id']) ? 'selected' : '' ?>"
                 data-id="<?= $staff['id'] ?>" onclick="selectStaff(this)">
              <div class="staff-avatar"><?= h($initials) ?></div>
              <div>
                <div class="fw-semibold" style="font-size:.9rem;"><?= h($staff['name']) ?></div>
                <div style="font-size:.78rem;color:#64748b;"><?= h($staff['designation']) ?></div>
                <div class="mt-1">
                  <?= roleBadge($staff['role']) ?>
                  <?php if ($staff['contact']): ?>
                  <small class="text-muted ms-2"><i class="bi bi-telephone me-1"></i><?= h($staff['contact']) ?></small>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Step 3: Priority -->
    <div class="card mb-4">
      <div class="card-header">
        <span class="badge bg-primary me-2">Step 3</span>Set Priority
      </div>
      <div class="card-body">
        <div class="d-flex gap-2">
          <?php foreach (['low' => ['bg-success','Low'], 'medium' => ['bg-warning text-dark','Medium'], 'high' => ['bg-danger','High']] as $val => [$cls, $label]): ?>
          <button type="button" class="btn btn-outline-secondary priority-btn <?= (($_POST['priority'] ?? 'medium') === $val) ? 'active btn-selected' : '' ?>"
                  onclick="selectPriority('<?= $val ?>', this)">
            <span class="badge <?= $cls ?> me-1">&nbsp;</span><?= $label ?>
          </button>
          <?php endforeach; ?>
          <input type="hidden" name="priority" id="priority_field" value="<?= h($_POST['priority'] ?? 'medium') ?>">
        </div>
        <small class="text-muted mt-2 d-block">Select <strong>High</strong> only for critical issues affecting work/classes.</small>
      </div>
    </div>

    <div class="text-end">
      <a href="<?= APP_URL ?>/user/dashboard" class="btn btn-outline-secondary me-2">Cancel</a>
      <button type="submit" class="btn btn-apollo px-4">
        <i class="bi bi-send me-2"></i>Submit Ticket
      </button>
    </div>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script src="<?= APP_URL ?>/assets/js/notifications.js"></script>
<script>
function selectCategory(el) {
    document.querySelectorAll('.category-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('category_id').value = el.dataset.id;
    const isOther = el.dataset.name === 'Other';
    const descSec = document.getElementById('description-section');
    const descField = document.getElementById('description_field');
    const descLabel = document.getElementById('desc-label');
    const reqTag = document.getElementById('desc-required-tag');
    descSec.classList.remove('d-none');
    if (isOther) {
        descField.required = true;
        descField.placeholder = 'Please describe the issue in detail...';
        descLabel.innerHTML = 'Describe Your Problem <span class="text-danger">*</span>';
    } else {
        descField.required = false;
        descField.placeholder = 'Any additional information (optional)...';
        descLabel.innerHTML = 'Additional Details <span class="text-muted">(optional)</span>';
    }
}

function selectStaff(el) {
    document.querySelectorAll('.staff-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('assigned_to').value = el.dataset.id;
}

function selectPriority(val, btn) {
    document.querySelectorAll('.priority-btn').forEach(b => b.classList.remove('active','btn-selected'));
    btn.classList.add('active','btn-selected');
    document.getElementById('priority_field').value = val;
}

document.getElementById('raise-ticket-form').addEventListener('submit', function(e) {
    if (!document.getElementById('category_id').value) {
        e.preventDefault();
        alert('Please select a problem category.');
        return;
    }
    if (!document.getElementById('assigned_to').value) {
        e.preventDefault();
        alert('Please select a staff member to assign the ticket.');
        return;
    }
});
</script>
</body>
</html>
