<?php
// File: staff/documents.php
// Staff review uploaded medical documents. Approve, or reject with a note.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/documents.php';

$staff = require_role('staff');
$pdo = getDatabase();

$filters = ['pending', 'approved', 'rejected', 'all'];
$filter = $_GET['status'] ?? 'pending';
if (!in_array($filter, $filters, true)) {
    $filter = 'pending';
}
$backUrl = 'staff/documents.php?status=' . $filter;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        set_flash('error', 'Your session expired. Please try again.');
        redirect($backUrl);
    }

    $note = trim($_POST['note'] ?? '');
    if (mb_strlen($note) > 400) {
        set_flash('error', 'The note must be 400 characters or fewer.');
        redirect($backUrl);
    }

    $result = review_document(
        $pdo,
        (int) $staff['id'],
        (int) ($_POST['document_id'] ?? 0),
        $_POST['action'] ?? '',
        $note
    );
    set_flash($result['ok'] ? 'success' : 'error', $result['message']);
    redirect($backUrl);
}

$sql = "SELECT d.id, d.document_type, d.original_filename, d.review_status, d.review_note,
               d.uploaded_at, sp.full_name, u.school_id
        FROM medical_documents d
        JOIN users u ON u.id = d.student_user_id
        JOIN student_profiles sp ON sp.user_id = u.id";
$params = [];
if ($filter !== 'all') {
    $sql .= ' WHERE d.review_status = :status';
    $params[':status'] = $filter;
}
$sql .= ' ORDER BY d.uploaded_at ASC, d.id ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$documents = $stmt->fetchAll();

$pageTitle = 'Documents';
require_once __DIR__ . '/../templates/header.php';
?>
<h1>Medical documents</h1>

<p>
  <?php foreach ($filters as $option): ?>
    <a class="filter<?= $filter === $option ? ' filter-active' : '' ?>"
       href="<?= e(url('staff/documents.php?status=' . $option)) ?>"><?= e(ucfirst($option)) ?></a>
  <?php endforeach; ?>
</p>

<?php if (empty($documents)): ?>
  <section class="card"><p>No documents match this filter.</p></section>
<?php endif; ?>

<?php foreach ($documents as $doc): ?>
  <section class="card">
    <div class="row-head">
      <h2><?= e($doc['full_name']) ?> <small>(<?= e($doc['school_id']) ?>)</small></h2>
      <span class="badge badge-<?= e($doc['review_status']) ?>"><?= e(ucfirst($doc['review_status'])) ?></span>
    </div>
    <p>
      <strong>Type:</strong> <?= e(DOCUMENT_TYPES[$doc['document_type']] ?? 'Other') ?><br>
      <strong>File:</strong> <?= e($doc['original_filename']) ?><br>
      <strong>Uploaded:</strong> <?= e($doc['uploaded_at']) ?>
    </p>
    <p><a href="<?= e(url('public/document.php?id=' . $doc['id'])) ?>" target="_blank">Open document</a></p>

    <?php if (!empty($doc['review_note'])): ?>
      <p><strong>Note:</strong> <?= e($doc['review_note']) ?></p>
    <?php endif; ?>

    <?php if ($doc['review_status'] === 'pending'): ?>
      <form method="post" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="document_id" value="<?= e((string) $doc['id']) ?>">
        <label>Note (required to reject)
          <textarea name="note" rows="2" maxlength="400"></textarea>
        </label>
        <div class="button-row">
          <button type="submit" name="action" value="approve" class="btn">Approve</button>
          <button type="submit" name="action" value="reject" class="btn btn-danger">Reject</button>
        </div>
      </form>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>