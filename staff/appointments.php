<?php
// File: staff/appointments.php
// Staff review appointment requests and record each decision.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/appointments.php';

$staff = require_role('staff');
$pdo = getDatabase();

$statuses = ['pending', 'approved', 'rejected', 'cancelled', 'completed'];
$filter = $_GET['status'] ?? 'all';
if ($filter !== 'all' && !in_array($filter, $statuses, true)) {
    $filter = 'all';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        set_flash('error', 'Your session expired. Please try again.');
        redirect('staff/appointments.php?status=' . urlencode($filter));
    }

    $note = trim($_POST['note'] ?? '');
    if (mb_strlen($note) > 400) {
        set_flash('error', 'The note must be 400 characters or fewer.');
        redirect('staff/appointments.php?status=' . urlencode($filter));
    }

    $result = staff_update_appointment(
        $pdo,
        (int) $staff['id'],
        (int) ($_POST['appointment_id'] ?? 0),
        $_POST['action'] ?? '',
        $note
    );
    set_flash($result['ok'] ? 'success' : 'error', $result['message']);
    redirect('staff/appointments.php?status=' . urlencode($filter));
}

$sql = "SELECT a.id, a.status, a.reason, a.staff_note,
               s.slot_date, s.start_time, s.end_time,
               sp.full_name, u.school_id
        FROM appointments a
        JOIN appointment_slots s ON s.id = a.slot_id
        JOIN student_profiles sp ON sp.user_id = a.student_user_id
        JOIN users u ON u.id = a.student_user_id";
$params = [];
if ($filter !== 'all') {
    $sql .= ' WHERE a.status = :status';
    $params[':status'] = $filter;
}
$sql .= ' ORDER BY s.slot_date ASC, s.start_time ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

// The actions each status allows. These match the rules in includes/appointments.php.
$allowedActions = [
    'pending'   => ['approve' => 'Approve', 'reject' => 'Reject', 'cancel' => 'Cancel appointment'],
    'approved'  => ['complete' => 'Mark completed', 'cancel' => 'Cancel appointment'],
    'rejected'  => [],
    'cancelled' => [],
    'completed' => [],
];

$pageTitle = 'Appointments';
require_once __DIR__ . '/../templates/header.php';
?>
<h1>Appointments</h1>

<p>
  <?php foreach (array_merge(['all'], $statuses) as $option): ?>
    <a class="filter<?= $filter === $option ? ' filter-active' : '' ?>"
       href="<?= e(url('staff/appointments.php?status=' . $option)) ?>"><?= e(ucfirst($option)) ?></a>
  <?php endforeach; ?>
</p>

<?php if (empty($appointments)): ?>
  <section class="card"><p>No appointments match this filter.</p></section>
<?php endif; ?>

<?php foreach ($appointments as $row): ?>
  <section class="card">
    <div class="row-head">
      <h2><?= e($row['full_name']) ?> <small>(<?= e($row['school_id']) ?>)</small></h2>
      <span class="badge badge-<?= e($row['status']) ?>"><?= e(ucfirst($row['status'])) ?></span>
    </div>
    <p><strong>When:</strong> <?= e(slot_label($row)) ?></p>
    <p><strong>Reason:</strong> <?= e($row['reason']) ?></p>
    <?php if (!empty($row['staff_note'])): ?>
      <p><strong>Last note:</strong> <?= e($row['staff_note']) ?></p>
    <?php endif; ?>

    <?php if (!empty($allowedActions[$row['status']])): ?>
      <form method="post" action="<?= e(url('staff/appointments.php?status=' . $filter)) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="appointment_id" value="<?= e((string) $row['id']) ?>">
        <label>Note (required to reject or cancel)
          <textarea name="note" rows="2" maxlength="400"></textarea>
        </label>
        <div class="button-row">
          <?php foreach ($allowedActions[$row['status']] as $value => $label): ?>
            <button type="submit" name="action" value="<?= e($value) ?>"
                    class="btn<?= in_array($value, ['reject', 'cancel'], true) ? ' btn-danger' : '' ?>">
              <?= e($label) ?>
            </button>
          <?php endforeach; ?>
        </div>
      </form>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>