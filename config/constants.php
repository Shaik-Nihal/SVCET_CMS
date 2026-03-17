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
define('ORG_NAME', 'College Name');
define('APP_NAME', ORG_NAME . ' IT Support');
define('APP_SHORT', 'College TMS');
// Auto-detect project folder (e.g. /SVCET) to avoid hardcoded URL mismatches.
$appFolder = basename(dirname(__DIR__));
define('APP_URL', getenv('APP_URL') ?: ('http://localhost/' . $appFolder)); // No trailing slash
define('APP_TIMEZONE', 'Asia/Kolkata');
define('SUPPORT_PORTAL_NAME', ORG_NAME . ' IT Support Portal');
define('PRIMARY_DOMAIN', 'collegename.edu.in');
define('SUPPORT_PHONE', '9876543210');
define('SUPPORT_EMAIL', 'ict@' . PRIMARY_DOMAIN);
define('SUPPORT_HOURS', 'Mon–Sat, 9 AM – 6 PM');

// Keep all PHP date/time operations in one timezone for consistent "x min ago" values.
date_default_timezone_set(APP_TIMEZONE);

define('EMAIL_DOMAINS', [PRIMARY_DOMAIN]); // Allowed registration domains
define('EMAIL_DOMAIN', PRIMARY_DOMAIN); // Primary domain (kept for backward compat)

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
define('TICKET_PREFIX', 'CLG');

// Database
define('DB_NAME', 'tms_college');

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
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: 'tms@' . PRIMARY_DOMAIN);
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: '');
define('MAIL_FROM', getenv('MAIL_FROM') ?: MAIL_USERNAME);
define('MAIL_FROM_NAME', APP_NAME);

// Branding assets
define('APP_LOGO_FILE', 'sample_logo.svg');
define('APP_LOGO_URL', APP_URL . '/assets/images/' . APP_LOGO_FILE);
define('APP_LOGO_ALT', ORG_NAME . ' Logo');
define('APP_BACKGROUND_FILE', 'sample_background.svg');
define('APP_BACKGROUND_URL', APP_URL . '/assets/images/' . APP_BACKGROUND_FILE);

define('APP_EMBED_EMAIL_LOGO', false);
define('APP_EMAIL_LOGO_CID', 'app_logo');

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
