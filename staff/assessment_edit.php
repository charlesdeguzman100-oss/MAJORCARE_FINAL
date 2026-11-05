<?php
// File: staff/assessment_edit.php
// Staff edit a draft physical assessment. Published assessments must be unpublished first.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/assessments.php';

$staff = require_role('staff');
$pdo = getDatabase();

$assessmentId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT pa.id, pa.student_user_id, pa.assessment_date, pa.height_cm, pa.weight_kg,
            pa.systolic_bp, pa.diastolic_bp, pa.notes, pa.status,
            sp.full_name, u.school_id
     FROM physical_assessments pa
     JOIN users u ON u.id = pa.student_user_id
     JOIN student_profiles sp ON sp.user_id = u.id
     WHERE pa.id = :id AND u.role = 'student' AND u.account_status = 'approved'"
);
$stmt->execute([':id' => $assessmentId]);
$row = $stmt->fetch();

if ($row === false) {
    set_flash('error', 'That assessment was not found.');
    redirect('staff/students.php');
}

$backUrl = 'staff/student_assessments.php?id=' . $row['student_user_id'];

if ($row['status'] !== 'draft') {
    set_flash('error', 'Unpublish this assessment before you edit it.');
    redirect($backUrl);
}

$errors = [];
$formValues = [
    'assessment_date' => $row['assessment_date'],
    'height_cm'       => $row['height_cm'] ?? '',
    'weight_kg'       => $row['weight_kg'] ?? '',
    'systolic_bp'     => $row['systolic_bp'] ?? '',
    'diastolic_bp'    => $row['diastolic_bp'] ?? '',
    'notes'           => $row['notes'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        $errors[] = 'Your session expired. Please submit the form again.';
    } else {
        $formValues = assessment_input_from_post();
        [$errors, $clean] = assessment_validate($formValues);

        if (empty($errors)) {
            $result = save_assessment($pdo, (int) $row['student_user_id'], (int) $staff['id'], $assessmentId, $clean);
            set_flash($result['ok'] ? 'success' : 'error', $result['message']);
            redirect($backUrl);
        }
    }
}

$pageTitle = 'Edit assessment';
require_once __DIR__ . '/../templates/header.php';
?>
<h1>Edit draft assessment</h1>
<p class="muted"><?= e($row['full_name']) ?> (<?= e($row['school_id']) ?>)</p>

<section class="card">
  <?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endforeach; ?>

  <form method="post" action="">
    <?= csrf_field() ?>
    <?php render_assessment_fields($formValues); ?>
    <div class="button-row">
      <button type="submit" class="btn">Save changes</button>
      <a class="btn" href="<?= e(url($backUrl)) ?>">Cancel</a>
    </div>
  </form>
</section>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>