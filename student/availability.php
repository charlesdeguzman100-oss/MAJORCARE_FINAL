<?php
// File: student/availability.php
// Students see each approved clinic staff member's status and contact method. Read-only.

require_once __DIR__ . '/../includes/auth.php';

require_role('student');
$pdo = getDatabase();

$stmt = $pdo->query(
    "SELECT stp.full_name, stp.position, stp.availability_status,
            stp.contact_method, stp.availability_updated_at
     FROM staff_profiles stp
     JOIN users u ON u.id = stp.user_id
     WHERE u.account_status = 'approved'
     ORDER BY stp.full_name ASC"
);
$staffList = $stmt->fetchAll();

$labels = ['available' => 'Available', 'busy' => 'Busy', 'unavailable' => 'Unavailable'];

$pageTitle = 'Clinic staff availability';
require_once __DIR__ . '/../templates/header.php';
?>
<h1>Clinic staff availability</h1>
<p class="muted">Each staff member sets their own status. Staff who have not updated their status show as Unavailable.</p>

<?php if (empty($staffList)): ?>
  <section class="card"><p>No clinic staff are listed yet.</p></section>
<?php endif; ?>

<?php foreach ($staffList as $row): ?>
  <section class="card">
    <div class="row-head">
      <h2><?= e($row['full_name']) ?></h2>
      <span class="badge badge-<?= e($row['availability_status']) ?>">
        <?= e($labels[$row['availability_status']] ?? 'Unavailable') ?>
      </span>
    </div>
    <p>
      <strong>Position:</strong> <?= e($row['position']) ?><br>
      <strong>Contact:</strong> <?= e($row['contact_method'] ?? 'Not provided') ?><br>
      <strong>Last updated:</strong>
      <?= $row['availability_updated_at'] !== null
          ? e(date('M j, Y g:i A', strtotime($row['availability_updated_at'])))
          : 'Not updated yet' ?>
    </p>
  </section>
<?php endforeach; ?>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>