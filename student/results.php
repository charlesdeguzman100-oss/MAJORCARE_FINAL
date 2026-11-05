<?php
// File: student/results.php
// Student view of published check-up results (physical assessments). Read-only.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/assessments.php';

$user = require_role('student');
$pdo = getDatabase();

$stmt = $pdo->prepare(
    "SELECT assessment_date, height_cm, weight_kg, bmi, bmi_category,
            systolic_bp, diastolic_bp, notes
     FROM physical_assessments
     WHERE student_user_id = :id AND status = 'published'
     ORDER BY assessment_date DESC, id DESC"
);
$stmt->execute([':id' => $user['id']]);
$checkups = $stmt->fetchAll();

$pageTitle = 'My check-ups';
require_once __DIR__ . '/../templates/header.php';
?>
<h1>My check-ups</h1>

<?php if (empty($checkups)): ?>
  <section class="card"><p>No check-up results published yet.</p></section>
<?php endif; ?>

<?php foreach ($checkups as $row): ?>
  <section class="card">
    <h2><?= e(date('F j, Y', strtotime($row['assessment_date']))) ?></h2>
    <p>
      <strong>Height:</strong> <?= e(format_measure($row['height_cm'], 'cm')) ?><br>
      <strong>Weight:</strong> <?= e(format_measure($row['weight_kg'], 'kg')) ?><br>
      <strong>BMI:</strong> <?= e(format_bmi($row['bmi'], $row['bmi_category'])) ?><br>
      <strong>Blood pressure:</strong> <?= e(format_blood_pressure($row['systolic_bp'], $row['diastolic_bp'])) ?>
    </p>
    <?php if (!empty($row['notes'])): ?>
      <p><strong>Notes:</strong><br><?= nl2br(e($row['notes'])) ?></p>
    <?php endif; ?>
  </section>
<?php endforeach; ?>

<p class="muted">To correct any check-up result, contact the clinic.</p>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>