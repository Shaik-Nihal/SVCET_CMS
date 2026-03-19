# SVCET Complaint Management System — Complete Setup Guide (Windows)

> **Complaint Management System** for SVCET College Complaint Management  
> Tech Stack: PHP 8.x · MySQL 8.x · Bootstrap 5.3 · XAMPP (Windows)

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [Architecture & Directory Structure](#2-architecture--directory-structure)
3. [Prerequisites](#3-prerequisites)
4. [Installation (Step-by-Step)](#4-installation-step-by-step)
5. [Database Setup](#5-database-setup)
6. [Configuration](#6-configuration)
7. [Seeding Default Data](#7-seeding-default-data)
8. [Email Configuration](#8-email-configuration)
9. [User Roles & Permissions](#9-user-roles--permissions)
10. [Application Features](#10-application-features)
11. [Database Schema Reference](#11-database-schema-reference)
12. [API Endpoints](#12-api-endpoints)
13. [File-by-File Reference](#13-file-by-file-reference)
14. [Security Features](#14-security-features)
15. [Performance Optimizations](#15-performance-optimizations)
16. [Troubleshooting](#16-troubleshooting)

---

## 1. Project Overview

**SVCET Complaint Management System** is a web-based complaint portal built for SVCET College. It allows:

- **Students/Faculty** to register, raise complaints, track complaint status, and submit feedback.
- **IT Staff** (with role-based hierarchy) to receive, assign, resolve tickets, and generate reports.
- **Owner Admin** (environment-based login) to manage staff accounts, users, view system-wide dashboards, and change credentials.

### Key Capabilities

| Feature | Description |
|---|---|
| **Ticket Lifecycle** | Notified → Processing → Solving → Solved |
| **Role-Based Access** | 5 staff roles + Admin + User with permission matrix |
| **Email Notifications** | Deferred email queue (SMTP or Microsoft Graph API) — ticket creation is instant |
| **PDF/CSV Reports** | Scope-aware exports via RBAC (`Organization Reports` or `My Reports`) |
| **Real-Time Notifications** | AJAX polling (every 30s) for in-app notifications |
| **Single-Domain Support** | Allowed domains from `EMAIL_DOMAINS` (default: `@svcet.edu.org`) |
| **Security** | CSRF protection, bcrypt passwords, DB-backed brute-force lockout, HTTP security headers, CSP, session timeouts, password history |

---

## 2. Architecture & Directory Structure

```
SVCET/
├── index.php                    # Entry point — redirects to login or dashboard
├── .htaccess                    # Root security rules (directory listing, file blocking)
├── SETUP_GUIDE.md               # This file
│
├── config/                      # Configuration (protected by .htaccess)
│   ├── constants.php            # App-wide constants (roles, statuses, email, timeouts)
│   ├── database.php             # PDO singleton with persistent connections
│   ├── mailer.php               # PHPMailer + Microsoft Graph email factory
│   ├── local.env.php.example    # Template for local secrets (gitignored)
│   └── .htaccess                # "Require all denied" — blocks direct access
│
├── includes/                    # Shared PHP libraries (protected by .htaccess)
│   ├── auth.php                 # Session, CSRF, flash messages, login guards, DB brute-force
│   ├── functions.php            # Utility functions (formatting, pagination, validation)
│   ├── ticket_helpers.php       # Ticket CRUD, assignment logic, status transitions
│   ├── notification_helpers.php # Deferred email queue + notification dispatch
│   ├── security_headers.php     # HTTP security headers (CSP, X-Frame-Options, preconnect)
│   └── .htaccess                # Blocks direct access
│
├── auth/                        # Authentication pages
│   ├── login.php                # Tabbed login (Student/Faculty + IT Staff)
│   ├── register.php             # User registration (domain-validated email)
│   ├── forgot_password.php      # OTP-based password reset initiation
│   ├── verify_otp.php           # OTP verification step
│   ├── reset_password.php       # New password form (after OTP)
│   └── logout.php               # Session destruction
│
├── user/                        # Student/Faculty portal
│   ├── dashboard.php            # User home — ticket stats, recent tickets
│   ├── raise_ticket.php         # Create new support ticket (optimized — instant creation)
│   ├── my_tickets.php           # List all user's tickets with filters
│   ├── ticket_detail.php        # Detailed ticket view + status timeline
│   ├── feedback.php             # Submit feedback (1-5 stars) for solved tickets
│   ├── profile.php              # View/edit profile + change password
│   └── notifications.php        # Full notification history
│
├── staff/                       # IT Staff portal
│   ├── dashboard.php            # Staff home — workload stats, assigned tickets
│   ├── tickets.php              # Browse tickets with status/priority filters
│   ├── ticket_detail.php        # View ticket + assignment + status timeline
│   ├── assign_ticket.php        # Assign/reassign tickets to subordinates
│   ├── update_status.php        # Advance ticket status (forward transitions only)
│   ├── reports.php              # Analytics dashboard — charts, tables, date filters
│   ├── profile.php              # Staff profile + password change
│   └── notifications.php        # Staff notification history
│
├── admin/                       # System Admin panel
│   ├── dashboard.php            # Overview — total staff, users, tickets, pending
│   ├── staff.php                # IT Staff management (list + actions)
│   ├── staff_form.php           # Add/edit staff member
│   ├── staff_detail.php         # Detailed staff performance view
│   ├── staff_delete.php         # Deactivate/delete staff
│   ├── users.php                # User management (list + actions)
│   ├── user_detail.php          # Detailed user profile view
│   ├── reset_password.php       # Admin resets staff/user passwords
│   ├── reports.php              # System Reports (same as staff reports)
│   └── profile.php              # Admin profile + password change
│
├── api/                         # AJAX API endpoints (JSON responses)
│   ├── get_notifications.php    # Poll notifications (unread count + recent list)
│   ├── get_staff_list.php       # Get staff for ticket assignment dropdown
│   ├── mark_notification_read.php # Mark notification as read (POST only)
│   └── validate_email.php       # Check email domain + uniqueness during registration
│
├── reports/                     # Report generation
│   ├── generate_pdf.php         # PDF report (FPDF native or HTML print fallback)
│   └── generate_csv.php         # CSV export of ticket data
│
├── sql/                         # Database scripts (protected by .htaccess)
│   ├── schema.sql               # Full database schema (11 tables with performance indexes)
│   └── .htaccess                # Blocks direct access
│
├── admin_seed/                  # One-time database seeder (protected by .htaccess)
│   ├── seed.php                 # Seeds staff, categories, test user (DELETE AFTER USE)
│   └── .htaccess                # Extra protection
│
├── assets/                      # Frontend assets
│   ├── css/
│   │   ├── style.css            # Main application styles
│   │   ├── auth.css             # Login/register page styles
│   │   ├── dashboard.css        # Dashboard-specific styles
│   │   └── admin.css            # Admin panel styles
│   ├── js/
│   │   ├── main.js              # Password strength meter, form helpers
│   │   ├── notifications.js     # AJAX notification polling + rendering
│   │   └── ticket.js            # Ticket form interactions
│   └── images/
│       ├── college_logo.png     # SVCET logo
│       └── sample_background.svg # Login page background
│
└── vendor/                      # Third-party libraries (not via Composer)
    ├── phpmailer/               # PHPMailer library (SMTP email sending)
    │   └── src/ (Exception.php, PHPMailer.php, SMTP.php)
    ├── fpdf/                    # FPDF library (native PDF generation)
    │   └── fpdf.php
    └── .htaccess                # Blocks direct access
```

---

## 3. Prerequisites

| Requirement | Details |
|---|---|
| **XAMPP for Windows** | Version 8.x+ — download from [apachefriends.org](https://www.apachefriends.org/) |
| **PHP** | 8.0 or higher (included with XAMPP 8.x) |
| **MySQL** | 5.7+ or MariaDB 10.3+ (included with XAMPP) |
| **Web Browser** | Chrome, Firefox, or Edge (Bootstrap 5 required) |
| **Git** | Optional — for cloning the repository |

### Verify PHP Version

Open **Command Prompt** and run:

```cmd
C:\xampp\php\php.exe -v
```

Should show PHP 8.0 or higher.

### Verify Required PHP Extensions

```cmd
C:\xampp\php\php.exe -m
```

You need: `pdo_mysql`, `curl` (for Graph mail), `mbstring`, `openssl` (for PHPMailer STARTTLS). These are all included by default in XAMPP.

---

## 4. Installation (Step-by-Step)

### Step 1: Start XAMPP Services

1. Open **XAMPP Control Panel** (`C:\xampp\xampp-control.exe`)
2. Click **Start** next to **Apache**
3. Click **Start** next to **MySQL**

Both should show green indicators.

### Step 2: Clone/Copy the Project

Place the project in XAMPP's document root:

```
C:\xampp\htdocs\SVCET\
```

**If cloning from Git:**

Open Command Prompt:

```cmd
cd C:\xampp\htdocs
git clone https://github.com/Shaik-Nihal/Ticket_Management_System.git SVCET
```

**If copying manually:** Extract or copy the SVCET folder into `C:\xampp\htdocs\`.

### Step 3: Verify Apache Configuration

Ensure `mod_rewrite` is enabled (for `.htaccess` files to work):

1. Open `C:\xampp\apache\conf\httpd.conf` in a text editor
2. Find the line: `#LoadModule rewrite_module modules/mod_rewrite.so`
3. **Remove the `#`** to uncomment it (if commented)
4. Find the `<Directory>` block for `htdocs` and ensure `AllowOverride All` is set:

```apache
<Directory "C:/xampp/htdocs">
    AllowOverride All
    Require all granted
</Directory>
```

5. **Restart Apache** from the XAMPP Control Panel

### Step 4: Verify the Application Loads

Open your browser and go to: **http://localhost/SVCET/**

You should be redirected to the login page at `http://localhost/SVCET/auth/login.php`.

> ⚠️ If you see a database error, proceed to Section 5 (Database Setup) first.

---

## 5. Database Setup

### Step 1: Access MySQL

**Option A — Command Line:**

```cmd
C:\xampp\mysql\bin\mysql.exe -u root
```

**Option B — phpMyAdmin (recommended):**

Open: **http://localhost/phpmyadmin**

### Step 2: Import the Schema

This creates the `svcet_cms` database with all 11 tables + performance indexes:

**Via Command Line:**

```cmd
C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\SVCET\sql\schema.sql
```

**Via phpMyAdmin:**
1. Click the **Import** tab at the top
2. Click **Choose File** → select `C:\xampp\htdocs\SVCET\sql\schema.sql`
3. Click **Go**

### Step 3: Verify Database Creation

In phpMyAdmin or MySQL command line:

```sql
SHOW DATABASES LIKE 'svcet_cms';
USE svcet_cms;
SHOW TABLES;
```

You should see these **11 tables**:

| # | Table | Purpose |
|---|---|---|
| 1 | `users` | Students/faculty accounts |
| 2 | `it_staff` | IT staff accounts with roles (including admin) |
| 3 | `problem_categories` | Predefined issue categories (WiFi, Printer, etc.) |
| 4 | `tickets` | Support tickets |
| 5 | `ticket_assignments` | Assignment history (who assigned to whom) |
| 6 | `ticket_status_history` | Status change timeline |
| 7 | `feedback` | User feedback (1-5 star rating) |
| 8 | `password_reset_tokens` | OTP-based password reset tokens |
| 9 | `notifications` | In-app notifications |
| 10 | `password_history` | Prevents password reuse (last 5) |
| 11 | `login_attempts` | DB-backed brute-force protection |

### Step 4: Performance Indexes

No separate migration is required. `sql/schema.sql` already includes the performance indexes.

---

## 6. Configuration

### 6.1 Database Connection

**File:** `config\database.php`

```php
$host   = '127.0.0.1';   // Use IP, not 'localhost' (avoids socket issues)
$port   = 3306;
$dbName = 'svcet_cms';
$user   = 'root';
$pass   = '';             // Set your MySQL password here if applicable
```

**If your MySQL has a password:**

```php
// Comment this:
// $pass = '';

// Uncomment this:
$pass = 'your_password_here';
```

### 6.2 Application Constants

**File:** `config\constants.php`

Key settings you may want to customize:

| Constant | Default | Description |
|---|---|---|
| `APP_URL` | Auto-detected (`http://localhost/<project-folder>`) | Base URL (no trailing slash) |
| `APP_NAME` | `SVCET College Complaint Management` | Displayed in navbar & emails |
| `APP_TIMEZONE` | `Asia/Kolkata` | PHP timezone for all date/time ops |
| `EMAIL_DOMAINS` | `['svcet.edu.org']` | Allowed registration + forgot-password email domains |
| `SESSION_IDLE_TIMEOUT` | `1800` (30 min) | Session idle timeout in seconds |
| `SESSION_ABS_TIMEOUT` | `28800` (8 hrs) | Absolute session lifetime |
| `OTP_EXPIRY_MINUTES` | `15` | OTP expiry for password reset |
| `PASSWORD_MIN_LENGTH` | `8` | Minimum password length |
| `PASSWORD_HISTORY_DEPTH` | `5` | Cannot reuse last N passwords |
| `LOGIN_MAX_FAILURES` | `5` | Failed logins before lockout |
| `LOGIN_LOCKOUT_SECS` | `300` (5 min) | Lockout duration |

### 6.3 Local Secrets (Optional)

For sensitive credentials, create a local secrets file:

```cmd
copy config\local.env.php.example config\local.env.php
```

Edit `config\local.env.php` with your real credentials. This file is **gitignored** and loaded automatically by `constants.php`.

---

## 7. Seeding Default Data

The seeder creates initial IT staff accounts, problem categories, and a test user.

### Run the Seeder

Open in your browser (localhost only):

```
http://localhost/SVCET/admin_seed/seed.php
```

### What Gets Created

#### IT Staff Accounts (current seed)

| Name | Email | Role | Default Password |
|---|---|---|---|
| ICT Head | `icthead@svcet.edu.org` | ICT Head | `ChangeMe@2026!` |
| Assistant ICT | `assistantict@svcet.edu.org` | Assistant ICT | `ChangeMe@2026!` |
| Technician | `assistantit1@svcet.edu.org` | Assistant IT | `ChangeMe@2026!` |
| Technician | `assistantit2@svcet.edu.org` | Assistant IT | `ChangeMe@2026!` |
| Technician | `assistantit3@svcet.edu.org` | Assistant IT | `ChangeMe@2026!` |

#### Problem Categories (9)

WiFi Signal Weak / Slow, No Internet Connectivity, Computer/Laptop Not Working, Printer Issue, Email/Login Problem, Software Installation Required, Projector/Display Problem, Network/LAN Issue, Other.

#### Test User

| Email | Password |
|---|---|
| `test@svcet.edu.org` | `Test@2026!` |

#### Owner Admin Login

| Email | Password | How to Login |
|---|---|---|
| `ni78ha34l8@gmail.com` | `OWNER_ADMIN_PASSWORD` or `OWNER_ADMIN_PASSWORD_HASH` from `config/local.env.php` | Use the **IT Staff** tab on the login page |

> Owner admin credentials are read from environment values in `config/local.env.php` and are not stored in the `it_staff` table.

> ⚠️ **CRITICAL SECURITY:** Delete `admin_seed\seed.php` immediately after seeding!

```cmd
del C:\xampp\htdocs\SVCET\admin_seed\seed.php
```

---

## 8. Email Configuration

The system supports **two** email drivers:

### Option A: SMTP (PHPMailer)

Set in `config\constants.php` or `config\local.env.php`:

```php
putenv('MAIL_DRIVER=smtp');
putenv('MAIL_HOST=smtp.office365.com');     // or smtp.gmail.com
putenv('MAIL_PORT=587');
putenv('MAIL_USERNAME=your_email@domain.com');
putenv('MAIL_PASSWORD=your_app_password');
putenv('MAIL_FROM=your_email@domain.com');
```

**For Gmail:** Use an [App Password](https://support.google.com/accounts/answer/185833) (not your regular password).

### Option B: Microsoft Graph API (Recommended for Microsoft 365)

```php
putenv('MAIL_DRIVER=graph');
putenv('GRAPH_TENANT_ID=your-azure-tenant-id');
putenv('GRAPH_CLIENT_ID=your-app-client-id');
putenv('GRAPH_CLIENT_SECRET=your-client-secret');
putenv('GRAPH_SENDER=ni78ha34l8@gmail.com');
```

**Azure AD Setup Required:**
1. Register an app in [Azure Portal](https://portal.azure.com) → App Registrations
2. Grant `Mail.Send` application permission
3. Admin consent the permission
4. Create a client secret

### Disable Email (Development)

If you don't need emails during development, leave `MAIL_PASSWORD` empty. Emails will fail silently (logged to PHP error log) but won't break app functionality.

> **Note:** Emails are sent asynchronously via a deferred queue — ticket creation is always instant regardless of email configuration.

---

## 9. User Roles & Permissions

### Role Hierarchy

```
Owner Admin (environment-based, not stored in it_staff)
    └── ICT Head (ict_head)
        ├── Assistant ICT (assistant_ict)
        └── Assistant Manager (assistant_manager)
            └── Sr. IT Executive (sr_it_executive)
                └── Assistant IT (assistant_it)
```

### Assignment Permission Matrix

| Actor → Can Assign To ↓ | Asst Manager | Asst ICT | Sr IT Exec | Asst IT |
|---|:---:|:---:|:---:|:---:|
| **ICT Head** | ✅ | ✅ | ✅ | ❌ |
| **Assistant ICT** | ✅ | ✅ | ✅ | ❌ |
| **Assistant Manager** | ❌ | ❌ | ✅ | ✅ |
| **Sr IT Executive** | ❌ | ❌ | ❌ | ✅ |
| **Assistant IT** | ❌ | ❌ | ❌ | ❌ |

### Assignment Rules

- Single-domain setup: assignment rules are based on role hierarchy only.

### Report Permissions (RBAC)

- `Organization Reports` (`reports.view_all`): View/export organization-wide reports.
- `My Reports` (`reports.view_own`): View/export only tickets assigned to the logged-in staff member.
- `View Reports (Legacy)` (`reports.view`): Backward-compatible legacy permission, treated as organization scope.

Assign these permissions from Roles & Permissions in the staff/admin role management screens.

### Ticket Status Transitions (Forward Only)

```
notified → processing → solving → solved
```

Each transition is irreversible and logged in `ticket_status_history`.

---

## 10. Application Features

### For Users (Students/Faculty)

| Feature | URL | Description |
|---|---|---|
| Register | `/auth/register.php` | Domain-validated email registration |
| Login | `/auth/login.php` | "Student/Faculty" tab |
| Dashboard | `/user/dashboard.php` | Overview of tickets + stats |
| Raise Ticket | `/user/raise_ticket.php` | Select category, priority, staff assignee |
| My Tickets | `/user/my_tickets.php` | Filterable list of all your tickets |
| Ticket Detail | `/user/ticket_detail.php?id=X` | Full ticket timeline + assignee info |
| Feedback | `/user/feedback.php?ticket_id=X` | 1-5 star rating for solved tickets |
| Profile | `/user/profile.php` | Edit profile + change password |
| Notifications | `/user/notifications.php` | Full notification history |

### For IT Staff

| Feature | URL | Description |
|---|---|---|
| Login | `/auth/login.php` | "IT Staff" tab |
| Dashboard | `/staff/dashboard.php` | Assigned/pending ticket stats |
| All Tickets | `/staff/tickets.php` | Browse all tickets with filters |
| Ticket Detail | `/staff/ticket_detail.php?id=X` | View + assign + update status |
| Assign Ticket | `/staff/assign_ticket.php?id=X` | Delegate to subordinate |
| Reports | `/staff/reports.php` | Analytics with RBAC report scope (`Organization Reports` or `My Reports`) |
| Profile | `/staff/profile.php` | Edit profile + change password |
| Notifications | `/staff/notifications.php` | Staff notification history |

### For Owner Admin

| Feature | URL | Description |
|---|---|---|
| Login | `/auth/login.php` | Use **IT Staff** tab with `ni78ha34l8@gmail.com` |
| Dashboard | `/admin/dashboard.php` | System-wide overview |
| Staff Management | `/admin/staff.php` | Add/edit/deactivate IT staff |
| User Management | `/admin/users.php` | View/manage registered users |
| System Reports | `/admin/reports.php` | Date-ranged analytics + CSV/PDF export |
| Reset Passwords | `/admin/reset_password.php` | Admin password reset for any account |
| My Profile | `/admin/profile.php` | Admin profile + change own password |

---

## 11. Database Schema Reference

### Entity Relationship Overview

```
users ──────────┐
                 ├──→ tickets ──→ ticket_assignments
it_staff ───────┘       │     ──→ ticket_status_history
                        │     ──→ feedback
problem_categories ─────┘

notifications (polymorphic: user or staff)
password_reset_tokens (polymorphic: user or staff)
password_history (polymorphic: user or staff)
login_attempts (IP + email tracking)
```

### Key Column Details

**`tickets` Table:**
- `ticket_number` — Format: `APL-YYYYMMDD-XXXX` (auto-generated using MAX-based sequence, race-condition safe)
- `status` — ENUM: `notified`, `processing`, `solving`, `solved`
- `priority` — ENUM: `low`, `medium`, `high`
- `solved_at` — Set automatically when status changes to `solved`

**`it_staff` Table:**
- `role` — ENUM: `admin`, `ict_head`, `assistant_manager`, `assistant_ict`, `sr_it_executive`, `assistant_it`
- `is_active` — Soft delete flag (0 = deactivated)

---

## 12. API Endpoints

All API endpoints return JSON and are located in `/api/`:

| Endpoint | Method | Auth | Description |
|---|---|---|---|
| `/api/get_notifications.php?limit=N` | GET | Any logged-in | Returns unread count + recent notifications |
| `/api/mark_notification_read.php` | POST | Any logged-in | Marks a notification as read |
| `/api/get_staff_list.php` | GET | User only | Returns assignable staff list (domain-aware) |
| `/api/validate_email.php?email=X` | GET | None | Checks domain validity + uniqueness |

---

## 13. File-by-File Reference

### Config Layer

| File | Purpose |
|---|---|
| `config\constants.php` | All app constants. Loads `local.env.php` if present. Defines roles, statuses, email config. |
| `config\database.php` | `getDB()` — PDO singleton with persistent connections, `127.0.0.1` TCP connection. |
| `config\mailer.php` | `sendEmail()` — auto-routes to SMTP or Graph. `emailTemplate()` — branded HTML wrapper. |
| `config\local.env.php.example` | Template for local secrets. Copy to `local.env.php` and fill values. |

### Includes Layer

| File | Key Functions |
|---|---|
| `includes\auth.php` | `startSecureSession()`, `requireLogin()`, `requireUser()`, `requireStaff()`, `requireRole()`, `requireAdmin()`, CSRF token management + rotation, DB-backed brute-force lockout, flash messages. |
| `includes\functions.php` | `h()` (XSS-safe output), `redirect()`, `statusBadge()`, `roleLabel()`, `timeAgo()`, `paginate()`, `isValidPhone()`, `isValidPassword()`, `isAllowedEmailDomain()`, `generateOTP()`. |
| `includes\ticket_helpers.php` | `generateTicketNumber()` (MAX-based), `createTicket()` (returns id + ticket_number), `assignTicket()`, `updateTicketStatus()`, `getTicketById()`, `canAssignTo()`. |
| `includes\notification_helpers.php` | `dispatchNotification()` (DB + queued email), `queueEmail()`, `flushEmailQueue()`, `sendResponseAndFlushEmails()`, `notifyAllLeadership()`, `notifyUserTicketCreated()`, `notifyStaffAssigned()`, `notifyUserStatusChange()`. |
| `includes\security_headers.php` | HTTP security headers: CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, Cache-Control, CDN preconnect. |

---

## 14. Security Features

| Feature | Implementation |
|---|---|
| **HTTP Security Headers** | `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `X-XSS-Protection`, `Referrer-Policy`, `Permissions-Policy`, `X-Robots-Tag`, `Content-Security-Policy` — auto-applied via `security_headers.php`. |
| **Content Security Policy** | Only allows scripts/styles from `self` + `cdn.jsdelivr.net`. Blocks `frame-ancestors`, restricts `form-action`. |
| **CSRF Protection** | Token generated per session, validated on every POST, **rotated after each successful submission**. |
| **Password Hashing** | bcrypt with cost factor 12. |
| **Password History** | Last 5 passwords stored — prevents reuse. Uses prepared statements for LIMIT clause. |
| **Password Complexity** | Min 8 chars + uppercase + digit + special character. Enforced on all forms. |
| **DB-Backed Brute-Force** | 5 failed attempts → 5-minute lockout. Tracked in `login_attempts` by IP + email. |
| **Session Security** | HTTPOnly cookies, SameSite=Strict, 30-min idle timeout, 8-hr absolute timeout. |
| **XSS Prevention** | `h()` function used in all template outputs. |
| **SQL Injection** | All queries use PDO prepared statements with parameter binding (including LIMIT). |
| **Directory Protection** | Root `.htaccess` disables listing. Subdirectory `.htaccess` blocks `config/`, `includes/`, `sql/`, `vendor/`, `admin_seed/`. |
| **Sensitive File Blocking** | `.htaccess` blocks access to `.sql`, `.md`, `.log`, `.env`, `.bak`, `.git` files. |
| **Error Disclosure** | Database errors are logged server-side, never shown to users. |
| **API Security** | State-mutating endpoints use POST only. |
| **HSTS Ready** | Uncomment in `security_headers.php` when running on HTTPS. |

---

## 15. Performance Optimizations

| Optimization | Impact | Details |
|---|---|---|
| **Deferred Email Queue** | Ticket creation: 5-20s → <1s | Emails queued in memory, sent after response close via `sendResponseAndFlushEmails()` |
| **Persistent DB Connections** | ~10-20ms saved per request | `PDO::ATTR_PERSISTENT => true` reuses TCP connections |
| **Composite DB Indexes** | Faster dashboards & notifications | `idx_user_status`, `idx_assigned_status`, `idx_recipient_unread`, `idx_feedback_user` |
| **Ticket Number (MAX-based)** | Race-condition safe | Uses `MAX(ticket_number)` + retry loop instead of `COUNT(*)` |
| **Reduced DB Queries** | 4 fewer queries per ticket | `createTicket()` returns ticket_number, user data passed to notification functions |
| **CDN Preconnect** | ~100-200ms faster first load | `Link: <cdn.jsdelivr.net>; rel=preconnect` header on every page |

---

## 16. Troubleshooting

### Database Connection Error

```
Database Error: Could not connect to the database.
```

**Fix:**
1. Ensure MySQL is running in XAMPP Control Panel (green indicator)
2. Verify database exists: `SHOW DATABASES LIKE 'svcet_cms';`
3. Check credentials in `config\database.php` (password, port)
4. Ensure using `127.0.0.1` not `localhost` (avoids socket path issues on Windows)

### Blank Page / 500 Error

**Fix:**
1. Check PHP error log: `C:\xampp\php\logs\php_error_log`
2. Enable error display temporarily — add to `index.php`:
   ```php
   ini_set('display_errors', 1);
   error_reporting(E_ALL);
   ```
3. Verify PHP 8.0+ is installed (`str_ends_with()` requires PHP 8.0)

### .htaccess Not Working

**Fix:**
1. Open `C:\xampp\apache\conf\httpd.conf`
2. Uncomment: `LoadModule rewrite_module modules/mod_rewrite.so`
3. Set `AllowOverride All` in the `<Directory>` block for `htdocs`
4. **Restart Apache** from XAMPP Control Panel

### Emails Not Sending

**Fix:**
1. Check PHP error log for mailer errors
2. For SMTP: Verify credentials; for Gmail use App Passwords
3. For Graph: Verify tenant/client IDs, ensure `Mail.Send` permission is admin-consented
4. Verify `curl` extension is enabled in `C:\xampp\php\php.ini` (uncomment `extension=curl`)
5. Email failures are **non-blocking** — tickets still get created instantly

### Forgot Password OTP Not Received

1. Confirm the email is under allowed domains in `config\constants.php` (`EMAIL_DOMAINS`).
2. Forgot password now validates domain first; non-allowed domains are rejected.
3. OTP success message is shown only when email delivery succeeds.
4. If delivery fails, verify SMTP/Graph settings in `config\local.env.php`.
5. Check `php_error_log` for `Mailer error` / Graph token/send errors.

### Session Expiring Too Quickly

**Fix:** Adjust timeouts in `config\constants.php`:
```php
define('SESSION_IDLE_TIMEOUT', 3600);  // 60 minutes
define('SESSION_ABS_TIMEOUT', 43200);  // 12 hours
```

### Login Locked Out

After 5 failed attempts, wait 5 minutes. The lockout is **database-backed** and cannot be bypassed by clearing cookies.

To manually clear (admin only):
```sql
DELETE FROM login_attempts WHERE email = 'user@example.com';
```

### Port Conflicts (Apache Won't Start)

**Fix:**
1. Open XAMPP Control Panel → Click **Config** next to Apache → `httpd.conf`
2. Change `Listen 80` to `Listen 8080` (or another free port)
3. Also change `ServerName localhost:80` to `ServerName localhost:8080`
4. Update `APP_URL` in `config\constants.php` to `http://localhost:8080/SVCET`
5. Restart Apache

### MySQL Won't Start (Port 3306 in Use)

**Fix:**
1. Open XAMPP Control Panel → Click **Config** next to MySQL → `my.ini`
2. Change `port=3306` to `port=3307`
3. Update `$port = 3307;` in `config\database.php`
4. Restart MySQL

---

## Quick Start Checklist

- [ ] XAMPP installed and running (Apache + MySQL green in Control Panel)
- [ ] Project placed in `C:\xampp\htdocs\SVCET\`
- [ ] PHP 8.0+ verified (`C:\xampp\php\php.exe -v`)
- [ ] `mod_rewrite` enabled and `AllowOverride All` set in `httpd.conf`
- [ ] Database schema imported (`sql\schema.sql`)
- [ ] Schema imported from `sql\schema.sql`
- [ ] Database credentials configured (`config\database.php`)
- [ ] Seeder run (`http://localhost/SVCET/admin_seed/seed.php`)
- [ ] `admin_seed\seed.php` **deleted** after seeding
- [ ] (Optional) Email configured (`config\local.env.php`)
- [ ] Application accessible at `http://localhost/SVCET/`
- [ ] Login tested: Owner via IT Staff tab (OWNER_ADMIN_EMAIL / configured owner password)
- [ ] Security headers verified (F12 → Network → check response headers)
- [ ] Sensitive files blocked (try `http://localhost/SVCET/sql/schema.sql` → should return 403)
- [ ] (When on HTTPS) Uncomment HSTS header in `includes\security_headers.php`

---

*Generated for SVCET Complaint Management System — Updated 19 March 2026*  
*Includes: RBAC Report Scope + Staff Route Split + Domain-Strict Forgot Password + Mail/OTP Reliability*
