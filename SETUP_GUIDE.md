# Apollo University TMS — Complete Setup Guide

> **Ticket Management System** for Apollo University IT Support  
> Tech Stack: PHP 8.x · MySQL 8.x · Bootstrap 5.3 · XAMPP/LAMPP

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
15. [Troubleshooting](#15-troubleshooting)

---

## 1. Project Overview

**Apollo University TMS** is a web-based IT Support Ticket Management System built for Apollo University. It allows:

- **Students/Faculty** to register, raise IT support tickets, track their status, and submit feedback.
- **IT Staff** (with role-based hierarchy) to receive, assign, resolve tickets, and generate reports.
- **System Admin** to manage staff accounts, users, and view system-wide dashboards.

### Key Capabilities

| Feature | Description |
|---|---|
| **Ticket Lifecycle** | Notified → Processing → Solving → Solved |
| **Role-Based Access** | 5 staff roles + Admin + User with permission matrix |
| **Email Notifications** | SMTP (PHPMailer) or Microsoft Graph API |
| **PDF/CSV Reports** | ICT Head can generate date-ranged reports (FPDF or browser print) |
| **Real-Time Notifications** | AJAX polling (every 30s) for in-app notifications |
| **Multi-Domain Support** | `@apollouniversity.edu.in` and `@aimsrchittoor.edu.in` email domains |
| **Security** | CSRF protection, bcrypt passwords, DB-backed brute-force lockout, HTTP security headers, CSP, session timeouts, password history |

---

## 2. Architecture & Directory Structure

```
TMS/
├── index.php                    # Entry point — redirects to login or dashboard
├── .htaccess                    # Root security rules (directory listing, file blocking)
├── SETUP_GUIDE.md               # This file
│
├── config/                      # Configuration (protected by .htaccess)
│   ├── constants.php            # App-wide constants (roles, statuses, email, timeouts)
│   ├── database.php             # PDO singleton database connection
│   ├── mailer.php               # PHPMailer + Microsoft Graph email factory
│   ├── local.env.php.example    # Template for local secrets (gitignored)
│   └── .htaccess                # "Require all denied" — blocks direct access
│
├── includes/                    # Shared PHP libraries (protected by .htaccess)
│   ├── auth.php                 # Session, CSRF, flash messages, login guards, DB brute-force
│   ├── functions.php            # Utility functions (formatting, pagination, validation)
│   ├── ticket_helpers.php       # Ticket CRUD, assignment logic, status transitions
│   ├── notification_helpers.php # Notification dispatch (DB + email) for all events
│   ├── security_headers.php     # HTTP security headers (CSP, X-Frame-Options, etc.)
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
│   ├── raise_ticket.php         # Create new support ticket
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
│   ├── staff_detail.php         # Detailed staff profile view
│   ├── staff_delete.php         # Deactivate/delete staff
│   ├── users.php                # User management (list + actions)
│   ├── user_detail.php          # Detailed user profile view
│   └── reset_password.php       # Admin resets staff/user passwords
│
├── api/                         # AJAX API endpoints (JSON responses)
│   ├── get_notifications.php    # Poll notifications (unread count + recent list)
│   ├── get_staff_list.php       # Get staff for ticket assignment dropdown
│   ├── mark_notification_read.php # Mark notification as read
│   └── validate_email.php       # Check email domain + uniqueness during registration
│
├── reports/                     # Report generation
│   ├── generate_pdf.php         # PDF report (FPDF native or HTML print fallback)
│   └── generate_csv.php         # CSV export of ticket data
│
├── sql/                         # Database scripts (protected by .htaccess)
│   ├── schema.sql               # Full database schema (11 tables)
│   ├── seed_data.sql            # Reference seed data (use seed.php instead)
│   ├── migrate_roles.sql        # One-time migration for role expansion
│   ├── migrate_security.sql     # Security hardening migration (admin role + login_attempts)
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
│   │   └── ticket.js            # Ticket form interactions (staff list loading, etc.)
│   └── images/
│       ├── apollo_logo.png      # University logo
│       └── Apollo_Background.png # Login page background
│
└── vendor/                      # Third-party libraries (not via Composer)
    ├── phpmailer/               # PHPMailer library (SMTP email sending)
    │   └── src/
    │       ├── Exception.php
    │       ├── PHPMailer.php
    │       └── SMTP.php
    ├── fpdf/                    # FPDF library (native PDF generation)
    │   └── fpdf.php
    └── .htaccess                # Blocks direct access
```

---

## 3. Prerequisites

| Requirement | Details |
|---|---|
| **XAMPP / LAMPP** | Version 8.x+ (includes Apache, MySQL, PHP) |
| **PHP** | 8.0 or higher (uses `str_ends_with()`, named arguments, union types) |
| **MySQL** | 5.7+ or MariaDB 10.3+ (with InnoDB support) |
| **Web Browser** | Modern browser (Chrome, Firefox, Edge — Bootstrap 5 required) |
| **cURL Extension** | Required if using Microsoft Graph email (`php-curl`) |

### Verify PHP Version

```bash
php -v
# Should show PHP 8.0+ 
```

### Verify Required PHP Extensions

```bash
php -m | grep -E "pdo_mysql|curl|mbstring|openssl"
```

You need: `pdo_mysql`, `curl` (for Graph mail), `mbstring`, `openssl` (for PHPMailer STARTTLS).

---

## 4. Installation (Step-by-Step)

### Step 1: Start XAMPP/LAMPP Services

```bash
# Linux (LAMPP)
sudo /opt/lampp/lampp start

# Or start individually
sudo /opt/lampp/lampp startapache
sudo /opt/lampp/lampp startmysql
```

**On Windows (XAMPP):** Open XAMPP Control Panel → Start **Apache** and **MySQL**.

### Step 2: Clone/Copy Project

Place the project folder in the web server's document root:

```bash
# Linux
/opt/lampp/htdocs/TMS/

# Windows
C:\xampp\htdocs\TMS\
```

If cloning from Git:

```bash
cd /opt/lampp/htdocs
git clone <repository-url> TMS
```

### Step 3: Set File Permissions (Linux Only)

```bash
sudo chown -R daemon:daemon /opt/lampp/htdocs/TMS
sudo chmod -R 755 /opt/lampp/htdocs/TMS
```

### Step 4: Verify Apache Configuration

Ensure `mod_rewrite` is enabled (for `.htaccess` files to work):

```bash
# Check if mod_rewrite is loaded
/opt/lampp/bin/apachectl -M | grep rewrite
```

If not enabled, edit `/opt/lampp/etc/httpd.conf`:
- Uncomment: `LoadModule rewrite_module modules/mod_rewrite.so`
- Ensure `AllowOverride All` is set for the `htdocs` directory

### Step 5: Verify the Application Loads

Open: **http://localhost/TMS/**

You should be redirected to the login page at `http://localhost/TMS/auth/login.php`.

> ⚠️ If you see a database error, proceed to Section 5 (Database Setup) first.

---

## 5. Database Setup

### Step 1: Access MySQL

```bash
# Via command line
/opt/lampp/bin/mysql -u root

# Or use phpMyAdmin
# Open: http://localhost/phpmyadmin
```

### Step 2: Import the Schema

This creates the `tms_apollo` database with all 11 tables:

```bash
/opt/lampp/bin/mysql -u root < /opt/lampp/htdocs/TMS/sql/schema.sql
```

Or in phpMyAdmin:
1. Go to **Import** tab
2. Select `/opt/lampp/htdocs/TMS/sql/schema.sql`
3. Click **Go**

### Step 3: Verify Database Creation

```sql
SHOW DATABASES LIKE 'tms_apollo';
USE tms_apollo;
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
| 11 | `login_attempts` | DB-backed brute-force protection (IP + email tracking) |

### Step 4: (If Needed) Run Security Migration

If upgrading from a previous version of TMS, apply the security migration:

```bash
/opt/lampp/bin/mysql -u root tms_apollo < /opt/lampp/htdocs/TMS/sql/migrate_security.sql
```

This adds the `admin` role to `it_staff` and creates the `login_attempts` table.

### Step 5: (If Needed) Run Role Migration

If you imported older seed data and need the expanded roles (`assistant_ict`, `assistant_it`):

```bash
/opt/lampp/bin/mysql -u root tms_apollo < /opt/lampp/htdocs/TMS/sql/migrate_roles.sql
```

---

## 6. Configuration

### 6.1 Database Connection

**File:** `config/database.php`

```php
$host   = '127.0.0.1';   // Use IP, not 'localhost' (avoids socket issues)
$port   = 3306;
$dbName = 'tms_apollo';
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

**File:** `config/constants.php`

Key settings you may want to customize:

| Constant | Default | Description |
|---|---|---|
| `APP_URL` | `http://localhost/TMS` | Base URL (no trailing slash) |
| `APP_NAME` | `Apollo University IT Support` | Displayed in navbar & emails |
| `APP_TIMEZONE` | `Asia/Kolkata` | PHP timezone for all date/time ops |
| `EMAIL_DOMAINS` | `['apollouniversity.edu.in', 'aimsrchittoor.edu.in']` | Allowed registration email domains |
| `SESSION_IDLE_TIMEOUT` | `1800` (30 min) | Session idle timeout in seconds |
| `SESSION_ABS_TIMEOUT` | `28800` (8 hrs) | Absolute session lifetime |
| `OTP_EXPIRY_MINUTES` | `15` | OTP expiry for password reset |
| `PASSWORD_MIN_LENGTH` | `8` | Minimum password length |
| `PASSWORD_HISTORY_DEPTH` | `5` | Cannot reuse last N passwords |
| `LOGIN_MAX_FAILURES` | `5` | Failed logins before lockout |
| `LOGIN_LOCKOUT_SECS` | `300` (5 min) | Lockout duration |

### 6.3 Local Secrets (Optional)

For sensitive credentials, create a local secrets file:

```bash
cp config/local.env.php.example config/local.env.php
```

Edit `config/local.env.php` with your real credentials. This file is **gitignored** and loaded automatically by `constants.php`.

---

## 7. Seeding Default Data

The seeder creates initial IT staff accounts, problem categories, and a test user.

### Run the Seeder

Open in your browser (localhost only):

```
http://localhost/TMS/admin_seed/seed.php
```

### What Gets Created

#### IT Staff Accounts (10 members, including System Admin)

| Name | Email | Role | Default Password |
|---|---|---|---|
| **System Admin** | `tms@apollouniversity.edu.in` | **Admin** | `Apollo@2026!` |
| Dr G B Hima Bindu | `dyd_ict@apollouniversity.edu.in` | ICT Head | `Apollo@2026!` |
| Dr Pakkairaha | `pakkairaha@apollouniversity.edu.in` | Assistant ICT | `Apollo@2026!` |
| Mr Ashok Kumar | `ashok.kumar@apollouniversity.edu.in` | Assistant Manager | `Apollo@2026!` |
| Mr K Prasanna | `k.prasanna@apollouniversity.edu.in` | Sr. IT Executive | `Apollo@2026!` |
| Mr K Jagadeesh | `k.jagadeesh@apollouniversity.edu.in` | Sr. IT Executive | `Apollo@2026!` |
| Mr Mohan | `mohan@apollouniversity.edu.in` | Assistant IT | `Apollo@2026!` |
| Mr Bhargav | `bhargav@apollouniversity.edu.in` | Assistant IT | `Apollo@2026!` |
| Mr Gopi | `gopi@apollouniversity.edu.in` | Assistant IT | `Apollo@2026!` |
| Mr Vijay | `vijay@apollouniversity.edu.in` | Assistant IT | `Apollo@2026!` |

#### Problem Categories (10)

WiFi Issues, No Internet, Computer/Laptop, Printer, Email/Login, Software Installation, Power/Electricity, Projector/Display, Network/LAN, Other.

#### Test User

| Email | Password |
|---|---|
| `test@apollouniversity.edu.in` | `Test@2026!` |

#### System Admin Login

| Email | Password | How to Login |
|---|---|---|
| `tms@apollouniversity.edu.in` | `Apollo@2026!` | Use the **IT Staff** tab on the login page |

> The admin account is stored in the `it_staff` database table with `role = 'admin'`.

> ⚠️ **CRITICAL SECURITY:** Delete `admin_seed/seed.php` immediately after seeding!

```bash
rm /opt/lampp/htdocs/TMS/admin_seed/seed.php
```

---

## 8. Email Configuration

The system supports **two** email drivers:

### Option A: SMTP (PHPMailer)

Set in `config/constants.php` or `config/local.env.php`:

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
putenv('GRAPH_SENDER=tms@apollouniversity.edu.in');
```

**Azure AD Setup Required:**
1. Register an app in [Azure Portal](https://portal.azure.com) → App Registrations
2. Grant `Mail.Send` application permission
3. Admin consent the permission
4. Create a client secret

### Disable Email (Development)

If you don't need emails during development, leave `MAIL_PASSWORD` empty. Emails will fail silently (logged to PHP error log) but won't break app functionality.

---

## 9. User Roles & Permissions

### Role Hierarchy

```
System Admin (admin role in it_staff table)
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

### Domain-Based Assignment Rules

- **`@apollouniversity.edu.in` tickets:** ICT Head, Asst Manager, Asst ICT, or Sr IT Executive can assign.
- **`@aimsrchittoor.edu.in` tickets:** **Only** the Assistant Manager can assign.

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
| Login | `/auth/login.php?type=staff` | "IT Staff" tab |
| Dashboard | `/staff/dashboard.php` | Assigned/pending ticket stats |
| All Tickets | `/staff/tickets.php` | Browse all tickets with filters |
| Ticket Detail | `/staff/ticket_detail.php?id=X` | View + assign + update status |
| Assign Ticket | `/staff/assign_ticket.php?id=X` | Delegate to subordinate |
| Reports | `/staff/reports.php` | Analytics (ICT Head only) |
| Profile | `/staff/profile.php` | Edit profile + change password |
| Notifications | `/staff/notifications.php` | Staff notification history |

### For System Admin

| Feature | URL | Description |
|---|---|---|
| Login | `/auth/login.php?type=staff` | Use `tms@apollouniversity.edu.in` |
| Dashboard | `/admin/dashboard.php` | System-wide overview |
| Staff Management | `/admin/staff.php` | Add/edit/deactivate IT staff |
| User Management | `/admin/users.php` | View/manage registered users |
| Reset Passwords | `/admin/reset_password.php` | Admin password reset for any account |

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
```

### Key Column Details

**`tickets` Table:**
- `ticket_number` — Format: `APL-YYYYMMDD-XXXX` (auto-generated, unique per day)
- `status` — ENUM: `notified`, `processing`, `solving`, `solved`
- `priority` — ENUM: `low`, `medium`, `high`
- `solved_at` — Set automatically when status changes to `solved`

**`it_staff` Table:**
- `role` — ENUM: `admin`, `ict_head`, `assistant_manager`, `assistant_ict`, `sr_it_executive`, `assistant_it`
- `is_active` — Soft delete flag (0 = deactivated)

**`notifications` Table:**
- `recipient_type` — ENUM: `user`, `staff` (polymorphic reference)

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
| `config/constants.php` | All app constants. Loads `local.env.php` if present. Defines roles, statuses, email config. |
| `config/database.php` | `getDB()` — PDO singleton with `127.0.0.1` TCP connection, error-handling wrapper. |
| `config/mailer.php` | `sendEmail()` — auto-routes to SMTP or Graph. `emailTemplate()` — branded HTML wrapper. |
| `config/local.env.php.example` | Template for local secrets. Copy to `local.env.php` and fill values. |

### Includes Layer

| File | Key Functions |
|---|---|
| `includes/auth.php` | `startSecureSession()`, `requireLogin()`, `requireUser()`, `requireStaff()`, `requireRole()`, `requireAdmin()`, CSRF token management + rotation, DB-backed brute-force lockout (`checkLoginLockDB()`, `recordLoginFailureDB()`, `clearLoginFailuresDB()`), `getClientIP()`, flash messages. |
| `includes/functions.php` | `h()` (XSS-safe output), `redirect()`, `statusBadge()`, `roleLabel()`, `timeAgo()`, `paginate()`, `renderPagination()`, `isValidPhone()`, `isValidPassword()`, `generateOTP()`. |
| `includes/ticket_helpers.php` | `generateTicketNumber()`, `createTicket()`, `assignTicket()`, `updateTicketStatus()`, `getTicketById()`, `canAssignTo()`, `canAssignForDomain()`. |
| `includes/notification_helpers.php` | `dispatchNotification()`, `notifyAllLeadership()`, `notifyUserTicketCreated()`, `notifyStaffAssigned()`, `notifyUserStatusChange()`, `notifyManagementStatusChange()`. |
| `includes/security_headers.php` | Sends HTTP security headers: `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`, `Referrer-Policy`, `Permissions-Policy`, `X-Robots-Tag`, `Content-Security-Policy`, `Cache-Control`. Removes `X-Powered-By`. |

### Frontend Assets

| File | Purpose |
|---|---|
| `assets/css/style.css` | Main styles — navbar, sidebar, cards, ticket cards, responsive layout. |
| `assets/css/auth.css` | Login/register page styling — centered card, background image, tabs. |
| `assets/css/dashboard.css` | Dashboard stat cards, quick-action tiles. |
| `assets/css/admin.css` | Admin panel — dark sidebar, stat cards, table styles. |
| `assets/js/main.js` | Password strength meter (`updateStrengthMeter()`), common UI helpers. |
| `assets/js/notifications.js` | AJAX polling every 30s, notification badge update, dropdown rendering. |
| `assets/js/ticket.js` | Staff list loading via AJAX, dynamic category-based form interactions. |

---

## 14. Security Features

| Feature | Implementation |
|---|---|
| **HTTP Security Headers** | `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `X-XSS-Protection`, `Referrer-Policy`, `Permissions-Policy`, `X-Robots-Tag: noindex`, `Content-Security-Policy` — auto-applied via `security_headers.php`. |
| **Content Security Policy** | Only allows scripts/styles from `self` + `cdn.jsdelivr.net`. Blocks `frame-ancestors`, restricts `form-action` to `self`. |
| **CSRF Protection** | Token generated per session, validated on every POST form, **rotated after each successful submission** to prevent reuse. |
| **Password Hashing** | bcrypt with cost factor 12 (`password_hash()` + `password_verify()`). |
| **Password History** | Last 5 passwords stored in `password_history` — prevents reuse. |
| **Password Complexity** | Min 8 chars + uppercase + digit + special character (`isValidPassword()`). Enforced on all forms including admin reset. |
| **DB-Backed Brute-Force** | After 5 failed attempts → 5-minute lockout. Tracked in `login_attempts` table by IP + email. **Cannot be bypassed** by clearing cookies. Automatically cleaned up after 1 hour. |
| **Session Security** | HTTPOnly cookies, SameSite=Strict, 30-min idle timeout, 8-hr absolute timeout. |
| **Session Regeneration** | `session_regenerate_id(true)` on every successful login. |
| **XSS Prevention** | `h()` function used in all template outputs (`htmlspecialchars`). |
| **SQL Injection** | All queries use PDO prepared statements with parameter binding (including LIMIT clauses). |
| **Directory Protection** | Root `.htaccess` disables directory listing (`Options -Indexes`). Subdirectory `.htaccess` with `Require all denied` on `config/`, `includes/`, `sql/`, `vendor/`, `admin_seed/`. |
| **Sensitive File Blocking** | Root `.htaccess` blocks direct access to `.sql`, `.md`, `.log`, `.env`, `.bak`, `.git` files and directories. |
| **Server Info Hidden** | `X-Powered-By` header removed. `ServerSignature Off` set in `.htaccess`. |
| **API Security** | State-mutating endpoints (`mark_notification_read`) use POST only. GET returns 405. |
| **Email Domain Validation** | Server-side validation — only allowed domains can register. |
| **OTP Security** | SHA-256 hashed in DB, 15-minute expiry, max 3 attempts. |
| **Error Disclosure** | Database error messages are logged (not exposed to users). |
| **Sensitive Files Gitignored** | `config/local.env.php`, `.env`, `*.log` files excluded from version control. |
| **HSTS Ready** | HSTS header prepared in `security_headers.php` — uncomment when running on HTTPS. |

---

## 15. Troubleshooting

### Database Connection Error

```
Database Error: Could not connect to the database.
```

**Fix:**
1. Ensure MySQL is running: `sudo /opt/lampp/lampp startmysql`
2. Verify database exists: `SHOW DATABASES LIKE 'tms_apollo';`
3. Check credentials in `config/database.php` (password, port)
4. Ensure using `127.0.0.1` not `localhost` (avoids socket path issues)

### Blank Page / 500 Error

**Fix:**
1. Check PHP error log: `tail -f /opt/lampp/logs/php_error_log`
2. Enable error display for debugging:
   ```php
   // Add temporarily to index.php
   ini_set('display_errors', 1);
   error_reporting(E_ALL);
   ```
3. Verify PHP 8.0+ is installed (`str_ends_with()` requires PHP 8.0)

### .htaccess Not Working

**Fix:**
1. Enable `mod_rewrite` in Apache config
2. Set `AllowOverride All` in your Apache virtual host or `httpd.conf`
3. Restart Apache: `sudo /opt/lampp/lampp restartapache`

### Emails Not Sending

**Fix:**
1. Check PHP error log for mailer errors
2. For SMTP: Verify credentials; for Gmail use App Passwords
3. For Graph: Verify tenant/client IDs, ensure `Mail.Send` permission is admin-consented
4. Verify `php-curl` extension is enabled: `php -m | grep curl`
5. Email failures are **non-blocking** — tickets still get created

### Session Expiring Too Quickly

**Fix:** Adjust timeouts in `config/constants.php`:
```php
define('SESSION_IDLE_TIMEOUT', 3600);  // 60 minutes
define('SESSION_ABS_TIMEOUT', 43200);  // 12 hours
```

### Login Locked Out

After 5 failed attempts, wait 5 minutes. The lockout is **database-backed** (tracked by IP + email) and cannot be bypassed by clearing cookies.

To manually clear a lockout (admin/developer only):
```sql
DELETE FROM login_attempts WHERE email = 'user@example.com';
```

### Security Headers Not Showing

**Fix:** Verify `mod_headers` is enabled in Apache:
```bash
/opt/lampp/bin/apachectl -M | grep headers
```
If not enabled, edit `/opt/lampp/etc/httpd.conf` and uncomment `LoadModule headers_module modules/mod_headers.so`.

### Sensitive Files Accessible (403 Not Working)

**Fix:**
1. Ensure `mod_rewrite` is enabled and `AllowOverride All` is set
2. Check that the root `.htaccess` file exists in `/opt/lampp/htdocs/TMS/`
3. Restart Apache: `sudo /opt/lampp/lampp restartapache`

---

## Quick Start Checklist

- [ ] XAMPP/LAMPP installed and running (Apache + MySQL)
- [ ] Project placed in `/opt/lampp/htdocs/TMS/` (or `C:\xampp\htdocs\TMS\`)
- [ ] PHP 8.0+ verified
- [ ] `mod_rewrite` and `AllowOverride All` confirmed in Apache config
- [ ] Database schema imported (`sql/schema.sql`)
- [ ] Security migration applied (if upgrading: `sql/migrate_security.sql`)
- [ ] Database credentials configured (`config/database.php`)
- [ ] Seeder run (`http://localhost/TMS/admin_seed/seed.php`)
- [ ] `admin_seed/seed.php` deleted after seeding
- [ ] (Optional) Email configured (`config/local.env.php`)
- [ ] Application accessible at `http://localhost/TMS/`
- [ ] Login tested: Admin via IT Staff tab (`tms@apollouniversity.edu.in` / `Apollo@2026!`)
- [ ] Security headers verified (F12 → Network → check response headers)
- [ ] Sensitive files blocked (try accessing `http://localhost/TMS/sql/schema.sql` → should return 403)
- [ ] (When on HTTPS) Uncomment HSTS header in `includes/security_headers.php`

---

*Generated for Apollo University TMS — Updated 14 March 2026 (Security Hardening Applied)*
