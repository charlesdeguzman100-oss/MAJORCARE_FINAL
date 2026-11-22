<?php
// File: includes/auth.php
// Sessions, login, logout, and access checks.
//
// require_role() protects normal pages. It also sends students to the password
// change page and the consent page when those steps are not done yet.
// The setup pages call require_login() instead, so they can open before setup finishes.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';

function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// Returns the logged-in user, or null. The database is checked on every request,
// so a rejected or removed account loses access at once.
function current_user(): ?array
{
    start_session();

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = getDatabase()->prepare(
        'SELECT u.id, u.school_id, u.role, u.account_status, u.rejection_reason,
                u.must_change_password,
                COALESCE(sp.full_name, stp.full_name) AS full_name,
                sp.consent_accepted_at
         FROM users u
         LEFT JOIN student_profiles sp ON sp.user_id = u.id
         LEFT JOIN staff_profiles stp ON stp.user_id = u.id
         WHERE u.id = :id'
    );
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user === false) {
        logout_user();
        return null;
    }
    return $user;
}

// Requires a logged-in, approved user of any role. Used by the setup pages.
function require_login(): array
{
    $user = current_user();

    if ($user === null) {
        set_flash('error', 'Please log in to open that page.');
        redirect('public/index.php');
    }

    if ($user['account_status'] !== 'approved') {
            if ($user['account_status'] === 'removed') {
        $message = 'This account has been deactivated. Contact clinic staff for help.';
        } elseif ($user['account_status'] === 'rejected') {
        $message = 'Your registration was rejected. Reason: ' . ($user['rejection_reason'] ?? 'No reason given.');
        } else {
        $message = 'Your account is still waiting for clinic staff approval.';
        }
        logout_user();
        set_flash('error', $message);
        redirect('public/index.php');
    }

    return $user;
}

// Requires an approved user with the given role, and completes setup steps first.
function require_role(string $role): array
{
    $user = require_login();

    if ($user['role'] !== $role) {
        set_flash('error', 'You do not have permission to open that page.');
        redirect(dashboard_path($user['role']));
    }

    // A temporary password must be replaced before anything else opens.
    if ((int) $user['must_change_password'] === 1) {
        redirect('public/change_password.php');
    }

    // Students must accept the privacy notice before using any student page.
    if ($user['role'] === 'student' && $user['consent_accepted_at'] === null) {
        redirect('student/consent.php');
    }

    return $user;
}

function dashboard_path(string $role): string
{
    return $role === 'staff' ? 'staff/dashboard.php' : 'student/dashboard.php';
}

function login_user(int $userId): void
{
    start_session();
    // A new session ID at login prevents session fixation attacks.
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function logout_user(): void
{
    start_session();
    $_SESSION = [];
    session_destroy();
    // Start a fresh, empty session so a flash message can be shown after logout.
    session_start();
}