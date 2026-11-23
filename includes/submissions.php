<?php
// File: includes/submissions.php
// Registration submissions. Each registration is stored with its ID photo.
// A student account is created only when staff approve one submission.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/appointments.php'; // add_notification()
require_once __DIR__ . '/accounts.php';     // valid_school_id(), log_account_event()
require_once __DIR__ . '/documents.php';    // check_uploaded_file(), ALLOWED_MIME_TYPES

function photos_dir(): string
{
    return rtrim(str_replace('\\', '/', STORAGE_PATH), '/') . '/id_photos';
}

// Full path to a stored ID photo, or null if the name is unsafe or missing
function id_photo_path(string $storedName): ?string
{
    if (!preg_match('/^[a-f0-9]{32}\.(pdf|jpg|png)$/', $storedName)) {
        return null;
    }
    $path = photos_dir() . '/' . $storedName;
    return is_file($path) ? $path : null;
}

// Moves an uploaded ID photo into storage under a random name. Returns that name.
function store_id_photo(string $tmpPath, string $mime): string
{
    $dir = photos_dir();
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('The ID photo folder cannot be created.');
    }
    $name = bin2hex(random_bytes(16)) . '.' . ALLOWED_MIME_TYPES[$mime];
    if (!move_uploaded_file($tmpPath, $dir . '/' . $name)) {
        throw new RuntimeException('The ID photo could not be moved into storage.');
    }
    return $name;
}

// Explains why a school ID cannot register now, or returns null if it can.
// A pending submission reserves the ID. A rejected (or superseded) one releases it.
function registration_block_reason(PDO $pdo, string $schoolId): ?string
{
    $account = $pdo->prepare("SELECT account_status FROM users WHERE school_id = :id AND role = 'student'");
    $account->execute([':id' => $schoolId]);
    $status = $account->fetchColumn();

    if ($status === 'approved') {
        return 'This account is already approved. Please log in.';
    }
    if ($status === 'removed') {
        return 'This account has been removed. Please contact the campus clinic.';
    }

    // Only one pending submission may hold an ID at a time
    $pending = $pdo->prepare(
        "SELECT id FROM registration_submissions
         WHERE school_id = :id AND status = 'pending'
         ORDER BY id DESC LIMIT 1"
    );
    $pending->execute([':id' => $schoolId]);
    if ($pending->fetch() !== false) {
        return 'A registration with this school ID is already being reviewed. '
             . 'If someone used your ID by mistake, contact the clinic to have it released.';
    }

    return null;
}

// Sends one notification to every approved staff member, linking to the registrations page
function notify_staff_registrations(PDO $pdo, string $title, string $message): void
{
    $staffIds = $pdo->query(
        "SELECT id FROM users WHERE role = 'staff' AND account_status = 'approved'"
    )->fetchAll(PDO::FETCH_COLUMN);

    foreach ($staffIds as $staffId) {
        add_notification($pdo, (int) $staffId, 'registration', $title, $message, 'staff/registrations.php');
    }
}

