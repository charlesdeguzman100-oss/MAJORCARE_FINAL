<?php
// File: includes/documents.php
// Medical document rules: upload checks, storage, review decisions, and notifications.
//
// Files are stored in STORAGE_PATH/documents, outside htdocs, so Apache cannot serve them.
// public/document.php serves a file only after the access check passes.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/appointments.php'; // provides add_notification()

const DOCUMENT_TYPES = [
    'laboratory_result'  => 'Laboratory result',
    'physical_exam_form' => 'Physical examination form',
    'medical_clearance'  => 'Medical clearance',
    'other'              => 'Other',
];

// Accepted file types. The type is read from the file's content, not from its name.
const ALLOWED_MIME_TYPES = [
    'application/pdf' => 'pdf',
    'image/jpeg'      => 'jpg',
    'image/png'       => 'png',
];

function documents_dir(): string
{
    return rtrim(str_replace('\\', '/', STORAGE_PATH), '/') . '/documents';
}

// Checks one uploaded file. Returns [list of errors, detected mime type or null].
function check_uploaded_file(array $file): array
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($error === UPLOAD_ERR_NO_FILE) {
        return [['Choose a file to upload.'], null];
    }
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        return [['The file is larger than the server allows. The limit is 5 MB.'], null];
    }
    if ($error !== UPLOAD_ERR_OK) {
        return [['The file could not be uploaded. Please try again.'], null];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return [['The upload is not valid.'], null];
    }
    if ($file['size'] <= 0 || $file['size'] > UPLOAD_MAX_BYTES) {
        return [['The file must be between 1 byte and 5 MB.'], null];
    }

    // fileinfo reads the first bytes of the file to find its real type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!is_string($mime) || !isset(ALLOWED_MIME_TYPES[$mime])) {
        return [['Only PDF, JPG, and PNG files are accepted.'], null];
    }

    return [[], $mime];
}

// Moves the uploaded file into storage under a random name. Returns that name.
function store_uploaded_file(string $tmpPath, string $mime): string
{
    $dir = documents_dir();
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('The storage folder cannot be created.');
    }

    $name = bin2hex(random_bytes(16)) . '.' . ALLOWED_MIME_TYPES[$mime];
    if (!move_uploaded_file($tmpPath, $dir . '/' . $name)) {
        throw new RuntimeException('The file could not be moved into storage.');
    }
    return $name;
}

// Returns the full path to a stored document, or null if the name is unsafe or missing.
function document_file_path(string $storedName): ?string
{
    // Only names made by store_uploaded_file() are accepted. This blocks path tricks like "../".
    if (!preg_match('/^[a-f0-9]{32}\.(pdf|jpg|png)$/', $storedName)) {
        return null;
    }
    $path = documents_dir() . '/' . $storedName;
    return is_file($path) ? $path : null;
}

// Sends one notification to every approved staff member, linking to the documents page
function notify_staff_documents(PDO $pdo, string $title, string $message): void
{
    $staffIds = $pdo->query(
        "SELECT id FROM users WHERE role = 'staff' AND account_status = 'approved'"
    )->fetchAll(PDO::FETCH_COLUMN);

    foreach ($staffIds as $staffId) {
        add_notification($pdo, (int) $staffId, 'document', $title, $message, 'staff/documents.php');
    }
}

// Saves a validated upload: file to storage, record to the database, and a staff notification.
// If the database step fails, the stored file is removed.
function save_document_upload(PDO $pdo, int $studentId, string $type, array $file, string $mime): array
{
    try {
        $stored = store_uploaded_file($file['tmp_name'], $mime);
    } catch (RuntimeException $e) {
        error_log('Document storage failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'The file could not be saved. Please try again.'];
    }

    $originalName = mb_substr(basename($file['name']), 0, 255);

    try {
        $pdo->beginTransaction();

        $insert = $pdo->prepare(
            'INSERT INTO medical_documents
               (student_user_id, document_type, original_filename, stored_filename, mime_type, file_size)
             VALUES (:student, :type, :original, :stored, :mime, :size)'
        );
        $insert->execute([
            ':student'  => $studentId,
            ':type'     => $type,
            ':original' => $originalName,
            ':stored'   => $stored,
            ':mime'     => $mime,
            ':size'     => (int) $file['size'],
        ]);

        notify_staff_documents($pdo, 'New medical document',
            'A student uploaded a ' . strtolower(DOCUMENT_TYPES[$type]) . ' for review.');

        $pdo->commit();
        return ['ok' => true, 'message' => 'Document uploaded. Clinic staff will review it.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        @unlink(documents_dir() . '/' . $stored);
        error_log('Document record failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'The document could not be saved. Please try again.'];
    }
}

// Staff decision on a pending document. Returns ['ok' => bool, 'message' => string].
function review_document(PDO $pdo, int $staffId, int $documentId, string $action, string $note): array
{
    if (!in_array($action, ['approve', 'reject'], true)) {
        return ['ok' => false, 'message' => 'Unknown action.'];
    }
    if ($action === 'reject' && $note === '') {
        return ['ok' => false, 'message' => 'Enter a note explaining why the document was rejected.'];
    }

    $newStatus = $action === 'approve' ? 'approved' : 'rejected';

    try {
        $pdo->beginTransaction();

        $lock = $pdo->prepare(
            'SELECT id, student_user_id, original_filename, review_status
             FROM medical_documents
             WHERE id = :id
             FOR UPDATE'
        );
        $lock->execute([':id' => $documentId]);
        $doc = $lock->fetch();

        if ($doc === false) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'That document was not found.'];
        }
        if ($doc['review_status'] !== 'pending') {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'This document has already been reviewed.'];
        }

        $update = $pdo->prepare(
            'UPDATE medical_documents
             SET review_status = :status, review_note = :note,
                 reviewed_by = :staff, reviewed_at = NOW()
             WHERE id = :id'
        );
        $update->execute([
            ':status' => $newStatus,
            ':note'   => $note !== '' ? $note : null,
            ':staff'  => $staffId,
            ':id'     => $documentId,
        ]);

        $message = $action === 'approve'
            ? 'Your document "' . $doc['original_filename'] . '" was approved.'
            : 'Your document "' . $doc['original_filename'] . '" was rejected. Reason: ' . $note;
        add_notification($pdo, (int) $doc['student_user_id'], 'document',
            'Document ' . $newStatus, $message, 'student/documents.php');

        $pdo->commit();
        return ['ok' => true, 'message' => $action === 'approve' ? 'Document approved.' : 'Document rejected.'];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Document review failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'The decision could not be saved. Please try again.'];
    }
}