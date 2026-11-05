<?php
// File: staff/dashboard.php
// Staff home page. Counts come from the database.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/appointments.php';

$user = require_role('staff');
$pdo = getDatabase();

create_reminders($pdo, (int) $user['id'], 'staff');

$totalStudents = (int) $pdo->query(
    "SELECT COUNT(*) FROM users WHERE role = 'student' AND account_status = 'approved'"
)->fetchColumn();

$pendingRegistrations = (int) $pdo->query(
    "SELECT COUNT(*) FROM users WHERE role = 'student' AND account_status = 'pending'"
)->fetchColumn();

$pendingRequests = (int) $pdo->query(
    "SELECT COUNT(*) FROM appointments WHERE status = 'pending'"
)->fetchColumn();

$upcomingStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM appointments a
     JOIN appointment_slots s ON s.id = a.slot_id
     WHERE a.status = 'approved' AND CONCAT(s.slot_date, ' ', s.start_time) > :now"
);
$upcomingStmt->execute([':now' => now_datetime()]);
$upcomingApproved = (int) $upcomingStmt->fetchColumn();

// Visits from the first day of this month through today
$visitsStmt = $pdo->prepare(
    'SELECT COUNT(*) FROM clinic_visits WHERE visit_date BETWEEN :start AND :today'
);
$visitsStmt->execute([':start' => date('Y-m-01'), ':today' => date('Y-m-d')]);
$visitsThisMonth = (int) $visitsStmt->fetchColumn();

$pageTitle = 'Staff dashboard';
require_once __DIR__ . '/../templates/header.php';
?>
<h1>Welcome, <?= e($user['full_name']) ?></h1>

<div class="stat-grid">
  <section class="card stat">
    <div class="stat-number"><?= $totalStudents ?></div>
    <div>Approved students</div>
    <a href="<?= e(url('staff/students.php')) ?>">Manage students</a>
  </section>
  <section class="card stat">
    <div class="stat-number"><?= $pendingRegistrations ?></div>
    <div>Pending registrations</div>
    <a href="<?= e(url('staff/registrations.php')) ?>">Review registrations</a>
  </section>
  <section class="card stat">
    <div class="stat-number"><?= $pendingRequests ?></div>
    <div>Pending appointment requests</div>
    <a href="<?= e(url('staff/appointments.php?status=pending')) ?>">Review requests</a>
  </section>
  <section class="card stat">
    <div class="stat-number"><?= $upcomingApproved ?></div>
    <div>Upcoming approved appointments</div>
    <a href="<?= e(url('staff/appointments.php?status=approved')) ?>">View approved</a>
  </section>
  <section class="card stat">
    <div class="stat-number"><?= $visitsThisMonth ?></div>
    <div>Visits recorded this month</div>
    <a href="<?= e(url('staff/students.php')) ?>">Open a student's visits</a>
  </section>
    <section class="card stat">
    <?php
    $pendingDocs = (int) $pdo->query(
        "SELECT COUNT(*) FROM medical_documents WHERE review_status = 'pending'"
    )->fetchColumn();
    ?>
    <div class="stat-number"><?= $pendingDocs ?></div>
    <div>Documents awaiting review</div>
    <a href="<?= e(url('staff/documents.php')) ?>">Review documents</a>
  </section>
</div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>