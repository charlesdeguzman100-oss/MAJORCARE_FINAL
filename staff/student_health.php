<?php
// File: staff/student_health.php
// Staff record a student's health information and vaccination records.
// Students can view these records but cannot change them.

require_once __DIR__ . '/../includes/auth.php';

$staff = require_role('staff');
$pdo = getDatabase();

$studentId = (int) ($_GET['id'] ?? 0);
$pageUrl = 'staff/student_health.php?id=' . $studentId;

// Only approved student accounts can have records
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

$bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        set_flash('error', 'Your session expired. Please try again.');
        redirect($pageUrl);
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save_health') {
        $bloodType = $_POST['blood_type'] ?? '';
        $allergies = trim($_POST['allergies'] ?? '');
        $notes     = trim($_POST['other_health_notes'] ?? '');

        if (!in_array($bloodType, $bloodTypes, true)) {
            set_flash('error', 'Choose a valid blood type.');
        } elseif (mb_strlen($allergies) > 1000 || mb_strlen($notes) > 1000) {
            set_flash('error', 'Allergies and notes must be 1000 characters or fewer.');
        } else {
            // Insert the record the first time, and update it after that
            $stmt = $pdo->prepare(
                'INSERT INTO health_information
                   (student_user_id, blood_type, allergies, other_health_notes, updated_by)
                 VALUES (:student, :blood, :allergies, :notes, :staff)
                 ON DUPLICATE KEY UPDATE
                   blood_type = :blood2,
                   allergies = :allergies2,
                   other_health_notes = :notes2,
                   updated_by = :staff2'
            );
            $stmt->execute([
                ':student'    => $studentId,
                ':blood'      => $bloodType,
                ':allergies'  => $allergies !== '' ? $allergies : null,
                ':notes'      => $notes !== '' ? $notes : null,
                ':staff'      => $staff['id'],
                ':blood2'     => $bloodType,
                ':allergies2' => $allergies !== '' ? $allergies : null,
                ':notes2'     => $notes !== '' ? $notes : null,
                ':staff2'     => $staff['id'],
            ]);
            set_flash('success', 'Health information saved.');
        }
        redirect($pageUrl);

    } elseif ($action === 'add_vaccine') {
        $vaccine = trim($_POST['vaccine_name'] ?? '');
        $dose    = trim($_POST['dose_label'] ?? '');
        $date    = trim($_POST['date_given'] ?? '');

        // The date is optional. When given, it must be a real calendar date.
        $dateValid = true;
        if ($date !== '') {
            $parsed = DateTime::createFromFormat('Y-m-d', $date);
            $dateValid = $parsed !== false && $parsed->format('Y-m-d') === $date;
        }

        if ($vaccine === '' || mb_strlen($vaccine) > 120) {
            set_flash('error', 'Enter the vaccine name (120 characters or fewer).');
        } elseif (mb_strlen($dose) > 40) {
            set_flash('error', 'Dose label must be 40 characters or fewer.');
        } elseif (!$dateValid) {
            set_flash('error', 'Enter the date as a real calendar date.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO vaccination_records
                   (student_user_id, vaccine_name, dose_label, date_given, recorded_by)
                 VALUES (:student, :vaccine, :dose, :date, :staff)'
            );
            $stmt->execute([
                ':student' => $studentId,
                ':vaccine' => $vaccine,
                ':dose'    => $dose !== '' ? $dose : null,
                ':date'    => $date !== '' ? $date : null,
                ':staff'   => $staff['id'],
            ]);
            set_flash('success', 'Vaccination record added.');
        }
        redirect($pageUrl);

    } elseif ($action === 'delete_vaccine') {
        $vaccineId = (int) ($_POST['vaccine_id'] ?? 0);

        // The student_user_id check stops a record from another student being deleted
        $stmt = $pdo->prepare(
            'DELETE FROM vaccination_records
             WHERE id = :id AND student_user_id = :student'
        );
        $stmt->execute([':id' => $vaccineId, ':student' => $studentId]);

        set_flash(
            $stmt->rowCount() === 1 ? 'success' : 'error',
            $stmt->rowCount() === 1 ? 'Vaccination record deleted.' : 'That record was not found.'
        );
        redirect($pageUrl);

    } else {
        set_flash('error', 'Unknown action.');
        redirect($pageUrl);
    }
}

// Load current records
$healthStmt = $pdo->prepare(
    'SELECT blood_type, allergies, other_health_notes
     FROM health_information
     WHERE student_user_id = :id'
);
$healthStmt->execute([':id' => $studentId]);
$health = $healthStmt->fetch() ?: ['blood_type' => 'Unknown', 'allergies' => '', 'other_health_notes' => ''];

$vaccineStmt = $pdo->prepare(
    'SELECT id, vaccine_name, dose_label, date_given
     FROM vaccination_records
     WHERE student_user_id = :id
     ORDER BY date_given DESC, id DESC'
);
$vaccineStmt->execute([':id' => $studentId]);
$vaccines = $vaccineStmt->fetchAll();

$pageTitle = 'Health records';
require_once __DIR__ . '/../templates/header.php';
?>
<h1>Health records: <?= e($student['full_name']) ?></h1>
<p class="muted">School ID: <?= e($student['school_id']) ?></p>

<section class="card">
  <h2>Basic information</h2>
  <form method="post" action="<?= e(url($pageUrl)) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_health">

    <label>Blood type
      <select name="blood_type">
        <?php foreach ($bloodTypes as $type): ?>
          <option value="<?= e($type) ?>" <?= $health['blood_type'] === $type ? 'selected' : '' ?>>
            <?= e($type) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Allergies
      <textarea name="allergies" rows="3" maxlength="1000"><?= e($health['allergies'] ?? '') ?></textarea>
    </label>
    <label>Other health notes
      <textarea name="other_health_notes" rows="3" maxlength="1000"><?= e($health['other_health_notes'] ?? '') ?></textarea>
    </label>
    <button type="submit" class="btn">Save basic information</button>
  </form>
</section>

<section class="card">
  <h2>Vaccination records</h2>

  <?php if (empty($vaccines)): ?>
    <p>No vaccination records yet.</p>
  <?php else: ?>
    <table>
      <thead>
        <tr><th>Vaccine</th><th>Dose</th><th>Date given</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php foreach ($vaccines as $row): ?>
          <tr>
            <td><?= e($row['vaccine_name']) ?></td>
            <td><?= e($row['dose_label'] ?? 'Not recorded') ?></td>
            <td><?= e($row['date_given'] ?? 'Not recorded') ?></td>
            <td>
              <form method="post" action="<?= e(url($pageUrl)) ?>"
                    onsubmit="return confirm('Delete this vaccination record?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_vaccine">
                <input type="hidden" name="vaccine_id" value="<?= e((string) $row['id']) ?>">
                <button type="submit" class="btn btn-danger">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <h2>Add a vaccination record</h2>
  <form method="post" action="<?= e(url($pageUrl)) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_vaccine">
    <label>Vaccine name
      <input type="text" name="vaccine_name" maxlength="120" required>
    </label>
    <label>Dose (optional, for example "Dose 1")
      <input type="text" name="dose_label" maxlength="40">
    </label>
    <label>Date given (optional)
      <input type="date" name="date_given">
    </label>
    <button type="submit" class="btn">Add record</button>
  </form>
</section>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>