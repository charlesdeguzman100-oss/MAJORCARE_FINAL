<?php
// File: student/consent.php
// Privacy and consent notice. Students accept it once, before using the portal.

require_once __DIR__ . '/../includes/auth.php';

$user = require_login();

if ($user['role'] !== 'student') {
    redirect(dashboard_path($user['role']));
}
if ((int) $user['must_change_password'] === 1) {
    redirect('public/change_password.php');
}
if ($user['consent_accepted_at'] !== null) {
    redirect('student/dashboard.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        $error = 'Your session expired. Please try again.';
    } elseif (!isset($_POST['agree'])) {
        $error = 'Tick the box to confirm that you have read and accept this notice.';
    } else {
        $stmt = getDatabase()->prepare(
            'UPDATE student_profiles
             SET consent_accepted_at = NOW()
             WHERE user_id = :id AND consent_accepted_at IS NULL'
        );
        $stmt->execute([':id' => $user['id']]);

        set_flash('success', 'Thank you. You can now use the portal.');
        redirect('student/dashboard.php');
    }
}

$pageTitle = 'Privacy notice';
require_once __DIR__ . '/../templates/header.php';
?>
<section class="card">
  <h1>Privacy and consent notice</h1>

  <p>MajorCare stores personal and health-related information for campus clinic purposes. This includes:</p>
  <p>your account and profile details, your emergency contact, the health information the clinic records for you,
     your clinic visit records, the documents you upload, and the laboratory results the clinic publishes for you.</p>

  <p>Only you and authorized clinic staff can view this information. The portal uses logins and role-based
     access to protect it. Clinic staff use it to manage appointments and clinic records.</p>

  <p>MajorCare is an administrative portal. It does not diagnose conditions or replace professional medical care.
     In an emergency, go to the clinic or call emergency services.</p>

  <?php if ($error !== null): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" action="">
    <?= csrf_field() ?>
    <label class="checkbox-label">
      <input type="checkbox" name="agree" value="1">
      I have read this notice and I accept how MajorCare processes my information.
    </label>
    <button type="submit" class="btn">Accept and continue</button>
  </form>
</section>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>