<?php
// File: staff/availability.php
// Staff set their own availability status and contact method. Students can see both.

require_once __DIR__ . '/../includes/auth.php';

$staff = require_role('staff');
$pdo = getDatabase();

$statuses = ['available' => 'Available', 'busy' => 'Busy', 'unavailable' => 'Unavailable'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        set_flash('error', 'Your session expired. Please try again.');
        redirect('staff/availability.php');
    }

    $status  = $_POST['availability_status'] ?? '';
    $contact = trim($_POST['contact_method'] ?? '');

    if (!array_key_exists($status, $statuses)) {
        set_flash('error', 'Choose a valid status.');
    } elseif (mb_strlen($contact) > 255) {
        set_flash('error', 'The contact method must be 255 characters or fewer.');
    } else {
        $stmt = $pdo->prepare(
            'UPDATE staff_profiles
             SET availability_status = :status,
                 contact_method = :contact,
                 availability_updated_at = NOW()
             WHERE user_id = :id'
        );
        $stmt->execute([
            ':status'  => $status,
            ':contact' => $contact !== '' ? $contact : null,
            ':id'      => $staff['id'],
        ]);
        set_flash('success', 'Availability updated.');
    }
    redirect('staff/availability.php');
}

$stmt = $pdo->prepare(
    'SELECT availability_status, contact_method, availability_updated_at
     FROM staff_profiles
     WHERE user_id = :id'
);
$stmt->execute([':id' => $staff['id']]);
$current = $stmt->fetch();

$pageTitle = 'My availability';
require_once __DIR__ . '/../templates/header.php';
?>
<h1>My availability</h1>

<section class="card">
  <p class="muted">
    Last updated:
    <?= $current['availability_updated_at'] !== null
        ? e(date('M j, Y g:i A', strtotime($current['availability_updated_at'])))
        : 'Not updated yet' ?>
  </p>

  <form method="post" action="">
    <?= csrf_field() ?>
    <fieldset>
      <legend>My status</legend>
      <?php foreach ($statuses as $value => $label): ?>
        <label class="radio-label">
          <input type="radio" name="availability_status" value="<?= e($value) ?>"
                 <?= $current['availability_status'] === $value ? 'checked' : '' ?> required>
          <?= e($label) ?>
        </label>
      <?php endforeach; ?>
    </fieldset>
    <label>Contact method (shown to students)
      <input type="text" name="contact_method" maxlength="255"
             value="<?= e($current['contact_method'] ?? '') ?>"
             placeholder="for example, clinic phone or room number">
    </label>
    <button type="submit" class="btn">Save availability</button>
  </form>
</section>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>