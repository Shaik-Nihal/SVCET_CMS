-- ============================================================
-- RBAC Migration: Custom Roles + Custom Permissions
-- Run this once on existing databases.
-- ============================================================

-- 1) Make it_staff.role flexible (from ENUM to VARCHAR)
ALTER TABLE it_staff
  MODIFY COLUMN role VARCHAR(64) NOT NULL;

-- 2) Roles master table
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

-- 3) Permissions catalog
CREATE TABLE IF NOT EXISTS permissions (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug        VARCHAR(80) NOT NULL UNIQUE,
    name        VARCHAR(120) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    group_name  VARCHAR(50) DEFAULT 'general',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4) Role-permission mapping
CREATE TABLE IF NOT EXISTS role_permissions (
    role_id       INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5) Seed permissions (idempotent)
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

-- 6) Seed default roles (idempotent)
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

-- 7) Seed default role-permission matrix (idempotent)
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
