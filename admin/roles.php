<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requirePermission('roles.manage');

$pdo = getDB();

// Keep roles aligned with current IT staff role slugs.
syncMissingRolesFromStaff();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'], $_POST['action'], $_POST['slug'])) {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        setFlash('error', 'Invalid request token.');
        header('Location: ' . APP_URL . '/admin/roles');
        exit;
    }

    $slug = trim((string)$_POST['slug']);
    $action = trim((string)$_POST['action']);

    try {
        $stmt = $pdo->prepare("SELECT id, slug, is_active FROM roles WHERE slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        $role = $stmt->fetch();

        if (!$role) {
            throw new RuntimeException('Role not found.');
        }

        if ($action === 'toggle_active') {
            if ($slug === ROLE_ADMIN) {
                throw new RuntimeException('Admin role cannot be disabled.');
            }

            $newState = ((int)$role['is_active'] === 1) ? 0 : 1;
            $pdo->prepare("UPDATE roles SET is_active = ? WHERE id = ?")
                ->execute([$newState, (int)$role['id']]);

            if ($newState === 0) {
                // Keep data consistent: staff under disabled role are also inactive.
                $pdo->prepare("UPDATE it_staff SET is_active = 0 WHERE LOWER(TRIM(role)) = LOWER(?)")
                    ->execute([$slug]);
            }

            setFlash('success', 'Role status updated.');
        }
    } catch (Throwable $e) {
        setFlash('error', $e->getMessage());
    }

    header('Location: ' . APP_URL . '/admin/roles');
    exit;
}

$roles = $pdo->query("\n    SELECT r.id, r.slug, r.name, r.is_system, r.is_active,\n           (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS permission_count,\n           (SELECT COUNT(*) FROM it_staff s WHERE LOWER(TRIM(s.role)) = LOWER(r.slug)) AS staff_count_total,\n           (SELECT COUNT(*) FROM it_staff s WHERE LOWER(TRIM(s.role)) = LOWER(r.slug) AND s.is_active = 1) AS staff_count_active\n    FROM roles r\n    ORDER BY r.is_system DESC, r.display_order ASC, r.name ASC\n")->fetchAll();

// Show only owner admin and roles that exist in IT Staff Management.
$roles = array_values(array_filter($roles, static function (array $r): bool {
    return $r['slug'] === ROLE_ADMIN || (int)$r['staff_count_total'] > 0;
}));

$staffRows = $pdo->query("\n    SELECT id, name, role, is_active\n    FROM it_staff\n    WHERE role IS NOT NULL AND TRIM(role) <> ''\n    ORDER BY is_active DESC, name ASC\n")->fetchAll();

$staffByRole = [];
foreach ($staffRows as $staffRow) {
    $roleKey = strtolower(trim((string)$staffRow['role']));
    if ($roleKey === '') {
        continue;
    }
    if (!isset($staffByRole[$roleKey])) {
        $staffByRole[$roleKey] = [];
    }
    $staffByRole[$roleKey][] = $staffRow;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Role Management - <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/admin.css">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark admin-navbar fixed-top">
  <div class="container-fluid">
    <button class="btn btn-sm text-white me-2 d-lg-none" onclick="document.getElementById('adminSidebar').classList.toggle('show')">
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
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 fw-bold">Roles & Permissions</h4>
    <a href="<?= APP_URL ?>/admin/role_form" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Create Role</a>
  </div>

  <?php renderFlash(); ?>

  <div class="admin-table-container">
    <div class="table-responsive">
      <table class="table admin-table align-middle">
        <thead>
          <tr>
            <th>Role</th>
            <th>Slug</th>
            <th>Permissions</th>
            <th>Assigned Staff</th>
            <th>Type</th>
            <th>Status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($roles as $role): ?>
          <tr>
            <td class="fw-semibold"><?= h($role['name']) ?></td>
            <td><span class="badge bg-light text-dark border"><?= h($role['slug']) ?></span></td>
            <td><?= (int)$role['permission_count'] ?></td>
            <td>
              <?php
                $roleKey = strtolower(trim((string)$role['slug']));
                $staffTotal = (int)$role['staff_count_total'];
                $staffActive = (int)$role['staff_count_active'];
                $roleStaff = $staffByRole[$roleKey] ?? [];
              ?>
              <?php if ($role['slug'] === ROLE_ADMIN && OWNER_ADMIN_EMAIL !== ''): ?>
                <span class="badge bg-primary-subtle text-primary-emphasis border">Owner (env)</span>
                <?php if ($staffTotal > 0): ?>
                  <div class="small text-muted mt-1">DB staff: <?= $staffActive ?>/<?= $staffTotal ?></div>
                <?php endif; ?>
              <?php else: ?>
                <div><?= $staffActive ?><?php if ($staffTotal !== $staffActive): ?> <span class="small text-muted">/ <?= $staffTotal ?></span><?php endif; ?></div>
                <?php if (!empty($roleStaff)): ?>
                  <div class="small text-muted mt-1" style="max-width:260px;white-space:normal;">
                    <?php foreach ($roleStaff as $idx => $member): ?>
                      <?php if ($idx > 0): ?>, <?php endif; ?>
                      <span class="<?= ((int)$member['is_active'] === 1) ? 'text-success' : 'text-danger' ?>"><?= h($member['name']) ?></span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              <?php endif; ?>
            </td>
            <td>
              <?php if ((int)$role['is_system'] === 1): ?>
                <span class="badge bg-secondary">System</span>
              <?php else: ?>
                <span class="badge bg-info text-dark">Custom</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ((int)$role['is_active'] === 1): ?>
                <span class="badge bg-success">Active</span>
              <?php else: ?>
                <span class="badge bg-danger">Inactive</span>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <a href="<?= APP_URL ?>/admin/role_form?slug=<?= urlencode($role['slug']) ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-pencil-square"></i>
              </a>
              <?php if ($role['slug'] !== ROLE_ADMIN): ?>
              <form method="post" class="d-inline" onsubmit="return confirm('Change active state for this role?')">
                <input type="hidden" name="csrf_token" value="<?= h(generateCSRFToken()) ?>">
                <input type="hidden" name="slug" value="<?= h($role['slug']) ?>">
                <input type="hidden" name="action" value="toggle_active">
                <button type="submit" class="btn btn-sm btn-outline-warning">
                  <i class="bi bi-power"></i>
                </button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($roles)): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">No staff-linked roles found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
