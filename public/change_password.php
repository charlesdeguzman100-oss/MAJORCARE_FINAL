<?php
// File: public/change_password.php
// Students with a temporary password must replace it before using the portal.

require_once __DIR__ . '/../includes/auth.php';

$user = require_login();

// Nothing to do if no change is required
if ((int) $user['must_change_password'] !== 1) {
    redirect(dashboard_path($user['role']));
}

$errors = [];
$pdo = getDatabase();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        $errors[] = 'Your session expired. Please try again.';
    }

    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
    $stmt->execute([':id' => $user['id']]);
    $row = $stmt->fetch();

    if ($row === false || !password_verify($current, $row['password_hash'])) {
        $errors[] = 'The temporary password is not correct.';
    }
    if (strlen($new) < 8) {
        $errors[] = 'The new password must be at least 8 characters.';
    }
    if ($new !== $confirm) {
        $errors[] = 'The new passwords do not match.';
    }
    if ($new !== '' && $new === $current) {
        $errors[] = 'Choose a password that is different from the temporary one.';
    }

    if (empty($errors)) {
        $update = $pdo->prepare(
            'UPDATE users SET password_hash = :hash, must_change_password = 0 WHERE id = :id'
        );
        $update->execute([
            ':hash' => password_hash($new, PASSWORD_DEFAULT),
            ':id'   => $user['id'],
        ]);

        set_flash('success', 'Your password was changed.');
        redirect(dashboard_path($user['role']));
    }
}

$pageTitle = 'Change password';
require_once __DIR__ . '/../templates/header.php';
?>
<section class="card narrow">
  <h1>Change your password</h1>
  <p>Clinic staff gave you a temporary password. Replace it before you continue.</p>

  <?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endforeach; ?>

  <form method="post" action="">
    <?= csrf_field() ?>
    <label>Temporary password
      <input type="password" name="current_password" required>
    </label>
    <label>New password (at least 8 characters)
      <input type="password" name="new_password" required>
    </label>
    <label>Confirm new password
      <input type="password" name="confirm_password" required>
    </label>
    <button type="submit" class="btn">Change password</button>
  </form>
</section>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>