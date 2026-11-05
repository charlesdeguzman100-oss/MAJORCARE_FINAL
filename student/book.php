<?php
// File: student/book.php
// Students choose a slot that has seats left and submit an appointment request.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/appointments.php';

$user = require_role('student');
$pdo = getDatabase();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        set_flash('error', 'Your session expired. Please try again.');
        redirect('student/book.php');
    }

    $slotId = (int) ($_POST['slot_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');

    if ($slotId <= 0) {
        set_flash('error', 'Choose a time slot.');
        redirect('student/book.php');
    }
    if ($reason === '' || mb_strlen($reason) > 500) {
        set_flash('error', 'Enter the reason for your visit (500 characters or fewer).');
        redirect('student/book.php');
    }

    $result = book_appointment($pdo, (int) $user['id'], $slotId, $reason);
    set_flash($result['ok'] ? 'success' : 'error', $result['message']);
    redirect($result['ok'] ? 'student/appointments.php' : 'student/book.php');
}

// Open slots that have not started and still have seats
$stmt = $pdo->prepare(
    "SELECT s.id, s.slot_date, s.start_time, s.end_time, s.capacity,
            (SELECT COUNT(*) FROM appointments a
              WHERE a.slot_id = s.id AND a.status IN ('pending', 'approved', 'completed')) AS seats_taken
     FROM appointment_slots s
     WHERE s.status = 'open' AND CONCAT(s.slot_date, ' ', s.start_time) > :now
     HAVING seats_taken < s.capacity
     ORDER BY s.slot_date, s.start_time"
);
$stmt->execute([':now' => now_datetime()]);
$slots = $stmt->fetchAll();

$pageTitle = 'Book an appointment';
require_once __DIR__ . '/../templates/header.php';
?>
<h1>Book an appointment</h1>

<section class="card">
  <?php if (empty($slots)): ?>
    <p>No open time slots right now. Check again later or contact the clinic.</p>
  <?php else: ?>
    <form method="post" action="">
      <?= csrf_field() ?>
      <fieldset>
        <legend>Choose a time</legend>
        <?php foreach ($slots as $slot): ?>
          <?php $seatsLeft = (int) $slot['capacity'] - (int) $slot['seats_taken']; ?>
          <label class="radio-label">
            <input type="radio" name="slot_id" value="<?= e((string) $slot['id']) ?>" required>
            <?= e(slot_label($slot)) ?>
            <span class="muted">(<?= $seatsLeft ?> seat<?= $seatsLeft === 1 ? '' : 's' ?> left)</span>
          </label>
        <?php endforeach; ?>
      </fieldset>
      <label>Reason for your visit
        <textarea name="reason" rows="3" maxlength="500" required></textarea>
      </label>
      <button type="submit" class="btn">Submit request</button>
    </form>
  <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>