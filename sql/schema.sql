-- ============================================================
-- Apollo University - Ticket Management System
-- Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS tms_apollo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tms_apollo;

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
    role           ENUM('admin','ict_head','assistant_manager','assistant_ict','sr_it_executive','assistant_it') NOT NULL,
    designation    VARCHAR(100),
    contact        VARCHAR(15),
    is_active      TINYINT(1) DEFAULT 1,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_role (role)
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
