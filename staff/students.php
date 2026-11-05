<?php
// File: staff/students.php
// Approved students. Staff can reset a student's password to a temporary one.

require_once __DIR__ . '/../includes/auth.php';

require_role('staff');
$pdo = getDatabase();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        set_flash('error', 'Your session expired. Please try again.');
        redirect('staff/students.php');
    }

    $studentId = (int) ($_POST['user_id'] ?? 0);

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

    // 12 random hexadecimal characters. Only the hash is stored.
    $temporary = bin2hex(random_bytes(6));

    $update = $pdo->prepare(
        'UPDATE users SET password_hash = :hash, must_change_password = 1 WHERE id = :id'
    );
    $update->execute([
        ':hash' => password_hash($temporary, PASSWORD_DEFAULT),
        ':id'   => $studentId,
    ]);

    set_flash(
        'success',
        'Password reset for ' . $student['full_name'] . ' (' . $student['school_id'] . '). '
        . 'Temporary password: ' . $temporary . '. '
        . 'Give it to the student in person. The student must change it at next login. '
        . 'This message appears only once.'
    );
    redirect('staff/students.php');
}

$students = $pdo->query(
    "SELECT u.id, u.school_id, u.must_change_password, sp.full_name, sp.consent_accepted_at
     FROM users u
     JOIN student_profiles sp ON sp.user_id = u.id
     WHERE u.role = 'student' AND u.account_status = 'approved'
     ORDER BY sp.full_name ASC"
)->fetchAll();

$pageTitle = 'Students';
require_once __DIR__ . '/../templates/header.php';
?>
<h1>Approved students</h1>

<section class="card">
  <?php if (empty($students)): ?>
    <p>No approved students yet.</p>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th>School ID</th>
          <th>Privacy notice</th>
          <th>Password</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($students as $row): ?>
          <tr>
            <td><?= e($row['full_name']) ?></td>
            <td><?= e($row['school_id']) ?></td>
            <td><?= $row['consent_accepted_at'] !== null ? 'Accepted' : 'Not yet accepted' ?></td>
            <td><?= (int) $row['must_change_password'] === 1 ? 'Temporary, must change' : 'Set by student' ?></td>
            <td>
              <a href="<?= e(url('staff/student_profile.php?id=' . $row['id'])) ?>">View profile</a><br><br>
              <a href="<?= e(url('staff/student_health.php?id=' . $row['id'])) ?>">Health records</a><br><br>
              <a href="<?= e(url('staff/student_visits.php?id=' . $row['id'])) ?>">Visits</a><br><br>
              <a href="<?= e(url('staff/student_assessments.php?id=' . $row['id'])) ?>">Check-ups</a><br><br>
              <form method="post" action=""
                    onsubmit="return confirm('Reset this student\'s password to a temporary one?');">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= e((string) $row['id']) ?>">
                <button type="submit" class="btn btn-danger">Reset password</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>