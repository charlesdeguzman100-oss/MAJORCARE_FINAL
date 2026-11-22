<?php
// File: public/submission_photo.php
// Serves one registration's ID photo. Approved clinic staff only.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/submissions.php';

require_role('staff');
$pdo = getDatabase();

function photo_deny(int $code, string $message): void
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=UTF-8');
    exit($message);
}

$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT photo_stored_name, photo_original_name, photo_mime
     FROM registration_submissions WHERE id = :id'
);
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();

if ($row === false) {
    photo_deny(404, 'Photo not found.');
}

$path = id_photo_path($row['photo_stored_name']);
if ($path === null) {
    photo_deny(404, 'The photo is missing from storage.');
}

header('Content-Type: ' . $row['photo_mime']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . str_replace('"', '', $row['photo_original_name']) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
exit;