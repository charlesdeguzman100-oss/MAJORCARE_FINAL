<?php
// File: public/logout.php
// Ends the session. Accepts POST requests with a valid CSRF token only.

require_once __DIR__ . '/../includes/auth.php';

start_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_is_valid()) {
    set_flash('error', 'That logout request was not valid.');
    redirect('public/index.php');
}

logout_user();
set_flash('success', 'You have logged out.');
redirect('public/index.php');