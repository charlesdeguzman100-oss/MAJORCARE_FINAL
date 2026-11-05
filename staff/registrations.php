<?php
// File: staff/registrations.php
// Staff review of pending student registrations. Approve, or reject with a reason.

require_once __DIR__ . '/../includes/auth.php';

require_role('staff');
$pdo = getDatabase();

// Handle the approve or reject form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        set_flash('error', 'Your session expired. Please try again.');
        redirect('staff/registrations.php');
    }

    $studentId = (int) ($_POST['user_id'] ?? 0);
    $action    = $_POST['action'] ?? '';
    $reason    = trim($_POST['reason'] ?? '');

    // Only pending student accounts can be decided
    $find = $pdo->prepare(
        "SELECT u.id, u.account_status, sp.full_name
         FROM users u
         JOIN student_profiles sp ON sp.user_id = u.id
         WHERE u.id = :id AND u.role = 'student'"
    );
    $find->execute([':id' => $studentId]);
    $student = $find->fetch();

    if ($student === false || $student['account_status'] !== 'pending') {
        set_flash('error', 'That registration is no longer pending.');
        redirect('staff/registrations.php');
    }

    if ($action === 'approve') {
        $newStatus = 'approved';
        $title     = 'Registration approved';
        $message   = 'Your registration was approved. You can now log in.';
        $success   = $student['full_name'] . ' was approved.';
    } elseif ($action === 'reject') {
        if ($reason === '' || mb_strlen($reason) > 400) {
            set_flash('error', 'Enter a rejection reason of 400 characters or fewer.');
            redirect('staff/registrations.php');
        }
        $newStatus = 'rejected';
        $title     = 'Registration rejected';
        $message   = 'Your registration was rejected. Reason: ' . $reason;
        $success   = $student['full_name'] . ' was rejected.';
    } else {
        set_flash('error', 'Unknown action.');
        redirect('staff/registrations.php');
    }

    try {
        $pdo->beginTransaction();

        $update = $pdo->prepare(
            'UPDATE users
             SET account_status = :status,
                 rejection_reason = :reason
             WHERE id = :id'
        );
        $update->execute([
            ':status' => $newStatus,
            ':reason' => $newStatus === 'rejected' ? $reason : null,
            ':id'     => $studentId,
        ]);

        // Tell the student the result
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
        $pdo->rollBack();
        error_log('Registration decision failed: ' . $e->getMessage());
        set_flash('error', 'The decision could not be saved. Please try again.');
    }

    redirect('staff/registrations.php');
}

// Load pending registrations
$pending = $pdo->query(
    "SELECT u.id, u.school_id, u.created_at, sp.full_name, sp.email
     FROM users u
     JOIN student_profiles sp ON sp.user_id = u.id
     WHERE u.role = 'student' AND u.account_status = 'pending'
     ORDER BY u.created_at ASC"
)->fetchAll();

$pageTitle = 'Registrations';
require_once __DIR__ . '/../templates/header.php';
?>
<h1>Pending registrations</h1>

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
        <strong>Submitted:</strong> <?= e($row['created_at']) ?>
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
<?php require_once __DIR__ . '/../templates/footer.php'; ?>