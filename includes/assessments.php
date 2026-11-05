<?php
// File: includes/assessments.php
// Physical assessment rules: validation, BMI, drafts, publishing, and notifications.
// New assessments are drafts. Students see only published assessments.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/appointments.php'; // add_notification()

// BMI = weight in kg divided by height in metres squared, rounded to one decimal place
function calculate_bmi(float $heightCm, float $weightKg): float
{
    $heightM = $heightCm / 100;
    return round($weightKg / ($heightM * $heightM), 1);
}

// WHO categories for adults
function bmi_category(float $bmi): string
{
    if ($bmi < 18.5) {
        return 'Underweight';
    }
    if ($bmi < 25.0) {
        return 'Normal weight';
    }
    if ($bmi < 30.0) {
        return 'Overweight';
    }
    return 'Obese';
}

// Display helpers. Missing values show "Not recorded" or "Not calculated".
function format_measure($value, string $unit): string
{
    return ($value === null || $value === '') ? 'Not recorded' : $value . ' ' . $unit;
}

function format_blood_pressure($systolic, $diastolic): string
{
    if ($systolic === null || $diastolic === null) {
        return 'Not recorded';
    }
    return $systolic . '/' . $diastolic . ' mmHg';
}

function format_bmi($bmi, $category): string
{
    if ($bmi === null) {
        return 'Not calculated';
    }
    return $bmi . ' (' . $category . ')';
}

// Reads the assessment form fields from POST data
function assessment_input_from_post(): array
{
    return [
        'assessment_date' => trim($_POST['assessment_date'] ?? ''),
        'height_cm'       => trim($_POST['height_cm'] ?? ''),
        'weight_kg'       => trim($_POST['weight_kg'] ?? ''),
        'systolic_bp'     => trim($_POST['systolic_bp'] ?? ''),
        'diastolic_bp'    => trim($_POST['diastolic_bp'] ?? ''),
        'notes'           => trim($_POST['notes'] ?? ''),
    ];
}

// Checks the form and converts it to database values. Returns [errors, values].
function assessment_validate(array $input): array
{
    $errors = [];
    $values = [
        'assessment_date' => $input['assessment_date'],
        'height_cm'       => null,
        'weight_kg'       => null,
        'bmi'             => null,
        'bmi_category'    => null,
        'systolic_bp'     => null,
        'diastolic_bp'    => null,
        'notes'           => $input['notes'] !== '' ? $input['notes'] : null,
    ];

    $date = DateTime::createFromFormat('Y-m-d', $input['assessment_date']);
    if ($date === false || $date->format('Y-m-d') !== $input['assessment_date']) {
        $errors[] = 'Enter the assessment date as a real calendar date.';
    } elseif ($input['assessment_date'] > date('Y-m-d')) {
        $errors[] = 'The assessment date cannot be in the future.';
    }

    $hasHeight    = $input['height_cm'] !== '';
    $hasWeight    = $input['weight_kg'] !== '';
    $hasSystolic  = $input['systolic_bp'] !== '';
    $hasDiastolic = $input['diastolic_bp'] !== '';

    if ($hasHeight) {
        if (!is_numeric($input['height_cm']) || $input['height_cm'] < 50 || $input['height_cm'] > 250) {
            $errors[] = 'Height must be a number from 50 to 250 cm.';
        } else {
            $values['height_cm'] = round((float) $input['height_cm'], 1);
        }
    }

    if ($hasWeight) {
        if (!is_numeric($input['weight_kg']) || $input['weight_kg'] < 20 || $input['weight_kg'] > 300) {
            $errors[] = 'Weight must be a number from 20 to 300 kg.';
        } else {
            $values['weight_kg'] = round((float) $input['weight_kg'], 1);
        }
    }

    if ($hasSystolic !== $hasDiastolic) {
        $errors[] = 'Enter both systolic and diastolic blood pressure, or leave both empty.';
    } elseif ($hasSystolic) {
        $s = $input['systolic_bp'];
        $d = $input['diastolic_bp'];
        if (!ctype_digit($s) || !ctype_digit($d)) {
            $errors[] = 'Blood pressure values must be whole numbers.';
        } elseif ((int) $s < 60 || (int) $s > 250 || (int) $d < 30 || (int) $d > 150) {
            $errors[] = 'Systolic must be 60 to 250 and diastolic must be 30 to 150.';
        } elseif ((int) $s <= (int) $d) {
            $errors[] = 'Systolic pressure must be higher than diastolic pressure.';
        } else {
            $values['systolic_bp']  = (int) $s;
            $values['diastolic_bp'] = (int) $d;
        }
    }

    if (!$hasHeight && !$hasWeight && !$hasSystolic) {
        $errors[] = 'Enter at least one measurement.';
    }
    if (mb_strlen($input['notes']) > 2000) {
        $errors[] = 'Notes must be 2000 characters or fewer.';
    }

    // BMI needs both height and weight
    if ($values['height_cm'] !== null && $values['weight_kg'] !== null) {
        $values['bmi'] = calculate_bmi($values['height_cm'], $values['weight_kg']);
        $values['bmi_category'] = bmi_category($values['bmi']);
    }

    return [$errors, $values];
}

