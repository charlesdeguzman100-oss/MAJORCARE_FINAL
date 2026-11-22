-- File: database/migrate_submissions.sql
-- Registration submissions with ID photos. Run once on an existing majorcare database.
-- Deletes pending and rejected student accounts (test data). Approved and removed accounts are kept.

USE majorcare;

-- 1. Delete pending and rejected student accounts. Their profiles, notifications,
--    and audit entries are removed with them (ON DELETE CASCADE).
DELETE FROM users
WHERE role = 'student' AND account_status IN ('pending', 'rejected');

-- 2. One row per registration submission, with its ID photo.
CREATE TABLE registration_submissions (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id           VARCHAR(30) NOT NULL,
  full_name           VARCHAR(120) NOT NULL,
  email               VARCHAR(120) NULL,
  password_hash       VARCHAR(255) NOT NULL,
  photo_stored_name   VARCHAR(255) NOT NULL UNIQUE,
  photo_original_name VARCHAR(255) NOT NULL,
  photo_mime          VARCHAR(100) NOT NULL,
  photo_size          INT UNSIGNED NOT NULL,
  status              ENUM('pending', 'rejected', 'superseded', 'approved') NOT NULL DEFAULT 'pending',
  rejection_reason    VARCHAR(500) NULL,
  decided_by          INT UNSIGNED NULL,
  decided_at          DATETIME NULL,
  user_id             INT UNSIGNED NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_submission_school (school_id, status),
  CONSTRAINT fk_submission_decider
    FOREIGN KEY (decided_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_submission_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;