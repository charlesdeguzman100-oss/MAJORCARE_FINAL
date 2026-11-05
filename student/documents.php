<?php
// File: student/documents.php
// Students upload medical documents and see each document's review status.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/documents.php';

$user = require_role('student');
$pdo = getDatabase();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        set_flash('error', 'Your session expired. Please try again.');
        redirect('student/documents.php');
    }

    $type = $_POST['document_type'] ?? '';
    if (!array_key_exists($type, DOCUMENT_TYPES)) {
        set_flash('error', 'Choose a document type.');
        redirect('student/documents.php');
    }

    $file = $_FILES['document'] ?? ['error' => UPLOAD_ERR_NO_FILE];
    [$errors, $mime] = check_uploaded_file($file);

    if (!empty($errors)) {
        set_flash('error', $errors[0]);
        redirect('student/documents.php');
    }

    $result = save_document_upload($pdo, (int) $user['id'], $type, $file, $mime);
    set_flash($result['ok'] ? 'success' : 'error', $result['message']);
    redirect('student/documents.php');
}

$stmt = $pdo->prepare(
    'SELECT id, document_type, original_filename, review_status, review_note, uploaded_at
     FROM medical_documents
     WHERE student_user_id = :id
     ORDER BY uploaded_at DESC, id DESC'
);
$stmt->execute([':id' => $user['id']]);
$documents = $stmt->fetchAll();

$pageTitle = 'My documents';
require_once __DIR__ . '/../templates/header.php';
?>
<h1>My documents</h1>

<section class="card">
  <h2>Upload a document</h2>
  <form method="post" action="" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <label>Document type
      <select name="document_type" required>
        <option value="">Choose a type</option>
        <?php foreach (DOCUMENT_TYPES as $value => $label): ?>
          <option value="<?= e($value) ?>"><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>File
      <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required>
    </label>
    <p class="muted">PDF, JPG, or PNG, up to 5 MB.</p>
    <button type="submit" class="btn">Upload</button>
  </form>
</section>

<h2>Submitted documents</h2>
<?php if (empty($documents)): ?>
  <section class="card"><p>You have not uploaded any documents yet.</p></section>
<?php endif; ?>

<?php foreach ($documents as $doc): ?>
  <section class="card">
    <div class="row-head">
      <h2><?= e(DOCUMENT_TYPES[$doc['document_type']] ?? 'Other') ?></h2>
      <span class="badge badge-<?= e($doc['review_status']) ?>"><?= e(ucfirst($doc['review_status'])) ?></span>
    </div>
    <p><strong>File:</strong> <?= e($doc['original_filename']) ?></p>
    <p><strong>Uploaded:</strong> <?= e($doc['uploaded_at']) ?></p>
    <?php if ($doc['review_status'] === 'rejected' && !empty($doc['review_note'])): ?>
      <p><strong>Reason:</strong> <?= e($doc['review_note']) ?></p>
    <?php endif; ?>
    <?php if ($doc['review_status'] === 'approved'): ?>
      <p><a href="<?= e(url('public/document.php?id=' . $doc['id'])) ?>" target="_blank">Open document</a></p>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>