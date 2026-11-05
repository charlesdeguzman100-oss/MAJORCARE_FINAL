<?php
// File: includes/helpers.php
// Small functions used on every page.

require_once __DIR__ . '/../config/app.php';

const BASE_URL = '/majorcare';

// Escape text before printing it in HTML. This blocks script injection (XSS).
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Build a URL to a page in the project, for example url('student/dashboard.php').
function url(string $path): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

// One-time message shown on the next page.
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}