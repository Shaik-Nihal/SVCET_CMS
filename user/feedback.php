<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireUser();

$pdo      = getDB();
$userId   = currentUserId();
$ticketId = (int)($_GET['ticket_id'] ?? 0);

// Verify ticket
$stmt = $pdo->prepare("
    SELECT t.id, t.ticket_number, t.status,
           COALESCE(pc.name,'Custom') AS category_name,
           s.name AS staff_name, s.designation
    FROM tickets t
    LEFT JOIN problem_categories pc ON t.problem_category_id = pc.id
    LEFT JOIN it_staff s ON t.assigned_to = s.id
    WHERE t.id = ? AND t.user_id = ? AND t.status = 'solved'
");
$stmt->execute([$ticketId, $userId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    setFlash('error', 'Ticket not found or not yet solved.');
    header('Location: ' . APP_URL . '/user/my_tickets');
    exit;
}

// Check existing feedback
$stmt = $pdo->prepare("SELECT id FROM feedback WHERE ticket_id = ?");
$stmt->execute([$ticketId]);
if ($stmt->fetch()) {
    setFlash('info', 'You have already submitted feedback for this ticket.');
    header('Location: ' . APP_URL . '/user/ticket_detail?id=' . $ticketId);
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $rating  = (int)($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        if ($rating < 1 || $rating > 5) {
            $error = 'Please select a rating between 1 and 5.';
        } else {
            $pdo->prepare("INSERT INTO feedback (ticket_id, user_id, rating, comment) VALUES (?,?,?,?)")
                ->execute([$ticketId, $userId, $rating, $comment ?: null]);
            setFlash('success', 'Thank you for your feedback!');
            header('Location: ' . APP_URL . '/user/ticket_detail?id=' . $ticketId);
            exit;
        }
    }
}

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
<title>Feedback — <?= APP_NAME ?></title>
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
        <a class="sub-link active" href="<?= APP_URL ?>/user/my_tickets"><i class="bi bi-list-check"></i>My Tickets</a>
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

    <main class="user-content">
<div class="container" style="max-width:600px;">
  <div class="py-4">
    <a href="<?= APP_URL ?>/user/ticket_detail?id=<?= $ticketId ?>" class="btn btn-sm btn-outline-secondary mb-3">
      <i class="bi bi-arrow-left me-1"></i>Back to Ticket
    </a>

    <?php if ($error): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>

    <!-- Ticket Summary -->
    <div class="card mb-4">
      <div class="card-body">
        <div class="d-flex align-items-center gap-3">
          <div style="width:48px;height:48px;background:#dcfce7;border-radius:10px;display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-check-circle-fill text-success" style="font-size:1.4rem;"></i>
          </div>
          <div>
            <div class="ticket-number"><?= h($ticket['ticket_number']) ?></div>
            <div style="font-size:.85rem;color:#64748b;"><?= h($ticket['category_name']) ?> · Resolved by <?= h($ticket['staff_name'] ?? 'IT Team') ?></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Feedback Form -->
    <div class="card">
      <div class="card-header"><i class="bi bi-star-fill text-warning me-2"></i>Rate Your Experience</div>
      <div class="card-body">
        <p class="text-muted mb-4" style="font-size:.9rem;">Your feedback helps us improve our complaint resolution service. How was your experience?</p>

        <form method="POST" action="feedback?ticket_id=<?= $ticketId ?>">
          <?= csrfField() ?>

          <div class="mb-4 text-center">
            <label class="form-label fw-semibold d-block mb-3">How would you rate the support?</label>
            <div class="star-rating justify-content-center" style="font-size:2.5rem;gap:6px;">
              <?php for ($i = 5; $i >= 1; $i--): ?>
              <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" required>
              <label for="star<?= $i ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">
                <i class="bi bi-star-fill"></i>
              </label>
              <?php endfor; ?>
            </div>
            <div class="mt-2" id="ratingLabel" style="font-size:.85rem;color:#64748b;min-height:1.2em;"></div>
          </div>

          <div class="mb-4">
            <label for="comment" class="form-label fw-semibold">Comments <span class="text-muted">(optional)</span></label>
            <textarea id="comment" name="comment" class="form-control" rows="4" maxlength="500"
                      placeholder="Tell us more about your experience..."><?= h($_POST['comment'] ?? '') ?></textarea>
            <small class="text-muted float-end"><span id="charCount">0</span>/500</small>
          </div>

          <div class="d-grid">
            <button type="submit" class="btn btn-svcet btn-lg">
              <i class="bi bi-send me-2"></i>Submit Feedback
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
    </div>
    </main>
  </div>
</div>

<script nonce="<?= cspNonce() ?>" src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script nonce="<?= cspNonce() ?>" src="<?= APP_URL ?>/assets/js/main.js"></script>
<script nonce="<?= cspNonce() ?>" src="<?= APP_URL ?>/assets/js/notifications.js"></script>
<script nonce="<?= cspNonce() ?>">
const labels = {1:'Poor',2:'Fair',3:'Good',4:'Very Good',5:'Excellent'};
document.querySelectorAll('input[name="rating"]').forEach(r => {
    r.addEventListener('change', () => {
        document.getElementById('ratingLabel').textContent = labels[r.value] || '';
    });
});
document.getElementById('comment').addEventListener('input', function() {
    document.getElementById('charCount').textContent = this.value.length;
});
</script>
</body>
</html>