// Prints the assessment form fields. $values holds the current values.
function render_assessment_fields(array $values): void
{
    ?>
    <label>Assessment date
      <input type="date" name="assessment_date" max="<?= e(date('Y-m-d')) ?>"
             value="<?= e($values['assessment_date']) ?>" required>
    </label>
    <label>Height (cm)
      <input type="number" name="height_cm" step="0.1" min="50" max="250"
             value="<?= e((string) ($values['height_cm'] ?? '')) ?>">
    </label>
    <label>Weight (kg)
      <input type="number" name="weight_kg" step="0.1" min="20" max="300"
             value="<?= e((string) ($values['weight_kg'] ?? '')) ?>">
    </label>
    <label>Systolic blood pressure (mmHg)
      <input type="number" name="systolic_bp" min="60" max="250"
             value="<?= e((string) ($values['systolic_bp'] ?? '')) ?>">
    </label>
    <label>Diastolic blood pressure (mmHg)
      <input type="number" name="diastolic_bp" min="30" max="150"
             value="<?= e((string) ($values['diastolic_bp'] ?? '')) ?>">
    </label>
    <label>Notes (optional)
      <textarea name="notes" rows="3" maxlength="2000"><?= e($values['notes'] ?? '') ?></textarea>
    </label>
    <?php
}

// Saves a draft. With $assessmentId, it updates an existing draft. Without it, it adds a new draft.
function save_assessment(PDO $pdo, int $studentId, int $staffId, ?int $assessmentId, array $values): array
{
    try {
        $pdo->beginTransaction();

        $fields = [
            ':date'     => $values['assessment_date'],
            ':height'   => $values['height_cm'],
            ':weight'   => $values['weight_kg'],
            ':bmi'      => $values['bmi'],
            ':category' => $values['bmi_category'],
            ':systolic' => $values['systolic_bp'],
            ':diastolic'=> $values['diastolic_bp'],
            ':notes'    => $values['notes'],
        ];

        if ($assessmentId !== null) {
            $lock = $pdo->prepare(
                'SELECT status FROM physical_assessments
                 WHERE id = :id AND student_user_id = :student
                 FOR UPDATE'
            );
            $lock->execute([':id' => $assessmentId, ':student' => $studentId]);
            $row = $lock->fetch();

            if ($row === false) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'That assessment was not found.'];
            }
            if ($row['status'] !== 'draft') {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'Unpublish the assessment before you edit it.'];
            }

            $update = $pdo->prepare(
                'UPDATE physical_assessments
                 SET assessment_date = :date, height_cm = :height, weight_kg = :weight,
                     bmi = :bmi, bmi_category = :category,
                     systolic_bp = :systolic, diastolic_bp = :diastolic, notes = :notes
                 WHERE id = :id'
            );
            $update->execute($fields + [':id' => $assessmentId]);
            $message = 'Draft updated.';
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO physical_assessments
                   (student_user_id, assessment_date, height_cm, weight_kg, bmi, bmi_category,
                    systolic_bp, diastolic_bp, notes, status, recorded_by)
                 VALUES (:student, :date, :height, :weight, :bmi, :category,
                         :systolic, :diastolic, :notes, \'draft\', :staff)'
            );
            $insert->execute($fields + [':student' => $studentId, ':staff' => $staffId]);
            $message = 'Assessment saved as a draft. Publish it to make it visible to the student.';
        }

        $pdo->commit();
        return ['ok' => true, 'message' => $message];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Assessment save failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'The assessment could not be saved. Please try again.'];
    }
}

// Publishes a draft, or returns a published assessment to draft.
// Publishing notifies the student. Unpublishing does not.
function set_assessment_status(PDO $pdo, int $studentId, int $assessmentId, string $action): array
{
    if (!in_array($action, ['publish', 'unpublish'], true)) {
        return ['ok' => false, 'message' => 'Unknown action.'];
    }

    try {
        $pdo->beginTransaction();

        $lock = $pdo->prepare(
            'SELECT status, assessment_date FROM physical_assessments
             WHERE id = :id AND student_user_id = :student
             FOR UPDATE'
        );
        $lock->execute([':id' => $assessmentId, ':student' => $studentId]);
        $row = $lock->fetch();

        if ($row === false) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'That assessment was not found.'];
        }

        if ($action === 'publish') {
            if ($row['status'] !== 'draft') {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'This assessment is already published.'];
            }

            $update = $pdo->prepare(
                "UPDATE physical_assessments SET status = 'published', published_at = NOW() WHERE id = :id"
            );
            $update->execute([':id' => $assessmentId]);

            add_notification($pdo, $studentId, 'result', 'New physical assessment',
                'Your physical assessment for ' . date('F j, Y', strtotime($row['assessment_date']))
                . ' is available.', 'student/results.php');

            $message = 'Assessment published.';
        } else {
            if ($row['status'] !== 'published') {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'Only published assessments can be unpublished.'];
            }

            $update = $pdo->prepare(
                "UPDATE physical_assessments SET status = 'draft', published_at = NULL WHERE id = :id"
            );
            $update->execute([':id' => $assessmentId]);

            $message = 'Assessment returned to draft. The student cannot see it until you publish it again.';
        }

        $pdo->commit();
        return ['ok' => true, 'message' => $message];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Assessment status change failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'The change could not be saved. Please try again.'];
    }
}