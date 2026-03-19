<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireStaff();
requirePermission('staff.manage');

$pdo = getDB();
$staffId = currentStaffId();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$staff = [
    'name' => '', 'email' => '', 'password' => '', 'role' => '', 'designation' => '', 'contact' => ''
];
$isEdit = false;
$roles = getAllRoles(false);
$returnToStaffForm = $id > 0 ? ('/staff/staff_form?id=' . $id) : '/staff/staff_form';
$createRoleUrl = APP_URL . '/staff/role_form?return=' . urlencode($returnToStaffForm);

$roleSlugFromQuery = strtolower(trim((string)($_GET['role_slug'] ?? '')));
$roleWasCreated = isset($_GET['role_created']) && $_GET['role_created'] === '1';

if ($roleWasCreated && $roleSlugFromQuery !== '') {
  setFlash('success', 'Custom role created. You can now assign it to this staff member.');
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE recipient_id=? AND recipient_type='staff' AND is_read=0");
$stmt->execute([$staffId]);
$unreadCount = (int)$stmt->fetchColumn();

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM it_staff WHERE id = ?");
    $stmt->execute([$id]);
    $fetched = $stmt->fetch();
    if ($fetched) {
        $staff = $fetched;
        $isEdit = true;
    } else {
        setFlash('error', 'Staff member not found.');
        header('Location: ' . APP_URL . '/staff/manage_staff');
        exit;
    }
}

