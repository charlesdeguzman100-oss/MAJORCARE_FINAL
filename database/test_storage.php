<?php
// File: database/test_storage.php
// Checks that the storage folders exist, are writable, and are outside htdocs.
//
// Command: C:\xampp\php\php.exe C:\xampp\htdocs\majorcare\database\test_storage.php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only run from the command line.');
}

require_once __DIR__ . '/../config/app.php';

$storage = rtrim(str_replace('\\', '/', STORAGE_PATH), '/');
$htdocs  = str_replace('\\', '/', realpath(dirname(__DIR__, 2)));

$results = [];
$results['Storage folder exists']      = is_dir($storage);
$results['documents folder exists']    = is_dir($storage . '/documents');
$results['results folder exists']      = is_dir($storage . '/results');
$results['Storage is outside htdocs']  = strpos($storage, $htdocs . '/') !== 0;

// Write a temporary file, then delete it
$writeTest = $storage . '/write_test.tmp';
$results['Storage is writable'] = is_dir($storage)
    && file_put_contents($writeTest, 'test') !== false;
if (file_exists($writeTest)) {
    unlink($writeTest);
}

$allPassed = true;
foreach ($results as $label => $ok) {
    echo ($ok ? 'PASS  ' : 'FAIL  ') . $label . "\n";
    if (!$ok) {
        $allPassed = false;
    }
}

echo $allPassed ? "PASSED. Storage is ready.\n" : "FAILED. See the lines above.\n";
exit($allPassed ? 0 : 1);