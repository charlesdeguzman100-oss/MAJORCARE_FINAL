<?php
// File: staff/registrations.php
// Registrations in four sections: Pending, Rejected, Approved, and Removed.
// The tabs at the top show one section at a time. All shows every section.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/submissions.php';

$staff = require_role('staff');
$pdo = getDatabase();

// The tab the staff member chose: all, or one section
$sectionTitles = [
    'pending'  => 'Pending',
    'rejected' => 'Rejected',
    'approved' => 'Approved',
    'removed'  => 'Removed',
];
$filter = $_GET['status'] ?? 'all';
if ($filter !== 'all' && !isset($sectionTitles[$filter])) {
    $filter = 'all';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        set_flash('error', 'Your session expired. Please try again.');
        redirect('staff/registrations.php?status=' . urlencode($filter));
    }

    $action       = $_POST['action'] ?? '';
    $submissionId = (int) ($_POST['submission_id'] ?? 0);

    if ($action === 'approve') {
        $result = approve_submission($pdo, (int) $staff['id'], $submissionId, isset($_POST['verified']));
    } elseif ($action === 'reject') {
        $result = reject_submission($pdo, (int) $staff['id'], $submissionId, trim($_POST['reason'] ?? ''));
    } elseif ($action === 'undo') {
        $result = undo_submission_rejection($pdo, (int) $staff['id'], $submissionId);
    } else {
        $result = ['ok' => false, 'message' => 'Unknown action.'];
    }

    set_flash($result['ok'] ? 'success' : 'error', $result['message']);
    // Stay on the tab the staff member was using
    redirect('staff/registrations.php?status=' . urlencode($filter));
}

$submissions = $pdo->query(
    'SELECT id, school_id, full_name, email, status, rejection_reason,
            photo_original_name, created_at, user_id
     FROM registration_submissions
     ORDER BY school_id ASC, created_at DESC, id DESC'
)->fetchAll();

// Student accounts keyed by school ID
$accounts = $pdo->query(
    "SELECT school_id, id, account_status FROM users WHERE role = 'student'"
)->fetchAll(PDO::FETCH_UNIQUE);

// Group submissions by school ID, newest first within each group
// One card per submission, even when two students use the same school ID
$groups = [];
foreach ($submissions as $row) {
    $groups[$row['id']][] = $row;
}

// The status shown on a card. An account's own status wins.
// Without an account, the newest submission decides (pending or rejected).
function card_status(array $items, ?array $account): string
{
    if ($account !== null) {
        return $account['account_status'];
    }
    return $items[0]['status'];
}

// Badge text and style for each status
$statusMeta = [
    'pending'    => ['Pending', 'pending'],
    'rejected'   => ['Rejected', 'rejected'],
    'approved'   => ['Approved', 'approved'],
    'removed'    => ['Removed', 'removed'],
    'superseded' => ['Superseded', 'draft'],
];

// Sort each school ID into one of the four sections
$sections = ['pending' => [], 'rejected' => [], 'approved' => [], 'removed' => []];
foreach ($groups as $items) {
    $schoolId = $items[0]['school_id'];
    $account  = $accounts[$schoolId] ?? null;
    $status  = card_status($items, $account);
    if (isset($sections[$status])) {
        $sections[$status][] = [
            'id'      => $schoolId,
            'items'   => $items,
            'account' => $account,
            'status'  => $status,
        ];
    }
}

