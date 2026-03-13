# Apollo University — Ticket Management System (TMS)
## Complete Setup & Usage Guide

---

## TABLE OF CONTENTS

1. [System Overview](#1-system-overview)
2. [System Requirements](#2-system-requirements)
3. [Project Structure](#3-project-structure)
4. [XAMPP Installation](#4-xampp-installation)
5. [Project Setup](#5-project-setup)
6. [Database Setup](#6-database-setup)
7. [PHPMailer Setup (Email)](#7-phpmailer-setup-email)
8. [FPDF Setup (PDF Reports)](#8-fpdf-setup-pdf-reports)
9. [Configuration File](#9-configuration-file)
10. [Run the Seeder](#10-run-the-seeder)
11. [Login Credentials](#11-login-credentials)
12. [How to Use Each Portal](#12-how-to-use-each-portal)
13. [Ticket Lifecycle](#13-ticket-lifecycle)
14. [Notifications](#14-notifications)
15. [Reports](#15-reports)
16. [Troubleshooting](#16-troubleshooting)
17. [Security Notes](#17-security-notes)
18. [FAQ](#18-faq)

---

## 1. SYSTEM OVERVIEW

The Apollo University Ticket Management System (TMS) is a web-based IT support portal built with:

- **Backend:** PHP 8.x
- **Database:** MySQL 8.x
- **Frontend:** Bootstrap 5.3 + Bootstrap Icons
- **Email:** PHPMailer (Gmail SMTP)
- **SMS:** Fast2SMS API
- **PDF:** FPDF Library
- **Server:** Apache (XAMPP)

### What It Does

| Feature | Description |
|---------|-------------|
| User Registration | Students/Faculty register with @apollouniversity.edu.in email |
| Ticket Raising | Raise IT support tickets with category, staff, priority |
| Ticket Tracking | Real-time status updates (Notified → Processing → Solving → Solved) |
| Staff Management | 3-tier staff roles with separate access levels |
| Notifications | Email + SMS + In-app notifications on every status change |
| Feedback | Star rating system after ticket resolution |
| Reports | Weekly/Monthly reports in CSV and PDF format |
| Password Security | OTP-based reset, bcrypt hashing, reuse prevention |

### User Roles

| Role | Access |
|------|--------|
| Student / Faculty | Raise tickets, track status, give feedback |
| Sr. IT Executive | Update ticket status (Notified → Processing → Solving → Solved) |
| Assistant Manager | Assign tickets to Sr. IT Executives |
| ICT Head | Full access, assign to anyone, download reports |

---

## 2. SYSTEM REQUIREMENTS

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| OS | Windows 10 | Windows 10/11 |
| RAM | 4 GB | 8 GB |
| Storage | 500 MB free | 2 GB free |
| XAMPP | 8.2.x | 8.2.x or newer |
| PHP | 8.0+ | 8.2+ |
| MySQL | 8.0+ | 8.0+ |
| Browser | Chrome 90+ | Chrome / Edge latest |
| Internet | Required for CDN (Bootstrap) | Broadband |

---

## 3. PROJECT STRUCTURE

```
TMS/
│
├── index.php                    Smart redirector
├── .htaccess                    Apache security rules
├── SETUP_GUIDE.md               This file
│
├── config/
│   ├── constants.php            All app settings ← EDIT THIS
│   ├── database.php             MySQL connection
│   ├── mailer.php               Email (PHPMailer)
│   └── sms.php                  SMS (Fast2SMS)
│
├── includes/
│   ├── auth.php                 Sessions, CSRF, login security
│   ├── functions.php            Helper functions
│   ├── ticket_helpers.php       Ticket logic (create, assign, update)
│   └── notification_helpers.php Email + SMS + in-app dispatch
│
├── sql/
│   ├── schema.sql               Database table definitions ← IMPORT THIS
│   └── seed_data.sql            (Ignore - do not import)
│
├── auth/
│   ├── login.php                Login page (User + Staff tabs)
│   ├── register.php             New user registration
│   ├── logout.php               Logout
│   ├── forgot_password.php      Request OTP
│   ├── verify_otp.php           Enter OTP
│   └── reset_password.php       Set new password
│
├── user/
│   ├── dashboard.php            User home page
│   ├── raise_ticket.php         Create new ticket
│   ├── my_tickets.php           View all tickets
│   ├── ticket_detail.php        View single ticket
│   ├── feedback.php             Submit star rating
│   ├── notifications.php        View notifications
│   └── profile.php              Edit profile / change password
│
├── staff/
│   ├── dashboard.php            Staff home page
│   ├── tickets.php              View tickets (role-filtered)
│   ├── ticket_detail.php        View + manage ticket
│   ├── assign_ticket.php        Ticket assignment handler
│   ├── update_status.php        Status update handler
│   ├── reports.php              Download reports (ICT Head)
│   ├── notifications.php        Staff notifications
│   └── profile.php              Staff profile / password
│
├── api/
│   ├── get_notifications.php    Live notification polling
│   ├── get_staff_list.php       Staff list for dropdown
│   ├── mark_notification_read.php  Mark notification read
│   └── validate_email.php       Email validation check
│
├── reports/
│   ├── generate_csv.php         Download CSV report
│   └── generate_pdf.php         Download PDF report
│
├── assets/
│   ├── css/style.css            Global styles
│   ├── css/auth.css             Login/register styles
│   ├── css/dashboard.css        Dashboard styles
│   ├── js/main.js               Core JavaScript
│   ├── js/ticket.js             Ticket form JavaScript
│   └── js/notifications.js      Live notification polling
│
├── admin_seed/
│   └── seed.php                 One-time setup ← RUN ONCE THEN DELETE
│
└── vendor/
    ├── phpmailer/src/           PHPMailer library ← ADD MANUALLY
    └── fpdf/fpdf.php            FPDF library ← ADD MANUALLY
```

---

## 4. XAMPP INSTALLATION

### Step 1 — Download XAMPP

Go to: https://www.apachefriends.org/download.html

Download **XAMPP for Windows** — PHP 8.2.x version.

### Step 2 — Install

- Run the installer as Administrator
- Install to: `C:\xampp\` (recommended) or any drive like `N:\xampp\`
- Select components: **Apache**, **MySQL**, **PHP**, **phpMyAdmin** (minimum)
- Complete the installation

### Step 3 — Start Services

- Open **XAMPP Control Panel** (from Start menu or install folder)
- Click **Start** next to **Apache**
- Click **Start** next to **MySQL**
- Both rows should turn green and show "Running"

### Step 4 — Test

Open browser and go to: `http://localhost/`

You should see the XAMPP welcome page.

### MySQL Won't Start? Common Fixes:

**Fix A — Port conflict (most common)**
```
Open Command Prompt as Administrator:
netstat -ano | findstr :3306

If something is using 3306:
Open services.msc → find MySQL80 or MySQL → Stop it
Then start XAMPP MySQL again
```

**Fix B — Another MySQL already running**
```
Open Task Manager → Details tab
Find mysqld.exe → End Task
Then start XAMPP MySQL
```

**Fix C — Check error log**
```
XAMPP Control Panel → Logs button (next to MySQL)
Look for [ERROR] lines
```

---

## 5. PROJECT SETUP

### Step 1 — Place the Project

Copy the entire `TMS` folder into XAMPP's web root:

- If XAMPP is on C: drive → copy to `C:\xampp\htdocs\TMS\`
- If XAMPP is on N: drive → copy to `N:\xampp\htdocs\TMS\`

### Step 2 — Verify URL

Open browser: `http://localhost/TMS/`

You should see a redirect or login page (not a 404 error).

If you get "Not Found":
- Check the folder is directly inside `htdocs/` (not `htdocs/TMS/TMS/`)
- Check Apache is running in XAMPP Control Panel

---

## 6. DATABASE SETUP

### Step 1 — Open phpMyAdmin

Go to: `http://localhost/phpmyadmin`

### Step 2 — Create Database

1. Click **New** in the left sidebar
2. Database name: `tms_apollo`
3. Collation: `utf8mb4_unicode_ci`
4. Click **Create**

### Step 3 — Import Schema

1. Click on `tms_apollo` in the left sidebar
2. Click the **Import** tab at the top
3. Click **Choose File**
4. Navigate to your TMS folder → `sql` folder → select `schema.sql`
5. Click **Go** at the bottom

Expected result: "Import has been successfully finished. 10 table(s) created."

### Step 4 — Verify Tables

Click on `tms_apollo` in the left sidebar. You should see 10 tables:

```
feedback
it_staff
notifications
password_history
password_reset_tokens
problem_categories
ticket_assignments
ticket_status_history
tickets
users
```

### IMPORTANT — Do NOT Import seed_data.sql

The `seed_data.sql` file contains placeholder (broken) password hashes.
Only `schema.sql` should be imported. Staff accounts are created by `seed.php`.

---

## 7. PHPMAILER SETUP (EMAIL)

PHPMailer sends OTP emails and ticket notification emails.

### Step 1 — Download PHPMailer

Go to: https://github.com/PHPMailer/PHPMailer/releases

Click the latest release → download `Source code (zip)`.

### Step 2 — Extract Files

Inside the zip, open the `src/` folder. You need these 3 files:
- `PHPMailer.php`
- `SMTP.php`
- `Exception.php`

### Step 3 — Place Files

Create this folder structure and paste the 3 files:
```
TMS/vendor/phpmailer/src/PHPMailer.php
TMS/vendor/phpmailer/src/SMTP.php
TMS/vendor/phpmailer/src/Exception.php
```

### Step 4 — Gmail App Password

TMS uses Gmail SMTP to send emails. You need a Gmail App Password:

1. Go to your Google Account at https://myaccount.google.com
2. Click **Security** in the left menu
3. Under "How you sign in to Google" → enable **2-Step Verification** (required)
4. After enabling 2FA, search for **App Passwords** in the search bar
5. Select App: `Mail`, Device: `Windows Computer`
6. Click **Generate**
7. Copy the 16-character password (example: `abcd efgh ijkl mnop`)

### Step 5 — Update constants.php

```php
define('MAIL_USERNAME', 'your.email@gmail.com');
define('MAIL_PASSWORD', 'abcd efgh ijkl mnop');  // App password with spaces is fine
define('MAIL_FROM',     'your.email@gmail.com');
```

---

## 8. FPDF SETUP (PDF REPORTS)

FPDF generates PDF reports for ICT Head.

### Step 1 — Download FPDF

Go to: http://www.fpdf.org

Click **Download** → download `fpdf186.zip`.

### Step 2 — Place File

Extract the zip and copy `fpdf.php` to:
```
TMS/vendor/fpdf/fpdf.php
```

### Note

If FPDF is not installed, the PDF report will fall back to a print-friendly HTML page with a "Print / Save as PDF" button. The system still works — PDF is just enhanced.

---

## 9. CONFIGURATION FILE

Open: `TMS/config/constants.php`

Update all the values marked with comments:

```php
// ── App URL ──────────────────────────────────────────────────
define('APP_URL', 'http://localhost/TMS');   // Change if deployed to a server
                                              // Example: 'http://192.168.1.10/TMS'

// ── Email (Gmail SMTP) ───────────────────────────────────────
define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_PORT',     587);
define('MAIL_USERNAME', 'your.email@gmail.com');   // Your Gmail address
define('MAIL_PASSWORD', 'xxxx xxxx xxxx xxxx');    // Gmail App Password (16 chars)
define('MAIL_FROM',     'your.email@gmail.com');   // Same Gmail address

// ── SMS (Fast2SMS) ───────────────────────────────────────────
define('FAST2SMS_API_KEY', 'your_api_key_here');   // From fast2sms.com dashboard

// ── Database ─────────────────────────────────────────────────
// (This is in config/database.php, not constants.php)
// $dbName = 'tms_apollo';   // Must match database you created in phpMyAdmin
// $pass   = '';              // Leave blank for default XAMPP MySQL
```

### Getting Fast2SMS API Key

1. Register at https://www.fast2sms.com (free account)
2. Login → Dashboard → **Dev API** tab
3. Copy your API key (long string of characters)
4. Paste into `FAST2SMS_API_KEY`

Note: Free Fast2SMS account only sends SMS to the registered mobile number.
For sending to all users, a paid plan is needed.

---

## 10. RUN THE SEEDER

The seeder creates all staff accounts, problem categories, and a test user.

### Step 1 — Open in Browser

```
http://localhost/TMS/admin_seed/seed.php
```

### Step 2 — Expected Output

```
✓ Staff added: Dr. Ramesh Kumar (ICT Head)
✓ Staff added: Ms. Priya Sharma (Assistant ICT)
✓ Staff added: Mr. Arun Verma (Assistant Manager)
✓ Staff added: Mr. Kiran Patel (Sr. IT Executive)
✓ Staff added: Mr. Suresh Reddy (Sr. IT Executive)
✓ Staff added: Ms. Deepa Nair (Sr. IT Executive)

✓ Category added: WiFi Signal Weak / Slow
✓ Category added: No Internet Connectivity
✓ Category added: Computer / Laptop Not Working
✓ Category added: Printer Issue
✓ Category added: Email / Account Login Problem
✓ Category added: Software Installation Required
✓ Category added: Power / Electricity Issue
✓ Category added: Projector / Display Problem
✓ Category added: Network / LAN Issue
✓ Category added: Other

✓ Test user created: test@apollouniversity.edu.in / Test@2026!
```

### Step 3 — DELETE seed.php Immediately

After seed runs successfully, delete the file:
```
TMS/admin_seed/seed.php
```

This file can create admin accounts — it must not remain on the server.

---

## 11. LOGIN CREDENTIALS

### After Running seed.php

| Name | Email | Password | Role |
|------|-------|----------|------|
| Dr. Ramesh Kumar | ramesh.kumar@apollouniversity.edu.in | Apollo@2026! | ICT Head |
| Ms. Priya Sharma | priya.sharma@apollouniversity.edu.in | Apollo@2026! | Assistant ICT |
| Mr. Arun Verma | arun.verma@apollouniversity.edu.in | Apollo@2026! | Assistant Manager |
| Mr. Kiran Patel | kiran.patel@apollouniversity.edu.in | Apollo@2026! | Sr. IT Executive |
| Mr. Suresh Reddy | suresh.reddy@apollouniversity.edu.in | Apollo@2026! | Sr. IT Executive |
| Ms. Deepa Nair | deepa.nair@apollouniversity.edu.in | Apollo@2026! | Sr. IT Executive |
| Test User | test@apollouniversity.edu.in | Test@2026! | Student/Faculty |

### Login Page

Go to: `http://localhost/TMS/auth/login.php`

- For **students/faculty** → use the **Student / Faculty** tab
- For **IT staff** → use the **IT Staff** tab

### Change Passwords

All staff should change their passwords after first login:
- Go to: Staff Portal → Profile → Change Password section

---

## 12. HOW TO USE EACH PORTAL

---

### Student / Faculty Portal

#### Register
1. Go to `http://localhost/TMS/auth/register.php`
2. Fill in: Full Name, Email (@apollouniversity.edu.in only), Password, Phone, Department, Roll Number
3. Password must be: 8+ characters, 1 uppercase, 1 lowercase, 1 number, 1 special character
4. Click **Register**

#### Raise a Ticket
1. Login → Dashboard → Click **Raise New Ticket** OR go to `user/raise_ticket.php`
2. **Step 1 — Category:** Click the card that matches your problem
   - WiFi Signal Weak, No Internet, Computer Issue, Printer, etc.
   - If none match → click **Other** and describe your problem
3. **Step 2 — Assign to Staff:** Click on the IT staff member you want
   - Shows their name, designation, and contact number
   - You can choose any staff member
4. **Step 3 — Priority & Details:**
   - Select priority: Low / Medium / High
   - Enter description of your problem
   - Click **Submit Ticket**
5. You will receive an email/SMS confirmation with your ticket number (format: APL-20260101-0001)

#### Track a Ticket
1. Dashboard → Click on any ticket OR go to `user/my_tickets.php`
2. Click on a ticket to see full details
3. The status progress bar shows current stage:
   ```
   Notified → Processing → Solving → Solved
   ```
4. See full history of who was assigned and when status changed

#### Give Feedback
1. After a ticket is marked **Solved**, you will see a feedback prompt
2. Go to `user/my_tickets.php` → find the solved ticket → click **Give Feedback**
3. Select 1–5 stars, optionally write a comment
4. Click **Submit Feedback**

#### Forgot Password
1. Go to login page → click **Forgot Password?**
2. Enter your registered email address
3. Check your email for a 6-digit OTP (valid for 15 minutes)
4. Enter the OTP on the verify page
5. Set your new password
6. Password cannot be the same as your last 5 passwords

---

### ICT Head Portal

ICT Head has full visibility of all tickets across the entire system.

#### Dashboard
- Total tickets raised
- Open tickets
- Average resolution time (hours)
- Tickets solved today
- Recent tickets from all users

#### View & Assign Tickets
1. Go to **Tickets** in the sidebar
2. Filter by status, priority, or search by ticket number
3. Click any ticket to open it
4. In the **Assign Ticket** section:
   - Select any IT staff member (Assistant Manager or Sr. IT Executive)
   - Click **Assign**
5. The assigned staff receives an email + SMS notification

#### Download Reports
1. Go to **Reports** in the sidebar (only visible to ICT Head)
2. Select report type:
   - **Weekly** — pick a week
   - **Monthly** — pick month and year
3. Choose download format:
   - **CSV** — opens in Excel, contains all ticket data
   - **PDF** — formatted report with staff performance table
4. The report includes:
   - Total tickets raised
   - Tickets solved
   - Average resolution time
   - Per-staff performance breakdown
   - Category-wise breakdown
   - Full ticket listing with assignee and resolution time

---

### Assistant Manager Portal

#### Dashboard
- Tickets assigned to the Assistant Manager
- Pending tickets count
- Solved tickets count

#### Assign Tickets to Sr. IT Executives
1. Go to **Tickets** in the sidebar
2. Click on an assigned ticket
3. In the **Assign Ticket** section:
   - Only Sr. IT Executives appear in the dropdown (cannot assign to ICT Head)
   - Select the executive and click **Assign**

---

### Sr. IT Executive Portal

#### Update Ticket Status
1. Go to **Tickets** in the sidebar
2. Click on an assigned ticket
3. In the **Update Status** section:
   - Status can only move forward: Notified → Processing → Solving → Solved
   - Cannot skip stages or go backwards
   - Add optional notes when updating
   - Click **Update Status**
4. The user receives an email + SMS notification on every status change

---

## 13. TICKET LIFECYCLE

```
┌─────────────────────────────────────────────────────────┐
│                     TICKET LIFECYCLE                    │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  User raises ticket                                     │
│         ↓                                               │
│    [NOTIFIED]    ← Email + SMS sent to assigned staff   │
│         ↓                                               │
│  ICT Head assigns to Asst. Manager or Sr. IT Exec       │
│         OR                                              │
│  Asst. Manager assigns to Sr. IT Exec                   │
│         ↓                                               │
│   [PROCESSING]   ← Sr. IT Exec starts working           │
│         ↓                                               │
│    [SOLVING]     ← Sr. IT Exec actively solving         │
│         ↓                                               │
│    [SOLVED]      ← User notified, feedback prompt shown │
│         ↓                                               │
│  User submits ★★★★★ feedback                           │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

### Ticket Number Format

Every ticket gets a unique number:
```
APL-20260312-0001
 │    │        │
 │    │        └── Sequential 4-digit number
 │    └─────────── Date (YYYYMMDD)
 └────────────────── University prefix
```

### Priority Levels

| Priority | Use When |
|----------|----------|
| Low | Non-urgent, can wait |
| Medium | Affects work but has workaround |
| High | Blocking work completely |

---

## 14. NOTIFICATIONS

Every action in the system sends notifications through 3 channels:

### In-App Notifications
- Bell icon in navbar shows unread count (updates every 30 seconds)
- Click bell to see recent notifications
- Go to Notifications page to see all history
- Click any notification to go directly to that ticket

### Email Notifications
Sent via Gmail SMTP (PHPMailer) for:
- New ticket raised (to assigned staff)
- Ticket assigned (to assignee)
- Status changed (to ticket owner)
- Ticket solved (to ticket owner)
- OTP for password reset

### SMS Notifications
Sent via Fast2SMS for:
- Same events as email notifications
- Requires valid Indian mobile numbers
- Free account only sends to registered developer number

---

## 15. REPORTS

Available to ICT Head only, at: `staff/reports.php`

### Report Contents

**Summary Section**
- Total tickets in period
- Solved tickets
- Pending tickets
- Average resolution time

**Staff Performance Table**
| Staff Name | Assigned | Solved | Avg Hours |
|------------|----------|--------|-----------|

**Category Breakdown**
- Bar chart showing which issue types are most reported

**Full Ticket Table**
- All tickets with: number, user, category, priority, assignee, status, date, resolution time

### Download Formats

**CSV (Excel)**
- Opens directly in Microsoft Excel or Google Sheets
- UTF-8 encoded with BOM (no garbled characters)
- Contains summary rows at the bottom

**PDF**
- Landscape A4 format
- University header on every page
- Staff performance table
- Full ticket listing with alternating row colors
- Page numbers in footer

---

## 16. TROUBLESHOOTING

---

### "Not Found" — 404 Error

**Cause:** Project not in XAMPP htdocs folder, or Apache not running.

**Fix:**
1. Check Apache is running (green in XAMPP Control Panel)
2. Verify folder path: should be `xampp/htdocs/TMS/`
3. Check URL: `http://localhost/TMS/` (capital TMS matters on Linux)

---

### MySQL Won't Start

**Fix 1 — Kill conflicting process:**
```
Open Command Prompt as Administrator
netstat -ano | findstr :3306
tasklist | findstr <PID from above>
```
If it's `mysqld.exe`, open Task Manager → End that process → restart XAMPP MySQL.

**Fix 2 — Stop Windows MySQL service:**
```
Press Win+R → type services.msc
Find MySQL or MySQL80 → Right-click → Stop
Then start XAMPP MySQL
```

**Fix 3 — Corrupted log files:**
```
Go to: xampp/mysql/data/
Delete: ib_logfile0, ib_logfile1 (backup first)
Start MySQL again
```

---

### "Database Error" on any page

**Cause:** Wrong database name, MySQL not running, or wrong credentials.

**Fix:**
1. Check MySQL is running in XAMPP
2. Open `config/database.php` — verify:
   ```php
   $host   = 'localhost';
   $dbName = 'tms_apollo';   // Must match your database name in phpMyAdmin
   $user   = 'root';
   $pass   = '';             // Blank for default XAMPP
   ```
3. Open phpMyAdmin and confirm `tms_apollo` database exists with 10 tables

---

### "Invalid email or password" for Staff Login

**Cause:** Placeholder hashes in database (seed_data.sql was imported instead of using seed.php).

**Fix:**
1. Open phpMyAdmin → `tms_apollo` → SQL tab
2. Run:
   ```sql
   DELETE FROM password_history WHERE user_type = 'staff';
   DELETE FROM it_staff;
   ```
3. Visit: `http://localhost/TMS/admin_seed/seed.php`
4. Use incognito window to login (avoids session lockout)

---

### "Too many failed attempts" Lockout

**Cause:** 5 wrong password attempts triggers a 5-minute lockout (stored in session).

**Fix:** Open an incognito / private window (`Ctrl+Shift+N`) and try again.

Or wait 5 minutes for the lockout to expire.

---

### Email Not Sending (OTP / Notifications)

**Fix 1 — Check App Password:**
- Gmail App Password must be used, NOT your regular Gmail password
- It looks like: `abcd efgh ijkl mnop` (16 characters with spaces)
- Regular password will give "Authentication failed" error

**Fix 2 — Check 2FA enabled:**
- App Passwords only work if 2-Step Verification is ON in your Google Account

**Fix 3 — Check Less Secure Apps (older Gmail):**
- Modern Gmail requires App Passwords, not "less secure app access"

**Fix 4 — Check constants.php:**
```php
define('MAIL_USERNAME', 'your.actual.email@gmail.com');
define('MAIL_PASSWORD', 'your 16 char app password');
define('MAIL_FROM',     'your.actual.email@gmail.com');
```

---

### SMS Not Sending

**Cause:** Free Fast2SMS account only sends to the registered number.

**Fix:**
- Login to fast2sms.com → check your registered mobile number
- For testing, use that number as the "phone" in your user profile
- For production (sending to all users), upgrade to a paid plan

---

### PDF Report Shows HTML Instead of PDF File

**Cause:** FPDF library not installed.

**Behavior:** System falls back to a print-friendly HTML page with a "Print / Save as PDF" button.

**Fix:**
1. Download FPDF from http://www.fpdf.org
2. Place `fpdf.php` at: `TMS/vendor/fpdf/fpdf.php`
3. Try the PDF download again

---

### "Class not found" PHP Error

**Cause:** PHPMailer library files are missing from vendor folder.

**Fix:**
1. Download PHPMailer from https://github.com/PHPMailer/PHPMailer/releases
2. Copy `src/PHPMailer.php`, `src/SMTP.php`, `src/Exception.php`
3. Place in: `TMS/vendor/phpmailer/src/`

---

### Session Expired — Keeps Logging Out

**Cause:** Session idle timeout (30 mins) or absolute timeout (8 hrs) reached.

**Change timeout in constants.php:**
```php
define('SESSION_IDLE_TIMEOUT',  1800);   // 30 min — change to 3600 for 1 hour
define('SESSION_ABS_TIMEOUT',   28800);  // 8 hrs  — change to 43200 for 12 hours
```

---

### OTP Email Not Arriving

1. Check spam/junk folder
2. OTP expires in 15 minutes — request a new one after that
3. Maximum 3 wrong OTP attempts, then request a new OTP
4. Check Gmail credentials in constants.php

---

## 17. SECURITY NOTES

The following security measures are built into the system:

| Feature | Details |
|---------|---------|
| Password hashing | bcrypt cost=12 via PHP password_hash() |
| Password history | Cannot reuse last 5 passwords |
| Brute force protection | 5 failed logins → 5 min lockout |
| CSRF protection | Token on every POST form |
| XSS prevention | All output through h() = htmlspecialchars() |
| SQL injection | 100% PDO prepared statements |
| OTP security | SHA256 hashed in DB, 15 min expiry |
| Session security | Regenerated on login, idle+absolute timeout |
| Directory protection | .htaccess denies access to config/, includes/, sql/, vendor/ |
| Error hiding | PHP errors hidden from browser in production |

### After Going Live

1. Delete `admin_seed/seed.php` immediately after first run
2. Change all default passwords (`Apollo@2026!`) to strong unique passwords
3. Set `APP_URL` to the actual server IP or domain
4. If deployed on a real server (not localhost), remove `Require all denied` from vendor/ only for public assets

---

## 18. FAQ

**Q: Can multiple students raise tickets at the same time?**
A: Yes. The system handles concurrent ticket creation using database transactions.

**Q: Can a user raise multiple open tickets?**
A: Yes. There is no limit on open tickets per user.

**Q: Can a user select which staff handles their ticket?**
A: Yes. During ticket creation, Step 2 shows all available IT staff with their designation. The user can pick anyone.

**Q: Can ICT Head re-assign a ticket?**
A: Yes. Tickets can be reassigned at any time. All assignment history is logged and visible in the ticket timeline.

**Q: What if I don't have a Fast2SMS account?**
A: SMS won't be sent but email notifications and in-app notifications still work normally. The system never fails if SMS sending fails.

**Q: What if PHPMailer is not installed?**
A: Email notifications will silently fail. OTP email will not be sent. Ticket status notifications will only appear as in-app notifications. Install PHPMailer for full functionality.

**Q: Can staff members register themselves?**
A: No. Staff accounts are created only through the seed script or directly in phpMyAdmin. Only students and faculty can self-register.

**Q: How do I add a new IT staff member?**
A: Use phpMyAdmin → `it_staff` table → Insert a new row. Password must be a PHP bcrypt hash. Run this in phpMyAdmin's SQL tab:
```sql
INSERT INTO it_staff (name, email, password_hash, role, designation, contact, is_active)
VALUES (
    'New Staff Name',
    'new.staff@apollouniversity.edu.in',
    '$2y$12$REPLACE_WITH_REAL_HASH',
    'sr_it_executive',
    'Sr. IT Executive',
    '9876543200',
    1
);
```
To generate a real hash, create a small PHP file and run it:
```php
<?php echo password_hash('YourPassword@123', PASSWORD_BCRYPT, ['cost' => 12]); ?>
```

**Q: How do I add more ticket categories?**
A: Go to phpMyAdmin → `tms_apollo` → `problem_categories` table → Insert → Add name, Bootstrap icon class (from https://icons.getbootstrap.com), and description.

**Q: Can this run on a real web server (not localhost)?**
A: Yes. Upload all files to the server, set up MySQL, update `APP_URL` in constants.php to your domain or IP, and run seed.php once.

**Q: How do I access from other computers on the same network?**
A: Find your computer's local IP address (`ipconfig` in Command Prompt → IPv4 Address, example: `192.168.1.10`).
Update constants.php: `define('APP_URL', 'http://192.168.1.10/TMS');`
Other devices on the same WiFi/LAN can then access: `http://192.168.1.10/TMS/`

**Q: What is the default MySQL password in XAMPP?**
A: Username is `root`, password is blank (empty). This is handled in `config/database.php` with `$pass = '';`

---

## QUICK SETUP CHECKLIST

```
[ ] XAMPP installed and Apache + MySQL both running
[ ] TMS folder copied to xampp/htdocs/TMS/
[ ] http://localhost/TMS/ opens without 404
[ ] phpMyAdmin: database tms_apollo created
[ ] phpMyAdmin: schema.sql imported (10 tables created)
[ ] PHPMailer files placed in vendor/phpmailer/src/
[ ] FPDF placed at vendor/fpdf/fpdf.php
[ ] config/constants.php updated (Gmail + Fast2SMS)
[ ] http://localhost/TMS/admin_seed/seed.php run successfully
[ ] seed.php deleted after running
[ ] Login tested with ramesh.kumar@apollouniversity.edu.in / Apollo@2026!
[ ] Register a new student with @apollouniversity.edu.in email
[ ] Raise a test ticket as student
[ ] Assign ticket as ICT Head
[ ] Update status as Sr. IT Executive
[ ] Submit feedback as student
[ ] Download report as ICT Head
```

---

*Apollo University IT Support — TMS v1.0*
*Built with PHP 8.x + MySQL + Bootstrap 5 + XAMPP*
