<?php
// File: staff/student_profile.php
// Student profile for staff: account record, school ID correction, remove and restore,
// approval record, and the audit history.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/accounts.php';

$staff = require_role('staff');
$pdo = getDatabase();

$studentId = (int) ($_GET['id'] ?? 0);
$pageUrl = 'staff/student_profile.php?id=' . $studentId;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        set_flash('error', 'Your session expired. Please try again.');
        redirect($pageUrl);
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'remove') {
        // Fail-safe: the typed school ID must match this account exactly
        $target = find_student_account($pdo, $studentId);
        $typed  = trim($_POST['confirm_school_id'] ?? '');

        if ($target === false) {
            $result = ['ok' => false, 'message' => 'That account was not found.'];
        } elseif ($typed !== $target['school_id']) {
            $result = ['ok' => false, 'message' => 'The school ID you typed does not match '
                . $target['full_name'] . ' (' . $target['school_id'] . '). Nothing was removed.'];
        } else {
            $result = decide_registration($pdo, (int) $staff['id'], $studentId, 'remove',
                trim($_POST['reason'] ?? ''), false);
        }
    } elseif ($action === 'restore') {
        $result = decide_registration($pdo, (int) $staff['id'], $studentId, 'restore',
            trim($_POST['reason'] ?? ''), isset($_POST['verified']));
    } elseif ($action === 'change_id') {
        $result = change_school_id($pdo, (int) $staff['id'], $studentId,
            trim($_POST['new_school_id'] ?? ''), trim($_POST['reason'] ?? ''));
    } else {
        $result = ['ok' => false, 'message' => 'Unknown action.'];
    }

        // A successful removal shows the dialog instead of a flash message
    if ($action === 'remove' && $result['ok']) {
        redirect($pageUrl . '&removed=1');
    }

    set_flash($result['ok'] ? 'success' : 'error', $result['message']);
    redirect($pageUrl);
}

$stmt = $pdo->prepare(
    "SELECT u.id, u.school_id, u.account_status, u.rejection_reason, u.created_at,
            u.last_login_at, u.approved_at, sta.full_name AS approver_name,
            sp.full_name, sp.email, sp.contact_number, sp.address,
            sp.emergency_contact_name, sp.emergency_contact_relationship,
            sp.emergency_contact_number, sp.consent_accepted_at
     FROM users u
     JOIN student_profiles sp ON sp.user_id = u.id
     LEFT JOIN staff_profiles sta ON sta.user_id = u.approved_by
     WHERE u.id = :id AND u.role = 'student'"
);
$stmt->execute([':id' => $studentId]);
$student = $stmt->fetch();

if ($student === false) {
    set_flash('error', 'That student account was not found.');
    redirect('staff/students.php');
}

$history = audit_history($pdo, $studentId);

$pageTitle = 'Student profile';
require_once __DIR__ . '/../templates/header.php';
?>
<?php if (isset($_GET['removed']) && $student['account_status'] === 'removed'): ?>
  <div class="modal-backdrop">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="removed-title">
      <h2 id="removed-title">Success: account removed</h2>
      <p><strong><?= e($student['full_name']) ?></strong> (school ID <?= e($student['school_id']) ?>) has been removed.</p>
      <p class="muted">The student can no longer log in. The account can be restored from its profile.</p>
      <a class="btn" href="<?= e(url('staff/dashboard.php')) ?>">Back to dashboard</a>
    </div>
  </div>
  <script>
    // Make everything except the dialog unreachable by mouse and keyboard
    document.querySelectorAll('header, .sidebar, .topbar, footer, main > *:not(.modal-backdrop)')
      .forEach(function (el) { el.setAttribute('inert', ''); });
  </script>
<?php endif; ?>
<h1><?= e($student['full_name']) ?></h1>
<p class="muted">
  <a href="<?= e(url('staff/students.php')) ?>">Back to students</a> &middot;
  <a href="<?= e(url('staff/student_health.php?id=' . $studentId)) ?>">Health records</a> &middot;
  <a href="<?= e(url('staff/student_visits.php?id=' . $studentId)) ?>">Visits</a> &middot;
  <a href="<?= e(url('staff/student_assessments.php?id=' . $studentId)) ?>">Check-ups</a>
</p>

<section class="card">
  <h2>Account</h2>
  <p>
    <strong>School ID:</strong> <?= e($student['school_id']) ?><br>
    <strong>Status:</strong> <?= e(ucfirst($student['account_status'])) ?><br>
    <strong>Registered:</strong> <?= e(date('M j, Y g:i A', strtotime($student['created_at']))) ?><br>
    <strong>Last login:</strong> <?= e($student['last_login_at'] ? date('M j, Y g:i A', strtotime($student['last_login_at'])) : 'Never') ?><br>
    <strong>Privacy notice:</strong>
    <?= $student['consent_accepted_at'] !== null
        ? 'Accepted on ' . e(date('M j, Y g:i A', strtotime($student['consent_accepted_at'])))
        : 'Not yet accepted' ?>
  </p>
  <?php if ($student['account_status'] === 'approved'): ?>
    <p>
      <strong>Approved by:</strong>
      <?= e($student['approver_name'] ?? 'Not recorded (approved before audit logging)') ?>
      <?php if ($student['approved_at'] !== null): ?>
        on <?= e(date('M j, Y g:i A', strtotime($student['approved_at']))) ?>
      <?php endif; ?>
    </p>
  <?php endif; ?>
