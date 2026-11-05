<?php
// File: public/lab_result.php
// Serves one laboratory result file after the access check.
// Students see only their own published results. Staff see any result.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/results.php';

function deny(int $code, string $message): void
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=UTF-8');
    exit($message);
}

$user = require_login();
$pdo = getDatabase();

if ($user['role'] === 'student') {
    if ((int) $user['must_change_password'] === 1) {
        deny(403, 'Finish your account setup first.');
    }
    if ($user['consent_accepted_at'] === null) {
        deny(403, 'Accept the privacy notice first.');
    }
}

$resultId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT student_user_id, original_filename, stored_filename, mime_type, is_published
     FROM laboratory_results
     WHERE id = :id'
);
$stmt->execute([':id' => $resultId]);
$result = $stmt->fetch();

if ($result === false) {
    deny(404, 'Result not found.');
}

if ($user['role'] === 'student') {
    if ((int) $result['student_user_id'] !== (int) $user['id']) {
        deny(403, 'You do not have access to this result.');
    }
    if ((int) $result['is_published'] !== 1) {
        deny(403, 'This result has not been published yet.');
    }
} elseif ($user['role'] !== 'staff') {
    deny(403, 'You do not have access to this result.');
}

$path = lab_result_file_path($result['stored_filename']);
if ($path === null) {
    deny(404, 'The file is missing from storage.');
}

header('Content-Type: ' . $result['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . str_replace('"', '', $result['original_filename']) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
exit;