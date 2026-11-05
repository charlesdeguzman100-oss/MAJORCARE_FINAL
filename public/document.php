<?php
// File: public/document.php
// Serves one stored document after checking who is asking.
// Files are never linked directly, so this page is the only route to them.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/documents.php';

// Stops the request with a plain-text message
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

$docId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT student_user_id, original_filename, stored_filename, mime_type, review_status
     FROM medical_documents
     WHERE id = :id'
);
$stmt->execute([':id' => $docId]);
$doc = $stmt->fetch();

if ($doc === false) {
    deny(404, 'Document not found.');
}

if ($user['role'] === 'student') {
    if ((int) $doc['student_user_id'] !== (int) $user['id']) {
        deny(403, 'You do not have access to this document.');
    }
    if ($doc['review_status'] !== 'approved') {
        deny(403, 'This document has not been approved yet.');
    }
} elseif ($user['role'] !== 'staff') {
    deny(403, 'You do not have access to this document.');
}

$path = document_file_path($doc['stored_filename']);
if ($path === null) {
    deny(404, 'The file is missing from storage.');
}

// Send the file. Inline display lets the browser show it in a new tab.
header('Content-Type: ' . $doc['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . str_replace('"', '', $doc['original_filename']) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
exit;