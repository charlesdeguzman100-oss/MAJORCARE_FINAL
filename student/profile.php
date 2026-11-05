<?php
// File: student/profile.php
// Student profile. Students edit their own contact and emergency details.
// The school ID and account status are shown but cannot be changed here.

require_once __DIR__ . '/../includes/auth.php';

$user = require_role('student');
$pdo = getDatabase();

// Empty form fields are stored as NULL in the database
function nullable_value(string $value): ?string
{
    return $value === '' ? null : $value;
}

$errors = [];
$form = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        $errors[] = 'Your session expired. Please try again.';
    }

    $form = [
        'full_name'                      => trim($_POST['full_name'] ?? ''),
        'email'                          => trim($_POST['email'] ?? ''),
        'contact_number'                 => trim($_POST['contact_number'] ?? ''),
        'address'                        => trim($_POST['address'] ?? ''),
        'emergency_contact_name'         => trim($_POST['emergency_contact_name'] ?? ''),
        'emergency_contact_relationship' => trim($_POST['emergency_contact_relationship'] ?? ''),
        'emergency_contact_number'       => trim($_POST['emergency_contact_number'] ?? ''),
    ];

    if ($form['full_name'] === '' || mb_strlen($form['full_name']) > 120) {
        $errors[] = 'Enter your full name (120 characters or fewer).';
    }
    if ($form['email'] !== ''
        && (mb_strlen($form['email']) > 120 || !filter_var($form['email'], FILTER_VALIDATE_EMAIL))) {
        $errors[] = 'Enter a valid email address, or leave it empty.';
    }
    if ($form['contact_number'] !== ''
        && !preg_match('/^[0-9+()\- ]{7,20}$/', $form['contact_number'])) {
        $errors[] = 'Contact number must be 7 to 20 characters: digits, spaces, +, (, ), or dashes.';
    }
    if (mb_strlen($form['address']) > 255) {
        $errors[] = 'Address must be 255 characters or fewer.';
    }
    if (mb_strlen($form['emergency_contact_name']) > 120) {
        $errors[] = 'Emergency contact name must be 120 characters or fewer.';
    }
    if (mb_strlen($form['emergency_contact_relationship']) > 50) {
        $errors[] = 'Relationship must be 50 characters or fewer.';
    }
    if ($form['emergency_contact_number'] !== ''
        && !preg_match('/^[0-9+()\- ]{7,20}$/', $form['emergency_contact_number'])) {
        $errors[] = 'Emergency contact number must be 7 to 20 characters: digits, spaces, +, (, ), or dashes.';
    }

    // A name without a number, or a number without a name, is incomplete
    $hasName   = $form['emergency_contact_name'] !== '';
    $hasNumber = $form['emergency_contact_number'] !== '';
    if ($hasName !== $hasNumber) {
        $errors[] = 'Enter both the emergency contact name and number, or leave both empty.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'UPDATE student_profiles
             SET full_name = :full_name,
                 email = :email,
                 contact_number = :contact_number,
                 address = :address,
                 emergency_contact_name = :ec_name,
                 emergency_contact_relationship = :ec_relationship,
                 emergency_contact_number = :ec_number
             WHERE user_id = :id'
        );
        $stmt->execute([
            ':full_name'       => $form['full_name'],
            ':email'           => nullable_value($form['email']),
            ':contact_number'  => nullable_value($form['contact_number']),
            ':address'         => nullable_value($form['address']),
            ':ec_name'         => nullable_value($form['emergency_contact_name']),
            ':ec_relationship' => nullable_value($form['emergency_contact_relationship']),
            ':ec_number'       => nullable_value($form['emergency_contact_number']),
            ':id'              => $user['id'],
        ]);

        set_flash('success', 'Your profile was updated.');
        redirect('student/profile.php');
    }
}

// Load the saved profile for the form, unless a failed submission is being shown
if ($form === null) {
    $stmt = $pdo->prepare(
        'SELECT full_name, email, contact_number, address,
                emergency_contact_name, emergency_contact_relationship, emergency_contact_number
         FROM student_profiles
         WHERE user_id = :id'
    );
    $stmt->execute([':id' => $user['id']]);
    $form = $stmt->fetch() ?: [];
}

$pageTitle = 'My profile';
require_once __DIR__ . '/../templates/header.php';
?>
<h1>My profile</h1>

<?php foreach ($errors as $error): ?>
  <div class="alert alert-error"><?= e($error) ?></div>
<?php endforeach; ?>

<section class="card">
  <p><strong>School ID:</strong> <?= e($user['school_id']) ?> (cannot be changed)</p>

  <form method="post" action="">
    <?= csrf_field() ?>

    <h2>Personal details</h2>
    <label>Full name
      <input type="text" name="full_name" value="<?= e($form['full_name'] ?? '') ?>" required>
    </label>
    <label>Email (optional)
      <input type="email" name="email" value="<?= e($form['email'] ?? '') ?>">
    </label>
    <label>Contact number (optional)
      <input type="text" name="contact_number" value="<?= e($form['contact_number'] ?? '') ?>">
    </label>
    <label>Address (optional)
      <input type="text" name="address" value="<?= e($form['address'] ?? '') ?>">
    </label>

    <h2>Emergency contact</h2>
    <label>Name
      <input type="text" name="emergency_contact_name" value="<?= e($form['emergency_contact_name'] ?? '') ?>">
    </label>
    <label>Relationship
      <input type="text" name="emergency_contact_relationship" value="<?= e($form['emergency_contact_relationship'] ?? '') ?>">
    </label>
    <label>Number
      <input type="text" name="emergency_contact_number" value="<?= e($form['emergency_contact_number'] ?? '') ?>">
    </label>

    <button type="submit" class="btn">Save profile</button>
  </form>
</section>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>