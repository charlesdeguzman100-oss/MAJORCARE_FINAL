<?php
// File: includes/results.php
// Laboratory and examination result rules: upload, storage, publishing, and notifications.
// Result files are stored in STORAGE_PATH/results, outside htdocs.
// public/lab_result.php serves them after the access check.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/appointments.php';  // add_notification()
require_once __DIR__ . '/documents.php';     // check_uploaded_file(), ALLOWED_MIME_TYPES

function results_dir(): string
{
    return rtrim(str_replace('\\', '/', STORAGE_PATH), '/') . '/results';
}

// Moves an uploaded result file into storage under a random name. Returns that name.
function store_result_file(string $tmpPath, string $mime): string
{
    $dir = results_dir();
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('The results storage folder cannot be created.');
    }

    $name = bin2hex(random_bytes(16)) . '.' . ALLOWED_MIME_TYPES[$mime];
    if (!move_uploaded_file($tmpPath, $dir . '/' . $name)) {
        throw new RuntimeException('The result file could not be moved into storage.');
    }
    return $name;
}

// Full path to a stored result file, or null if the name is unsafe or missing
function lab_result_file_path(string $storedName): ?string
{
    if (!preg_match('/^[a-f0-9]{32}\.(pdf|jpg|png)$/', $storedName)) {
        return null;
    }
    $path = results_dir() . '/' . $storedName;
    return is_file($path) ? $path : null;
}

// Checks the result form fields. Returns a list of errors.
function lab_result_errors(string $type, string $date, string $description): array
{
    $errors = [];

    if ($type === '' || mb_strlen($type) > 120) {
        $errors[] = 'Enter the result type (120 characters or fewer).';
    }

    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
        $errors[] = 'Enter the result date as a real calendar date.';
    } elseif ($date > date('Y-m-d')) {
        $errors[] = 'The result date cannot be in the future.';
    }

    if (mb_strlen($description) > 500) {
        $errors[] = 'Description must be 500 characters or fewer.';
    }

    return $errors;
}

// Saves an uploaded result as unpublished. If the database step fails, the file is removed.
function save_lab_result(
    PDO $pdo,
    int $studentId,
    int $staffId,
    string $type,
    string $date,
    string $description,
    array $file,
    string $mime
): array {
    try {
        $stored = store_result_file($file['tmp_name'], $mime);
    } catch (RuntimeException $e) {
        error_log('Result storage failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'The file could not be saved. Please try again.'];
    }

    $originalName = mb_substr(basename($file['name']), 0, 255);

    try {
        $pdo->beginTransaction();

        $insert = $pdo->prepare(
            'INSERT INTO laboratory_results
               (student_user_id, result_type, result_date, description,
                original_filename, stored_filename, mime_type, file_size, is_published, uploaded_by)
             VALUES (:student, :type, :date, :description,
                     :original, :stored, :mime, :size, 0, :staff)'
        );
        $insert->execute([
            ':student'     => $studentId,
            ':type'        => $type,
            ':date'        => $date,
            ':description' => $description !== '' ? $description : null,
            ':original'    => $originalName,
            ':stored'      => $stored,
            ':mime'        => $mime,
            ':size'        => (int) $file['size'],
            ':staff'       => $staffId,
        ]);

        $pdo->commit();
        return ['ok' => true, 'message' => 'Result saved as unpublished. Publish it to make it visible to the student.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        @unlink(results_dir() . '/' . $stored);
        error_log('Result record failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'The result could not be saved. Please try again.'];
    }
}

// Publishes a result, or unpublishes it. Publishing notifies the student.
function set_lab_result_status(PDO $pdo, int $studentId, int $resultId, string $action): array
{
    if (!in_array($action, ['publish', 'unpublish'], true)) {
        return ['ok' => false, 'message' => 'Unknown action.'];
    }

    try {
        $pdo->beginTransaction();

        $lock = $pdo->prepare(
            'SELECT is_published, result_type, result_date FROM laboratory_results
             WHERE id = :id AND student_user_id = :student
             FOR UPDATE'
        );
        $lock->execute([':id' => $resultId, ':student' => $studentId]);
        $row = $lock->fetch();

        if ($row === false) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'That result was not found.'];
        }

        if ($action === 'publish') {
            if ((int) $row['is_published'] === 1) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'This result is already published.'];
            }

            $update = $pdo->prepare(
                'UPDATE laboratory_results SET is_published = 1, published_at = NOW() WHERE id = :id'
            );
            $update->execute([':id' => $resultId]);

            add_notification($pdo, $studentId, 'result', 'New laboratory result',
                'A ' . $row['result_type'] . ' result dated '
                . date('F j, Y', strtotime($row['result_date'])) . ' is available.',
                'student/results.php');

            $message = 'Result published.';
        } else {
            if ((int) $row['is_published'] !== 1) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'This result is not published.'];
            }

            $update = $pdo->prepare(
                'UPDATE laboratory_results SET is_published = 0, published_at = NULL WHERE id = :id'
            );
            $update->execute([':id' => $resultId]);

            $message = 'Result unpublished. The student cannot see it until you publish it again.';
        }

        $pdo->commit();
        return ['ok' => true, 'message' => $message];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Result status change failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'The change could not be saved. Please try again.'];
    }
}