<?php
// File: staff/registrations.php
// Staff review pending registrations, see rejected ones, and undo a rejection.
// Undo returns the account to Pending. Approval still needs a separate decision.

require_once __DIR__ . '/../includes/auth.php';

require_role('staff');
$pdo = getDatabase();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        set_flash('error', 'Your session expired. Please try again.');
        redirect('staff/registrations.php');
    }

    $studentId = (int) ($_POST['user_id'] ?? 0);
    $action    = $_POST['action'] ?? '';
    $reason    = trim($_POST['reason'] ?? '');

    if (!in_array($action, ['approve', 'reject', 'undo'], true)) {
        set_flash('error', 'Unknown action.');
        redirect('staff/registrations.php');
    }

    $find = $pdo->prepare(
        "SELECT u.id, u.account_status, sp.full_name
         FROM users u
         JOIN student_profiles sp ON sp.user_id = u.id
         WHERE u.id = :id AND u.role = 'student'"
    );
    $find->execute([':id' => $studentId]);
    $student = $find->fetch();

    if ($student === false) {
        set_flash('error', 'That registration was not found.');
        redirect('staff/registrations.php');
    }

    // Approve and reject need a pending account. Undo needs a rejected account.
    $requiredStatus = $action === 'undo' ? 'rejected' : 'pending';
    if ($student['account_status'] !== $requiredStatus) {
        set_flash('error', $requiredStatus === 'pending'
            ? 'That registration is no longer pending.'
            : 'That registration is not rejected, so it cannot be undone.');
        redirect('staff/registrations.php');
    }

    if ($action === 'reject' && ($reason === '' || mb_strlen($reason) > 400)) {
        set_flash('error', 'Enter a rejection reason of 400 characters or fewer.');
        redirect('staff/registrations.php');
    }

    if ($action === 'approve') {
        $newStatus = 'approved';
        $newReason = null;
        $title     = 'Registration approved';
        $message   = 'Your registration was approved. You can now log in.';
        $success   = $student['full_name'] . ' was approved.';
    } elseif ($action === 'reject') {
        $newStatus = 'rejected';
        $newReason = $reason;
        $title     = 'Registration rejected';
        $message   = 'Your registration was rejected. Reason: ' . $reason;
        $success   = $student['full_name'] . ' was rejected.';
    } else {
        // Undo: back to pending, reason cleared, student told it is under review again
        $newStatus = 'pending';
        $newReason = null;
        $title     = 'Registration under review';
        $message   = 'Your registration is under review again. Clinic staff will decide shortly.';
        $success   = $student['full_name'] . ' was returned to pending.';
    }

    try {
        $pdo->beginTransaction();

        $update = $pdo->prepare(
            'UPDATE users SET account_status = :status, rejection_reason = :reason WHERE id = :id'
        );
        $update->execute([
            ':status' => $newStatus,
            ':reason' => $newReason,
            ':id'     => $studentId,
        ]);

        $notify = $pdo->prepare(
            "INSERT INTO notifications (user_id, notification_type, title, message, link)
             VALUES (:user_id, 'registration', :title, :message, 'public/index.php')"
        );
        $notify->execute([
            ':user_id' => $studentId,
            ':title'   => $title,
            ':message' => $message,
        ]);

        $pdo->commit();
        set_flash('success', $success);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Registration decision failed: ' . $e->getMessage());
        set_flash('error', 'The decision could not be saved. Please try again.');
    }

    redirect('staff/registrations.php');
}

// Pending registrations, oldest first
$pending = $pdo->query(
    "SELECT u.id, u.school_id, u.created_at, sp.full_name, sp.email
     FROM users u
     JOIN student_profiles sp ON sp.user_id = u.id
     WHERE u.role = 'student' AND u.account_status = 'pending'
     ORDER BY u.created_at ASC"
)->fetchAll();

// Rejected registrations, most recent first. updated_at is the time the account was rejected.
$rejected = $pdo->query(
    "SELECT u.id, u.school_id, u.rejection_reason, u.updated_at, sp.full_name
     FROM users u
     JOIN student_profiles sp ON sp.user_id = u.id
     WHERE u.role = 'student' AND u.account_status = 'rejected'
     ORDER BY u.updated_at DESC"
)->fetchAll();

$pageTitle = 'Registrations';
require_once __DIR__ . '/../templates/header.php';
?>
<h1>Registrations</h1>

<h2>Pending</h2>
<?php if (empty($pending)): ?>
  <section class="card">
    <p>No registrations are waiting for review.</p>
  </section>
<?php else: ?>
  <?php foreach ($pending as $row): ?>
    <section class="card">
      <h2><?= e($row['full_name']) ?></h2>
      <p>
        <strong>School ID:</strong> <?= e($row['school_id']) ?><br>
        <strong>Email:</strong> <?= e($row['email'] ?? 'Not provided') ?><br>
        <strong>Submitted:</strong> <?= e(date('M j, Y g:i A', strtotime($row['created_at']))) ?>
      </p>

      <form method="post" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="user_id" value="<?= e((string) $row['id']) ?>">
        <label>Rejection reason (required only to reject)
          <textarea name="reason" rows="2" maxlength="400"></textarea>
        </label>
        <div class="button-row">
          <button type="submit" name="action" value="approve" class="btn">Approve</button>
          <button type="submit" name="action" value="reject" class="btn btn-danger">Reject</button>
        </div>
      </form>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

<h2>Rejected</h2>
<p class="muted">If a rejection was a mistake, use Undo. The registration returns to Pending, and the student is told it is under review again.</p>
<?php if (empty($rejected)): ?>
  <section class="card">
    <p>No rejected registrations.</p>
  </section>
<?php else: ?>
  <?php foreach ($rejected as $row): ?>
    <section class="card">
      <h2><?= e($row['full_name']) ?></h2>
      <p>
        <strong>School ID:</strong> <?= e($row['school_id']) ?><br>
        <strong>Rejected on:</strong> <?= e(date('M j, Y g:i A', strtotime($row['updated_at']))) ?><br>
        <strong>Reason:</strong> <?= e($row['rejection_reason'] ?? 'No reason recorded') ?>
      </p>

      <form method="post" action=""
            onsubmit="return confirm('Return this registration to pending?');">
        <?= csrf_field() ?>
        <input type="hidden" name="user_id" value="<?= e((string) $row['id']) ?>">
        <input type="hidden" name="action" value="undo">
        <button type="submit" class="btn">Undo rejection</button>
      </form>
    </section>
  <?php endforeach; ?>
<?php endif; ?>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>