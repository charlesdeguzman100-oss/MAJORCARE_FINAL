-- File: database/majorcare_full_install.sql
-- Complete MajorCare database for a fresh install. Includes every table and change from Phases 1 to 10.
--
-- WARNING: The first two lines delete an existing majorcare database and all its data.
-- Do not run this on a database that holds records you need.
--
-- Import: phpMyAdmin > SQL tab > paste this file > Go.
-- Then create the first staff account with database/create_staff.php.

DROP DATABASE IF EXISTS majorcare;
CREATE DATABASE majorcare CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE majorcare;

-- 1. Accounts for students and clinic staff
CREATE TABLE users (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id            VARCHAR(30) NOT NULL UNIQUE,
  password_hash        VARCHAR(255) NOT NULL,
  role                 ENUM('student', 'staff') NOT NULL,
  account_status       ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  rejection_reason     VARCHAR(500) NULL,
  approved_by          INT UNSIGNED NULL,
  approved_at          DATETIME NULL,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  last_login_at        DATETIME NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_role_status (role, account_status),
  CONSTRAINT fk_users_approved_by
  FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 2. Student profile, privacy consent, and emergency contact
CREATE TABLE student_profiles (
  user_id                        INT UNSIGNED PRIMARY KEY,
  full_name                      VARCHAR(120) NOT NULL,
  email                          VARCHAR(120) NULL,
  contact_number                 VARCHAR(20) NULL,
  address                        VARCHAR(255) NULL,
  emergency_contact_name         VARCHAR(120) NULL,
  emergency_contact_relationship VARCHAR(50) NULL,
  emergency_contact_number       VARCHAR(20) NULL,
  consent_accepted_at            DATETIME NULL,
  CONSTRAINT fk_student_profile_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. Staff profile and availability
CREATE TABLE staff_profiles (
  user_id                 INT UNSIGNED PRIMARY KEY,
  full_name               VARCHAR(120) NOT NULL,
  position                VARCHAR(80) NOT NULL DEFAULT 'Clinic Staff',
  availability_status     ENUM('available', 'busy', 'unavailable') NOT NULL DEFAULT 'unavailable',
  contact_method          VARCHAR(255) NULL,
  availability_updated_at DATETIME NULL,
  CONSTRAINT fk_staff_profile_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Basic health information, recorded by staff (one row per student)
CREATE TABLE health_information (
  student_user_id    INT UNSIGNED PRIMARY KEY,
  blood_type         ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown') NOT NULL DEFAULT 'Unknown',
  allergies          TEXT NULL,
  other_health_notes TEXT NULL,
  updated_by         INT UNSIGNED NULL,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_health_student
    FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_health_updater
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 5. Vaccination records (many per student)
CREATE TABLE vaccination_records (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_user_id INT UNSIGNED NOT NULL,
  vaccine_name    VARCHAR(120) NOT NULL,
  dose_label      VARCHAR(40) NULL,
  date_given      DATE NULL,
  recorded_by     INT UNSIGNED NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_vaccine_student (student_user_id),
  CONSTRAINT fk_vaccine_student
    FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_vaccine_recorder
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 6. Appointment slots. capacity is the number of students who can hold a seat.
--    A slot is full when its seats are taken. Full is not stored as a status.
CREATE TABLE appointment_slots (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slot_date   DATE NOT NULL,
  start_time  TIME NOT NULL,
  end_time    TIME NOT NULL,
  capacity    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  status      ENUM('open', 'closed') NOT NULL DEFAULT 'open',
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_slot_date_time (slot_date, start_time),
  INDEX idx_slot_status_date (status, slot_date),
  CONSTRAINT chk_slot_capacity CHECK (capacity >= 1),
  CONSTRAINT fk_slot_creator
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 7. Appointment requests. slot_id is not UNIQUE, because a slot can hold several
--    requests over time (for example, a rejected request followed by a new one).
--    The booking code checks the seat count inside a locked transaction.
CREATE TABLE appointments (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slot_id           INT UNSIGNED NOT NULL,
  student_user_id   INT UNSIGNED NOT NULL,
  reason            VARCHAR(500) NOT NULL,
  status            ENUM('pending', 'approved', 'rejected', 'cancelled', 'completed')
                    NOT NULL DEFAULT 'pending',
  staff_note        VARCHAR(500) NULL,
  status_updated_by INT UNSIGNED NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_appointment_student (student_user_id, status),
  INDEX idx_appointment_slot (slot_id),
  CONSTRAINT fk_appointment_slot
    FOREIGN KEY (slot_id) REFERENCES appointment_slots(id) ON DELETE RESTRICT,
  CONSTRAINT fk_appointment_student
    FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_appointment_updater
    FOREIGN KEY (status_updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 8. Clinic visit records written by staff. Records are corrected, never deleted.
CREATE TABLE clinic_visits (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_user_id INT UNSIGNED NOT NULL,
  visit_date      DATE NOT NULL,
  reason          VARCHAR(500) NOT NULL,
  notes           TEXT NULL,
  recommendations TEXT NULL,
  recorded_by     INT UNSIGNED NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_visit_student_date (student_user_id, visit_date),
  CONSTRAINT fk_visit_student
    FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_visit_recorder
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 9. Documents uploaded by students. Files are stored in STORAGE_PATH/documents.
CREATE TABLE medical_documents (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_user_id   INT UNSIGNED NOT NULL,
  document_type     ENUM('laboratory_result', 'physical_exam_form', 'medical_clearance', 'other')
                    NOT NULL,
  original_filename VARCHAR(255) NOT NULL,
  stored_filename   VARCHAR(255) NOT NULL UNIQUE,
  mime_type         VARCHAR(100) NOT NULL,
  file_size         INT UNSIGNED NOT NULL,
  review_status     ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  review_note       VARCHAR(500) NULL,
  reviewed_by       INT UNSIGNED NULL,
  reviewed_at       DATETIME NULL,
  uploaded_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_document_student_status (student_user_id, review_status),
  CONSTRAINT fk_document_student
    FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_document_reviewer
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 10. Laboratory result files that staff publish (created by the Phase 7 design, kept for the schema).
CREATE TABLE laboratory_results (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_user_id   INT UNSIGNED NOT NULL,
  result_type       VARCHAR(120) NOT NULL,
  result_date       DATE NOT NULL,
  description       VARCHAR(500) NULL,
  original_filename VARCHAR(255) NOT NULL,
  stored_filename   VARCHAR(255) NOT NULL UNIQUE,
  mime_type         VARCHAR(100) NOT NULL,
  file_size         INT UNSIGNED NOT NULL,
  is_published      TINYINT(1) NOT NULL DEFAULT 0,
  published_at      DATETIME NULL,
  uploaded_by       INT UNSIGNED NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_result_student_published (student_user_id, is_published),
  CONSTRAINT fk_result_student
    FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_result_uploader
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 11. One private conversation per student
CREATE TABLE conversations (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_user_id  INT UNSIGNED NOT NULL UNIQUE,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_message_at  DATETIME NULL,
  CONSTRAINT fk_conversation_student
    FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 12. Messages inside a conversation
CREATE TABLE messages (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT UNSIGNED NOT NULL,
  sender_user_id  INT UNSIGNED NOT NULL,
  body            TEXT NOT NULL,
  is_read         TINYINT(1) NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_message_conversation (conversation_id, created_at),
  CONSTRAINT fk_message_conversation
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_message_sender
    FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 13. In-system notifications
CREATE TABLE notifications (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id           INT UNSIGNED NOT NULL,
  notification_type ENUM('registration', 'appointment', 'appointment_reminder',
                         'document', 'result', 'message', 'announcement') NOT NULL,
  title             VARCHAR(150) NOT NULL,
  message           VARCHAR(500) NOT NULL,
  link              VARCHAR(255) NULL,
  appointment_id    INT UNSIGNED NULL,
  is_read           TINYINT(1) NOT NULL DEFAULT 0,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notification_user (user_id, is_read, created_at),
  INDEX idx_notification_reminder (appointment_id, notification_type),
  CONSTRAINT fk_notification_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_notification_appointment
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 14. Clinic information. Exactly one row, id = 1. It is edited, never deleted.
CREATE TABLE clinic_information (
  id              TINYINT UNSIGNED PRIMARY KEY,
  clinic_name     VARCHAR(120) NOT NULL,
  location        VARCHAR(255) NULL,
  operating_hours VARCHAR(255) NULL,
  contact_number  VARCHAR(40) NULL,
  contact_email   VARCHAR(120) NULL,
  updated_by      INT UNSIGNED NULL,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_clinic_info_updater
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO clinic_information (id, clinic_name)
VALUES (1, 'University Campus Clinic');

-- 15. Frequently asked questions
CREATE TABLE clinic_faqs (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question      VARCHAR(255) NOT NULL,
  answer        TEXT NOT NULL,
  display_order INT NOT NULL DEFAULT 0,
  is_published  TINYINT(1) NOT NULL DEFAULT 1,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_faq_creator
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 16. Clinic announcements
CREATE TABLE announcements (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(150) NOT NULL,
  body         TEXT NOT NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 0,
  published_at DATETIME NULL,
  created_by   INT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_announcement_creator
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 17. Physical assessments (check-ups recorded by the campus clinic). Drafts are hidden from students.
CREATE TABLE physical_assessments (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_user_id   INT UNSIGNED NOT NULL,
  assessment_date   DATE NOT NULL,
  height_cm         DECIMAL(5,1) NULL,
  weight_kg         DECIMAL(5,1) NULL,
  bmi               DECIMAL(4,1) NULL,
  bmi_category      VARCHAR(30) NULL,
  systolic_bp       SMALLINT UNSIGNED NULL,
  diastolic_bp      SMALLINT UNSIGNED NULL,
  notes             TEXT NULL,
  status            ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
  recorded_by       INT UNSIGNED NULL,
  published_at      DATETIME NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_assessment_student_status (student_user_id, status),
  CONSTRAINT fk_assessment_student
    FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_assessment_recorder
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

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