if (!$isEdit && $roleSlugFromQuery !== '' && roleExists($roleSlugFromQuery, false)) {
  $staff['role'] = $roleSlugFromQuery;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid request token.";
    } else {
        $staff['name']        = trim($_POST['name'] ?? '');
        $staff['email']       = trim(strtolower($_POST['email'] ?? ''));
        $staff['role']        = trim($_POST['role'] ?? '');
        $staff['designation'] = trim($_POST['designation'] ?? '');
        $staff['contact']     = trim($_POST['contact'] ?? '');
        $pass                 = trim($_POST['password'] ?? '');

        if (!$staff['name']) $errors[] = "Name is required.";
        if (!filter_var($staff['email'], FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email required.";
        if (!$staff['role']) $errors[] = "Role is required.";
        if ($staff['role'] === ROLE_ADMIN) $errors[] = "Admin role is reserved for owner environment access and cannot be assigned here.";
        if ($staff['role'] && !roleExists($staff['role'], !$isEdit)) $errors[] = "Selected role is not available.";
        
        // Ensure email uniqueness
        $stmt = $pdo->prepare("SELECT id FROM it_staff WHERE email = ? AND id != ?");
        $stmt->execute([$staff['email'], $id]);
        if ($stmt->fetch()) $errors[] = "Email is already registered by another staff member.";

        if (!$isEdit && !$pass) $errors[] = "Password is required for new staff.";
        if ($pass && !isValidPassword($pass)) {
            $errors[] = "Password must be at least 8 characters with uppercase, number, and special character.";
        }
        if ($staff['contact'] && !preg_match('/^[0-9]{10,15}$/', $staff['contact'])) {
            $errors[] = "Contact number must be 10-15 digits.";
        }
        
        if (empty($errors)) {
            if ($isEdit) {
                if ($pass) {
                    $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
                    $stmt = $pdo->prepare("UPDATE it_staff SET name=?, email=?, password_hash=?, role=?, designation=?, contact=? WHERE id=?");
                    $stmt->execute([$staff['name'], $staff['email'], $hash, $staff['role'], $staff['designation'], $staff['contact'], $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE it_staff SET name=?, email=?, role=?, designation=?, contact=? WHERE id=?");
                    $stmt->execute([$staff['name'], $staff['email'], $staff['role'], $staff['designation'], $staff['contact'], $id]);
                }
                setFlash('success', 'Staff details updated successfully.');
            } else {
                $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $pdo->prepare("INSERT INTO it_staff (name, email, password_hash, role, designation, contact) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$staff['name'], $staff['email'], $hash, $staff['role'], $staff['designation'], $staff['contact']]);
                
                $newId = $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO password_history (user_id, user_type, password_hash) VALUES (?, 'staff', ?)")->execute([$newId, $hash]);
                
                setFlash('success', 'New IT staff created successfully.');
            }
            header('Location: ' . APP_URL . '/staff/manage_staff');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= $isEdit ? 'Edit' : 'Add' ?> Staff — <?= APP_NAME ?></title>
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
    <a class="nav-link active" href="<?= APP_URL ?>/staff/manage_staff"><i class="bi bi-person-badge"></i>Team Hub</a>
    <?php if (currentStaffHasPermission('users.manage')): ?><a class="nav-link" href="<?= APP_URL ?>/staff/manage_users"><i class="bi bi-people"></i>User Directory</a><?php endif; ?>
    <?php if (currentStaffHasPermission('roles.manage')): ?><a class="nav-link" href="<?= APP_URL ?>/staff/roles"><i class="bi bi-diagram-3"></i>Roles & Permissions</a><?php endif; ?>
    <div class="sidebar-section">Account</div>
    <a class="nav-link" href="<?= APP_URL ?>/staff/profile"><i class="bi bi-person-gear"></i>Profile</a>
    <a class="nav-link" href="<?= APP_URL ?>/auth/logout"><i class="bi bi-box-arrow-right"></i>Logout</a>
  </nav>
</div>

<div class="main-content">
  <div class="staff-headline">
    <div>
      <h3><i class="bi bi-person-plus-fill me-2"></i><?= $isEdit ? 'Edit Team Member' : 'Invite Team Member' ?></h3>
      <p>Configure staff access, role assignment, and contact details in your staff workspace.</p>
    </div>
    <span class="pill"><?= $isEdit ? 'Edit Mode' : 'New Staff' ?></span>
  </div>

  <?php renderFlash(); ?>

  <div class="page-title-bar">
    <h4><i class="bi bi-person-badge me-2"></i><?= $isEdit ? 'Edit Staff Member' : 'Add New Staff Member' ?></h4>
    <a href="<?= APP_URL ?>/staff/manage_staff" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Team Hub</a>
  </div>

  <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
          <ul class="mb-0">
              <?php foreach ($errors as $err) echo "<li>" . h($err) . "</li>"; ?>
          </ul>
      </div>
  <?php endif; ?>

  <div class="card shadow-sm border-0">
    <div class="card-body p-4">
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h(generateCSRFToken()) ?>">
        
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Full Name *</label>
            <input type="text" name="name" class="form-control" value="<?= h($staff['name']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Email Address *</label>
            <input type="email" name="email" class="form-control" value="<?= h($staff['email']) ?>" required>
          </div>
          
          <div class="col-md-6">
            <label class="form-label fw-semibold">Designation</label>
            <input type="text" name="designation" class="form-control" value="<?= h($staff['designation']) ?>" placeholder="e.g. Assistant Director of ICT">
          </div>
          <div class="col-md-6">
            <div class="d-flex justify-content-between align-items-center">
              <label class="form-label fw-semibold mb-1">System Role *</label>
              <a href="<?= h($createRoleUrl) ?>" class="small text-decoration-none">
                <i class="bi bi-plus-circle me-1"></i>Create Custom Role
              </a>
            </div>
            <select name="role" class="form-select" required>
              <option value="">Select Role</option>
              <?php foreach ($roles as $roleOpt): ?>
              <?php if ($roleOpt['slug'] === ROLE_ADMIN) continue; ?>
              <option value="<?= h($roleOpt['slug']) ?>" <?= $staff['role'] === $roleOpt['slug'] ? 'selected' : '' ?>>
                <?= h($roleOpt['name']) ?><?= !$roleOpt['is_active'] ? ' (Inactive Role)' : '' ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="col-md-6">
            <label class="form-label fw-semibold">Contact Number</label>
            <input type="text" name="contact" class="form-control" value="<?= h($staff['contact']) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Password <?= $isEdit ? '<small class="text-muted fw-normal">(Leave blank to keep existing)</small>' : '*' ?></label>
            <input type="password" name="password" class="form-control" <?= $isEdit ? '' : 'required' ?>>
          </div>
        </div>

        <div class="mt-4 pt-3 border-top text-end">
          <button type="submit" class="btn btn-svcet px-4"><i class="bi bi-save me-1"></i> <?= $isEdit ? 'Update Details' : 'Create Staff' ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
