-- ============================================================
-- Performance Optimization Migration
-- Run this on existing databases to add performance indexes
-- ============================================================

-- Composite index for unread notification count (used on every page load)
ALTER TABLE notifications
  ADD INDEX idx_recipient_unread (recipient_id, recipient_type, is_read);

-- Composite index for user dashboard ticket stats
ALTER TABLE tickets
  ADD INDEX idx_user_status (user_id, status);

-- Composite index for staff dashboard ticket stats
ALTER TABLE tickets
  ADD INDEX idx_assigned_status (assigned_to, status);

-- Composite index for ticket number generation (MAX query)
ALTER TABLE tickets
  ADD INDEX idx_ticket_number_prefix (ticket_number);

-- Composite index for feedback lookups by user
ALTER TABLE feedback
  ADD INDEX idx_feedback_user (user_id);
