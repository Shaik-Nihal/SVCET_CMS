<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireStaff();
requirePermission('roles.manage');

$pdo = getDB();
$staffId = currentStaffId();
$originalSlug = trim((string)($_GET['slug'] ?? ''));
$isEdit = $originalSlug !== '';
$returnTo = trim((string)($_GET['return'] ?? ($_POST['return_to'] ?? '')));
if ($returnTo === '' || $returnTo[0] !== '/') {
  $returnTo = '/staff/roles';
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE recipient_id=? AND recipient_type='staff' AND is_read=0");
$stmt->execute([$staffId]);
$unreadCount = (int)$stmt->fetchColumn();

$form = [
    'slug' => '',
    'name' => '',
    'is_active' => 1,
    'is_system' => 0,
    'permissions' => [],
];

if ($isEdit) {
    $role = getRoleBySlug($originalSlug);
    if (!$role) {
        setFlash('error', 'Role not found.');
        header('Location: ' . APP_URL . '/staff/roles');
        exit;
    }

    $form['slug'] = (string)$role['slug'];
    $form['name'] = (string)$role['name'];
    $form['is_active'] = (int)$role['is_active'];
    $form['is_system'] = (int)$role['is_system'];
    $form['permissions'] = getRolePermissions($originalSlug);
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token.';
    } else {
        $form['slug'] = strtolower(trim((string)($_POST['slug'] ?? '')));
        $form['name'] = trim((string)($_POST['name'] ?? ''));
        $form['is_active'] = isset($_POST['is_active']) ? 1 : 0;
        $form['permissions'] = array_values(array_unique(array_map('strval', $_POST['permissions'] ?? [])));

        if (!preg_match('/^[a-z][a-z0-9_]{2,63}$/', $form['slug'])) {
            $errors[] = 'Role slug must start with a letter and contain only lowercase letters, numbers, and underscore.';
        }
        if ($form['name'] === '' || mb_strlen($form['name']) > 100) {
            $errors[] = 'Role name is required and must be under 100 characters.';
        }
        if ($form['slug'] === 'admin') {
            $form['is_active'] = 1;
        }

        if (empty($errors)) {
            try {
                saveRoleWithPermissions($isEdit ? $originalSlug : '', $form['slug'], $form['name'], (bool)$form['is_active'], $form['permissions']);
                setFlash('success', $isEdit ? 'Role updated successfully.' : 'Role created successfully.');
                $joiner = (strpos($returnTo, '?') === false) ? '?' : '&';
                header('Location: ' . APP_URL . $returnTo . $joiner . 'role_slug=' . urlencode($form['slug']) . '&role_created=1');
                exit;
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
}

$permissions = getPermissionsWithSelection($isEdit ? $originalSlug : '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedMap = array_flip($form['permissions']);
    foreach ($permissions as &$perm) {
        $perm['selected'] = isset($selectedMap[$perm['slug']]);
    }
}

$grouped = [];
foreach ($permissions as $perm) {
    $grouped[$perm['group_name']][] = $perm;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= $isEdit ? 'Edit Access Role' : 'Create Access Role' ?> - <?= APP_NAME ?></title>
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
        <span class="badge rounded-pill bg-danger position-absolute <?= $unreadCount ? '' : 'd-none' ?>" id="notif-badge" style="top:-6px;right:-8px;font-size:.6rem;"><?= $unreadCount ?: '' ?></span>
      </a>
      <div class="dropdown">
        <a class="staff-user-toggle dropdown-toggle" href="#" data-bs-toggle="dropdown">
          <i class="bi bi-person-circle me-1"></i>
          <span class="d-none d-md-inline"><?= h($_SESSION['staff_name'] ?? 'Staff') ?></span>
        </a>
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
    <a class="nav-link" href="<?= APP_URL ?>/staff/tickets"><i class="bi bi-ticket-perforated"></i>Work Queue</a>
    <a class="nav-link" href="<?= APP_URL ?>/staff/notifications"><i class="bi bi-bell"></i>Alerts<?php if ($unreadCount): ?><span class="badge bg-danger ms-auto"><?= $unreadCount ?></span><?php endif; ?></a>
    <?php if (currentStaffHasPermission('reports.view')): ?><a class="nav-link" href="<?= APP_URL ?>/staff/reports"><i class="bi bi-bar-chart-line"></i>Insights Lab</a><?php endif; ?>
    <div class="sidebar-section">Admin Studio</div>
    <?php if (currentStaffHasPermission('staff.manage')): ?><a class="nav-link" href="<?= APP_URL ?>/staff/manage_staff"><i class="bi bi-person-badge"></i>Team Hub</a><?php endif; ?>
    <?php if (currentStaffHasPermission('users.manage')): ?><a class="nav-link" href="<?= APP_URL ?>/staff/manage_users"><i class="bi bi-people"></i>User Directory</a><?php endif; ?>
    <a class="nav-link active" href="<?= APP_URL ?>/staff/roles"><i class="bi bi-diagram-3"></i>Access Studio</a>
    <div class="sidebar-section">Account</div>
    <a class="nav-link" href="<?= APP_URL ?>/staff/profile"><i class="bi bi-person-gear"></i>Profile</a>
    <a class="nav-link" href="<?= APP_URL ?>/auth/logout"><i class="bi bi-box-arrow-right"></i>Logout</a>
  </nav>
</div>

<div class="main-content">
  <div class="staff-headline">
    <div>
      <h3><i class="bi bi-sliders me-2"></i><?= $isEdit ? 'Edit Access Role' : 'Create Access Role' ?></h3>
      <p>Tune privilege sets and activation state while staying in the staff workspace.</p>
    </div>
    <span class="pill"><?= $isEdit ? 'Edit Mode' : 'New Role' ?></span>
  </div>

  <div class="page-title-bar">
    <h4><i class="bi bi-shield-lock me-2"></i><?= $isEdit ? 'Edit Access Profile' : 'Create New Access Profile' ?></h4>
    <a href="<?= APP_URL ?>/staff/roles" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Access Studio</a>
  </div>

  <?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="card border-0 shadow-sm">
    <div class="card-body p-4">
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h(generateCSRFToken()) ?>">
        <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Access Role Name *</label>
            <input type="text" name="name" class="form-control" value="<?= h($form['name']) ?>" maxlength="100" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Access Role Slug *</label>
            <input type="text" name="slug" class="form-control" value="<?= h($form['slug']) ?>" maxlength="64" required>
            <small class="text-muted">Example: campus_support_lead</small>
          </div>
          <div class="col-12">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="is_active" name="is_active" <?= $form['is_active'] ? 'checked' : '' ?> <?= $form['slug'] === 'admin' ? 'disabled' : '' ?>>
              <label class="form-check-label" for="is_active">Access role is active</label>
            </div>
          </div>
        </div>

        <hr class="my-4">
        <h6 class="fw-bold mb-3">Privilege Set</h6>

        <?php foreach ($grouped as $group => $perms): ?>
        <div class="mb-3">
          <div class="text-uppercase text-secondary small fw-semibold mb-2"><?= h($group) ?></div>
          <div class="row g-2">
            <?php foreach ($perms as $perm): ?>
            <div class="col-md-6">
              <label class="form-check border rounded p-2 bg-light-subtle">
                <input class="form-check-input me-2" type="checkbox" name="permissions[]" value="<?= h($perm['slug']) ?>" <?= !empty($perm['selected']) ? 'checked' : '' ?>>
                <span class="form-check-label">
                  <span class="fw-semibold"><?= h($perm['name']) ?></span><br>
                  <small class="text-muted"><?= h($perm['description']) ?></small>
                </span>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>

        <div class="mt-4 pt-3 border-top text-end">
          <button type="submit" class="btn btn-svcet px-4"><i class="bi bi-save me-1"></i><?= $isEdit ? 'Update Access Role' : 'Create Access Role' ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
