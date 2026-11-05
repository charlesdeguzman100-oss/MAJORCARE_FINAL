<?php
// File: staff/student_visits.php
// Staff view one student's clinic visit history and add new records.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/visits.php';

$staff = require_role('staff');
$pdo = getDatabase();

$studentId = (int) ($_GET['id'] ?? 0);
$pageUrl = 'staff/student_visits.php?id=' . $studentId;

// Only approved student accounts can have visit records
$find = $pdo->prepare(
    "SELECT u.id, u.school_id, sp.full_name
     FROM users u
     JOIN student_profiles sp ON sp.user_id = u.id
     WHERE u.id = :id AND u.role = 'student' AND u.account_status = 'approved'"
);
$find->execute([':id' => $studentId]);
$student = $find->fetch();

if ($student === false) {
    set_flash('error', 'That student account was not found.');
    redirect('staff/students.php');
}

$form = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        set_flash('error', 'Your session expired. Please try again.');
        redirect($pageUrl);
    }

    $form = visit_input_from_post();
    $errors = visit_input_errors($form);

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO clinic_visits
               (student_user_id, visit_date, reason, notes, recommendations, recorded_by)
             VALUES (:student, :date, :reason, :notes, :recommendations, :staff)'
        );
        $stmt->execute([
            ':student'         => $studentId,
            ':date'            => $form['visit_date'],
            ':reason'          => $form['reason'],
            ':notes'           => $form['notes'] !== '' ? $form['notes'] : null,
            ':recommendations' => $form['recommendations'] !== '' ? $form['recommendations'] : null,
            ':staff'           => $staff['id'],
        ]);

        set_flash('success', 'Visit record saved.');
        redirect($pageUrl);
    }
}

// Form values: the submitted values after an error, or empty defaults for today
$formValues = $form ?? [
    'visit_date'      => date('Y-m-d'),
    'reason'          => '',
    'notes'           => '',
    'recommendations' => '',
];

// Visit history, newest first
$list = $pdo->prepare(
    'SELECT id, visit_date, reason, notes, recommendations, updated_at
     FROM clinic_visits
     WHERE student_user_id = :id
     ORDER BY visit_date DESC, id DESC'
);
$list->execute([':id' => $studentId]);
$visits = $list->fetchAll();

$pageTitle = 'Clinic visits';
require_once __DIR__ . '/../templates/header.php';
?>
<h1>Clinic visits: <?= e($student['full_name']) ?></h1>
<p class="muted">School ID: <?= e($student['school_id']) ?></p>

<section class="card">
  <h2>Add a visit record</h2>

  <?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endforeach; ?>

  <form method="post" action="">
    <?= csrf_field() ?>
    <?php render_visit_fields($formValues); ?>
    <button type="submit" class="btn">Save visit record</button>
  </form>
</section>

<h2>Visit history</h2>
<?php if (empty($visits)): ?>
  <section class="card"><p>No clinic visits recorded yet.</p></section>
<?php endif; ?>

<?php foreach ($visits as $visit): ?>
  <section class="card">
    <div class="row-head">
      <h2><?= e(format_visit_date($visit['visit_date'])) ?></h2>
      <a href="<?= e(url('staff/visit_edit.php?id=' . $visit['id'])) ?>">Edit</a>
    </div>
    <p><strong>Reason:</strong> <?= e($visit['reason']) ?></p>
    <?php if (!empty($visit['notes'])): ?>
      <p><strong>Notes:</strong><br><?= nl2br(e($visit['notes'])) ?></p>
    <?php endif; ?>
    <?php if (!empty($visit['recommendations'])): ?>
      <p><strong>Recommendations:</strong><br><?= nl2br(e($visit['recommendations'])) ?></p>
    <?php endif; ?>
    <p class="muted">Last updated: <?= e($visit['updated_at']) ?></p>
  </section>
<?php endforeach; ?>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>