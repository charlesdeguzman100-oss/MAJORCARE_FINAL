<?php
// File: includes/visits.php
// Shared rules and form fields for clinic visit records.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

// Example output: "November 5, 2026"
function format_visit_date(string $date): string
{
    return date('F j, Y', strtotime($date));
}

// Reads the visit form fields from the submitted POST data
function visit_input_from_post(): array
{
    return [
        'visit_date'      => trim($_POST['visit_date'] ?? ''),
        'reason'          => trim($_POST['reason'] ?? ''),
        'notes'           => trim($_POST['notes'] ?? ''),
        'recommendations' => trim($_POST['recommendations'] ?? ''),
    ];
}

// Returns a list of problems with the input. An empty list means the input is valid.
function visit_input_errors(array $input): array
{
    $errors = [];

    $date = DateTime::createFromFormat('Y-m-d', $input['visit_date']);
    if ($date === false || $date->format('Y-m-d') !== $input['visit_date']) {
        $errors[] = 'Enter the visit date as a real calendar date.';
    } elseif ($input['visit_date'] > date('Y-m-d')) {
        $errors[] = 'The visit date cannot be in the future.';
    }

    if ($input['reason'] === '' || mb_strlen($input['reason']) > 500) {
        $errors[] = 'Enter the reason for the visit (500 characters or fewer).';
    }
    if (mb_strlen($input['notes']) > 5000) {
        $errors[] = 'Notes must be 5000 characters or fewer.';
    }
    if (mb_strlen($input['recommendations']) > 5000) {
        $errors[] = 'Recommendations must be 5000 characters or fewer.';
    }

    return $errors;
}

// Prints the visit form fields. $values holds the current values.
function render_visit_fields(array $values): void
{
    ?>
    <label>Visit date
      <input type="date" name="visit_date" max="<?= e(date('Y-m-d')) ?>"
             value="<?= e($values['visit_date']) ?>" required>
    </label>
    <label>Reason for visit
      <input type="text" name="reason" maxlength="500"
             value="<?= e($values['reason']) ?>" required>
    </label>
    <label>Notes (optional)
      <textarea name="notes" rows="3" maxlength="5000"><?= e($values['notes']) ?></textarea>
    </label>
    <label>Recommendations (optional)
      <textarea name="recommendations" rows="3" maxlength="5000"><?= e($values['recommendations']) ?></textarea>
    </label>
    <?php
}