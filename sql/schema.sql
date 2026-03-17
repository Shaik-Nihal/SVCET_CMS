-- ============================================================
-- College Ticket Management System
-- Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS tms_college CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tms_college;

-- ============================================================
-- 1. users (students / faculty)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(100) NOT NULL,
    email          VARCHAR(150) NOT NULL UNIQUE,
    password_hash  VARCHAR(255) NOT NULL,
    phone          VARCHAR(15),
    designation    VARCHAR(100),
    department     VARCHAR(100),
    roll_no        VARCHAR(30),
    email_verified TINYINT(1) DEFAULT 0,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 2. it_staff
-- ============================================================
CREATE TABLE IF NOT EXISTS it_staff (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(100) NOT NULL,
    email          VARCHAR(150) NOT NULL UNIQUE,
    password_hash  VARCHAR(255) NOT NULL,
    role           VARCHAR(64) NOT NULL,
    designation    VARCHAR(100),
    contact        VARCHAR(15),
    is_active      TINYINT(1) DEFAULT 1,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 2b. roles (RBAC)
-- ============================================================
CREATE TABLE IF NOT EXISTS roles (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug          VARCHAR(64) NOT NULL UNIQUE,
    name          VARCHAR(100) NOT NULL,
    is_system     TINYINT(1) NOT NULL DEFAULT 0,
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    display_order INT NOT NULL DEFAULT 999,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_roles_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 2c. permissions (RBAC)
-- ============================================================
CREATE TABLE IF NOT EXISTS permissions (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug        VARCHAR(80) NOT NULL UNIQUE,
    name        VARCHAR(120) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    group_name  VARCHAR(50) DEFAULT 'general',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 2d. role_permissions (RBAC)
-- ============================================================
CREATE TABLE IF NOT EXISTS role_permissions (
    role_id       INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 3. problem_categories
-- ============================================================
CREATE TABLE IF NOT EXISTS problem_categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    icon        VARCHAR(60) DEFAULT 'bi-question-circle',
    description TEXT,
    is_active   TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 4. tickets
-- ============================================================
CREATE TABLE IF NOT EXISTS tickets (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_number       VARCHAR(25) NOT NULL UNIQUE,
    user_id             INT UNSIGNED NOT NULL,
    problem_category_id INT UNSIGNED,
    custom_description  TEXT,
    assigned_to         INT UNSIGNED,
    status              ENUM('notified','processing','solving','solved') DEFAULT 'notified',
    priority            ENUM('low','medium','high') DEFAULT 'medium',
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    solved_at           DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (problem_category_id) REFERENCES problem_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES it_staff(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_user_status (user_id, status),
    INDEX idx_status (status),
    INDEX idx_assigned (assigned_to),
    INDEX idx_assigned_status (assigned_to, status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 5. ticket_assignments
-- ============================================================
CREATE TABLE IF NOT EXISTS ticket_assignments (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id   INT UNSIGNED NOT NULL,
    assigned_by INT UNSIGNED NULL,
    assigned_to INT UNSIGNED NOT NULL,
    notes       TEXT,
    assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    INDEX idx_ticket (ticket_id),
    INDEX idx_assigned_to (assigned_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 6. ticket_status_history
-- ============================================================
CREATE TABLE IF NOT EXISTS ticket_status_history (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id        INT UNSIGNED NOT NULL,
    old_status       VARCHAR(30),
    new_status       VARCHAR(30) NOT NULL,
    changed_by       INT UNSIGNED NOT NULL,
    changed_by_type  ENUM('user','staff') NOT NULL,
    notes            TEXT,
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    INDEX idx_ticket (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 7. feedback
-- ============================================================
CREATE TABLE IF NOT EXISTS feedback (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id  INT UNSIGNED NOT NULL UNIQUE,
    user_id    INT UNSIGNED NOT NULL,
    rating     TINYINT NOT NULL,
    comment    TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT chk_rating CHECK (rating BETWEEN 1 AND 5),
    INDEX idx_feedback_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 8. password_reset_tokens
-- ============================================================
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    user_type  ENUM('user','staff') NOT NULL,
    token      VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at    DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id, user_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 9. notifications
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient_id   INT UNSIGNED NOT NULL,
    recipient_type ENUM('user','staff') NOT NULL,
    message        TEXT NOT NULL,
    ticket_id      INT UNSIGNED,
    is_read        TINYINT(1) DEFAULT 0,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_recipient (recipient_id, recipient_type),
    INDEX idx_recipient_unread (recipient_id, recipient_type, is_read),
    INDEX idx_read (is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 10. password_history
-- ============================================================
CREATE TABLE IF NOT EXISTS password_history (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    user_type     ENUM('user','staff') NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    changed_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id, user_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 11. login_attempts (DB-backed brute-force protection)
-- ============================================================
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address   VARCHAR(45) NOT NULL,
    email        VARCHAR(150) NOT NULL,
    attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_email (ip_address, email),
    INDEX idx_attempted (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- RBAC Seed Data
-- ============================================================
INSERT INTO permissions (slug, name, description, group_name) VALUES
('admin.access', 'Admin Panel Access', 'Access admin dashboard and admin modules', 'admin'),
('roles.manage', 'Manage Roles & Permissions', 'Create roles and assign permissions', 'admin'),
('staff.manage', 'Manage Staff', 'Create, edit, activate/deactivate staff accounts', 'admin'),
('users.manage', 'Manage Users', 'View and manage user accounts', 'admin'),
('reports.view', 'View Reports', 'View report screens and export CSV/PDF', 'reports'),
('ticket.assign.lead', 'Assign Tickets (Leadership)', 'Assign/reassign tickets as a leadership role', 'tickets'),
('ticket.assign.exec', 'Assign Tickets (Executive)', 'Delegate tickets to execution team', 'tickets'),
('ticket.update_status', 'Update Ticket Status', 'Move ticket through processing states', 'tickets'),
('tickets.view_all', 'View All Tickets', 'See all tickets in staff panel', 'tickets'),
('tickets.view_involved', 'View Involved Tickets', 'See tickets assigned to/handled by the staff member', 'tickets'),
('notify.management', 'Receive Management Notifications', 'Receive leadership notifications for new ticket/status updates', 'notifications')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    group_name = VALUES(group_name);

INSERT INTO roles (slug, name, is_system, is_active, display_order) VALUES
('admin', 'System Admin', 1, 1, 1),
('ict_head', 'ICT Head', 1, 1, 10),
('assistant_manager', 'Assistant Manager', 1, 1, 20),
('assistant_ict', 'Assistant ICT', 1, 1, 30),
('sr_it_executive', 'Sr. IT Executive', 1, 1, 40),
('assistant_it', 'Assistant IT', 1, 1, 50)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    is_system = VALUES(is_system),
    is_active = VALUES(is_active),
    display_order = VALUES(display_order);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON (
  (r.slug = 'admin' AND p.slug IN (
      'admin.access','roles.manage','staff.manage','users.manage','reports.view',
      'ticket.assign.lead','ticket.assign.exec','ticket.update_status','tickets.view_all','tickets.view_involved','notify.management'
  )) OR
  (r.slug = 'ict_head' AND p.slug IN (
      'reports.view','ticket.assign.lead','tickets.view_all','tickets.view_involved','notify.management'
  )) OR
  (r.slug = 'assistant_manager' AND p.slug IN (
      'ticket.assign.lead','tickets.view_all','tickets.view_involved','notify.management'
  )) OR
  (r.slug = 'assistant_ict' AND p.slug IN (
      'ticket.assign.lead','tickets.view_all','tickets.view_involved','notify.management'
  )) OR
  (r.slug = 'sr_it_executive' AND p.slug IN (
      'ticket.assign.exec','ticket.update_status','tickets.view_involved'
  )) OR
  (r.slug = 'assistant_it' AND p.slug IN (
      'ticket.update_status','tickets.view_involved'
  ))
);
