<?php
// File: includes/accounts.php
// Existing student accounts: remove, restore, school ID correction, and the audit history.
// Approval, rejection, and undo of registration submissions are in includes/submissions.php.
// Every decision is written to account_audit_log with the staff member, the time, and the reason.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/appointments.php'; // add_notification()

// School IDs use the format 00-0000-000
function valid_school_id(string $id): bool
{
    return preg_match('/^\d{2}-\d{4}-\d{3}$/', $id) === 1;
}

// Loads a student account with its name. Returns the row, or false.
function find_student_account(PDO $pdo, int $studentId, bool $lock = false)
{
    $stmt = $pdo->prepare(
        "SELECT u.id, u.school_id, u.account_status, sp.full_name
         FROM users u
         JOIN student_profiles sp ON sp.user_id = u.id
         WHERE u.id = :id AND u.role = 'student'" . ($lock ? ' FOR UPDATE' : '')
    );
    $stmt->execute([':id' => $studentId]);
    return $stmt->fetch();
}

// Writes one audit row. Call it inside the caller's transaction.
function log_account_event(PDO $pdo, int $studentId, int $staffId, string $action, ?string $detail, ?string $reason): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO account_audit_log (student_user_id, staff_user_id, action, detail, reason)
         VALUES (:student, :staff, :action, :detail, :reason)'
    );
    $stmt->execute([
        ':student' => $studentId,
        ':staff'   => $staffId,
        ':action'  => $action,
        ':detail'  => $detail,
        ':reason'  => $reason,
    ]);
}

// Remove or restore an account. Returns ['ok' => bool, 'message' => string].
function decide_registration(PDO $pdo, int $staffId, int $studentId, string $action, string $reason, bool $verified): array
{
    $requiredStatus = ['remove' => 'approved', 'restore' => 'removed'];
    if (!isset($requiredStatus[$action])) {
        return ['ok' => false, 'message' => 'Unknown action.'];
    }

    if ($reason === '' || mb_strlen($reason) > 400) {
        return ['ok' => false, 'message' => 'Enter a reason of 400 characters or fewer.'];
    }
    if ($action === 'restore' && !$verified) {
        return ['ok' => false, 'message' => 'Tick the box confirming you checked this ID against the enrollment list before restoring.'];
    }

    try {
        $pdo->beginTransaction();

        $student = find_student_account($pdo, $studentId, true);
        if ($student === false) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'That account was not found.'];
        }
        if ($student['account_status'] !== $requiredStatus[$action]) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'This account is ' . $student['account_status']
                . ', so that action is not allowed.'];
        }

        $name = $student['full_name'];

        if ($action === 'remove') {
            $newStatus  = 'removed';
            $approverId = null;
            $stamp      = 0;
            $title      = 'Account deactivated';
            $message    = 'Your MajorCare account has been deactivated. Reason: ' . $reason
                        . ' Contact the campus clinic for help.';
            $auditDetail = null;
            $success    = $name . ' was removed. The account can be restored from the Removed list.';
        } else {
            // Restore goes straight to Approved, with the enrollment check recorded
            $newStatus   = 'approved';
            $approverId  = $staffId;
            $stamp       = 1;
            $title       = 'Account reinstated';
            $message     = 'Your MajorCare account has been reinstated. You can log in again.';
            $auditDetail = 'ID checked against the enrollment list';
            $success     = $name . ' was restored and is approved. The student can log in again.';
        }

        $update = $pdo->prepare(
            'UPDATE users
             SET account_status = :status,
                 approved_by = :approver,
                 approved_at = IF(:stamp = 1, NOW(), NULL)
             WHERE id = :id'
        );
        $update->execute([
            ':status'   => $newStatus,
            ':approver' => $approverId,
            ':stamp'    => $stamp,
            ':id'       => $studentId,
        ]);

        add_notification($pdo, $studentId, 'registration', $title, $message, 'public/index.php');
        log_account_event($pdo, $studentId, $staffId, $action, $auditDetail, $reason);

        $pdo->commit();
        return ['ok' => true, 'message' => $success];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Account decision failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'The decision could not be saved. Please try again.'];
    }
}

// Corrects a student's school ID. Returns ['ok' => bool, 'message' => string].
function change_school_id(PDO $pdo, int $staffId, int $studentId, string $newId, string $reason): array
{
    if (!valid_school_id($newId)) {
        return ['ok' => false, 'message' => 'The school ID must use the format 00-0000-000.'];
    }
    if ($reason === '' || mb_strlen($reason) > 400) {
        return ['ok' => false, 'message' => 'Enter a reason of 400 characters or fewer.'];
    }

    try {
        $pdo->beginTransaction();

        $student = find_student_account($pdo, $studentId, true);
        if ($student === false) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'That account was not found.'];
        }
        if ($student['school_id'] === $newId) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'That is already the school ID on this account.'];
        }

        $check = $pdo->prepare('SELECT id FROM users WHERE school_id = :new AND id <> :id');
        $check->execute([':new' => $newId, ':id' => $studentId]);
        if ($check->fetch()) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Another account already uses that school ID.'];
        }

        $update = $pdo->prepare('UPDATE users SET school_id = :new WHERE id = :id');
        $update->execute([':new' => $newId, ':id' => $studentId]);

        log_account_event($pdo, $studentId, $staffId, 'id_change',
            'Changed from ' . $student['school_id'] . ' to ' . $newId, $reason);

        add_notification($pdo, $studentId, 'registration', 'School ID corrected',
            'Your school ID was corrected by clinic staff. Use your new school ID to log in. Reason: ' . $reason,
            'public/index.php');

        $pdo->commit();
        return ['ok' => true, 'message' => 'School ID changed to ' . $newId . '.'];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('School ID change failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'The school ID could not be changed. Please try again.'];
    }
}

// Audit history for one student, newest first
function audit_history(PDO $pdo, int $studentId): array
{
    $stmt = $pdo->prepare(
        'SELECT l.action, l.detail, l.reason, l.created_at, sp.full_name AS staff_name
         FROM account_audit_log l
         LEFT JOIN staff_profiles sp ON sp.user_id = l.staff_user_id
         WHERE l.student_user_id = :id
         ORDER BY l.created_at DESC, l.id DESC'
    );
    $stmt->execute([':id' => $studentId]);
    return $stmt->fetchAll();
}

// Readable names for audit actions. Older actions stay listed so past history still reads correctly.
const AUDIT_ACTION_LABELS = [
    'approve'   => 'Approved',
    'reject'    => 'Rejected',
    'undo'      => 'Rejection undone',
    'revoke'    => 'Approval withdrawn',
    'remove'    => 'Account removed',
    'restore'   => 'Account restored',
    'id_change' => 'School ID changed',
];