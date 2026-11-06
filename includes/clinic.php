<?php
// File: includes/clinic.php
// Clinic information, FAQs, and announcements.
// Clinic information is a single row (id = 1). Staff edit it. It is never deleted.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/appointments.php'; // add_notification()

// Empty text is stored as NULL
function blank_to_null(string $value): ?string
{
    return $value === '' ? null : $value;
}

// ---------- Clinic information ----------

function get_clinic_information(PDO $pdo): array
{
    $row = $pdo->query(
        'SELECT clinic_name, location, operating_hours, contact_number, contact_email
         FROM clinic_information
         WHERE id = 1'
    )->fetch();

    return $row ?: [
        'clinic_name'     => 'University Campus Clinic',
        'location'        => null,
        'operating_hours' => null,
        'contact_number'  => null,
        'contact_email'   => null,
    ];
}

// Returns a list of problems with the clinic information form
function clinic_information_errors(array $in): array
{
    $errors = [];

    if ($in['clinic_name'] === '' || mb_strlen($in['clinic_name']) > 120) {
        $errors[] = 'Enter the clinic name (120 characters or fewer).';
    }
    if (mb_strlen($in['location']) > 255) {
        $errors[] = 'Location must be 255 characters or fewer.';
    }
    if (mb_strlen($in['operating_hours']) > 255) {
        $errors[] = 'Operating hours must be 255 characters or fewer.';
    }
    if ($in['contact_number'] !== '' && !preg_match('/^[0-9+()\- ]{7,40}$/', $in['contact_number'])) {
        $errors[] = 'Contact number must be 7 to 40 characters: digits, spaces, +, (, ), or dashes.';
    }
    if ($in['contact_email'] !== ''
        && (mb_strlen($in['contact_email']) > 120 || !filter_var($in['contact_email'], FILTER_VALIDATE_EMAIL))) {
        $errors[] = 'Enter a valid contact email, or leave it empty.';
    }

    return $errors;
}

function save_clinic_information(PDO $pdo, int $staffId, array $in): array
{
    try {
        $stmt = $pdo->prepare(
            'UPDATE clinic_information
             SET clinic_name = :name, location = :location, operating_hours = :hours,
                 contact_number = :number, contact_email = :email, updated_by = :staff
             WHERE id = 1'
        );
        $stmt->execute([
            ':name'     => $in['clinic_name'],
            ':location' => blank_to_null($in['location']),
            ':hours'    => blank_to_null($in['operating_hours']),
            ':number'   => blank_to_null($in['contact_number']),
            ':email'    => blank_to_null($in['contact_email']),
            ':staff'    => $staffId,
        ]);
        return ['ok' => true, 'message' => 'Clinic information saved.'];
    } catch (PDOException $e) {
        error_log('Clinic information save failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'The clinic information could not be saved. Please try again.'];
    }
}

// ---------- FAQs ----------

function faq_errors(string $question, string $answer, string $order): array
{
    $errors = [];

    if ($question === '' || mb_strlen($question) > 255) {
        $errors[] = 'Enter the question (255 characters or fewer).';
    }
    if ($answer === '' || mb_strlen($answer) > 5000) {
        $errors[] = 'Enter the answer (5000 characters or fewer).';
    }
    if (filter_var($order, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 999]]) === false) {
        $errors[] = 'Display order must be a whole number from 0 to 999.';
    }

    return $errors;
}

