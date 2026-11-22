-- File: database/migrate_phase13.sql
-- Phase 13: approver record and account audit log. Run once on an existing majorcare database.

USE majorcare;

ALTER TABLE users
  ADD COLUMN approved_by INT UNSIGNED NULL AFTER rejection_reason,
  ADD COLUMN approved_at DATETIME NULL AFTER approved_by,
  ADD CONSTRAINT fk_users_approved_by
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE account_audit_log (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_user_id INT UNSIGNED NOT NULL,
  staff_user_id   INT UNSIGNED NULL,
  action          ENUM('approve', 'reject', 'undo', 'revoke', 'id_change') NOT NULL,
  detail          VARCHAR(500) NULL,
  reason          VARCHAR(500) NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_student (student_user_id, created_at),
  CONSTRAINT fk_audit_student
    FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_audit_staff
    FOREIGN KEY (staff_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;