</section>

<section class="card">
  <h2>Correct the school ID</h2>
  <p class="muted">Use this when the ID was entered wrongly. The new ID must use the format 00-0000-000. A reason is required, and the student is notified.</p>
  <form method="post" action="<?= e(url($pageUrl)) ?>"
        onsubmit="return confirm('Change this school ID? The student will be notified.');">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="change_id">
    <label>New school ID
      <input type="text" name="new_school_id" maxlength="30" placeholder="00-0000-000" required>
    </label>
    <label>Reason for the change
      <textarea name="reason" rows="2" maxlength="400" required></textarea>
    </label>
    <button type="submit" class="btn">Change school ID</button>
  </form>
</section>

<?php if ($student['account_status'] === 'approved'): ?>
  <section class="card">
    <h2>Remove account</h2>
    <div class="alert alert-error">
      <strong>You are about to remove the account of <?= e($student['full_name']) ?></strong>
      (school ID <strong><?= e($student['school_id']) ?></strong>).
      The student will be unable to log in immediately.
    </div>
    <p class="muted">
      Appointments and records are kept. The account can be restored later from the Removed list.
      To continue, type the school ID exactly as shown above and enter a reason. To stop, leave the fields empty and do not submit.
    </p>
    <form method="post" action="<?= e(url($pageUrl)) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="remove">
      <label>Type the school ID to confirm
        <input type="text" name="confirm_school_id" maxlength="30" autocomplete="off" required>
      </label>
      <label>Reason
        <textarea name="reason" rows="2" maxlength="400" required></textarea>
      </label>
      <button type="submit" class="btn btn-danger">Remove account of <?= e($student['full_name']) ?></button>
    </form>
  </section>
<?php endif; ?>

<?php if ($student['account_status'] === 'removed'): ?>
  <section class="card">
    <h2>Restore account</h2>
    <div class="alert alert-error">
      <strong><?= e($student['full_name']) ?></strong> (school ID <strong><?= e($student['school_id']) ?></strong>)
      is currently removed and cannot log in.
    </div>
      <p class="muted">Restoring returns the account to Approved, so the student can log inagain. Tick the box to confirm the ID was checked against the enrollment list.</p>
    <form method="post" action="<?= e(url($pageUrl)) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="restore">
        <label class="checkbox-label">
        <input type="checkbox" name="verified" value="1">
        I checked this ID against the enrollment list (required to restore)
      </label>
      <label>Reason for restoring
        <textarea name="reason" rows="2" maxlength="400" required></textarea>
      </label>
      <button type="submit" class="btn">Restore account of <?= e($student['full_name']) ?></button>
    </form>
  </section>
<?php endif; ?>

<section class="card">
  <h2>Personal details</h2>
  <p><strong>Full name:</strong> <?= e($student['full_name']) ?></p>
  <p><strong>Email:</strong> <?= e($student['email'] ?? 'Not provided') ?></p>
  <p><strong>Contact number:</strong> <?= e($student['contact_number'] ?? 'Not provided') ?></p>
  <p><strong>Address:</strong> <?= e($student['address'] ?? 'Not provided') ?></p>
</section>

<section class="card">
  <h2>Emergency contact</h2>
  <p><strong>Name:</strong> <?= e($student['emergency_contact_name'] ?? 'Not provided') ?></p>
  <p><strong>Relationship:</strong> <?= e($student['emergency_contact_relationship'] ?? 'Not provided') ?></p>
  <p><strong>Number:</strong> <?= e($student['emergency_contact_number'] ?? 'Not provided') ?></p>
</section>

<section class="card">
  <h2>Account history</h2>
  <?php if (empty($history)): ?>
    <p>No decisions or changes recorded yet.</p>
  <?php else: ?>
    <div class="table-wrap">
    <table>
      <thead>
        <tr><th>When</th><th>Action</th><th>Staff member</th><th>Detail</th><th>Reason</th></tr>
      </thead>
      <tbody>
        <?php foreach ($history as $row): ?>
          <tr>
            <td><?= e(date('M j, Y g:i A', strtotime($row['created_at']))) ?></td>
            <td><?= e(AUDIT_ACTION_LABELS[$row['action']] ?? $row['action']) ?></td>
            <td><?= e($row['staff_name'] ?? 'Removed account') ?></td>
            <td><?= e($row['detail'] ?? '') ?></td>
            <td><?= e($row['reason'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>