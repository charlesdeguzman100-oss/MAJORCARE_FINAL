<?php
// File: public/notifications.php
// Notification page for students and clinic staff.
// Opening one item marks it read, then sends the user to its linked page.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/appointments.php'; // create_reminders()
require_once __DIR__ . '/../includes/notifications.php';

$user = require_login();
$pdo = getDatabase();
$userId = (int) $user['id'];

// Students finish setup first, as on every other student page
if ($user['role'] === 'student') {
    if ((int) $user['must_change_password'] === 1) {
        redirect('public/change_password.php');
    }
    if ($user['consent_accepted_at'] === null) {
        redirect('student/consent.php');
    }
}

// Reminders are created here too, so they appear without opening the dashboard first
create_reminders($pdo, $userId, $user['role']);

// Mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        set_flash('error', 'Your session expired. Please try again.');
        redirect('public/notifications.php');
    }
    if (($_POST['action'] ?? '') === 'mark_all_read') {
        $count = mark_all_notifications_read($pdo, $userId);
        set_flash('success', $count . ' notification' . ($count === 1 ? '' : 's') . ' marked as read.');
    }
    redirect('public/notifications.php');
}

// Open one notification: mark it read, then go to its page
if (isset($_GET['open'])) {
    $id = (int) $_GET['open'];
    mark_notification_read($pdo, $userId, $id);

    $stmt = $pdo->prepare('SELECT link FROM notifications WHERE id = :id AND user_id = :user');
    $stmt->execute([':id' => $id, ':user' => $userId]);
    $row = $stmt->fetch();

    redirect(safe_notification_link($row['link'] ?? null));
}

$notifications = list_notifications($pdo, $userId);
$unread = unread_notification_count($pdo, $userId);

$pageTitle = 'Notifications';
require_once __DIR__ . '/../templates/header.php';
?>
<h1>Notifications</h1>
<p class="muted"><?= $unread ?> unread</p>

<?php if ($unread > 0): ?>
  <form method="post" action="<?= e(url('public/notifications.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="mark_all_read">
    <button type="submit" class="btn">Mark all as read</button>
  </form>
<?php endif; ?>

<?php if (empty($notifications)): ?>
  <section class="card"><p>No notifications yet.</p></section>
<?php endif; ?>

<?php foreach ($notifications as $n): ?>
  <?php $isNew = (int) $n['is_read'] === 0; ?>
  <section class="card<?= $isNew ? ' notification-new' : '' ?>">
    <div class="row-head">
      <h2><?= e($n['title']) ?></h2>
      <span class="badge"><?= e(NOTIFICATION_TYPE_LABELS[$n['notification_type']] ?? 'Notice') ?></span>
    </div>
    <p><?= e($n['message']) ?></p>
    <p class="muted">
      <?= e(notification_time($n['created_at'])) ?>
      <?= $isNew ? '&middot; New' : '' ?>
    </p>
    <a class="btn" href="<?= e(url('public/notifications.php?open=' . $n['id'])) ?>">Open</a>
  </section>
<?php endforeach; ?>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>