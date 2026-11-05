<?php
// File: database/test_connection.php
// Phase 1 test. Confirms that PHP connects to MySQL and that all 16 tables exist.
//
// Command: C:\xampp\php\php.exe C:\xampp\htdocs\majorcare\database\test_connection.php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only run from the command line.');
}

require_once __DIR__ . '/../config/database.php';

$expectedTables = [
    'users', 'student_profiles', 'staff_profiles', 'health_information',
    'vaccination_records', 'appointment_slots', 'appointments', 'clinic_visits',
    'medical_documents', 'laboratory_results', 'conversations', 'messages',
    'notifications', 'clinic_information', 'clinic_faqs', 'announcements',
];

try {
    $pdo = getDatabase();
} catch (RuntimeException $e) {
    exit('FAILED: ' . $e->getMessage() . "\n");
}

$version = $pdo->query('SELECT VERSION()')->fetchColumn();
echo "Connected. Database server version: $version\n";

$foundTables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo 'Tables found: ' . count($foundTables) . " of " . count($expectedTables) . "\n";

$missing = array_diff($expectedTables, $foundTables);
if (!empty($missing)) {
    echo 'FAILED. Missing tables: ' . implode(', ', $missing) . "\n";
    exit(1);
}

$staffCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'staff'")->fetchColumn();
echo "Staff accounts: $staffCount\n";
echo "PASSED. All expected tables exist.\n";