// Adds a new FAQ when $faqId is null. Otherwise it updates the existing FAQ.
function save_faq(PDO $pdo, int $staffId, ?int $faqId, string $question, string $answer, int $order, bool $published): array
{
    try {
        if ($faqId === null) {
            $stmt = $pdo->prepare(
                'INSERT INTO clinic_faqs (question, answer, display_order, is_published, created_by)
                 VALUES (:q, :a, :o, :p, :staff)'
            );
            $stmt->execute([
                ':q'     => $question,
                ':a'     => $answer,
                ':o'     => $order,
                ':p'     => $published ? 1 : 0,
                ':staff' => $staffId,
            ]);
            return ['ok' => true, 'message' => 'FAQ added.'];
        }

        $stmt = $pdo->prepare(
            'UPDATE clinic_faqs
             SET question = :q, answer = :a, display_order = :o, is_published = :p
             WHERE id = :id'
        );
        $stmt->execute([
            ':q'  => $question,
            ':a'  => $answer,
            ':o'  => $order,
            ':p'  => $published ? 1 : 0,
            ':id' => $faqId,
        ]);
        return ['ok' => true, 'message' => 'FAQ updated.'];
    } catch (PDOException $e) {
        error_log('FAQ save failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'The FAQ could not be saved. Please try again.'];
    }
}

function delete_faq(PDO $pdo, int $faqId): array
{
    $stmt = $pdo->prepare('DELETE FROM clinic_faqs WHERE id = :id');
    $stmt->execute([':id' => $faqId]);

    return $stmt->rowCount() === 1
        ? ['ok' => true, 'message' => 'FAQ deleted.']
        : ['ok' => false, 'message' => 'That FAQ was not found.'];
}

// ---------- Announcements ----------

function announcement_errors(string $title, string $body): array
{
    $errors = [];

    if ($title === '' || mb_strlen($title) > 150) {
        $errors[] = 'Enter a title (150 characters or fewer).';
    }
    if ($body === '' || mb_strlen($body) > 5000) {
        $errors[] = 'Enter the announcement text (5000 characters or fewer).';
    }

    return $errors;
}

// Adds a new announcement as a draft when $announcementId is null.
// Otherwise it updates the text. Editing never changes whether the announcement is published.
function save_announcement(PDO $pdo, int $staffId, ?int $announcementId, string $title, string $body): array
{
    try {
        if ($announcementId === null) {
            $stmt = $pdo->prepare(
                'INSERT INTO announcements (title, body, is_published, created_by)
                 VALUES (:title, :body, 0, :staff)'
            );
            $stmt->execute([':title' => $title, ':body' => $body, ':staff' => $staffId]);
            return ['ok' => true, 'message' => 'Announcement saved as a draft. Publish it to notify students.'];
        }

        $stmt = $pdo->prepare(
            'UPDATE announcements SET title = :title, body = :body WHERE id = :id'
        );
        $stmt->execute([':title' => $title, ':body' => $body, ':id' => $announcementId]);
        return ['ok' => true, 'message' => 'Announcement updated.'];
    } catch (PDOException $e) {
        error_log('Announcement save failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'The announcement could not be saved. Please try again.'];
    }
}

// Publishing sends one notification to each approved student. Unpublishing sends none.
function set_announcement_published(PDO $pdo, int $announcementId, bool $publish): array
{
    try {
        $pdo->beginTransaction();

        $lock = $pdo->prepare('SELECT title, is_published FROM announcements WHERE id = :id FOR UPDATE');
        $lock->execute([':id' => $announcementId]);
        $row = $lock->fetch();

        if ($row === false) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'That announcement was not found.'];
        }

        if ($publish) {
            if ((int) $row['is_published'] === 1) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'This announcement is already published.'];
            }

            $update = $pdo->prepare(
                'UPDATE announcements SET is_published = 1, published_at = NOW() WHERE id = :id'
            );
            $update->execute([':id' => $announcementId]);

            $students = $pdo->query(
                "SELECT id FROM users WHERE role = 'student' AND account_status = 'approved'"
            )->fetchAll(PDO::FETCH_COLUMN);

            foreach ($students as $studentId) {
                add_notification($pdo, (int) $studentId, 'announcement', 'Clinic announcement',
                    $row['title'], 'student/clinic.php');
            }

            $message = 'Announcement published. ' . count($students) . ' student(s) notified.';
        } else {
            if ((int) $row['is_published'] !== 1) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'This announcement is not published.'];
            }

            $update = $pdo->prepare(
                'UPDATE announcements SET is_published = 0, published_at = NULL WHERE id = :id'
            );
            $update->execute([':id' => $announcementId]);

            $message = 'Announcement unpublished. Students can no longer see it.';
        }

        $pdo->commit();
        return ['ok' => true, 'message' => $message];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Announcement status change failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'The change could not be saved. Please try again.'];
    }
}

function delete_announcement(PDO $pdo, int $announcementId): array
{
    $stmt = $pdo->prepare('DELETE FROM announcements WHERE id = :id');
    $stmt->execute([':id' => $announcementId]);

    return $stmt->rowCount() === 1
        ? ['ok' => true, 'message' => 'Announcement deleted.']
        : ['ok' => false, 'message' => 'That announcement was not found.'];
}