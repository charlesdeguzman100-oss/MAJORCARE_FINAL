<?php
// File: staff/slots.php
// Staff create appointment slots, set how many students each slot holds,
// close or reopen slots, and delete slots with no bookings.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/appointments.php';

$staff = require_role('staff');
$pdo = getDatabase();
$today = date('Y-m-d');

// True only for a real time in HH:MM format, such as "09:30"
function valid_time(string $value): bool
{
    $time = DateTime::createFromFormat('H:i', $value);
    return $time !== false && $time->format('H:i') === $value;
}

// Capacity must be a whole number from 1 to 50. Returns the int, or false.
function parse_capacity($value)
{
    return filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 50],
    ]);
}

// Badge text and style for a slot. Full is shown when every seat is taken.
function slot_state(array $slot): array
{
    if ($slot['status'] === 'closed') {
        return ['Closed', 'closed'];
    }
    if ((int) $slot['seats_taken'] >= (int) $slot['capacity']) {
        return ['Full', 'full'];
    }
    return ['Open', 'open'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        set_flash('error', 'Your session expired. Please try again.');
        redirect('staff/slots.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $date     = trim($_POST['slot_date'] ?? '');
        $start    = trim($_POST['start_time'] ?? '');
        $end      = trim($_POST['end_time'] ?? '');
        $capacity = parse_capacity($_POST['capacity'] ?? '');

        $parsedDate = DateTime::createFromFormat('Y-m-d', $date);
        if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $date) {
            set_flash('error', 'Enter a valid date.');
        } elseif ($date < $today) {
            set_flash('error', 'The slot date cannot be in the past.');
        } elseif (!valid_time($start) || !valid_time($end) || $start >= $end) {
            set_flash('error', 'Enter a start time that is earlier than the end time.');
        } elseif ($capacity === false) {
            set_flash('error', 'Capacity must be a whole number from 1 to 50.');
        } else {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO appointment_slots (slot_date, start_time, end_time, capacity, status, created_by)
                     VALUES (:date, :start, :end, :capacity, 'open', :staff)"
                );
                $stmt->execute([
                    ':date'     => $date,
                    ':start'    => $start . ':00',
                    ':end'      => $end . ':00',
                    ':capacity' => $capacity,
                    ':staff'    => $staff['id'],
                ]);
                set_flash('success', 'Slot added: ' . $date . ', ' . $start . ' to ' . $end
                    . ', ' . $capacity . ' seat(s).');
            } catch (PDOException $e) {
                // SQLSTATE 23000 means a slot with this date and start time already exists
                set_flash('error', $e->getCode() === '23000'
                    ? 'A slot already exists at that date and start time.'
                    : 'The slot could not be saved.');
            }
        }
    } elseif ($action === 'capacity') {
        $slotId   = (int) ($_POST['slot_id'] ?? 0);
        $capacity = parse_capacity($_POST['capacity'] ?? '');

        if ($capacity === false) {
            set_flash('error', 'Capacity must be a whole number from 1 to 50.');
        } else {
            try {
                // Lock the slot so no booking happens while the capacity is checked and changed
                $pdo->beginTransaction();

                $lock = $pdo->prepare('SELECT id FROM appointment_slots WHERE id = :id FOR UPDATE');
                $lock->execute([':id' => $slotId]);

                if ($lock->fetch() === false) {
                    $pdo->rollBack();
                    set_flash('error', 'That slot was not found.');
                } else {
                    $taken = seats_taken($pdo, $slotId);
                    if ($capacity < $taken) {
                        $pdo->rollBack();
                        set_flash('error', 'This slot already has ' . $taken
                            . ' booked seat(s). Capacity cannot be lower than that.');
                    } else {
                        $update = $pdo->prepare('UPDATE appointment_slots SET capacity = :cap WHERE id = :id');
                        $update->execute([':cap' => $capacity, ':id' => $slotId]);
                        $pdo->commit();
                        set_flash('success', 'Capacity updated to ' . $capacity . ' seat(s).');
                    }
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Capacity update failed: ' . $e->getMessage());
                set_flash('error', 'The capacity could not be saved.');
            }
        }
    } elseif ($action === 'close' || $action === 'reopen') {
        $slotId = (int) ($_POST['slot_id'] ?? 0);
        $from = $action === 'close' ? 'open' : 'closed';
        $to   = $action === 'close' ? 'closed' : 'open';

        $stmt = $pdo->prepare(
            'UPDATE appointment_slots
             SET status = :to
             WHERE id = :id AND status = :from AND slot_date >= :today'
        );
        $stmt->execute([':to' => $to, ':id' => $slotId, ':from' => $from, ':today' => $today]);

        if ($stmt->rowCount() === 1) {
            set_flash('success', $action === 'close' ? 'Slot closed.' : 'Slot reopened.');
        } else {
            set_flash('error', 'That slot cannot be changed right now.');
        }
    } elseif ($action === 'delete') {
        $slotId = (int) ($_POST['slot_id'] ?? 0);

        // Only slots with no appointments at all can be deleted
        $stmt = $pdo->prepare(
            'DELETE FROM appointment_slots
             WHERE id = :id
               AND NOT EXISTS (SELECT 1 FROM appointments WHERE slot_id = :slot_check)'
        );
        $stmt->execute([':id' => $slotId, ':slot_check' => $slotId]);

        if ($stmt->rowCount() === 1) {
            set_flash('success', 'Slot deleted.');
        } else {
            set_flash('error', 'That slot has appointments, so it cannot be deleted.');
        }
    } else {
        set_flash('error', 'Unknown action.');
    }

    redirect('staff/slots.php');
}

