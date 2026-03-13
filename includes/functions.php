<?php
// ============================================================
// Shared Utility Functions
// ============================================================
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/auth.php';

/**
 * XSS-safe output helper. Use <?= h($var) ?> everywhere in templates.
 */
function h(?string $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect with optional flash message.
 */
function redirect(string $url, string $flashType = '', string $flashMsg = ''): void {
    if ($flashType && $flashMsg) {
        setFlash($flashType, $flashMsg);
    }
    header('Location: ' . $url);
    exit;
}

/**
 * Map ticket status to Bootstrap badge class.
 */
function statusBadge(string $status): string {
    $map = [
        'notified'   => 'bg-info text-dark',
        'processing' => 'bg-warning text-dark',
        'solving'    => 'bg-primary',
        'solved'     => 'bg-success',
    ];
    return $map[$status] ?? 'bg-secondary';
}

/**
 * Map ticket status to human-readable label.
 */
function statusLabel(string $status): string {
    $map = [
        'notified'   => 'Notified',
        'processing' => 'Processing',
        'solving'    => 'Solving',
        'solved'     => 'Solved',
    ];
    return $map[$status] ?? ucfirst($status);
}

/**
 * Map ticket status to Bootstrap icon class.
 */
function statusIcon(string $status): string {
    $map = [
        'notified'   => 'bi-bell-fill',
        'processing' => 'bi-gear-fill',
        'solving'    => 'bi-tools',
        'solved'     => 'bi-check-circle-fill',
    ];
    return $map[$status] ?? 'bi-circle';
}

/**
 * Map priority to Bootstrap badge class.
 */
function priorityBadge(string $priority): string {
    $map = [
        'low'    => 'bg-success',
        'medium' => 'bg-warning text-dark',
        'high'   => 'bg-danger',
    ];
    return $map[$priority] ?? 'bg-secondary';
}

/**
 * Map role to readable label.
 */
function roleLabel(string $role): string {
    $map = [
        'ict_head'          => 'ICT Head',
        'assistant_manager' => 'Assistant Manager',
        'sr_it_executive'   => 'Sr. IT Executive',
    ];
    return $map[$role] ?? ucwords(str_replace('_', ' ', $role));
}

/**
 * Format a datetime string to a readable format.
 */
function formatDate(string $datetime, string $format = 'd M Y, h:i A'): string {
    if (!$datetime) return '—';
    return date($format, strtotime($datetime));
}

/**
 * Time elapsed string (e.g. "2 hours ago").
 */
function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)         return 'Just now';
    if ($diff < 3600)       return floor($diff / 60) . ' min ago';
    if ($diff < 86400)      return floor($diff / 3600) . ' hr ago';
    if ($diff < 604800)     return floor($diff / 86400) . ' day' . (floor($diff/86400)>1?'s':'') . ' ago';
    return formatDate($datetime, 'd M Y');
}

/**
 * Calculate resolution time in a readable format.
 */
function resolutionTime(?string $createdAt, ?string $solvedAt): string {
    if (!$createdAt || !$solvedAt) return 'Pending';
    $diff = strtotime($solvedAt) - strtotime($createdAt);
    if ($diff < 3600) return floor($diff / 60) . ' min';
    if ($diff < 86400) return round($diff / 3600, 1) . ' hrs';
    return round($diff / 86400, 1) . ' days';
}

/**
 * Pagination helper - returns array of pagination data.
 */
function paginate(int $totalRows, int $perPage = 15, string $pageParam = 'page'): array {
    $currentPage = max(1, (int)($_GET[$pageParam] ?? 1));
    $totalPages  = (int)ceil($totalRows / $perPage);
    $offset      = ($currentPage - 1) * $perPage;
    return [
        'current'    => $currentPage,
        'total'      => $totalPages,
        'per_page'   => $perPage,
        'offset'     => $offset,
        'total_rows' => $totalRows,
    ];
}

/**
 * Render Bootstrap pagination HTML.
 */
function renderPagination(array $pg, string $baseUrl = ''): string {
    if ($pg['total'] <= 1) return '';
    $base = $baseUrl ?: strtok($_SERVER['REQUEST_URI'], '?');
    $params = $_GET;
    $html = '<nav><ul class="pagination pagination-sm justify-content-center mb-0">';
    // Previous
    if ($pg['current'] > 1) {
        $params['page'] = $pg['current'] - 1;
        $html .= '<li class="page-item"><a class="page-link" href="' . h($base . '?' . http_build_query($params)) . '">«</a></li>';
    }
    // Pages
    for ($i = max(1, $pg['current'] - 2); $i <= min($pg['total'], $pg['current'] + 2); $i++) {
        $active = $i === $pg['current'] ? ' active' : '';
        $params['page'] = $i;
        $html .= "<li class=\"page-item{$active}\"><a class=\"page-link\" href=\"" . h($base . '?' . http_build_query($params)) . "\">{$i}</a></li>";
    }
    // Next
    if ($pg['current'] < $pg['total']) {
        $params['page'] = $pg['current'] + 1;
        $html .= '<li class="page-item"><a class="page-link" href="' . h($base . '?' . http_build_query($params)) . '">»</a></li>';
    }
    $html .= '</ul></nav>';
    return $html;
}

/**
 * Validate phone number (10-digit Indian mobile).
 */
function isValidPhone(string $phone): bool {
    $phone = preg_replace('/^(\+91|0)/', '', trim($phone));
    return strlen($phone) === 10 && ctype_digit($phone);
}

/**
 * Validate password complexity.
 * Min 8 chars, 1 uppercase, 1 digit, 1 special character.
 */
function isValidPassword(string $password): bool {
    return strlen($password) >= PASSWORD_MIN_LENGTH
        && preg_match('/[A-Z]/', $password)
        && preg_match('/\d/', $password)
        && preg_match('/[\W_]/', $password);
}

/**
 * Generate a cryptographically secure 6-digit OTP string.
 */
function generateOTP(): string {
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Hash OTP for DB storage.
 */
function hashOTP(string $otp): string {
    return hash('sha256', $otp);
}

/**
 * Star rating HTML (read-only display).
 */
function renderStars(int $rating): string {
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $html .= $i <= $rating
            ? '<i class="bi bi-star-fill text-warning"></i>'
            : '<i class="bi bi-star text-muted"></i>';
    }
    return $html;
}

/**
 * Bootstrap role badge HTML.
 */
function roleBadge(string $role): string {
    $colors = [
        'ict_head'          => 'bg-danger',
        'assistant_manager' => 'bg-warning text-dark',
        'sr_it_executive'   => 'bg-primary',
    ];
    $cls = $colors[$role] ?? 'bg-secondary';
    return '<span class="badge ' . $cls . '">' . h(roleLabel($role)) . '</span>';
}
