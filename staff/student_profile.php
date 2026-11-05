<?php
// File: staff/student_profile.php
// Read-only view of one approved student's profile. Staff cannot edit it here.

require_once __DIR__ . '/../includes/auth.php';

require_role('staff');
$pdo = getDatabase();

$studentId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT u.id, u.school_id, u.created_at, u.last_login_at,
            sp.full_name, sp.email, sp.contact_number, sp.address,
            sp.emergency_contact_name, sp.emergency_contact_relationship,
            sp.emergency_contact_number, sp.consent_accepted_at
     FROM users u
     JOIN student_profiles sp ON sp.user_id = u.id
     WHERE u.id = :id AND u.role = 'student' AND u.account_status = 'approved'"
);
$stmt->execute([':id' => $studentId]);
$student = $stmt->fetch();

if ($student === false) {
    set_flash('error', 'That student account was not found.');
    redirect('staff/students.php');
}

$pageTitle = 'Student profile';
require_once __DIR__ . '/../templates/header.php';
?>
<h1><?= e($student['full_name']) ?></h1>

<section class="card">
  <h2>Account</h2>
  <p><strong>School ID:</strong> <?= e($student['school_id']) ?></p>
  <p><strong>Registered:</strong> <?= e($student['created_at']) ?></p>
  <p><strong>Last login:</strong> <?= e($student['last_login_at'] ?? 'Never') ?></p>
  <p><strong>Privacy notice:</strong>
    <?= $student['consent_accepted_at'] !== null
        ? 'Accepted on ' . e($student['consent_accepted_at'])
        : 'Not yet accepted' ?>
  </p>
</section>

<section class="card">
  <h2>Personal details</h2>
  <p><strong>Full name:</strong> <?= e($student['full_name']) ?></p>
  <p><strong>Email:</strong> <?= e($student['email'] ?? 'Not provided') ?></p>
  <p><strong>Contact number:</strong> <?= e($student['contact_number'] ?? 'Not provided') ?></p>
  <p><strong>Address:</strong> <?= e($student['address'] ?? 'Not provided') ?></p>
</section>

<section class="card">
  <h2>Emergency contact</h2>
  <p><strong>Name:</strong> <?= e($student['emergency_contact_name'] ?? 'Not provided') ?></p>
  <p><strong>Relationship:</strong> <?= e($student['emergency_contact_relationship'] ?? 'Not provided') ?></p>
  <p><strong>Number:</strong> <?= e($student['emergency_contact_number'] ?? 'Not provided') ?></p>
</section>

<p>
  <a href="<?= e(url('staff/student_health.php?id=' . $studentId)) ?>">Health records</a>
  &middot;
  <a href="<?= e(url('staff/students.php')) ?>">Back to students</a>
</p>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>