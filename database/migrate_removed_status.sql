-- File: database/migrate_removed_status.sql
-- Adds the removed account status and the remove and restore audit actions. Run once.

USE majorcare;

ALTER TABLE users
  MODIFY account_status ENUM('pending', 'approved', 'rejected', 'removed') NOT NULL DEFAULT 'pending';

ALTER TABLE account_audit_log
  MODIFY action ENUM('approve', 'reject', 'undo', 'revoke', 'remove', 'restore', 'id_change') NOT NULL;