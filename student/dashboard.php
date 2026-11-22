<?php
// File: student/dashboard.php
// Student home page. Layout matches the staff dashboard: summary cards first, then detail cards.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/appointments.php';
require_once __DIR__ . '/../includes/visits.php';
require_once __DIR__ . '/../includes/messages.php';

$user = require_role('student');
$pdo = getDatabase();
$studentId = (int) $user['id'];

create_reminders($pdo, $studentId, 'student');

// Profile details
$profileStmt = $pdo->prepare(
    'SELECT email, contact_number, emergency_contact_name, emergency_contact_number
     FROM student_profiles
     WHERE user_id = :id'
);
$profileStmt->execute([':id' => $studentId]);
$profile = $profileStmt->fetch() ?: [];

// Health summary
$healthStmt = $pdo->prepare(
    'SELECT blood_type, allergies FROM health_information WHERE student_user_id = :id'
);
$healthStmt->execute([':id' => $studentId]);
$health = $healthStmt->fetch();

$vaccineStmt = $pdo->prepare(
    'SELECT COUNT(*) FROM vaccination_records WHERE student_user_id = :id'
);
$vaccineStmt->execute([':id' => $studentId]);
$vaccineCount = (int) $vaccineStmt->fetchColumn();

// Upcoming pending or approved appointments, soonest first
$upcomingStmt = $pdo->prepare(
    "SELECT a.status, s.slot_date, s.start_time, s.end_time
     FROM appointments a
     JOIN appointment_slots s ON s.id = a.slot_id
     WHERE a.student_user_id = :id
       AND a.status IN ('pending', 'approved')
       AND CONCAT(s.slot_date, ' ', s.start_time) > :now
     ORDER BY s.slot_date, s.start_time"
);
$upcomingStmt->execute([':id' => $studentId, ':now' => now_datetime()]);
$upcoming = $upcomingStmt->fetchAll();
$upcomingCount = count($upcoming);
$next = $upcoming[0] ?? false;

// Latest completed appointment, shown only when nothing is upcoming
$lastDone = false;
if ($next === false) {
    $doneStmt = $pdo->prepare(
        "SELECT s.slot_date, s.start_time, s.end_time
         FROM appointments a
         JOIN appointment_slots s ON s.id = a.slot_id
         WHERE a.student_user_id = :id AND a.status = 'completed'
         ORDER BY s.slot_date DESC, s.start_time DESC
         LIMIT 1"
    );
    $doneStmt->execute([':id' => $studentId]);
    $lastDone = $doneStmt->fetch();
}

// Most recent clinic visit
$visitStmt = $pdo->prepare(
    'SELECT visit_date, reason
     FROM clinic_visits
     WHERE student_user_id = :id
     ORDER BY visit_date DESC, id DESC
     LIMIT 1'
);
$visitStmt->execute([':id' => $studentId]);
$lastVisit = $visitStmt->fetch();

// Unread staff replies
$unread = unread_messages_for_student($pdo, $studentId);

// Document counts by review status
$docStmt = $pdo->prepare(
    'SELECT review_status, COUNT(*) FROM medical_documents WHERE student_user_id = :id GROUP BY review_status'
);
$docStmt->execute([':id' => $studentId]);
$docCounts = $docStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../templates/header.php';
?>
<h1>Welcome, <?= e($user['full_name']) ?></h1>

<div class="stat-grid">
  <section class="card stat">
    <div class="stat-number"><?= $upcomingCount ?></div>
    <div>Upcoming appointments</div>
    <?php if ($next !== false): ?>
      <div class="muted">Next: <?= e(slot_label($next)) ?></div>
    <?php elseif ($lastDone !== false): ?>
      <div class="muted">None upcoming. Last completed: <?= e(slot_label($lastDone)) ?></div>
    <?php else: ?>
      <div class="muted">No appointments yet.</div>
    <?php endif; ?>
    <a href="<?= e(url('student/appointments.php')) ?>">View appointments</a>
  </section>

  <section class="card stat">
    <div class="stat-number stat-text"><?= $lastVisit !== false ? e(format_visit_date($lastVisit['visit_date'])) : 'None' ?></div>
    <div>Last clinic visit</div>
    <div class="muted"><?= $lastVisit !== false ? e($lastVisit['reason']) : 'No visits recorded yet.' ?></div>
    <a href="<?= e(url('student/visits.php')) ?>">View visit history</a>
  </section>

  <section class="card stat">
    <div class="stat-number"><?= $unread ?></div>
    <div>Unread messages from staff</div>
    <a href="<?= e(url('student/messages.php')) ?>">Open messages</a>
  </section>

  <section class="card stat">
    <div class="stat-number"><?= (int) ($docCounts['pending'] ?? 0) ?></div>
    <div>Documents pending review</div>
    <div class="muted">
      Approved <?= (int) ($docCounts['approved'] ?? 0) ?> &middot;
      Rejected <?= (int) ($docCounts['rejected'] ?? 0) ?>
    </div>
    <a href="<?= e(url('student/documents.php')) ?>">Upload and view documents</a>
  </section>
</div>

<div class="stat-grid detail-row">
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

  <section class="card">
    <h2>Clinic staff availability</h2>
    <p>See which staff are available and how to reach them.</p>
    <a href="<?= e(url('student/availability.php')) ?>">View availability</a>
  </section>
</div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>