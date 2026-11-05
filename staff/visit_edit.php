<?php
// File: staff/visit_edit.php
// Staff edit one clinic visit record. Records are updated, never deleted.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/visits.php';

require_role('staff');
$pdo = getDatabase();

$visitId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT cv.id, cv.student_user_id, cv.visit_date, cv.reason, cv.notes, cv.recommendations,
            sp.full_name, u.school_id
     FROM clinic_visits cv
     JOIN users u ON u.id = cv.student_user_id
     JOIN student_profiles sp ON sp.user_id = u.id
     WHERE cv.id = :id AND u.role = 'student' AND u.account_status = 'approved'"
);
$stmt->execute([':id' => $visitId]);
$visit = $stmt->fetch();

if ($visit === false) {
    set_flash('error', 'That visit record was not found.');
    redirect('staff/students.php');
}

$backUrl = 'staff/student_visits.php?id=' . $visit['student_user_id'];
$errors = [];

// Starting values come from the database
$formValues = [
    'visit_date'      => $visit['visit_date'],
    'reason'          => $visit['reason'],
    'notes'           => $visit['notes'] ?? '',
    'recommendations' => $visit['recommendations'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        $errors[] = 'Your session expired. Please submit the form again.';
    } else {
        $formValues = visit_input_from_post();
        $errors = visit_input_errors($formValues);

        if (empty($errors)) {
            $update = $pdo->prepare(
                'UPDATE clinic_visits
                 SET visit_date = :date, reason = :reason,
                     notes = :notes, recommendations = :recommendations
                 WHERE id = :id'
            );
            $update->execute([
                ':date'            => $formValues['visit_date'],
                ':reason'          => $formValues['reason'],
                ':notes'           => $formValues['notes'] !== '' ? $formValues['notes'] : null,
                ':recommendations' => $formValues['recommendations'] !== '' ? $formValues['recommendations'] : null,
                ':id'              => $visitId,
            ]);

            set_flash('success', 'Visit record updated.');
            redirect($backUrl);
        }
    }
}

$pageTitle = 'Edit visit record';
require_once __DIR__ . '/../templates/header.php';
?>
<h1>Edit visit record</h1>
<p class="muted"><?= e($visit['full_name']) ?> (<?= e($visit['school_id']) ?>)</p>

<section class="card">
  <?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endforeach; ?>

  <form method="post" action="">
    <?= csrf_field() ?>
    <?php render_visit_fields($formValues); ?>
    <div class="button-row">
      <button type="submit" class="btn">Save changes</button>
      <a class="btn" href="<?= e(url($backUrl)) ?>">Cancel</a>
    </div>
  </form>
</section>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>