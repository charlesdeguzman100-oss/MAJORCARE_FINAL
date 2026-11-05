<?php
// File: student/dashboard.php
// Student home page. Summaries, the next appointment, the last visit, and reminders come from the database.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/appointments.php';
require_once __DIR__ . '/../includes/visits.php';

$user = require_role('student');
$pdo = getDatabase();

create_reminders($pdo, (int) $user['id'], 'student');

$profileStmt = $pdo->prepare(
    'SELECT email, contact_number, emergency_contact_name, emergency_contact_number
     FROM student_profiles
     WHERE user_id = :id'
);
$profileStmt->execute([':id' => $user['id']]);
$profile = $profileStmt->fetch() ?: [];

$healthStmt = $pdo->prepare(
    'SELECT blood_type, allergies FROM health_information WHERE student_user_id = :id'
);
$healthStmt->execute([':id' => $user['id']]);
$health = $healthStmt->fetch();

$vaccineCountStmt = $pdo->prepare(
    'SELECT COUNT(*) FROM vaccination_records WHERE student_user_id = :id'
);
$vaccineCountStmt->execute([':id' => $user['id']]);
$vaccineCount = (int) $vaccineCountStmt->fetchColumn();

// The next pending or approved appointment that has not started
$nextStmt = $pdo->prepare(
    "SELECT a.status, s.slot_date, s.start_time, s.end_time
     FROM appointments a
     JOIN appointment_slots s ON s.id = a.slot_id
     WHERE a.student_user_id = :id
       AND a.status IN ('pending', 'approved')
       AND CONCAT(s.slot_date, ' ', s.start_time) > :now
     ORDER BY s.slot_date, s.start_time
     LIMIT 1"
);
$nextStmt->execute([':id' => $user['id'], ':now' => now_datetime()]);
$next = $nextStmt->fetch();

// The most recent clinic visit
$visitStmt = $pdo->prepare(
    'SELECT visit_date, reason
     FROM clinic_visits
     WHERE student_user_id = :id
     ORDER BY visit_date DESC, id DESC
     LIMIT 1'
);
$visitStmt->execute([':id' => $user['id']]);
$lastVisit = $visitStmt->fetch();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../templates/header.php';
?>
<h1>Welcome, <?= e($user['full_name']) ?></h1>

<div class="stat-grid">
  <section class="card">
    <h2>Next appointment</h2>
    <?php if ($next === false): ?>
      <p>You have no upcoming appointment.</p>
    <?php else: ?>
      <p><?= e(slot_label($next)) ?></p>
      <p><span class="badge badge-<?= e($next['status']) ?>"><?= e(ucfirst($next['status'])) ?></span></p>
    <?php endif; ?>
    <a href="<?= e(url('student/appointments.php')) ?>">View appointments</a>
  </section>

  <section class="card">
    <h2>Last clinic visit</h2>
    <?php if ($lastVisit === false): ?>
      <p>No clinic visits recorded yet.</p>
    <?php else: ?>
      <p><?= e(format_visit_date($lastVisit['visit_date'])) ?></p>
      <p><strong>Reason:</strong> <?= e($lastVisit['reason']) ?></p>
    <?php endif; ?>
    <a href="<?= e(url('student/visits.php')) ?>">View visit history</a>
  </section>

  <section class="card">
    <h2>Profile</h2>
    <p><strong>School ID:</strong> <?= e($user['school_id']) ?></p>
    <p><strong>Email:</strong> <?= e($profile['email'] ?? 'Not provided') ?></p>
    <p><strong>Contact:</strong> <?= e($profile['contact_number'] ?? 'Not provided') ?></p>
    <p><strong>Emergency contact:</strong>
      <?= e($profile['emergency_contact_name'] ?? 'Not provided') ?>
      <?php if (!empty($profile['emergency_contact_number'])): ?>
        (<?= e($profile['emergency_contact_number']) ?>)
      <?php endif; ?>
    </p>
    <a href="<?= e(url('student/profile.php')) ?>">Edit profile</a>
  </section>

  <section class="card">
    <h2>Health information</h2>
    <?php if ($health === false): ?>
      <p>The clinic has not recorded your health information yet.</p>
    <?php else: ?>
      <p><strong>Blood type:</strong> <?= e($health['blood_type']) ?></p>
      <p><strong>Allergies:</strong> <?= e($health['allergies'] ?? 'None recorded') ?></p>
    <?php endif; ?>
    <p><strong>Vaccination records:</strong> <?= $vaccineCount ?></p>
    <a href="<?= e(url('student/health.php')) ?>">View health information</a>
  </section>
</div>

<section class="card">
    <h2>Documents</h2>
  <?php
  $docStmt = $pdo->prepare(
      'SELECT review_status, COUNT(*) FROM medical_documents WHERE student_user_id = :id GROUP BY review_status'
  );
  $docStmt->execute([':id' => $user['id']]);
  $docCounts = $docStmt->fetchAll(PDO::FETCH_KEY_PAIR);
  ?>
  <p>
    Approved: <?= (int) ($docCounts['approved'] ?? 0) ?> &middot;
    Pending review: <?= (int) ($docCounts['pending'] ?? 0) ?> &middot;
    Rejected: <?= (int) ($docCounts['rejected'] ?? 0) ?>
  </p>
  <a href="<?= e(url('student/documents.php')) ?>">Upload and view documents</a>
</section>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>