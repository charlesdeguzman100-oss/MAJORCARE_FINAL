<?php
// File: student/appointments.php
// Student appointment list. Students cancel pending or approved appointments that have not started.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/appointments.php';

$user = require_role('student');
$pdo = getDatabase();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        set_flash('error', 'Your session expired. Please try again.');
        redirect('student/appointments.php');
    }
    $result = cancel_by_student($pdo, (int) $user['id'], (int) ($_POST['appointment_id'] ?? 0));
    set_flash($result['ok'] ? 'success' : 'error', $result['message']);
    redirect('student/appointments.php');
}

// Renders one appointment. Cancel is shown only when the rules allow it.
function appointment_card(array $row, bool $cancellable): void
{
    ?>
    <section class="card">
      <div class="row-head">
        <h2><?= e(slot_label($row)) ?></h2>
        <span class="badge badge-<?= e($row['status']) ?>"><?= e(ucfirst($row['status'])) ?></span>
      </div>
      <p><strong>Reason:</strong> <?= e($row['reason']) ?></p>
      <?php if (!empty($row['staff_note'])): ?>
        <p><strong>Note from clinic staff:</strong> <?= e($row['staff_note']) ?></p>
      <?php endif; ?>
      <?php if ($cancellable): ?>
        <form method="post" action="" onsubmit="return confirm('Cancel this appointment?');">
          <?= csrf_field() ?>
          <input type="hidden" name="appointment_id" value="<?= e((string) $row['id']) ?>">
          <button type="submit" class="btn btn-danger">Cancel appointment</button>
        </form>
      <?php endif; ?>
    </section>
    <?php
}

$stmt = $pdo->prepare(
    'SELECT a.id, a.status, a.reason, a.staff_note, s.slot_date, s.start_time, s.end_time
     FROM appointments a
     JOIN appointment_slots s ON s.id = a.slot_id
     WHERE a.student_user_id = :id
     ORDER BY s.slot_date DESC, s.start_time DESC'
);
$stmt->execute([':id' => $user['id']]);

$now = now_datetime();
$upcoming = [];
$history = [];
foreach ($stmt->fetchAll() as $row) {
    $isOpenStatus = in_array($row['status'], ['pending', 'approved'], true);
    if ($isOpenStatus && slot_start($row) > $now) {
        $upcoming[] = $row;
    } else {
        $history[] = $row;
    }
}
// Soonest upcoming appointment first
usort($upcoming, fn($a, $b) => strcmp(slot_start($a), slot_start($b)));

$pageTitle = 'My appointments';
require_once __DIR__ . '/../templates/header.php';
?>
<h1>My appointments</h1>
<p><a href="<?= e(url('student/book.php')) ?>" class="btn">Book an appointment</a></p>

<h2>Upcoming</h2>
<?php if (empty($upcoming)): ?>
  <section class="card"><p>You have no upcoming appointments.</p></section>
<?php endif; ?>
<?php foreach ($upcoming as $row): appointment_card($row, true); endforeach; ?>

<h2>History</h2>
<?php if (empty($history)): ?>
  <section class="card"><p>No past appointments yet.</p></section>
<?php endif; ?>
<?php foreach ($history as $row): appointment_card($row, false); endforeach; ?>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>