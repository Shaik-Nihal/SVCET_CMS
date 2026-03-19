<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('roles.manage');

$pdo = getDB();
$originalSlug = trim((string)($_GET['slug'] ?? ''));
$isEdit = $originalSlug !== '';
$returnTo = trim((string)($_GET['return'] ?? ($_POST['return_to'] ?? '')));
if ($returnTo === '' || $returnTo[0] !== '/') {
  $returnTo = '/admin/roles';
}

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
        header('Location: ' . APP_URL . '/admin/roles');
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
<title><?= $isEdit ? 'Edit Role' : 'Create Role' ?> - <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/admin.css">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark admin-navbar fixed-top">
  <div class="container-fluid">
    <button class="btn btn-sm text-white me-2 d-lg-none" data-toggle-target="#adminSidebar">
      <i class="bi bi-list fs-4"></i>
    </button>
    <a class="navbar-brand" href="#"><i class="bi bi-shield-lock me-2"></i>SVCET Maintenance Panel</a>
    <div class="ms-auto">
      <span class="text-white me-3 d-none d-sm-inline"><i class="bi bi-person-circle me-1"></i><?= h($_SESSION['staff_name'] ?? 'Admin') ?></span>
      <a href="<?= APP_URL ?>/auth/logout" class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
    </div>
  </div>
</nav>

<div class="admin-sidebar" id="adminSidebar">
  <div class="p-3 text-uppercase text-secondary small fw-bold mt-2">Core System</div>
  <nav class="nav flex-column">
    <a class="nav-link" href="<?= APP_URL ?>/admin/dashboard"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/staff"><i class="bi bi-person-badge"></i> IT Staff Management</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/users"><i class="bi bi-people"></i> User Management</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/reports"><i class="bi bi-bar-chart-line-fill"></i> System Reports</a>
    <a class="nav-link active" href="<?= APP_URL ?>/admin/roles"><i class="bi bi-diagram-3"></i> Roles & Permissions</a>
  </nav>
  <div class="p-3 text-uppercase text-secondary small fw-bold">Account</div>
  <nav class="nav flex-column">
    <a class="nav-link" href="<?= APP_URL ?>/admin/profile"><i class="bi bi-person-gear"></i> My Profile</a>
  </nav>
</div>

<div class="admin-main">
  <div class="d-flex align-items-center mb-4">
    <a href="<?= APP_URL ?>/admin/roles" class="btn btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i> Back</a>
    <h4 class="mb-0 fw-bold"><?= $isEdit ? 'Edit Role' : 'Create New Role' ?></h4>
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
            <label class="form-label fw-semibold">Role Name *</label>
            <input type="text" name="name" class="form-control" value="<?= h($form['name']) ?>" maxlength="100" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Role Slug *</label>
            <input type="text" name="slug" class="form-control" value="<?= h($form['slug']) ?>" maxlength="64" required>
            <small class="text-muted">Example: campus_support_lead</small>
          </div>
          <div class="col-12">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="is_active" name="is_active" <?= $form['is_active'] ? 'checked' : '' ?> <?= $form['slug'] === 'admin' ? 'disabled' : '' ?>>
              <label class="form-check-label" for="is_active">Role is active</label>
            </div>
          </div>
        </div>

        <hr class="my-4">
        <h6 class="fw-bold mb-3">Permissions</h6>

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
          <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i><?= $isEdit ? 'Update Role' : 'Create Role' ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<script nonce="<?= cspNonce() ?>" src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script nonce="<?= cspNonce() ?>" src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
