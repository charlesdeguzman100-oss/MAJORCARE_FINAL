<?php
// File: staff/student_results.php
// Staff upload laboratory and examination results for one student, then publish them.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/results.php';

$staff = require_role('staff');
$pdo = getDatabase();

$studentId = (int) ($_GET['id'] ?? 0);
$pageUrl = 'staff/student_results.php?id=' . $studentId;

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        set_flash('error', 'Your session expired. Please try again.');
        redirect($pageUrl);
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        $type        = trim($_POST['result_type'] ?? '');
        $date        = trim($_POST['result_date'] ?? '');
        $description = trim($_POST['description'] ?? '');

        $errors = lab_result_errors($type, $date, $description);

        $file = $_FILES['result_file'] ?? ['error' => UPLOAD_ERR_NO_FILE];
        [$fileErrors, $mime] = check_uploaded_file($file);
        $errors = array_merge($errors, $fileErrors);

        if (!empty($errors)) {
            set_flash('error', $errors[0]);
        } else {
            $result = save_lab_result($pdo, $studentId, (int) $staff['id'],
                $type, $date, $description, $file, $mime);
            set_flash($result['ok'] ? 'success' : 'error', $result['message']);
        }
    } elseif ($action === 'publish' || $action === 'unpublish') {
        $result = set_lab_result_status($pdo, $studentId, (int) ($_POST['result_id'] ?? 0), $action);
        set_flash($result['ok'] ? 'success' : 'error', $result['message']);
    } else {
        set_flash('error', 'Unknown action.');
    }

    redirect($pageUrl);
}

$list = $pdo->prepare(
    'SELECT id, result_type, result_date, description, original_filename, is_published
     FROM laboratory_results
     WHERE student_user_id = :id
     ORDER BY result_date DESC, id DESC'
);
$list->execute([':id' => $studentId]);
$results = $list->fetchAll();

$pageTitle = 'Laboratory results';
require_once __DIR__ . '/../templates/header.php';
?>
<h1>Laboratory and examination results: <?= e($student['full_name']) ?></h1>
<p class="muted">School ID: <?= e($student['school_id']) ?></p>

<section class="card">
  <h2>Upload a result</h2>
  <p class="muted">Results are saved as unpublished. The student cannot see them until you publish them.</p>
  <form method="post" action="" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="upload">
    <label>Result type
      <input type="text" name="result_type" maxlength="120" placeholder="for example, Complete blood count" required>
    </label>
    <label>Result date
      <input type="date" name="result_date" max="<?= e(date('Y-m-d')) ?>" required>
    </label>
    <label>Description (optional)
      <textarea name="description" rows="2" maxlength="500"></textarea>
    </label>
    <label>File
      <input type="file" name="result_file" accept=".pdf,.jpg,.jpeg,.png" required>
    </label>
    <p class="muted">PDF, JPG, or PNG, up to 5 MB.</p>
    <button type="submit" class="btn">Upload result</button>
  </form>
</section>

<h2>Results</h2>
<?php if (empty($results)): ?>
  <section class="card"><p>No results uploaded yet.</p></section>
<?php endif; ?>

<?php foreach ($results as $row): ?>
  <section class="card">
    <div class="row-head">
      <h2><?= e($row['result_type']) ?></h2>
      <span class="badge badge-<?= $row['is_published'] ? 'published' : 'draft' ?>">
        <?= $row['is_published'] ? 'Published' : 'Not published' ?>
      </span>
    </div>
    <p>
      <strong>Date:</strong> <?= e(date('F j, Y', strtotime($row['result_date']))) ?><br>
      <strong>File:</strong> <?= e($row['original_filename']) ?>
    </p>
    <?php if (!empty($row['description'])): ?>
      <p><strong>Description:</strong> <?= e($row['description']) ?></p>
    <?php endif; ?>
    <p><a href="<?= e(url('public/lab_result.php?id=' . $row['id'])) ?>" target="_blank">Open file</a></p>

    <form method="post" action="<?= e(url($pageUrl)) ?>" class="inline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="result_id" value="<?= e((string) $row['id']) ?>">
      <?php if ($row['is_published']): ?>
        <input type="hidden" name="action" value="unpublish">
        <button type="submit" class="btn">Unpublish</button>
      <?php else: ?>
        <input type="hidden" name="action" value="publish">
        <button type="submit" class="btn">Publish</button>
      <?php endif; ?>
    </form>
  </section>
<?php endforeach; ?>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>