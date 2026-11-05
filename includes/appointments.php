<?php
// File: includes/appointments.php
// Appointment rules: booking with capacity, cancellation, staff decisions, and reminders.
//
// Each change runs inside a transaction. FOR UPDATE locks the slot row, so bookings
// for the same slot run one after another and cannot exceed its capacity.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

function now_datetime(): string
{
    return date('Y-m-d H:i:s');
}

// Combined start date and time of a slot, for comparisons
function slot_start(array $slot): string
{
    return $slot['slot_date'] . ' ' . $slot['start_time'];
}

// Readable label, for example "November 12, 2026, 9:00 AM to 9:30 AM"
function slot_label(array $slot): string
{
    return date('F j, Y', strtotime($slot['slot_date'])) . ', '
         . date('g:i A', strtotime($slot['start_time'])) . ' to '
         . date('g:i A', strtotime($slot['end_time']));
}

// Number of seats held in a slot. Pending, approved, and completed appointments hold a seat.
// Rejected and cancelled appointments release it.
function seats_taken(PDO $pdo, int $slotId): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM appointments
         WHERE slot_id = :id AND status IN ('pending', 'approved', 'completed')"
    );
    $stmt->execute([':id' => $slotId]);
    return (int) $stmt->fetchColumn();
}

function add_notification(
    PDO $pdo,
    int $userId,
    string $type,
    string $title,
    string $message,
    string $link,
    ?int $appointmentId = null
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO notifications (user_id, notification_type, title, message, link, appointment_id)
         VALUES (:user_id, :type, :title, :message, :link, :appointment_id)'
    );
    $stmt->execute([
        ':user_id'        => $userId,
        ':type'           => $type,
        ':title'          => mb_substr($title, 0, 150),
        ':message'        => mb_substr($message, 0, 500),
        ':link'           => $link,
        ':appointment_id' => $appointmentId,
    ]);
}

// Sends one notification to every approved staff member
function notify_staff(PDO $pdo, string $type, string $title, string $message, ?int $appointmentId): void
{
    $staffIds = $pdo->query(
        "SELECT id FROM users WHERE role = 'staff' AND account_status = 'approved'"
    )->fetchAll(PDO::FETCH_COLUMN);

    foreach ($staffIds as $staffId) {
        add_notification($pdo, (int) $staffId, $type, $title, $message,
            'staff/appointments.php', $appointmentId);
    }
}

// Books one seat in an open slot. Returns ['ok' => bool, 'message' => string].
function book_appointment(PDO $pdo, int $studentId, int $slotId, string $reason): array
{
    try {
        $pdo->beginTransaction();

        // Lock the slot. Other bookings for this slot wait until this one finishes.
        $lock = $pdo->prepare(
            'SELECT id, slot_date, start_time, end_time, status, capacity
             FROM appointment_slots
             WHERE id = :id
             FOR UPDATE'
        );
        $lock->execute([':id' => $slotId]);
        $slot = $lock->fetch();

        if ($slot === false || $slot['status'] !== 'open') {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'That time is no longer available. Choose another slot.'];
        }
        if (slot_start($slot) <= now_datetime()) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'That time has already passed. Choose another slot.'];
        }

        // One student holds at most one seat in a slot
        $existing = $pdo->prepare(
            "SELECT id FROM appointments
             WHERE slot_id = :slot AND student_user_id = :student
               AND status IN ('pending', 'approved', 'completed')"
        );
        $existing->execute([':slot' => $slotId, ':student' => $studentId]);
        if ($existing->fetch()) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'You already have a request for this time.'];
        }

        if (seats_taken($pdo, $slotId) >= (int) $slot['capacity']) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'That time is full. Choose another slot.'];
        }

        $insert = $pdo->prepare(
            "INSERT INTO appointments (slot_id, student_user_id, reason, status)
             VALUES (:slot_id, :student_id, :reason, 'pending')"
        );
        $insert->execute([
            ':slot_id'    => $slotId,
            ':student_id' => $studentId,
            ':reason'     => $reason,
        ]);
        $appointmentId = (int) $pdo->lastInsertId();

        $label = slot_label($slot);
        add_notification($pdo, $studentId, 'appointment', 'Appointment request submitted',
            'Your request for ' . $label . ' is pending clinic approval.',
            'student/appointments.php', $appointmentId);
        notify_staff($pdo, 'appointment', 'New appointment request',
            'A student requested ' . $label . '.', $appointmentId);

        $pdo->commit();
        return ['ok' => true, 'message' => 'Your appointment request was submitted. Status: pending.'];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Booking failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'The request could not be saved. Please try again.'];
    }
}

// Lets a student cancel their own pending or approved appointment before it starts.
// The seat is released automatically, because cancelled rows do not count as taken.
function cancel_by_student(PDO $pdo, int $studentId, int $appointmentId): array
{
    try {
        $pdo->beginTransaction();

        $lock = $pdo->prepare(
            'SELECT a.id, a.status, s.slot_date, s.start_time, s.end_time
             FROM appointments a
             JOIN appointment_slots s ON s.id = a.slot_id
             WHERE a.id = :id AND a.student_user_id = :student
             FOR UPDATE'
        );
        $lock->execute([':id' => $appointmentId, ':student' => $studentId]);
        $row = $lock->fetch();

        if ($row === false) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'That appointment was not found.'];
        }
        if (!in_array($row['status'], ['pending', 'approved'], true)) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Only pending or approved appointments can be cancelled.'];
        }
        if (slot_start($row) <= now_datetime()) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'This appointment has already started or passed, so it cannot be cancelled.'];
        }

        $cancel = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = :id");
        $cancel->execute([':id' => $appointmentId]);

        $label = slot_label($row);
        add_notification($pdo, $studentId, 'appointment', 'Appointment cancelled',
            'You cancelled your appointment for ' . $label . '.',
            'student/appointments.php', $appointmentId);
        notify_staff($pdo, 'appointment', 'Appointment cancelled by student',
            'A student cancelled the appointment for ' . $label . '.', $appointmentId);

        $pdo->commit();
        return ['ok' => true, 'message' => 'Your appointment was cancelled.'];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Student cancel failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'The appointment could not be cancelled. Please try again.'];
    }
}

