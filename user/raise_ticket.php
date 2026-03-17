<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ticket_helpers.php';
require_once __DIR__ . '/../includes/notification_helpers.php';
require_once __DIR__ . '/../includes/rbac.php';

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

          if ($staffRow && !roleHasPermission($staffRow['role'], 'notify.management')) {
              $errors[] = 'You can assign tickets only to roles configured for ticket intake.';
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
$staffList = getStaffByPermission('notify.management');

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
        <a class="sub-link active" href="<?= APP_URL ?>/user/raise_ticket"><i class="bi bi-plus-circle"></i>Raise Ticket</a>
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
    <input type="hidden" id="assigned_to" name="assigned_to" value="<?= h($_POST['assigned_to'] ?? '') ?>">

    <!-- Step 1: Category -->
    <div class="card mb-3">
      <div class="card-header">
        <span class="badge bg-primary me-2">Step 1</span>Select Problem Category
      </div>
      <div class="card-body">
        <div class="row g-2">
          <div class="col-lg-8">
            <label for="category_id" class="form-label fw-semibold">Problem Category <span class="text-danger">*</span></label>
            <select class="form-select" id="category_id" name="category_id" onchange="handleCategoryChange()" required>
              <option value="">-- Select a category --</option>
              <?php foreach ($categories as $cat): ?>
              <option value="<?= (int)$cat['id'] ?>"
                      data-name="<?= h($cat['name']) ?>"
                      <?= ((int)($_POST['category_id'] ?? 0) === (int)$cat['id']) ? 'selected' : '' ?>>
                <?= h($cat['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
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
      <button type="submit" class="btn btn-svcet px-4">
        <i class="bi bi-send me-2"></i>Submit Ticket
      </button>
    </div>
  </form>
    </main>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script src="<?= APP_URL ?>/assets/js/notifications.js"></script>
<script>
function handleCategoryChange() {
  const categorySelect = document.getElementById('category_id');
  const selectedOption = categorySelect.options[categorySelect.selectedIndex];
  const selectedName = selectedOption ? selectedOption.dataset.name : '';
  const isOther = selectedName === 'Other';
    const descSec = document.getElementById('description-section');
    const descField = document.getElementById('description_field');
    const descLabel = document.getElementById('desc-label');

  if (!categorySelect.value) {
    descSec.classList.add('d-none');
    descField.required = false;
    return;
  }

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

document.addEventListener('DOMContentLoaded', handleCategoryChange);

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
