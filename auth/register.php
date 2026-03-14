<?php
// ============================================================
// Register - Students/Faculty only (@apollouniversity.edu.in email)
// ============================================================
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
redirectIfLoggedIn();

$errors = [];
$input  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please refresh and try again.';
    } else {
        $input['name']       = trim($_POST['name'] ?? '');
        $input['email']      = strtolower(trim($_POST['email'] ?? ''));
        $input['phone']      = trim($_POST['phone'] ?? '');
        $input['designation']= trim($_POST['designation'] ?? '');
        $input['department'] = trim($_POST['department'] ?? '');
        $input['roll_no']    = trim($_POST['roll_no'] ?? '');
        $password            = $_POST['password'] ?? '';
        $confirmPwd          = $_POST['confirm_password'] ?? '';

        // Validate
        if (strlen($input['name']) < 2)  $errors[] = 'Full name must be at least 2 characters.';
        $domainValid = false;
        foreach (EMAIL_DOMAINS as $domain) {
            if (str_ends_with($input['email'], '@' . $domain)) {
                $domainValid = true;
                break;
            }
        }
        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL) || !$domainValid) {
            $errors[] = 'Email must be a valid ' . implode(' or ', EMAIL_DOMAINS) . ' address.';
        }
        if ($input['phone'] && !isValidPhone($input['phone'])) {
            $errors[] = 'Phone number must be a 10-digit Indian mobile number.';
        }
        if (!isValidPassword($password)) {
            $errors[] = 'Password must be at least 8 characters and include uppercase, number, and special character.';
        }
        if ($password !== $confirmPwd) {
            $errors[] = 'Passwords do not match.';
        }

        if (empty($errors)) {
            $pdo = getDB();
            // Check duplicate email
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$input['email']]);
            if ($stmt->fetch()) {
                $errors[] = 'This email is already registered. Please log in.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO users (name, email, password_hash, phone, designation, department, roll_no)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$input['name'], $input['email'], $hash,
                                   $input['phone'] ?: null, $input['designation'] ?: null, $input['department'] ?: null, $input['roll_no'] ?: null]);
                    $userId = (int) $pdo->lastInsertId();

                    // Save initial password to history
                    $pdo->prepare("
                        INSERT INTO password_history (user_id, user_type, password_hash) VALUES (?, 'user', ?)
                    ")->execute([$userId, $hash]);

                    $pdo->commit();
                    setFlash('success', 'Account created successfully! Please log in.');
                    header('Location: ' . APP_URL . '/auth/login');
                    exit;
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    error_log('Registration error: ' . $e->getMessage());
                    $errors[] = 'Registration failed. Please try again.';
                }
            }
        }
    }
}
$csrf = generateCSRFToken();
$departments = ['Computer Science','Information Technology','Electronics','Mechanical','Civil','MBA','MCA','BCA','Other'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register — <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/auth.css">
</head>
<body class="auth-page">

<div class="auth-card" style="max-width:520px;">
    <div class="auth-header">
        <img src="<?= APP_URL ?>/assets/images/apollo_logo.png" alt="Apollo University Logo">
        <h1>Create Account</h1>
        <p>The Apollo University · IT Support Portal</p>
    </div>

    <div class="auth-body">
        <?php if ($errors): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="register">
            <?= csrfField() ?>

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control"
                           value="<?= h($input['name'] ?? '') ?>"
                           placeholder="e.g. Ravi Kumar" required>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">College Email <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="email" name="email" class="form-control"
                               value="<?= h($input['email'] ?? '') ?>"
                               placeholder="yourname@apollouniversity.edu.in" required id="emailField" data-domains="<?= h(json_encode(EMAIL_DOMAINS)) ?>">
                        <span class="input-group-text" id="emailCheck"></span>
                    </div>
                    <small class="text-muted">Must be an @apollouniversity.edu.in or @aimsrchittoor.edu.in email</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Phone Number</label>
                    <input type="tel" name="phone" class="form-control"
                           value="<?= h($input['phone'] ?? '') ?>" placeholder="10-digit mobile">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Roll / Emp Number</label>
                    <input type="text" name="roll_no" class="form-control"
                           value="<?= h($input['roll_no'] ?? '') ?>" placeholder="e.g. 22CS001">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Designation <span class="text-danger">*</span></label>
                    <select name="designation" class="form-select" required>
                        <option value="">— Select —</option>
                        <option value="Student" <?= ($input['designation'] ?? '') === 'Student' ? 'selected' : '' ?>>Student</option>
                        <option value="Faculty" <?= ($input['designation'] ?? '') === 'Faculty' ? 'selected' : '' ?>>Faculty</option>
                        <option value="Staff" <?= ($input['designation'] ?? '') === 'Staff' ? 'selected' : '' ?>>Staff</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Department</label>
                    <select name="department" class="form-select">
                        <option value="">— Select —</option>
                        <?php foreach ($departments as $d): ?>
                        <option value="<?= h($d) ?>" <?= ($input['department'] ?? '') === $d ? 'selected' : '' ?>><?= h($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" name="password" id="pwdInput" class="form-control"
                               placeholder="Min 8 chars, uppercase, number, special char" required>
                        <button type="button" class="btn btn-outline-secondary"
                                onclick="this.previousElementSibling.type = this.previousElementSibling.type === 'password' ? 'text' : 'password'">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="mt-1">
                        <div class="pwd-strength-bar" id="strengthBar" style="width:0;"></div>
                        <span id="strengthText" class="pwd-strength-text"></span>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                    <input type="password" name="confirm_password" id="confirmPwd" class="form-control"
                           placeholder="Re-enter password" required>
                    <small id="pwdMatch" class=""></small>
                </div>
            </div>

            <button type="submit" class="btn-login mt-4">
                <i class="bi bi-person-check me-2"></i>Create Account
            </button>
        </form>

        <div class="text-center mt-3">
            <small class="text-muted">Already have an account?
                <a href="login" class="text-primary fw-semibold">Sign in</a>
            </small>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script>
updateStrengthMeter('pwdInput', 'strengthBar', 'strengthText');

// Confirm password match
document.getElementById('confirmPwd').addEventListener('input', function() {
    const match = document.getElementById('pwdMatch');
    if (this.value === document.getElementById('pwdInput').value) {
        match.textContent = '✓ Passwords match';
        match.className = 'text-success';
    } else {
        match.textContent = '✗ Passwords do not match';
        match.className = 'text-danger';
    }
});

// Email domain validation (client-side preview)
document.getElementById('emailField').addEventListener('blur', function() {
    const check = document.getElementById('emailCheck');
    const val = this.value.toLowerCase();
    const domains = JSON.parse(this.dataset.domains || '[]');
    let isValid = false;
    
    for (let d of domains) {
        if (val.endsWith('@' + d)) {
            isValid = true;
            break;
        }
    }
    
    if (isValid) {
        check.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i>';
    } else if (val.includes('@')) {
        check.innerHTML = '<i class="bi bi-x-circle-fill text-danger"></i>';
    }
});
</script>
</body>
</html>
