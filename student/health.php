<?php
// File: student/health.php
// Student view of health information and vaccination records.
// Only clinic staff can change these records.

require_once __DIR__ . '/../includes/auth.php';

$user = require_role('student');
$pdo = getDatabase();

$healthStmt = $pdo->prepare(
    'SELECT blood_type, allergies, other_health_notes, updated_at
     FROM health_information
     WHERE student_user_id = :id'
);
$healthStmt->execute([':id' => $user['id']]);
$health = $healthStmt->fetch(); // false if the clinic has not recorded anything yet

$vaccineStmt = $pdo->prepare(
    'SELECT vaccine_name, dose_label, date_given
     FROM vaccination_records
     WHERE student_user_id = :id
     ORDER BY date_given DESC, id DESC'
);
$vaccineStmt->execute([':id' => $user['id']]);
$vaccines = $vaccineStmt->fetchAll();

$pageTitle = 'My health information';
require_once __DIR__ . '/../templates/header.php';
?>
<h1>My health information</h1>

<section class="card">
  <h2>Basic information</h2>
  <?php if ($health === false): ?>
    <p>The clinic has not recorded your health information yet.</p>
  <?php else: ?>
    <p><strong>Blood type:</strong> <?= e($health['blood_type']) ?></p>
    <p><strong>Allergies:</strong><br><?= nl2br(e($health['allergies'] ?? 'None recorded')) ?></p>
    <p><strong>Other health notes:</strong><br><?= nl2br(e($health['other_health_notes'] ?? 'None recorded')) ?></p>
    <p class="muted">Last updated by the clinic: <?= e($health['updated_at']) ?></p>
  <?php endif; ?>
</section>

<section class="card">
  <h2>Vaccination records</h2>
  <?php if (empty($vaccines)): ?>
    <p>No vaccination records yet.</p>
  <?php else: ?>
    <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Vaccine</th><th>Dose</th><th>Date given</th></tr>
      </thead>
      <tbody>
        <?php foreach ($vaccines as $row): ?>
          <tr>
            <td><?= e($row['vaccine_name']) ?></td>
            <td><?= e($row['dose_label'] ?? 'Not recorded') ?></td>
            <td><?= e($row['date_given'] ?? 'Not recorded') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</section>

<p>To correct any information here, contact the clinic.</p>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>