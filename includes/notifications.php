<?php
// File: includes/notifications.php
// In-system notifications: unread counts, listing, read status, and safe links.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

// Display names for each notification type
const NOTIFICATION_TYPE_LABELS = [
    'registration'         => 'Registration',
    'appointment'          => 'Appointment',
    'appointment_reminder' => 'Reminder',
    'document'             => 'Document',
    'result'               => 'Check-up',
    'message'              => 'Message',
    'announcement'         => 'Announcement',
];

// Number of unread notifications for one user
function unread_notification_count(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :id AND is_read = 0');
    $stmt->execute([':id' => $userId]);
    return (int) $stmt->fetchColumn();
}

// The user's newest notifications. The limit is an integer, so it is safe to place in the SQL text.
function list_notifications(PDO $pdo, int $userId, int $limit = 100): array
{
    $stmt = $pdo->prepare(
        'SELECT id, notification_type, title, message, link, is_read, created_at
         FROM notifications
         WHERE user_id = :id
         ORDER BY created_at DESC, id DESC
         LIMIT ' . $limit
    );
    $stmt->execute([':id' => $userId]);
    return $stmt->fetchAll();
}

// Marks one notification read. The user_id check means a user cannot change another user's notification.
function mark_notification_read(PDO $pdo, int $userId, int $notificationId): void
{
    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user');
    $stmt->execute([':id' => $notificationId, ':user' => $userId]);
}

// Marks every unread notification for this user read. Returns how many changed.
function mark_all_notifications_read(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :id AND is_read = 0');
    $stmt->execute([':id' => $userId]);
    return $stmt->rowCount();
}

// Returns the link only if it points to a page inside MajorCare, such as "student/appointments.php".
// Anything else, including full web addresses, falls back to the notification page.
function safe_notification_link(?string $link): string
{
    if ($link !== null && preg_match('/^(student|staff|public)\/[a-z_]+\.php(\?[A-Za-z0-9=&_-]*)?$/', $link)) {
        return $link;
    }
    return 'public/notifications.php';
}

function notification_time(string $dateTime): string
{
    return date('M j, Y g:i A', strtotime($dateTime));
}