// Slots from today onward, with seats taken and the total number of appointments
$stmt = $pdo->prepare(
    "SELECT s.id, s.slot_date, s.start_time, s.end_time, s.status, s.capacity,
            (SELECT COUNT(*) FROM appointments a
              WHERE a.slot_id = s.id AND a.status IN ('pending', 'approved', 'completed')) AS seats_taken,
            (SELECT COUNT(*) FROM appointments a WHERE a.slot_id = s.id) AS appointment_count
     FROM appointment_slots s
     WHERE s.slot_date >= :today
     ORDER BY s.slot_date, s.start_time"
);
$stmt->execute([':today' => $today]);
$slots = $stmt->fetchAll();

$pageTitle = 'Appointment slots';
require_once __DIR__ . '/../templates/header.php';
?>
<h1>Appointment slots</h1>

<section class="card">
  <h2>Add a slot</h2>
  <form method="post" action="">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <label>Date
      <input type="date" name="slot_date" min="<?= e($today) ?>" required>
    </label>
    <label>Start time
      <input type="time" name="start_time" required>
    </label>
    <label>End time
      <input type="time" name="end_time" required>
    </label>
    <label>Number of students (seats)
      <input type="number" name="capacity" min="1" max="50" value="1" required>
    </label>
    <button type="submit" class="btn">Add slot</button>
  </form>
</section>

<section class="card">
  <h2>Upcoming slots</h2>
  <?php if (empty($slots)): ?>
    <p>No upcoming slots. Add one above.</p>
  <?php else: ?>
    <table>
      <thead>
        <tr><th>Date</th><th>Time</th><th>Status</th><th>Seats</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php foreach ($slots as $slot): ?>
          <?php [$stateLabel, $stateClass] = slot_state($slot); ?>
          <tr>
            <td><?= e(date('M j, Y', strtotime($slot['slot_date']))) ?></td>
            <td>
              <?= e(date('g:i A', strtotime($slot['start_time']))) ?>
              to
              <?= e(date('g:i A', strtotime($slot['end_time']))) ?>
            </td>
            <td><span class="badge badge-<?= e($stateClass) ?>"><?= e($stateLabel) ?></span></td>
            <td>
              <?= (int) $slot['seats_taken'] ?> of <?= (int) $slot['capacity'] ?> taken
              <form method="post" action="" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="capacity">
                <input type="hidden" name="slot_id" value="<?= e((string) $slot['id']) ?>">
                <input type="number" name="capacity" min="1" max="50"
                       value="<?= (int) $slot['capacity'] ?>" class="small-input">
                <button type="submit" class="btn">Save seats</button>
              </form>
            </td>
            <td>
              <?php if ($slot['status'] === 'open' || $slot['status'] === 'closed'): ?>
                <form method="post" action="" class="inline-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="slot_id" value="<?= e((string) $slot['id']) ?>">
                  <input type="hidden" name="action" value="<?= $slot['status'] === 'open' ? 'close' : 'reopen' ?>">
                  <button type="submit" class="btn"><?= $slot['status'] === 'open' ? 'Close' : 'Reopen' ?></button>
                </form>
              <?php endif; ?>

              <?php if ((int) $slot['appointment_count'] === 0): ?>
                <form method="post" action="" class="inline-form"
                      onsubmit="return confirm('Delete this slot?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="slot_id" value="<?= e((string) $slot['id']) ?>">
                  <input type="hidden" name="action" value="delete">
                  <button type="submit" class="btn btn-danger">Delete</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>