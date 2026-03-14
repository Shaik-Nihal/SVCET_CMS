<?php
// ============================================================
// Application Constants
// ============================================================

// Optional local secrets bootstrap (kept out of version control).
$localSecrets = __DIR__ . '/local.env.php';
if (is_file($localSecrets)) {
	require_once $localSecrets;
}

// App Info
define('APP_NAME', 'Apollo University IT Support');
define('APP_SHORT', 'Apollo TMS');
define('APP_URL', 'http://localhost/TMS'); // No trailing slash
define('APP_TIMEZONE', 'Asia/Kolkata');

// Keep all PHP date/time operations in one timezone for consistent "x min ago" values.
date_default_timezone_set(APP_TIMEZONE);

define('EMAIL_DOMAINS', ['apollouniversity.edu.in', 'aimsr.in']); // Allowed registration domains
define('EMAIL_DOMAIN', 'apollouniversity.edu.in'); // Primary domain (kept for backward compat)
define('AIMSR_DOMAIN', 'aimsr.in'); // AIMSR domain - only Assistant Manager can assign

// Session
define('SESSION_IDLE_TIMEOUT', 1800); // 30 minutes idle
define('SESSION_ABS_TIMEOUT', 28800); // 8 hours absolute

// OTP
define('OTP_EXPIRY_MINUTES', 15);
define('OTP_MAX_ATTEMPTS', 3);
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_HISTORY_DEPTH', 5); // Cannot reuse last N passwords
define('LOGIN_MAX_FAILURES', 5);
define('LOGIN_LOCKOUT_SECS', 300); // 5 minutes

// Ticket
define('TICKET_PREFIX', 'AKC');

// Roles
define('ROLE_ADMIN', 'admin');
define('ROLE_ICT_HEAD', 'ict_head');
define('ROLE_ASST_MANAGER', 'assistant_manager');
define('ROLE_ASST_ICT', 'assistant_ict');
define('ROLE_SR_IT_EXEC', 'sr_it_executive');
define('ROLE_ASST_IT', 'assistant_it');

// Ticket Statuses
define('STATUS_NOTIFIED', 'notified');
define('STATUS_PROCESSING', 'processing');
define('STATUS_SOLVING', 'solving');
define('STATUS_SOLVED', 'solved');

// Priority
define('PRIORITY_LOW', 'low');
define('PRIORITY_MEDIUM', 'medium');
define('PRIORITY_HIGH', 'high');

// ============================================================
// Email (Gmail SMTP via PHPMailer)
// ============================================================
define('MAIL_DRIVER', getenv('MAIL_DRIVER') ?: 'smtp'); // smtp|graph
define('MAIL_HOST', getenv('MAIL_HOST') ?: 'smtp.office365.com');
define('MAIL_PORT', (int)(getenv('MAIL_PORT') ?: 587));
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: 'tms@apollouniversity.edu.in');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: '');
define('MAIL_FROM', getenv('MAIL_FROM') ?: MAIL_USERNAME);
define('MAIL_FROM_NAME', APP_NAME);

// Microsoft Graph Mail (recommended for Microsoft 365)
define('GRAPH_TENANT_ID', getenv('GRAPH_TENANT_ID') ?: '');
define('GRAPH_CLIENT_ID', getenv('GRAPH_CLIENT_ID') ?: '');
define('GRAPH_CLIENT_SECRET', getenv('GRAPH_CLIENT_SECRET') ?: '');
define('GRAPH_SENDER', getenv('GRAPH_SENDER') ?: MAIL_FROM); // mailbox UPN

// ============================================================
// Paths
// ============================================================
define('BASE_PATH', dirname(__DIR__)); // n:\TMS
define('VENDOR_PATH', BASE_PATH . '/vendor');

// ============================================================
// Security Headers (auto-applied to every page)
// ============================================================
if (php_sapi_name() !== 'cli' && !headers_sent()) {
    require_once BASE_PATH . '/includes/security_headers.php';
}
