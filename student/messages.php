<?php
// File: student/messages.php
// Private messages between the student and clinic staff. Not for emergencies.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/messages.php';

$user = require_role('student');
$pdo = getDatabase();
$studentId = (int) $user['id'];

$conversationId = get_or_create_conversation($pdo, $studentId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        set_flash('error', 'Your session expired. Please try again.');
        redirect('student/messages.php');
    }

    $result = send_message($pdo, $studentId, $studentId, 'student', $_POST['body'] ?? '');
    set_flash($result['ok'] ? 'success' : 'error', $result['message']);
    redirect('student/messages.php');
}

$messages = conversation_messages($pdo, $conversationId);
mark_messages_read($pdo, $conversationId, 'student');

$pageTitle = 'Messages';
require_once __DIR__ . '/../templates/header.php';
?>
<h1>Messages</h1>

<section class="card notice">
  <p><strong>Not for emergencies.</strong> Use messages for appointments, submitted documents, and non-urgent
     concerns. In an emergency, go to the clinic or call emergency services.</p>
</section>

<section class="card">
  <?php if (empty($messages)): ?>
    <p>No messages yet. Send a message below.</p>
  <?php endif; ?>

  <div class="thread">
    <?php foreach ($messages as $m): ?>
      <?php $mine = $m['sender_role'] === 'student'; ?>
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
  <h2>Send a message</h2>
  <form method="post" action="">
    <?= csrf_field() ?>
    <label>Message (up to 2000 characters)
      <textarea name="body" rows="4" maxlength="2000" required></textarea>
    </label>
    <button type="submit" class="btn">Send</button>
  </form>
</section>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>