// Prints one compact card: the school ID and status, with the details under Additional info
function render_registration_card(array $card, array $statusMeta, string $filter): void
{
    $schoolId = $card['id'];
    $items    = $card['items'];
    $account  = $card['account'];
    [$label, $cls] = $statusMeta[$card['status']];
    ?>
    <section class="card">
      <details class="info-toggle">
        <summary>
          <span class="info-id"><?= e($schoolId) ?></span>
          <span class="badge badge-<?= e($cls) ?>"><?= e($label) ?></span>
          <span class="info-hint">Additional info</span>
        </summary>

        <div class="info-body">
          <p>
            <strong><?= e($items[0]['full_name']) ?></strong>
            &middot; <?= count($items) ?> submission<?= count($items) === 1 ? '' : 's' ?>
          </p>

          <?php if ($account !== null): ?>
            <p><a href="<?= e(url('staff/student_profile.php?id=' . $account['id'])) ?>">Open account profile</a></p>
          <?php endif; ?>

          <?php foreach ($items as $s): ?>
            <?php [$subLabel, $subCls] = $statusMeta[$s['status']] ?? [ucfirst($s['status']), 'draft']; ?>
            <div class="submission">
              <div class="row-head">
                <strong>Submitted <?= e(date('M j, Y g:i A', strtotime($s['created_at']))) ?></strong>
                <span class="badge badge-<?= e($subCls) ?>"><?= e($subLabel) ?></span>
              </div>
              <p>
                <strong>Name:</strong> <?= e($s['full_name']) ?><br>
                <strong>Email:</strong> <?= e($s['email'] ?? 'Not provided') ?><br>
                <strong>ID photo:</strong>
                <a href="<?= e(url('public/submission_photo.php?id=' . $s['id'])) ?>" target="_blank">Open ID photo</a>
                (<?= e($s['photo_original_name']) ?>)
              </p>

              <?php if ($s['status'] === 'rejected'): ?>
                <p><strong>Reason:</strong> <?= e($s['rejection_reason'] ?? 'No reason recorded') ?></p>
              <?php endif; ?>

              <?php if ($s['status'] === 'pending'): ?>
              <form method="post" action="<?= e(url('staff/registrations.php?status=' . urlencode($filter))) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="submission_id" value="<?= e((string) $s['id']) ?>">
                  <label class="checkbox-label">
                    <input type="checkbox" name="verified" value="1">
                    I checked this ID against the enrollment list (required to approve)
                  </label>
                  <div class="button-row">
                    <button type="submit" name="action" value="approve" class="btn">Approve</button>
                  </div>
                </form>
                <form method="post" action="">
                  <?= csrf_field() ?>
                  <input type="hidden" name="submission_id" value="<?= e((string) $s['id']) ?>">
                  <label>Reason for rejecting
                    <textarea name="reason" rows="2" maxlength="400"></textarea>
                  </label>
                  <button type="submit" name="action" value="reject" class="btn btn-danger">Reject</button>
                </form>
              <?php endif; ?>

              <?php if ($s['status'] === 'rejected'): ?>
                <form method="post" action=""
                      onsubmit="return confirm('Return this submission to pending?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="submission_id" value="<?= e((string) $s['id']) ?>">
                  <input type="hidden" name="action" value="undo">
                  <button type="submit" class="btn">Undo rejection</button>
                </form>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </details>
    </section>
    <?php
}

$pageTitle = 'Registrations';
require_once __DIR__ . '/../templates/header.php';
?>
<h1>Registrations</h1>

<p>
  <a class="filter<?= $filter === 'all' ? ' filter-active' : '' ?>"
     href="<?= e(url('staff/registrations.php?status=all')) ?>">All</a>
  <?php foreach ($sectionTitles as $key => $title): ?>
    <a class="filter<?= $filter === $key ? ' filter-active' : '' ?>"
       href="<?= e(url('staff/registrations.php?status=' . $key)) ?>"><?= e($title) ?> (<?= count($sections[$key]) ?>)</a>
  <?php endforeach; ?>
</p>

<?php if (empty($groups)): ?>
  <section class="card"><p>No registrations have been submitted.</p></section>
<?php endif; ?>

<?php foreach ($sectionTitles as $key => $title): ?>
  <?php if ($filter !== 'all' && $filter !== $key) { continue; } ?>
  <h2><?= e($title) ?> <span class="muted">(<?= count($sections[$key]) ?>)</span></h2>
  <?php if (empty($sections[$key])): ?>
    <section class="card"><p class="muted">None.</p></section>
  <?php else: ?>
    <?php foreach ($sections[$key] as $card): ?>
    <?php render_registration_card($card, $statusMeta, $filter); ?>
    <?php endforeach; ?>
  <?php endif; ?>
<?php endforeach; ?>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>