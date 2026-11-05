<?php
// File: config/app.php
// Application-wide settings used by every other file.

define('APP_NAME', 'MajorCare');
define('APP_DEBUG', true); // Set to false before the final demonstration.
define('APP_TIMEZONE', 'Asia/Manila');

// Uploaded files live outside htdocs, so Apache cannot serve them directly.
// Phase 6 serves them through PHP after it checks the user's permissions.
define('STORAGE_PATH', 'C:/xampp/majorcare_storage');

define('UPLOAD_MAX_BYTES', 5 * 1024 * 1024); // 5 MB

date_default_timezone_set(APP_TIMEZONE);