// Staff decisions. Each action has allowed starting statuses and one result.
//   approve:  pending -> approved
//   reject:   pending -> rejected (note required; releases the seat)
//   cancel:   pending or approved -> cancelled (note required; releases the seat)
//   complete: approved -> completed
function staff_update_appointment(PDO $pdo, int $staffId, int $appointmentId, string $action, string $note): array
{
    $rules = [
        'approve'  => ['from' => ['pending'], 'to' => 'approved', 'note_required' => false, 'verb' => 'approved'],
        'reject'   => ['from' => ['pending'], 'to' => 'rejected', 'note_required' => true,  'verb' => 'rejected'],
        'cancel'   => ['from' => ['pending', 'approved'], 'to' => 'cancelled', 'note_required' => true, 'verb' => 'cancelled'],
        'complete' => ['from' => ['approved'], 'to' => 'completed', 'note_required' => false, 'verb' => 'marked completed'],
    ];

    if (!isset($rules[$action])) {
        return ['ok' => false, 'message' => 'Unknown action.'];
    }
    $rule = $rules[$action];

    if ($rule['note_required'] && $note === '') {
        return ['ok' => false, 'message' => 'Enter a note explaining the decision.'];
    }

    try {
        $pdo->beginTransaction();

        $lock = $pdo->prepare(
            'SELECT a.id, a.status, a.student_user_id,
                    s.slot_date, s.start_time, s.end_time
             FROM appointments a
             JOIN appointment_slots s ON s.id = a.slot_id
             WHERE a.id = :id
             FOR UPDATE'
        );
        $lock->execute([':id' => $appointmentId]);
        $row = $lock->fetch();

        if ($row === false) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'That appointment was not found.'];
        }
        if (!in_array($row['status'], $rule['from'], true)) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'This appointment cannot be changed from its current status (' . $row['status'] . ').'];
        }

        $update = $pdo->prepare(
            'UPDATE appointments
             SET status = :status, staff_note = :note, status_updated_by = :staff
             WHERE id = :id'
        );
        $update->execute([
            ':status' => $rule['to'],
            ':note'   => $note !== '' ? $note : null,
            ':staff'  => $staffId,
            ':id'     => $appointmentId,
        ]);

        $message = 'Your appointment for ' . slot_label($row) . ' was ' . $rule['verb'] . '.'
                 . ($note !== '' ? ' Note from clinic staff: ' . $note : '');
        add_notification($pdo, (int) $row['student_user_id'], 'appointment',
            'Appointment ' . $rule['verb'], $message,
            'student/appointments.php', $appointmentId);

        $pdo->commit();
        return ['ok' => true, 'message' => 'Appointment ' . $rule['verb'] . '.'];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Staff appointment update failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'The change could not be saved. Please try again.'];
    }
}

// Creates one reminder for each approved appointment that starts within the next 24 hours.
// Students get reminders for their own appointments. Staff get reminders for all of them.
// Each reminder is created only once per person and appointment.
function create_reminders(PDO $pdo, int $userId, string $role): void
{
    $now  = now_datetime();
    $soon = date('Y-m-d H:i:s', strtotime('+24 hours'));

    $sql = "SELECT a.id AS appointment_id, s.slot_date, s.start_time, s.end_time, sp.full_name
            FROM appointments a
            JOIN appointment_slots s ON s.id = a.slot_id
            JOIN student_profiles sp ON sp.user_id = a.student_user_id
            WHERE a.status = 'approved'
              AND CONCAT(s.slot_date, ' ', s.start_time) > :now
              AND CONCAT(s.slot_date, ' ', s.start_time) <= :soon";
    $params = [':now' => $now, ':soon' => $soon];

    if ($role === 'student') {
        $sql .= ' AND a.student_user_id = :student';
        $params[':student'] = $userId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    foreach ($stmt->fetchAll() as $row) {
        $check = $pdo->prepare(
            "SELECT id FROM notifications
             WHERE user_id = :user AND appointment_id = :appointment
               AND notification_type = 'appointment_reminder'"
        );
        $check->execute([':user' => $userId, ':appointment' => $row['appointment_id']]);
        if ($check->fetch()) {
            continue;
        }

        $label = slot_label($row);
        if ($role === 'student') {
            add_notification($pdo, $userId, 'appointment_reminder', 'Appointment reminder',
                'Reminder: your appointment is on ' . $label . '.',
                'student/appointments.php', (int) $row['appointment_id']);
        } else {
            add_notification($pdo, $userId, 'appointment_reminder', 'Appointment reminder',
                'Reminder: ' . $row['full_name'] . ' has an appointment on ' . $label . '.',
                'staff/appointments.php', (int) $row['appointment_id']);
        }
    }
}