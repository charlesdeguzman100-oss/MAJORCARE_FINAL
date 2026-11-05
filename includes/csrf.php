<?php
// File: includes/csrf.php
// CSRF protection. Every POST form includes a hidden token that must match
// the token stored in the user's session. This blocks forms submitted from other websites.

require_once __DIR__ . '/helpers.php';

function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_is_valid(): bool
{
    $submitted = $_POST['csrf_token'] ?? '';
    return is_string($submitted) && $submitted !== ''
        && hash_equals(csrf_token(), $submitted);
}