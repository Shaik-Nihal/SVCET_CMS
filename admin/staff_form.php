<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pdo = getDB();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$staff = [
    'name' => '', 'email' => '', 'password' => '', 'role' => '', 'designation' => '', 'contact' => ''
];
$isEdit = false;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM it_staff WHERE id = ?");
    $stmt->execute([$id]);
    $fetched = $stmt->fetch();
    if ($fetched) {
        $staff = $fetched;
        $isEdit = true;
    } else {
        setFlash('error', 'Staff member not found.');
        header('Location: ' . APP_URL . '/admin/staff.php');
        exit;
    }
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
            header('Location: ' . APP_URL . '/admin/staff.php');
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
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/admin.css">
</head>
<body class="bg-light">

<!-- Navbar -->
<nav class="navbar navbar-dark admin-navbar fixed-top">
  <div class="container-fluid">
    <button class="btn btn-sm text-white me-2 d-lg-none" onclick="document.getElementById('adminSidebar').classList.toggle('show')">
      <i class="bi bi-list fs-4"></i>
    </button>
    <a class="navbar-brand" href="#"><i class="bi bi-shield-lock me-2"></i>TMS Admin Panel</a>
    <div class="ms-auto">
      <span class="text-white me-3 d-none d-sm-inline"><i class="bi bi-person-circle me-1"></i>System Admin</span>
      <a href="<?= APP_URL ?>/auth/logout.php" class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
    </div>
  </div>
</nav>

<!-- Sidebar -->
<div class="admin-sidebar" id="adminSidebar">
  <div class="p-3 text-uppercase text-secondary small fw-bold mt-2">Core System</div>
  <nav class="nav flex-column">
    <a class="nav-link" href="<?= APP_URL ?>/admin/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a class="nav-link active" href="<?= APP_URL ?>/admin/staff.php"><i class="bi bi-person-badge"></i> IT Staff Management</a>
    <a class="nav-link" href="<?= APP_URL ?>/admin/users.php"><i class="bi bi-people"></i> User Management</a>
  </nav>
</div>

<!-- Main Content -->
<div class="admin-main">
  <div class="d-flex align-items-center mb-4">
    <a href="<?= APP_URL ?>/admin/staff.php" class="btn btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i> Back</a>
    <h4 class="mb-0 fw-bold"><?= $isEdit ? 'Edit Staff Member' : 'Add New Staff Member' ?></h4>
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
            <label class="form-label fw-semibold">System Role *</label>
            <select name="role" class="form-select" required>
              <option value="">Select Role</option>
              <option value="ict_head" <?= $staff['role'] === 'ict_head' ? 'selected' : '' ?>>ICT Head</option>
              <option value="assistant_manager" <?= $staff['role'] === 'assistant_manager' ? 'selected' : '' ?>>Assistant Manager</option>
              <option value="assistant_ict" <?= $staff['role'] === 'assistant_ict' ? 'selected' : '' ?>>Assistant ICT</option>
              <option value="sr_it_executive" <?= $staff['role'] === 'sr_it_executive' ? 'selected' : '' ?>>Sr. IT Executive</option>
              <option value="assistant_it" <?= $staff['role'] === 'assistant_it' ? 'selected' : '' ?>>Assistant IT</option>
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
          <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i> <?= $isEdit ? 'Update Details' : 'Create Staff' ?></button>
        </div>
      </form>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
