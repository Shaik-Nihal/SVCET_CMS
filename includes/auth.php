<?php
// ============================================================
// Auth, Session, CSRF, Flash, Role Guards
// ============================================================

require_once __DIR__ . '/../config/constants.php';

// ── Session Bootstrap ──────────────────────────────────────
function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.gc_maxlifetime', (string) SESSION_IDLE_TIMEOUT);
        session_start();
    }
}

// ── Flash Messages ─────────────────────────────────────────
function setFlash(string $type, string $message): void {
    startSecureSession();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function renderFlash(): void {
    $flash = getFlash();
    if (!$flash) return;
    $type  = htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8');
    $msg   = htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');
    $map   = ['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'];
    $cls   = $map[$flash['type']] ?? 'secondary';
    echo "<div class=\"alert alert-{$cls} alert-dismissible fade show\" role=\"alert\">"
       . "<i class=\"bi bi-info-circle-fill me-2\"></i>{$msg}"
       . "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\"></button>"
       . "</div>";
}

// ── CSRF ───────────────────────────────────────────────────
function generateCSRFToken(): string {
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken(string $token): bool {
    startSecureSession();
    $stored = $_SESSION['csrf_token'] ?? '';
    return $stored !== '' && hash_equals($stored, $token);
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') . '">';
}

// ── Session Checks ─────────────────────────────────────────
function checkSessionExpiry(): void {
    $now = time();
    // Idle timeout
    if (isset($_SESSION['last_activity']) && ($now - $_SESSION['last_activity']) > SESSION_IDLE_TIMEOUT) {
        destroySession();
        header('Location: ' . APP_URL . '/auth/login.php?timeout=1');
        exit;
    }
    // Absolute timeout
    if (isset($_SESSION['session_created']) && ($now - $_SESSION['session_created']) > SESSION_ABS_TIMEOUT) {
        destroySession();
        header('Location: ' . APP_URL . '/auth/login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = $now;
}

function destroySession(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ── Login Guards ───────────────────────────────────────────

/**
 * Require any logged-in user (user or staff).
 */
function requireLogin(): void {
    startSecureSession();
    checkSessionExpiry();
    if (empty($_SESSION['user_type'])) {
        setFlash('warning', 'Please log in to continue.');
        header('Location: ' . APP_URL . '/auth/login.php');
        exit;
    }
}

/**
 * Require a logged-in student/faculty user.
 */
function requireUser(): void {
    requireLogin();
    if ($_SESSION['user_type'] !== 'user') {
        if ($_SESSION['user_type'] === 'admin') {
            header('Location: ' . APP_URL . '/admin/dashboard.php');
        } else {
            header('Location: ' . APP_URL . '/staff/dashboard.php');
        }
        exit;
    }
}

/**
 * Require a logged-in IT staff member.
 */
function requireStaff(): void {
    requireLogin();
    if ($_SESSION['user_type'] !== 'staff') {
        if ($_SESSION['user_type'] === 'admin') {
            header('Location: ' . APP_URL . '/admin/dashboard.php');
        } else {
            header('Location: ' . APP_URL . '/user/dashboard.php');
        }
        exit;
    }
}

/**
 * Require specific staff role(s).
 * @param string|string[] $roles
 */
function requireRole($roles): void {
    requireStaff();
    $roles = (array) $roles;
    if (!in_array($_SESSION['staff_role'], $roles, true)) {
        setFlash('error', 'You do not have permission to access that page.');
        header('Location: ' . APP_URL . '/staff/dashboard.php');
        exit;
    }
}

/**
 * Require a logged-in admin.
 */
function requireAdmin(): void {
    requireLogin();
    if ($_SESSION['user_type'] !== 'admin') {
        setFlash('error', 'Admin access required.');
        header('Location: ' . APP_URL . '/auth/login.php');
        exit;
    }
}

// ── Login Brute-Force ──────────────────────────────────────
function checkLoginLock(): bool {
    if (!empty($_SESSION['login_locked_until'])) {
        if (time() < $_SESSION['login_locked_until']) {
            return true; // still locked
        }
        unset($_SESSION['login_locked_until'], $_SESSION['login_failures']);
    }
    return false;
}

function recordLoginFailure(): void {
    $_SESSION['login_failures'] = ($_SESSION['login_failures'] ?? 0) + 1;
    if ($_SESSION['login_failures'] >= LOGIN_MAX_FAILURES) {
        $_SESSION['login_locked_until'] = time() + LOGIN_LOCKOUT_SECS;
    }
}

function resetLoginFailures(): void {
    unset($_SESSION['login_failures'], $_SESSION['login_locked_until']);
}

// ── Redirect if already logged in ─────────────────────────
function redirectIfLoggedIn(): void {
    startSecureSession();
    if (!empty($_SESSION['user_type'])) {
        if ($_SESSION['user_type'] === 'admin') {
            header('Location: ' . APP_URL . '/admin/dashboard.php');
        } elseif ($_SESSION['user_type'] === 'staff') {
            header('Location: ' . APP_URL . '/staff/dashboard.php');
        } else {
            header('Location: ' . APP_URL . '/user/dashboard.php');
        }
        exit;
    }
}

// ── Helper: current user/staff ID from session ─────────────
function currentUserId(): int {
    return (int) ($_SESSION['user_id'] ?? 0);
}

function currentStaffId(): int {
    return (int) ($_SESSION['staff_id'] ?? 0);
}

function currentRole(): string {
    return $_SESSION['staff_role'] ?? '';
}
