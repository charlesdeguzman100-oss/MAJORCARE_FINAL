<?php
// File: public/register.php
// Student registration. New accounts start as Pending until clinic staff approve them.

require_once __DIR__ . '/../includes/auth.php';

start_session();

$current = current_user();
if ($current !== null && $current['account_status'] === 'approved') {
    redirect(dashboard_path($current['role']));
}

$errors = [];
$values = ['school_id' => '', 'full_name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = getDatabase();

    if (!csrf_is_valid()) {
        $errors[] = 'Your session expired. Please submit the form again.';
    }

    $values['school_id'] = trim($_POST['school_id'] ?? '');
    $values['full_name'] = trim($_POST['full_name'] ?? '');
    $values['email']     = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (!preg_match('/^[A-Za-z0-9-]{4,30}$/', $values['school_id'])) {
        $errors[] = 'School ID must be 4 to 30 letters, numbers, or dashes.';
    }
    if ($values['full_name'] === '' || mb_strlen($values['full_name']) > 120) {
        $errors[] = 'Enter your full name (120 characters or fewer).';
    }
    if ($values['email'] !== '' && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address, or leave the field empty.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'The password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'The passwords do not match.';
    }

    if (empty($errors)) {
        $check = $pdo->prepare('SELECT id FROM users WHERE school_id = :school_id');
        $check->execute([':school_id' => $values['school_id']]);
        if ($check->fetch()) {
            $errors[] = 'An account with this school ID already exists.';
        }
    }

    if (empty($errors)) {
        try {
            // The transaction saves the account, the profile, and the staff alerts together, or none of them.
            $pdo->beginTransaction();

            $insertUser = $pdo->prepare(
                "INSERT INTO users (school_id, password_hash, role, account_status)
                 VALUES (:school_id, :hash, 'student', 'pending')"
            );
            $insertUser->execute([
                ':school_id' => $values['school_id'],
                ':hash'      => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $userId = (int) $pdo->lastInsertId();

            $insertProfile = $pdo->prepare(
                'INSERT INTO student_profiles (user_id, full_name, email)
                 VALUES (:user_id, :full_name, :email)'
            );
            $insertProfile->execute([
                ':user_id'   => $userId,
                ':full_name' => $values['full_name'],
                ':email'     => $values['email'] !== '' ? $values['email'] : null,
            ]);

            // One notification for each approved staff member
            $notify = $pdo->prepare(
                "INSERT INTO notifications (user_id, notification_type, title, message, link)
                 SELECT id, 'registration', 'New student registration', :message, 'staff/registrations.php'
                 FROM users
                 WHERE role = 'staff' AND account_status = 'approved'"
            );
            $notify->execute([
                ':message' => $values['full_name'] . ' (' . $values['school_id'] . ') submitted a registration.',
            ]);

            $pdo->commit();

            set_flash('success', 'Registration submitted. You can log in after clinic staff approve your account.');
            redirect('public/index.php');
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('Registration failed: ' . $e->getMessage());
            $errors[] = 'The registration could not be saved. Please try again.';
        }
    }
}

$pageTitle = 'Register';
require_once __DIR__ . '/../templates/header.php';
?>
<section class="card narrow">
  <h1>Student registration</h1>
  <p>Clinic staff review each registration before you can log in.</p>

  <?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endforeach; ?>

  <form method="post" action="">
    <?= csrf_field() ?>
    <label>School ID
      <input type="text" name="school_id" value="<?= e($values['school_id']) ?>" required>
    </label>
    <label>Full name
      <input type="text" name="full_name" value="<?= e($values['full_name']) ?>" required>
    </label>
    <label>Email (optional)
      <input type="email" name="email" value="<?= e($values['email']) ?>">
    </label>
    <label>Password (at least 8 characters)
      <input type="password" name="password" required>
    </label>
    <label>Confirm password
      <input type="password" name="confirm_password" required>
    </label>
    <button type="submit" class="btn">Submit registration</button>
  </form>

  <p>Already registered? <a href="<?= e(url('public/index.php')) ?>">Log in</a></p>
</section>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>