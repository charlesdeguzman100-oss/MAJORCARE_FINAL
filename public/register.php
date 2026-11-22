<?php
// File: public/register.php
// Student registration. Each registration is a submission with an ID photo.
// The student account is created only when clinic staff approve a submission.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/submissions.php';

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

    if (!valid_school_id($values['school_id'])) {
        $errors[] = 'School ID must use the format 00-0000-000.';
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
    if (!isset($_POST['consent'])) {
        $errors[] = 'Tick the box to confirm you have read the privacy notice.';
    }

    $photo = $_FILES['id_photo'] ?? ['error' => UPLOAD_ERR_NO_FILE];
    [$photoErrors, $mime] = check_uploaded_file($photo);
    $errors = array_merge($errors, $photoErrors);

    if (empty($errors)) {
        $result = submit_registration($pdo, [
            'school_id' => $values['school_id'],
            'full_name' => $values['full_name'],
            'email'     => $values['email'],
            'password'  => $password,
        ], $photo, $mime);

        if ($result['ok']) {
            set_flash('success', $result['message']);
            redirect('public/index.php');
        }
        $errors[] = $result['message'];
    }
}

$pageTitle = 'Register';
require_once __DIR__ . '/../templates/header.php';
?>
<section class="card narrow">
  <h1>Student registration</h1>

  <div class="card notice">
    <h2>Privacy notice</h2>
    <p>MajorCare collects your name, email address, school ID, password, and a photo of your school ID. The photo lets clinic staff confirm that the account belongs to you. Only approved clinic staff can view it.</p>
    <p>Your password is stored only as a secure hash. The ID photo is kept for as long as your account exists. Your information is used only for campus clinic purposes and is protected by login and role-based access.</p>
    <p>MajorCare does not diagnose conditions or replace professional medical care.</p>
  </div>

  <?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endforeach; ?>

  <form method="post" action="" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <label>School ID
      <input type="text" name="school_id" value="<?= e($values['school_id']) ?>"
             placeholder="00-0000-000" required>
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
    <label>Photo of your school ID (JPG, PNG, or PDF, up to 5 MB)
      <input type="file" name="id_photo" accept=".jpg,.jpeg,.png,.pdf" required>
    </label>
    <label class="checkbox-label">
      <input type="checkbox" name="consent" value="1">
      I have read the privacy notice and I agree to how MajorCare uses my information.
    </label>
    <button type="submit" class="btn">Submit registration</button>
  </form>

  <p>Already registered? <a href="<?= e(url('public/index.php')) ?>">Log in</a></p>
</section>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>