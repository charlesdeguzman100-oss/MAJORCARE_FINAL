<?php
// File: student/visits.php
// Student view of their own clinic visit records. Read-only.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/visits.php';

$user = require_role('student');
$pdo = getDatabase();

$stmt = $pdo->prepare(
    'SELECT visit_date, reason, notes, recommendations
     FROM clinic_visits
     WHERE student_user_id = :id
     ORDER BY visit_date DESC, id DESC'
);
$stmt->execute([':id' => $user['id']]);
$visits = $stmt->fetchAll();

$pageTitle = 'My clinic visits';
require_once __DIR__ . '/../templates/header.php';
?>
<h1>My clinic visits</h1>

<?php if (empty($visits)): ?>
  <section class="card"><p>No clinic visits recorded yet.</p></section>
<?php endif; ?>

<?php foreach ($visits as $visit): ?>
  <section class="card">
    <h2><?= e(format_visit_date($visit['visit_date'])) ?></h2>
    <p><strong>Reason:</strong> <?= e($visit['reason']) ?></p>
    <?php if (!empty($visit['notes'])): ?>
      <p><strong>Notes:</strong><br><?= nl2br(e($visit['notes'])) ?></p>
    <?php endif; ?>
    <?php if (!empty($visit['recommendations'])): ?>
      <p><strong>Recommendations:</strong><br><?= nl2br(e($visit['recommendations'])) ?></p>
    <?php endif; ?>
  </section>
<?php endforeach; ?>

<p class="muted">To correct a record, contact the clinic.</p>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>