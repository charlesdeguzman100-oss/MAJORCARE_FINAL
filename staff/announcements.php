<?php
// File: staff/announcements.php
// Staff write, edit, publish, unpublish, and delete clinic announcements.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/clinic.php';

$staff = require_role('staff');
$pdo = getDatabase();

$errors = [];
$editId = (int) ($_GET['edit'] ?? 0);

$form = ['announcement_id' => 0, 'title' => '', 'body' => ''];

if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT id, title, body FROM announcements WHERE id = :id');
    $stmt->execute([':id' => $editId]);
    $row = $stmt->fetch();

    if ($row === false) {
        set_flash('error', 'That announcement was not found.');
        redirect('staff/announcements.php');
    }

    $form = ['announcement_id' => (int) $row['id'], 'title' => $row['title'], 'body' => $row['body']];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        set_flash('error', 'Your session expired. Please try again.');
        redirect('staff/announcements.php');
    }

    $action = $_POST['action'] ?? '';
    $announcementId = (int) ($_POST['announcement_id'] ?? 0);

    if ($action === 'save') {
        $title = trim($_POST['title'] ?? '');
        $body  = trim($_POST['body'] ?? '');

        $form = ['announcement_id' => $announcementId, 'title' => $title, 'body' => $body];
        $errors = announcement_errors($title, $body);

        if (empty($errors)) {
            $result = save_announcement($pdo, (int) $staff['id'],
                $announcementId > 0 ? $announcementId : null, $title, $body);
            set_flash($result['ok'] ? 'success' : 'error', $result['message']);
            redirect('staff/announcements.php');
        }
    } elseif ($action === 'publish' || $action === 'unpublish') {
        $result = set_announcement_published($pdo, $announcementId, $action === 'publish');
        set_flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect('staff/announcements.php');
    } elseif ($action === 'delete') {
        $result = delete_announcement($pdo, $announcementId);
        set_flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect('staff/announcements.php');
    } else {
        set_flash('error', 'Unknown action.');
        redirect('staff/announcements.php');
    }
}

$announcements = $pdo->query(
    'SELECT id, title, body, is_published, published_at, created_at
     FROM announcements
     ORDER BY created_at DESC, id DESC'
)->fetchAll();

$pageTitle = 'Announcements';
require_once __DIR__ . '/../templates/header.php';
?>
<h1>Clinic announcements</h1>
<p class="muted"><a href="<?= e(url('staff/clinic_info.php')) ?>">Back to clinic information</a></p>

<section class="card">
  <h2><?= $form['announcement_id'] > 0 ? 'Edit announcement' : 'Write an announcement' ?></h2>
  <p class="muted">New announcements are drafts. Publishing one sends a notification to every approved student.</p>

  <?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endforeach; ?>

  <form method="post" action="">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="announcement_id" value="<?= e((string) $form['announcement_id']) ?>">
    <label>Title
      <input type="text" name="title" maxlength="150" value="<?= e($form['title']) ?>" required>
    </label>
    <label>Announcement
      <textarea name="body" rows="5" maxlength="5000" required><?= e($form['body']) ?></textarea>
    </label>
    <div class="button-row">
      <button type="submit" class="btn">Save</button>
      <?php if ($form['announcement_id'] > 0): ?>
        <a class="btn" href="<?= e(url('staff/announcements.php')) ?>">Cancel</a>
      <?php endif; ?>
    </div>
  </form>
</section>

<h2>All announcements</h2>
<?php if (empty($announcements)): ?>
  <section class="card"><p>No announcements yet.</p></section>
<?php endif; ?>

<?php foreach ($announcements as $row): ?>
  <section class="card">
    <div class="row-head">
      <h2><?= e($row['title']) ?></h2>
      <span class="badge badge-<?= $row['is_published'] ? 'published' : 'draft' ?>">
        <?= $row['is_published'] ? 'Published' : 'Draft' ?>
      </span>
    </div>
    <p><?= nl2br(e($row['body'])) ?></p>
    <p class="muted">
      Created: <?= e(date('M j, Y g:i A', strtotime($row['created_at']))) ?>
      <?php if ($row['published_at'] !== null): ?>
        &middot; Published: <?= e(date('M j, Y g:i A', strtotime($row['published_at']))) ?>
      <?php endif; ?>
    </p>

    <div class="button-row">
      <a class="btn" href="<?= e(url('staff/announcements.php?edit=' . $row['id'])) ?>">Edit</a>

      <?php if ($row['is_published']): ?>
        <form method="post" action=""
              onsubmit="return confirm('Unpublish this announcement? Students will no longer see it.');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="unpublish">
          <input type="hidden" name="announcement_id" value="<?= e((string) $row['id']) ?>">
          <button type="submit" class="btn">Unpublish</button>
        </form>
      <?php else: ?>
        <form method="post" action="">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="publish">
          <input type="hidden" name="announcement_id" value="<?= e((string) $row['id']) ?>">
          <button type="submit" class="btn">Publish</button>
        </form>
      <?php endif; ?>

      <form method="post" action="" onsubmit="return confirm('Delete this announcement?');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="announcement_id" value="<?= e((string) $row['id']) ?>">
        <button type="submit" class="btn btn-danger">Delete</button>
      </form>
    </div>
  </section>
<?php endforeach; ?>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>