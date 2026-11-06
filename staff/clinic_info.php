<?php
// File: staff/clinic_info.php
// Staff edit the clinic details that students see. This is one record. It is never deleted.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/clinic.php';

$staff = require_role('staff');
$pdo = getDatabase();

$current = get_clinic_information($pdo);
$form = [
    'clinic_name'     => $current['clinic_name'] ?? '',
    'location'        => $current['location'] ?? '',
    'operating_hours' => $current['operating_hours'] ?? '',
    'contact_number'  => $current['contact_number'] ?? '',
    'contact_email'   => $current['contact_email'] ?? '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        set_flash('error', 'Your session expired. Please try again.');
        redirect('staff/clinic_info.php');
    }

    $form = [
        'clinic_name'     => trim($_POST['clinic_name'] ?? ''),
        'location'        => trim($_POST['location'] ?? ''),
        'operating_hours' => trim($_POST['operating_hours'] ?? ''),
        'contact_number'  => trim($_POST['contact_number'] ?? ''),
        'contact_email'   => trim($_POST['contact_email'] ?? ''),
    ];

    $errors = clinic_information_errors($form);

    if (empty($errors)) {
        $result = save_clinic_information($pdo, (int) $staff['id'], $form);
        set_flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect('staff/clinic_info.php');
    }
}

$pageTitle = 'Clinic information';
require_once __DIR__ . '/../templates/header.php';
?>
<h1>Clinic information</h1>
<p class="muted">Students see these details on their Clinic page. This record is edited, not deleted.</p>

<?php foreach ($errors as $error): ?>
  <div class="alert alert-error"><?= e($error) ?></div>
<?php endforeach; ?>

<section class="card">
  <form method="post" action="">
    <?= csrf_field() ?>
    <label>Clinic name
      <input type="text" name="clinic_name" maxlength="120" value="<?= e($form['clinic_name']) ?>" required>
    </label>
    <label>Location
      <input type="text" name="location" maxlength="255" value="<?= e($form['location']) ?>"
             placeholder="for example, Student Services Building, Room 101">
    </label>
    <label>Operating hours
      <input type="text" name="operating_hours" maxlength="255" value="<?= e($form['operating_hours']) ?>"
             placeholder="for example, Monday to Friday, 8:00 AM to 5:00 PM">
    </label>
    <label>Contact number
      <input type="text" name="contact_number" maxlength="40" value="<?= e($form['contact_number']) ?>">
    </label>
    <label>Contact email
      <input type="email" name="contact_email" maxlength="120" value="<?= e($form['contact_email']) ?>">
    </label>
    <button type="submit" class="btn">Save clinic information</button>
  </form>
</section>

<section class="card">
  <h2>Related pages</h2>
  <p>
    <a href="<?= e(url('staff/faqs.php')) ?>">Manage FAQs</a> &middot;
    <a href="<?= e(url('staff/announcements.php')) ?>">Manage announcements</a>
  </p>
</section>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>