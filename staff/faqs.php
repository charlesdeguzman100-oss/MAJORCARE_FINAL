<?php
// File: staff/faqs.php
// Staff add, edit, show or hide, reorder, and delete clinic FAQs.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/clinic.php';

$staff = require_role('staff');
$pdo = getDatabase();

$errors = [];
$editId = (int) ($_GET['edit'] ?? 0);

$form = [
    'faq_id'        => 0,
    'question'      => '',
    'answer'        => '',
    'display_order' => '0',
    'is_published'  => true,
];

// Load one FAQ into the form for editing
if ($editId > 0) {
    $stmt = $pdo->prepare(
        'SELECT id, question, answer, display_order, is_published FROM clinic_faqs WHERE id = :id'
    );
    $stmt->execute([':id' => $editId]);
    $row = $stmt->fetch();

    if ($row === false) {
        set_flash('error', 'That FAQ was not found.');
        redirect('staff/faqs.php');
    }

    $form = [
        'faq_id'        => (int) $row['id'],
        'question'      => $row['question'],
        'answer'        => $row['answer'],
        'display_order' => (string) $row['display_order'],
        'is_published'  => (int) $row['is_published'] === 1,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        set_flash('error', 'Your session expired. Please try again.');
        redirect('staff/faqs.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $faqId     = (int) ($_POST['faq_id'] ?? 0);
        $question  = trim($_POST['question'] ?? '');
        $answer    = trim($_POST['answer'] ?? '');
        $order     = trim($_POST['display_order'] ?? '0');
        $published = isset($_POST['is_published']);

        $form = [
            'faq_id'        => $faqId,
            'question'      => $question,
            'answer'        => $answer,
            'display_order' => $order,
            'is_published'  => $published,
        ];

        $errors = faq_errors($question, $answer, $order);

        if (empty($errors)) {
            $result = save_faq($pdo, (int) $staff['id'], $faqId > 0 ? $faqId : null,
                $question, $answer, (int) $order, $published);
            set_flash($result['ok'] ? 'success' : 'error', $result['message']);
            redirect('staff/faqs.php');
        }
    } elseif ($action === 'delete') {
        $result = delete_faq($pdo, (int) ($_POST['faq_id'] ?? 0));
        set_flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect('staff/faqs.php');
    } else {
        set_flash('error', 'Unknown action.');
        redirect('staff/faqs.php');
    }
}

$faqs = $pdo->query(
    'SELECT id, question, answer, display_order, is_published
     FROM clinic_faqs
     ORDER BY display_order ASC, id ASC'
)->fetchAll();

$pageTitle = 'FAQs';
require_once __DIR__ . '/../templates/header.php';
?>
<h1>Clinic FAQs</h1>
<p class="muted"><a href="<?= e(url('staff/clinic_info.php')) ?>">Back to clinic information</a></p>

<section class="card">
  <h2><?= $form['faq_id'] > 0 ? 'Edit FAQ' : 'Add an FAQ' ?></h2>

  <?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endforeach; ?>

  <form method="post" action="">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="faq_id" value="<?= e((string) $form['faq_id']) ?>">
    <label>Question
      <input type="text" name="question" maxlength="255" value="<?= e($form['question']) ?>" required>
    </label>
    <label>Answer
      <textarea name="answer" rows="4" maxlength="5000" required><?= e($form['answer']) ?></textarea>
    </label>
    <label>Display order (lower numbers appear first)
      <input type="number" name="display_order" min="0" max="999" value="<?= e($form['display_order']) ?>">
    </label>
    <label class="checkbox-label">
      <input type="checkbox" name="is_published" value="1" <?= $form['is_published'] ? 'checked' : '' ?>>
      Show this FAQ to students
    </label>
    <div class="button-row">
      <button type="submit" class="btn">Save FAQ</button>
      <?php if ($form['faq_id'] > 0): ?>
        <a class="btn" href="<?= e(url('staff/faqs.php')) ?>">Cancel</a>
      <?php endif; ?>
    </div>
  </form>
</section>

<h2>All FAQs</h2>
<?php if (empty($faqs)): ?>
  <section class="card"><p>No FAQs yet.</p></section>
<?php endif; ?>

<?php foreach ($faqs as $faq): ?>
  <section class="card">
    <div class="row-head">
      <h2><?= e($faq['question']) ?></h2>
      <span class="badge badge-<?= $faq['is_published'] ? 'published' : 'draft' ?>">
        <?= $faq['is_published'] ? 'Shown' : 'Hidden' ?>
      </span>
    </div>
    <p><?= nl2br(e($faq['answer'])) ?></p>
    <p class="muted">Display order: <?= (int) $faq['display_order'] ?></p>
    <div class="button-row">
      <a class="btn" href="<?= e(url('staff/faqs.php?edit=' . $faq['id'])) ?>">Edit</a>
      <form method="post" action="" onsubmit="return confirm('Delete this FAQ?');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="faq_id" value="<?= e((string) $faq['id']) ?>">
        <button type="submit" class="btn btn-danger">Delete</button>
      </form>
    </div>
  </section>
<?php endforeach; ?>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>