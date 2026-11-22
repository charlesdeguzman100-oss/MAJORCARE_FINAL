<?php
// File: public/index.php
// Login page for students and clinic staff.
// Accounts are checked first. If no account exists, the password is checked against
// the student's registration submissions, so a pending or rejected student sees the right message.

require_once __DIR__ . '/../includes/auth.php';

start_session();

// A logged-in, approved user goes straight to the dashboard
$current = current_user();
if ($current !== null && $current['account_status'] === 'approved') {
    redirect(dashboard_path($current['role']));
}

// Message for a school ID with no account: pending or rejected submissions only
function submission_login_message(PDO $pdo, string $schoolId, string $password): ?string
{
    $stmt = $pdo->prepare(
        "SELECT status, rejection_reason, password_hash
         FROM registration_submissions
         WHERE school_id = :id AND status IN ('pending', 'rejected')
         ORDER BY id DESC"
    );
    $stmt->execute([':id' => $schoolId]);

    foreach ($stmt->fetchAll() as $row) {
        if (password_verify($password, $row['password_hash'])) {
            if ($row['status'] === 'rejected') {
                return 'Your registration was rejected. Reason: ' . ($row['rejection_reason'] ?? 'No reason given.');
            }
            return 'Your registration is waiting for clinic staff approval.';
        }
    }
    return null;
}

$error = null;
$schoolId = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        $error = 'Your session expired. Please try again.';
    } else {
        $schoolId = trim($_POST['school_id'] ?? '');
        $password = $_POST['password'] ?? '';
        $pdo = getDatabase();

        $stmt = $pdo->prepare(
            'SELECT id, password_hash, role, account_status FROM users WHERE school_id = :school_id'
        );
        $stmt->execute([':school_id' => $schoolId]);
        $user = $stmt->fetch();

        if ($user !== false) {
            if (!password_verify($password, $user['password_hash'])) {
                $error = 'Invalid ID or password.';
            } elseif ($user['account_status'] === 'removed') {
                $error = 'This account has been deactivated. Contact the campus clinic for help.';
            } elseif ($user['account_status'] !== 'approved') {
                $error = 'Invalid ID or password.';
            } else {
                login_user((int) $user['id']);

                $update = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
                $update->execute([':id' => $user['id']]);

                set_flash('success', 'Welcome back.');
                redirect(dashboard_path($user['role']));
            }
        } else {
            // No account yet. Show a submission message only to someone who knows the password.
            $error = submission_login_message($pdo, $schoolId, $password) ?? 'Invalid ID or password.';
        }
    }
}

$pageTitle = 'Log in';
require_once __DIR__ . '/../templates/header.php';
?>
<section class="card narrow">
  <h1>Log in</h1>

  <?php if ($error !== null): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" action="">
    <?= csrf_field() ?>
    <label>School ID
      <input type="text" name="school_id" value="<?= e($schoolId) ?>" placeholder="00-0000-000" required autofocus>
    </label>
    <label>Password
      <input type="password" name="password" required>
    </label>
    <button type="submit" class="btn">Log in</button>
  </form>

  <p>New student? <a href="<?= e(url('public/register.php')) ?>">Register here</a></p>
</section>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>