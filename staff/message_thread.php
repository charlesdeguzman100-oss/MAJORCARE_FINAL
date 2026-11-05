<?php
// File: staff/message_thread.php
// Staff read and reply to one student's conversation.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/messages.php';

$staff = require_role('staff');
$pdo = getDatabase();

$studentId = (int) ($_GET['student'] ?? 0);
$pageUrl = 'staff/message_thread.php?student=' . $studentId;

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
    redirect('staff/messages.php');
}

$conversationId = get_or_create_conversation($pdo, $studentId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        set_flash('error', 'Your session expired. Please try again.');
        redirect($pageUrl);
    }

    $result = send_message($pdo, $studentId, (int) $staff['id'], 'staff', $_POST['body'] ?? '');
    set_flash($result['ok'] ? 'success' : 'error', $result['message']);
    redirect($pageUrl);
}

$messages = conversation_messages($pdo, $conversationId);
mark_messages_read($pdo, $conversationId, 'staff');

$pageTitle = 'Conversation';
require_once __DIR__ . '/../templates/header.php';
?>
<h1><?= e($student['full_name']) ?></h1>
<p class="muted">School ID: <?= e($student['school_id']) ?> &middot;
  <a href="<?= e(url('staff/messages.php')) ?>">Back to messages</a></p>

<section class="card">
  <?php if (empty($messages)): ?>
    <p>No messages yet.</p>
  <?php endif; ?>

  <div class="thread">
    <?php foreach ($messages as $m): ?>
      <?php $mine = $m['sender_role'] === 'staff'; ?>
      <div class="msg <?= $mine ? 'msg-mine' : 'msg-theirs' ?>">
        <div class="msg-meta">
          <?= $mine ? 'You' : e($m['sender_name']) ?> &middot;
          <?= e(date('M j, Y g:i A', strtotime($m['created_at']))) ?>
        </div>
        <div class="msg-body"><?= nl2br(e($m['body'])) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="card">
  <h2>Reply</h2>
  <form method="post" action="<?= e(url($pageUrl)) ?>">
    <?= csrf_field() ?>
    <label>Message (up to 2000 characters)
      <textarea name="body" rows="4" maxlength="2000" required></textarea>
    </label>
    <button type="submit" class="btn">Send reply</button>
  </form>
</section>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>