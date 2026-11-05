<?php
// File: staff/student_assessments.php
// Staff record physical assessments for one student, then publish them.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/assessments.php';

$staff = require_role('staff');
$pdo = getDatabase();

$studentId = (int) ($_GET['id'] ?? 0);
$pageUrl = 'staff/student_assessments.php?id=' . $studentId;

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

$errors = [];
$formValues = [
    'assessment_date' => date('Y-m-d'),
    'height_cm'       => '',
    'weight_kg'       => '',
    'systolic_bp'     => '',
    'diastolic_bp'    => '',
    'notes'           => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        set_flash('error', 'Your session expired. Please try again.');
        redirect($pageUrl);
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $formValues = assessment_input_from_post();
        [$errors, $clean] = assessment_validate($formValues);

        if (empty($errors)) {
            $result = save_assessment($pdo, $studentId, (int) $staff['id'], null, $clean);
            set_flash($result['ok'] ? 'success' : 'error', $result['message']);
            redirect($pageUrl);
        }
    } elseif ($action === 'publish' || $action === 'unpublish') {
        $result = set_assessment_status($pdo, $studentId, (int) ($_POST['assessment_id'] ?? 0), $action);
        set_flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect($pageUrl);
    } else {
        set_flash('error', 'Unknown action.');
        redirect($pageUrl);
    }
}

$list = $pdo->prepare(
    'SELECT id, assessment_date, height_cm, weight_kg, bmi, bmi_category,
            systolic_bp, diastolic_bp, notes, status
     FROM physical_assessments
     WHERE student_user_id = :id
     ORDER BY assessment_date DESC, id DESC'
);
$list->execute([':id' => $studentId]);
$assessments = $list->fetchAll();

$pageTitle = 'Physical assessments';
require_once __DIR__ . '/../templates/header.php';
?>
<h1>Physical assessments: <?= e($student['full_name']) ?></h1>
<p class="muted">School ID: <?= e($student['school_id']) ?></p>

<section class="card">
  <h2>Record an assessment</h2>
  <p class="muted">A new assessment is saved as a draft. The student cannot see drafts.</p>

  <?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endforeach; ?>

  <form method="post" action="">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <?php render_assessment_fields($formValues); ?>
    <button type="submit" class="btn">Save draft</button>
  </form>
</section>

<h2>Assessments</h2>
<?php if (empty($assessments)): ?>
  <section class="card"><p>No physical assessments recorded yet.</p></section>
<?php endif; ?>

<?php foreach ($assessments as $row): ?>
  <section class="card">
    <div class="row-head">
      <h2><?= e(date('F j, Y', strtotime($row['assessment_date']))) ?></h2>
      <span class="badge badge-<?= e($row['status']) ?>">
        <?= $row['status'] === 'published' ? 'Published' : 'Draft' ?>
      </span>
    </div>
    <p>
      <strong>Height:</strong> <?= e(format_measure($row['height_cm'], 'cm')) ?><br>
      <strong>Weight:</strong> <?= e(format_measure($row['weight_kg'], 'kg')) ?><br>
      <strong>BMI:</strong> <?= e(format_bmi($row['bmi'], $row['bmi_category'])) ?><br>
      <strong>Blood pressure:</strong> <?= e(format_blood_pressure($row['systolic_bp'], $row['diastolic_bp'])) ?>
    </p>
    <?php if (!empty($row['notes'])): ?>
      <p><strong>Notes:</strong><br><?= nl2br(e($row['notes'])) ?></p>
    <?php endif; ?>

    <div class="button-row">
      <?php if ($row['status'] === 'draft'): ?>
        <a class="btn" href="<?= e(url('staff/assessment_edit.php?id=' . $row['id'])) ?>">Edit draft</a>
        <form method="post" action="<?= e(url($pageUrl)) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="publish">
          <input type="hidden" name="assessment_id" value="<?= e((string) $row['id']) ?>">
          <button type="submit" class="btn">Publish</button>
        </form>
      <?php else: ?>
        <form method="post" action="<?= e(url($pageUrl)) ?>"
              onsubmit="return confirm('Return this assessment to draft? The student will lose access until you publish it again.');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="unpublish">
          <input type="hidden" name="assessment_id" value="<?= e((string) $row['id']) ?>">
          <button type="submit" class="btn">Unpublish</button>
        </form>
      <?php endif; ?>
    </div>
  </section>
<?php endforeach; ?>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>