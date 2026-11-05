<?php
// File: includes/messages.php
// Private messages between one student and clinic staff.
// Each student has one conversation. A message stays unread until the other party opens the thread.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/appointments.php'; // add_notification()

const MESSAGE_MAX_LENGTH = 2000;

// Returns the conversation ID for a student, creating the conversation the first time.
function get_or_create_conversation(PDO $pdo, int $studentId): int
{
    $find = $pdo->prepare('SELECT id FROM conversations WHERE student_user_id = :id');
    $find->execute([':id' => $studentId]);
    $id = $find->fetchColumn();

    if ($id === false) {
        // The unique key on student_user_id prevents a duplicate if two requests arrive together
        $create = $pdo->prepare(
            'INSERT INTO conversations (student_user_id) VALUES (:id)
             ON DUPLICATE KEY UPDATE student_user_id = student_user_id'
        );
        $create->execute([':id' => $studentId]);

        $find->execute([':id' => $studentId]);
        $id = $find->fetchColumn();
    }

    return (int) $id;
}

// Tells every approved staff member that a student sent a message
function notify_staff_of_message(PDO $pdo, int $studentId): void
{
    $staffIds = $pdo->query(
        "SELECT id FROM users WHERE role = 'staff' AND account_status = 'approved'"
    )->fetchAll(PDO::FETCH_COLUMN);

    foreach ($staffIds as $staffId) {
        add_notification($pdo, (int) $staffId, 'message', 'New message from a student',
            'A student sent you a message.', 'staff/message_thread.php?student=' . $studentId);
    }
}

// Saves one message and notifies the other party.
// $senderRole is 'student' or 'staff'. Returns ['ok' => bool, 'message' => string].
function send_message(PDO $pdo, int $studentId, int $senderId, string $senderRole, string $body): array
{
    $body = trim($body);
    if ($body === '' || mb_strlen($body) > MESSAGE_MAX_LENGTH) {
        return ['ok' => false, 'message' => 'Enter a message of 1 to 2000 characters.'];
    }

    try {
        $pdo->beginTransaction();

        $conversationId = get_or_create_conversation($pdo, $studentId);

        $insert = $pdo->prepare(
            'INSERT INTO messages (conversation_id, sender_user_id, body)
             VALUES (:conversation, :sender, :body)'
        );
        $insert->execute([
            ':conversation' => $conversationId,
            ':sender'       => $senderId,
            ':body'         => $body,
        ]);

        $touch = $pdo->prepare('UPDATE conversations SET last_message_at = NOW() WHERE id = :id');
        $touch->execute([':id' => $conversationId]);

        if ($senderRole === 'student') {
            notify_staff_of_message($pdo, $studentId);
        } else {
            add_notification($pdo, $studentId, 'message', 'New message from clinic staff',
                'You have a new message from clinic staff.', 'student/messages.php');
        }

        $pdo->commit();
        return ['ok' => true, 'message' => 'Message sent.'];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Message send failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'The message could not be sent. Please try again.'];
    }
}

// Marks the other party's messages as read when this party opens the thread.
// $readerRole is 'student' or 'staff'.
function mark_messages_read(PDO $pdo, int $conversationId, string $readerRole): void
{
    $otherRole = $readerRole === 'student' ? 'staff' : 'student';

    $stmt = $pdo->prepare(
        'UPDATE messages m
         JOIN users u ON u.id = m.sender_user_id
         SET m.is_read = 1
         WHERE m.conversation_id = :conversation AND u.role = :other AND m.is_read = 0'
    );
    $stmt->execute([':conversation' => $conversationId, ':other' => $otherRole]);
}

// All messages in a conversation, oldest first, with each sender's name and role
function conversation_messages(PDO $pdo, int $conversationId): array
{
    $stmt = $pdo->prepare(
        'SELECT m.id, m.body, m.created_at, u.role AS sender_role,
                COALESCE(sp.full_name, stp.full_name) AS sender_name
         FROM messages m
         JOIN users u ON u.id = m.sender_user_id
         LEFT JOIN student_profiles sp ON sp.user_id = u.id
         LEFT JOIN staff_profiles stp ON stp.user_id = u.id
         WHERE m.conversation_id = :conversation
         ORDER BY m.created_at ASC, m.id ASC'
    );
    $stmt->execute([':conversation' => $conversationId]);
    return $stmt->fetchAll();
}

// Unread student messages, counted for the staff dashboard
function unread_messages_for_staff(PDO $pdo): int
{
    return (int) $pdo->query(
        "SELECT COUNT(*)
         FROM messages m
         JOIN users u ON u.id = m.sender_user_id
         WHERE u.role = 'student' AND m.is_read = 0"
    )->fetchColumn();
}

// Unread staff replies, counted for one student
function unread_messages_for_student(PDO $pdo, int $studentId): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM messages m
         JOIN conversations c ON c.id = m.conversation_id
         JOIN users u ON u.id = m.sender_user_id
         WHERE c.student_user_id = :id AND u.role = 'staff' AND m.is_read = 0"
    );
    $stmt->execute([':id' => $studentId]);
    return (int) $stmt->fetchColumn();
}