<?php
// File: config/database.php
// Creates one shared PDO connection to the MajorCare database.
// Every module calls getDatabase() instead of building its own connection.

// Block direct browser access to this file
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/app.php';

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'majorcare');
define('DB_USER', 'root');   // XAMPP default user
define('DB_PASS', '');       // XAMPP default password is empty. Change it if you set one.
define('DB_CHARSET', 'utf8mb4');

function getDatabase(): PDO
{
    // static keeps one connection for the whole request
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT
         . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // errors throw exceptions
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // rows come back as arrays
        PDO::ATTR_EMULATE_PREPARES   => false,                  // real prepared statements
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // Log the real reason. Show only a generic message unless debugging.
        error_log('Database connection failed: ' . $e->getMessage());
        throw new RuntimeException(
            APP_DEBUG ? 'Database connection failed: ' . $e->getMessage()
                      : 'The system cannot connect to the database.'
        );
    }

    return $pdo;
}