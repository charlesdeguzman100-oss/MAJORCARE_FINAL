<?php
// File: database/create_staff.php
// Creates the first Head Nurse / Clinic Staff account.
// Run from the command line only. The browser cannot run this file.
//
// Command: C:\xampp\php\php.exe C:\xampp\htdocs\majorcare\database\create_staff.php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only run from the command line.');
}

require_once __DIR__ . '/../config/database.php';

echo "Create the initial clinic staff account\n";
echo "Note: the password is visible while you type it.\n\n";

echo 'Staff ID: ';
$schoolId = trim((string) fgets(STDIN));

echo 'Full name: ';
$fullName = trim((string) fgets(STDIN));

echo 'Password (at least 8 characters): ';
$password = rtrim((string) fgets(STDIN), "\r\n"); // do not trim spaces inside a password

echo 'Confirm password: ';
$confirm = rtrim((string) fgets(STDIN), "\r\n");

if ($schoolId === '' || $fullName === '') {
    exit("Staff ID and full name are required.\n");
}
if (strlen($password) < 8) {
    exit("The password must be at least 8 characters.\n");
}
if ($password !== $confirm) {
    exit("The passwords do not match.\n");
}

$pdo = getDatabase();

// Check for an existing account with this ID
$check = $pdo->prepare('SELECT id FROM users WHERE school_id = :school_id');
$check->execute([':school_id' => $schoolId]);
if ($check->fetch()) {
    exit("An account with that staff ID already exists.\n");
}

// Store a hash, never the password itself
$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    // A transaction saves both rows or neither
    $pdo->beginTransaction();

    $insertUser = $pdo->prepare(
        "INSERT INTO users (school_id, password_hash, role, account_status)
         VALUES (:school_id, :hash, 'staff', 'approved')"
    );
    $insertUser->execute([':school_id' => $schoolId, ':hash' => $hash]);
    $userId = (int) $pdo->lastInsertId();

    $insertProfile = $pdo->prepare(
        "INSERT INTO staff_profiles (user_id, full_name)
         VALUES (:user_id, :full_name)"
    );
    $insertProfile->execute([':user_id' => $userId, ':full_name' => $fullName]);

    $pdo->commit();
    echo "Staff account created. You can now log in with staff ID $schoolId.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('create_staff failed: ' . $e->getMessage());
    exit("The account could not be created. Check the error log.\n");
}