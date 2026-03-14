# Apollo University Ticket Management System (TMS)
## Updated Setup and Operations Guide

This guide matches the current repository state, including:
- Admin panel and management workflows
- Role-based ticket assignment and reassignment logic
- User and staff analytics pages in admin
- Microsoft Graph or SMTP mail configuration
- Safe local secret handling
- Post-setup cleanup steps

## 1. Stack and Architecture

- Backend: PHP 8.x
- Database: MySQL/MariaDB
- Frontend: Bootstrap 5 + Bootstrap Icons
- Mail: PHPMailer SMTP or Microsoft Graph API
- Reports: CSV and PDF (FPDF)

### Active portals

- User portal: ticket creation, tracking, profile, feedback, notifications
- Staff portal: role-based queues, assignment, status updates, reports
- Admin portal: dashboard, staff management, user management, analytics views

## 2. Current Project Structure

```text
TMS/
  admin/
    dashboard.php
    staff.php
    staff_form.php
    staff_delete.php
    staff_detail.php
    users.php
    user_detail.php
    reset_password.php
  admin_seed/
    .htaccess
    seed.php
  api/
  assets/
    css/
      admin.css
      auth.css
      dashboard.css
      style.css
    js/
  auth/
  config/
    constants.php
    database.php
    local.env.php.example
    mailer.php
  includes/
    auth.php
    functions.php
    notification_helpers.php
    ticket_helpers.php
  reports/
  sql/
    schema.sql
    migrate_roles.sql
    seed_data.sql
  staff/
  user/
  vendor/
```

## 3. Environment Setup (Linux XAMPP/LAMPP)

1. Ensure Apache and MySQL are running.
2. Place project at `/opt/lampp/htdocs/TMS`.
3. Open `http://localhost/TMS`.
4. Ensure PHP extensions are enabled:
- pdo_mysql
- curl
- openssl
- mbstring

## 4. Database Setup

1. Open phpMyAdmin.
2. Create DB: `tms_apollo`.
3. Import only [sql/schema.sql](sql/schema.sql).
4. Optional migration for roles/logic updates: [sql/migrate_roles.sql](sql/migrate_roles.sql).

Do not import `seed_data.sql` for production setup.

## 5. Config Setup

### 5.1 App and DB

- Update [config/constants.php](config/constants.php):
- `APP_URL` (for your host)
- timezone, domains, and security constants as needed

- Update [config/database.php](config/database.php) with DB credentials.

### 5.2 Local secrets (recommended)

1. Copy [config/local.env.php.example](config/local.env.php.example) to:
- `config/local.env.php`
2. Put all secrets in `config/local.env.php` via `putenv(...)`.
3. Keep secrets out of git (already ignored in `.gitignore`).

## 6. Email Configuration (Graph preferred)

The app supports two drivers in [config/constants.php](config/constants.php):
- `MAIL_DRIVER=smtp`
- `MAIL_DRIVER=graph`

### Option A: Microsoft Graph API (recommended for M365)

Required values:
- `GRAPH_TENANT_ID`
- `GRAPH_CLIENT_ID`
- `GRAPH_CLIENT_SECRET`
- `GRAPH_SENDER` (example: `tms@apollouniversity.edu.in`)

Entra app setup checklist:
1. App registration created.
2. API permission added: Microsoft Graph -> Application -> `Mail.Send`.
3. Admin consent granted.
4. Client secret value generated and stored.

### Option B: SMTP fallback

For temporary usage before Graph consent:
- `MAIL_HOST=smtp.office365.com`
- `MAIL_PORT=587`
- `MAIL_USERNAME=tms@apollouniversity.edu.in`
- `MAIL_PASSWORD=<mailbox password or app password>`
- `MAIL_FROM=tms@apollouniversity.edu.in`

## 7. Seed Initial Data

Run one-time seeder:
- `http://localhost/TMS/admin_seed/seed.php`

Seeder creates:
- staff records (including current roles)
- categories
- one test user
- password history entries

After successful run, delete [admin_seed/seed.php](admin_seed/seed.php).

## 8. Role Model and Flow

### Roles in system

- `ict_head`
- `assistant_manager`
- `assistant_ict`
- `sr_it_executive`
- `assistant_it`

### Current assignment behavior

- Domain-aware restrictions are enforced.
- Reassignment chain supports senior to assistant flows.
- Previous assignees can retain controlled visibility where implemented.
- Timeline/history shows assignment and status attribution.

### Status progression

`notified -> processing -> solving -> solved`

Only forward transitions are allowed.

## 9. Admin Panel (Current)

### Dashboard

- System totals: staff, users, tickets, pending
- Recent ticket activity snapshot

### IT Staff Management

- List/edit/add/delete staff
- Activate/deactivate staff
- Staff performance detail view via [admin/staff_detail.php](admin/staff_detail.php)

### User Management

- List users
- Password reset
- Delete user with associated cleanup
- User profile + ticket analytics view via [admin/user_detail.php](admin/user_detail.php)

### Admin analytics pages

- [admin/user_detail.php](admin/user_detail.php):
- account profile
- ticket counts by status
- average resolution time
- average feedback rating
- ticket history table

- [admin/staff_detail.php](admin/staff_detail.php):
- staff profile
- workload metrics
- solved/open split
- average resolution time
- average feedback rating
- assigned ticket history table

## 10. Notifications and Reports

### Notifications

- In-app notifications stored in DB
- Email dispatch from [includes/notification_helpers.php](includes/notification_helpers.php)

### Reports

- CSV: [reports/generate_csv.php](reports/generate_csv.php)
- PDF: [reports/generate_pdf.php](reports/generate_pdf.php)
- Staff report access based on role checks

## 11. Security and Operational Notes

- CSRF validation is enabled on protected POST forms.
- Password hashing uses bcrypt.
- Password history prevents recent reuse.
- Session idle and absolute timeouts are enforced.
- Local-only seeder restriction exists in [admin_seed/.htaccess](admin_seed/.htaccess).

## 12. Cleanup Checklist (Production Readiness)

After setup/testing:
1. Delete [admin_seed/seed.php](admin_seed/seed.php).
2. Confirm no debug/diagnostic scripts remain in `admin_seed/`.
3. Ensure real secrets are only in `config/local.env.php` or environment variables.
4. Ensure mail driver is correctly set (`graph` after consent granted).
5. Rotate any exposed credentials/secrets.

## 13. Quick Verification Checklist

1. Login works for user, staff, and admin accounts.
2. Raise ticket from user portal.
3. Assign/reassign from staff/admin based on role.
4. Update status through full lifecycle.
5. Verify timeline attribution and elapsed time display.
6. Check admin user and staff detail analytics pages.
7. Verify mail send path (Graph or SMTP fallback).
8. Generate CSV/PDF reports.

## 14. Known Temporary State

- If Microsoft Graph admin consent is pending, keep SMTP fallback enabled.
- Once consent is granted, switch `MAIL_DRIVER` to `graph` in local secrets.

