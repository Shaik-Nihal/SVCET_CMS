<?php
// ============================================================
// Auth, Session, CSRF, Flash, Role Guards
// ============================================================

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

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

/**
 * Rotate CSRF token after successful form processing.
 * Call this after a successful POST to prevent token reuse.
 */
function rotateCSRFToken(): void {
    startSecureSession();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
        if ($_SESSION['user_type'] === 'staff' && ($_SESSION['staff_role'] ?? '') === ROLE_ADMIN) {
            header('Location: ' . APP_URL . '/admin/dashboard.php');
        } elseif ($_SESSION['user_type'] === 'staff') {
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
        header('Location: ' . APP_URL . '/user/dashboard.php');
        exit;
    }
    // Admin is a staff member but shouldn't access regular staff pages — redirect to admin
    if (($_SESSION['staff_role'] ?? '') === ROLE_ADMIN) {
        header('Location: ' . APP_URL . '/admin/dashboard.php');
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
 * Require a logged-in admin (staff with admin role).
 */
function requireAdmin(): void {
    requireLogin();
    if ($_SESSION['user_type'] !== 'staff' || ($_SESSION['staff_role'] ?? '') !== ROLE_ADMIN) {
        setFlash('error', 'Admin access required.');
        header('Location: ' . APP_URL . '/auth/login.php');
        exit;
    }
}

// ── Redirect if already logged in ─────────────────────────
function redirectIfLoggedIn(): void {
    startSecureSession();
    if (!empty($_SESSION['user_type'])) {
        if ($_SESSION['user_type'] === 'staff') {
            if (($_SESSION['staff_role'] ?? '') === ROLE_ADMIN) {
                header('Location: ' . APP_URL . '/admin/dashboard.php');
            } else {
                header('Location: ' . APP_URL . '/staff/dashboard.php');
            }
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

// ── Client IP Helper ──────────────────────────────────────
function getClientIP(): string {
    // Check for forwarded headers (behind reverse proxy)
    $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            // X-Forwarded-For may contain comma-separated list, take the first
            $ip = trim(explode(',', $_SERVER[$header])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

// ── DB-Backed Login Brute-Force Protection ────────────────
/**
 * Check if login is locked for a given email+IP combination.
 * Returns remaining seconds if locked, 0 if not.
 */
function checkLoginLockDB(string $email): int {
    $pdo = getDB();
    $ip  = getClientIP();
    $window = LOGIN_LOCKOUT_SECS; // 5 minutes

    // Count recent failures for this IP+email within the lockout window
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM login_attempts
        WHERE ip_address = ? AND email = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
    ");
    $stmt->execute([$ip, $email, $window]);
    $count = (int) $stmt->fetchColumn();

    if ($count >= LOGIN_MAX_FAILURES) {
        // Find when the oldest attempt in the window was
        $stmt = $pdo->prepare("
            SELECT MIN(attempted_at) FROM login_attempts
            WHERE ip_address = ? AND email = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$ip, $email, $window]);
        $oldest = $stmt->fetchColumn();
        $remaining = $window - (time() - strtotime($oldest));
        return max(0, $remaining);
    }
    return 0;
}

/**
 * Record a failed login attempt in the database.
 */
function recordLoginFailureDB(string $email): void {
    $pdo = getDB();
    $ip  = getClientIP();
    $pdo->prepare("INSERT INTO login_attempts (ip_address, email) VALUES (?, ?)")->execute([$ip, $email]);
}

/**
 * Clear login failures for a given email+IP (on successful login).
 */
function clearLoginFailuresDB(string $email): void {
    $pdo = getDB();
    $ip  = getClientIP();
    $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND email = ?")->execute([$ip, $email]);
}

/**
 * Clean up old login attempts (call periodically, e.g. on login page load).
 */
function cleanupOldLoginAttempts(): void {
    $pdo = getDB();
    // Remove attempts older than 1 hour
    $pdo->exec("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
}
