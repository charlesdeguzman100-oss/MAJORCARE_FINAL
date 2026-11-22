<?php
// File: staff/messages.php
// Staff list of student conversations, with unread counts and a search by school ID.

require_once __DIR__ . '/../includes/auth.php';

require_role('staff');
$pdo = getDatabase();

// Search by school ID. Typed text is matched literally, so % and _ are escaped.
$search = trim($_GET['q'] ?? '');

$sql = "SELECT c.student_user_id, c.last_message_at, sp.full_name, u.school_id,
               (SELECT COUNT(*)
                FROM messages m
                JOIN users mu ON mu.id = m.sender_user_id
                WHERE m.conversation_id = c.id AND mu.role = 'student' AND m.is_read = 0) AS unread
        FROM conversations c
        JOIN users u ON u.id = c.student_user_id
        JOIN student_profiles sp ON sp.user_id = c.student_user_id
        WHERE c.last_message_at IS NOT NULL";
$params = [];

if ($search !== '') {
    $sql .= ' AND u.school_id LIKE :search';
    $params[':search'] = '%' . addcslashes($search, '%_\\') . '%';
}
$sql .= ' ORDER BY c.last_message_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$conversations = $stmt->fetchAll();

$pageTitle = 'Messages';
require_once __DIR__ . '/../templates/header.php';
?>
<h1>Student messages</h1>

<section class="card">
  <form method="get" action="<?= e(url('staff/messages.php')) ?>" class="search-form">
    <label>Search by school ID
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="for example, 00-0000-000" autocomplete="off">
    </label>
    <div class="button-row">
      <button type="submit" class="btn">Search</button>
      <?php if ($search !== ''): ?>
        <a class="btn" href="<?= e(url('staff/messages.php')) ?>">Clear</a>
      <?php endif; ?>
    </div>
  </form>
</section>

<?php if (empty($conversations)): ?>
  <section class="card">
    <p>
      <?= $search !== ''
          ? 'No conversation matches the school ID "' . e($search) . '".'
          : 'No student messages yet.' ?>
    </p>
  </section>
<?php endif; ?>

<?php foreach ($conversations as $row): ?>
  <section class="card">
    <div class="row-head">
      <h2><?= e($row['full_name']) ?> <small>(<?= e($row['school_id']) ?>)</small></h2>
      <?php if ((int) $row['unread'] > 0): ?>
        <span class="badge badge-pending"><?= (int) $row['unread'] ?> unread</span>
      <?php endif; ?>
    </div>
    <p class="muted">Last message: <?= e(date('M j, Y g:i A', strtotime($row['last_message_at']))) ?></p>
    <a class="btn" href="<?= e(url('staff/message_thread.php?student=' . $row['student_user_id'])) ?>">Open conversation</a>
  </section>
<?php endforeach; ?>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>