<?php
// ============================================================
// Application Constants
// ============================================================

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
define('TICKET_PREFIX', 'APL');

// Roles
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
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'iamthanos0001@gmail.com'); // <<< CHANGE THIS
define('MAIL_PASSWORD', 'ncwe qwgv cwhv ocpb'); // <<< Gmail App Password
define('MAIL_FROM', 'iamthanos0001@gmail.com'); // <<< CHANGE THIS
define('MAIL_FROM_NAME', APP_NAME);

// ============================================================
// SMS (Fast2SMS)
// ============================================================
define('FAST2SMS_API_KEY', '9YXiyBk7ehNPsWDEFpM8HjG1aKUg6tmJZnul0A3TrvCx54VdfoMdB3XWn1DRxqJkjcCzrp6oa7FSH5we'); // <<< CHANGE THIS
define('FAST2SMS_URL', 'https://www.fast2sms.com/dev/bulkV2');

// ============================================================
// Paths
// ============================================================
define('BASE_PATH', dirname(__DIR__)); // n:\TMS
define('VENDOR_PATH', BASE_PATH . '/vendor');
