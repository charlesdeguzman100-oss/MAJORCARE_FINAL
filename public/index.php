<?php
// File: public/index.php
// Login page for students and clinic staff.

require_once __DIR__ . '/../includes/auth.php';

start_session();

// A logged-in, approved user goes straight to the dashboard.
$current = current_user();
if ($current !== null && $current['account_status'] === 'approved') {
    redirect(dashboard_path($current['role']));
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
            'SELECT id, password_hash, role, account_status, rejection_reason
             FROM users WHERE school_id = :school_id'
        );
        $stmt->execute([':school_id' => $schoolId]);
        $user = $stmt->fetch();

        // The same message covers an unknown ID and a wrong password.
        // This prevents attackers from finding valid IDs.
        if ($user === false || !password_verify($password, $user['password_hash'])) {
            $error = 'Invalid ID or password.';
        } elseif ($user['account_status'] === 'pending') {
            $error = 'Your account is still waiting for clinic staff approval.';
        } elseif ($user['account_status'] === 'rejected') {
            $error = 'Your registration was rejected. Reason: '
                   . ($user['rejection_reason'] ?? 'No reason given.');
        } else {
            login_user((int) $user['id']);

            $update = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
            $update->execute([':id' => $user['id']]);

            set_flash('success', 'Welcome back.');
            redirect(dashboard_path($user['role']));
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
      <input type="text" name="school_id" value="<?= e($schoolId) ?>" required autofocus>
    </label>
    <label>Password
      <input type="password" name="password" required>
    </label>
    <button type="submit" class="btn">Log in</button>
  </form>

  <p>New student? <a href="<?= e(url('public/register.php')) ?>">Create an account</a></p>
</section>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>