// Saves one registration submission. $data holds school_id, full_name, email, and password.
function submit_registration(PDO $pdo, array $data, array $file, string $mime): array
{
    $blocked = registration_block_reason($pdo, $data['school_id']);
    if ($blocked !== null) {
        return ['ok' => false, 'message' => $blocked];
    }

    try {
        $stored = store_id_photo($file['tmp_name'], $mime);
    } catch (RuntimeException $e) {
        error_log('ID photo storage failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'The ID photo could not be saved. Please try again.'];
    }

    try {
        $pdo->beginTransaction();

        $insert = $pdo->prepare(
            "INSERT INTO registration_submissions
               (school_id, full_name, email, password_hash, photo_stored_name, photo_original_name,
                photo_mime, photo_size, status)
             VALUES (:school, :name, :email, :hash, :stored, :original, :mime, :size, 'pending')"
        );
        $insert->execute([
            ':school'   => $data['school_id'],
            ':name'     => $data['full_name'],
            ':email'    => $data['email'] !== '' ? $data['email'] : null,
            ':hash'     => password_hash($data['password'], PASSWORD_DEFAULT),
            ':stored'   => $stored,
            ':original' => mb_substr(basename($file['name']), 0, 255),
            ':mime'     => $mime,
            ':size'     => (int) $file['size'],
        ]);

        notify_staff_registrations(
            $pdo,
            'New registration submitted',
            $data['full_name'] . ' submitted a registration for review.'
        );

        $pdo->commit();
                return ['ok' => true, 'message' => 'Registration submitted. Clinic staff will check your ID and decide. Please allow up to 24 hours for the review.'];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        @unlink(photos_dir() . '/' . $stored);
        error_log('Submission failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'The registration could not be saved. Please try again.'];
    }
}

// Approves one pending submission. Creates the student account, which uses the
// password from this submission. Other pending submissions for the ID are rejected.
function approve_submission(PDO $pdo, int $staffId, int $submissionId, bool $verified): array
{
    if (!$verified) {
        return ['ok' => false, 'message' => 'Tick the box confirming you checked this ID against the enrollment list before approving.'];
    }

    try {
        $pdo->beginTransaction();

        $lock = $pdo->prepare('SELECT * FROM registration_submissions WHERE id = :id FOR UPDATE');
        $lock->execute([':id' => $submissionId]);
        $sub = $lock->fetch();

        if ($sub === false) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'That submission was not found.'];
        }
        if ($sub['status'] !== 'pending') {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'This submission is ' . $sub['status'] . ', so it cannot be approved.'];
        }

        $taken = $pdo->prepare('SELECT id FROM users WHERE school_id = :id');
        $taken->execute([':id' => $sub['school_id']]);
        if ($taken->fetch()) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'An account with this school ID already exists.'];
        }

        $account = $pdo->prepare(
            "INSERT INTO users (school_id, password_hash, role, account_status, approved_by, approved_at, must_change_password)
             VALUES (:school, :hash, 'student', 'approved', :staff, NOW(), 0)"
        );
        $account->execute([
            ':school' => $sub['school_id'],
            ':hash'   => $sub['password_hash'],
            ':staff'  => $staffId,
        ]);
        $userId = (int) $pdo->lastInsertId();

        $profile = $pdo->prepare(
            'INSERT INTO student_profiles (user_id, full_name, email) VALUES (:id, :name, :email)'
        );
        $profile->execute([':id' => $userId, ':name' => $sub['full_name'], ':email' => $sub['email']]);

        $decide = $pdo->prepare(
            "UPDATE registration_submissions
             SET status = 'approved', user_id = :uid, decided_by = :staff, decided_at = NOW()
             WHERE id = :id"
        );
        $decide->execute([':uid' => $userId, ':staff' => $staffId, ':id' => $submissionId]);

                $rejectOthers = $pdo->prepare(
            "UPDATE registration_submissions
             SET status = 'rejected',
                 rejection_reason = :reason,
                 decided_by = :staff,
                 decided_at = NOW()
             WHERE school_id = :school AND status = 'pending' AND id <> :id"
        );
        $rejectOthers->execute([
            ':reason' => 'Another registration with this school ID was approved.',
            ':staff'  => $staffId,
            ':school' => $sub['school_id'],
            ':id'     => $submissionId,
        ]);

        add_notification(
            $pdo,
            $userId,
            'registration',
            'Registration approved',
            'Your registration was approved. You can now log in with your school ID ' . $sub['school_id'] . '.',
            'public/index.php'
        );

        log_account_event($pdo, $userId, $staffId, 'approve', 'ID checked against the enrollment list', null);

        $pdo->commit();
        return ['ok' => true, 'message' => $sub['full_name'] . ' was approved. The student can now log in.'];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Submission approval failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'The approval could not be saved. Please try again.'];
    }
}

// Rejects one pending submission with a reason, which the student sees when they try again.
function reject_submission(PDO $pdo, int $staffId, int $submissionId, string $reason): array
{
    if ($reason === '' || mb_strlen($reason) > 400) {
        return ['ok' => false, 'message' => 'Enter a rejection reason of 400 characters or fewer.'];
    }

    try {
        $pdo->beginTransaction();

        $lock = $pdo->prepare('SELECT status FROM registration_submissions WHERE id = :id FOR UPDATE');
        $lock->execute([':id' => $submissionId]);
        $row = $lock->fetch();

        if ($row === false) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'That submission was not found.'];
        }
        if ($row['status'] !== 'pending') {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'This submission is ' . $row['status'] . ', so it cannot be rejected.'];
        }

        $update = $pdo->prepare(
            "UPDATE registration_submissions
             SET status = 'rejected', rejection_reason = :reason, decided_by = :staff, decided_at = NOW()
             WHERE id = :id"
        );
        $update->execute([':reason' => $reason, ':staff' => $staffId, ':id' => $submissionId]);

        $pdo->commit();
        return ['ok' => true, 'message' => 'Registration rejected.'];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Submission rejection failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'The rejection could not be saved. Please try again.'];
    }
}

// Returns a rejected submission to pending, so the student can be approved on review.
function undo_submission_rejection(PDO $pdo, int $staffId, int $submissionId): array
{
    try {
        $pdo->beginTransaction();

        $lock = $pdo->prepare('SELECT status FROM registration_submissions WHERE id = :id FOR UPDATE');
        $lock->execute([':id' => $submissionId]);
        $row = $lock->fetch();

        if ($row === false) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'That submission was not found.'];
        }
        if ($row['status'] !== 'rejected') {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'This submission is not rejected, so it cannot be undone.'];
        }

        $update = $pdo->prepare(
            "UPDATE registration_submissions
             SET status = 'pending', rejection_reason = NULL, decided_by = :staff, decided_at = NULL
             WHERE id = :id"
        );
        $update->execute([':staff' => $staffId, ':id' => $submissionId]);

        $pdo->commit();
        return ['ok' => true, 'message' => 'Registration returned to pending.'];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Submission undo failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'The change could not be saved. Please try again.'];
    }
}