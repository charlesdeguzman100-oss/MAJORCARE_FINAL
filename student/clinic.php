<?php
// File: student/clinic.php
// Clinic information, published FAQs, and published announcements. Read-only for students.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/clinic.php';

$user = require_role('student');
$pdo = getDatabase();

$info = get_clinic_information($pdo);

$faqs = $pdo->query(
    'SELECT question, answer FROM clinic_faqs
     WHERE is_published = 1
     ORDER BY display_order ASC, id ASC'
)->fetchAll();

$announcements = $pdo->query(
    'SELECT title, body, published_at FROM announcements
     WHERE is_published = 1
     ORDER BY published_at DESC, id DESC'
)->fetchAll();

$pageTitle = 'Clinic';
require_once __DIR__ . '/../templates/header.php';
?>
<h1><?= e($info['clinic_name']) ?></h1>

<section class="card">
  <h2>Clinic details</h2>
  <p>
    <strong>Location:</strong> <?= e($info['location'] ?? 'Not provided') ?><br>
    <strong>Operating hours:</strong> <?= e($info['operating_hours'] ?? 'Not provided') ?><br>
    <strong>Contact number:</strong> <?= e($info['contact_number'] ?? 'Not provided') ?><br>
    <strong>Contact email:</strong> <?= e($info['contact_email'] ?? 'Not provided') ?>
  </p>
</section>

<h2>Announcements</h2>
<?php if (empty($announcements)): ?>
  <section class="card"><p>No announcements right now.</p></section>
<?php endif; ?>
<?php foreach ($announcements as $row): ?>
  <section class="card">
    <h2><?= e($row['title']) ?></h2>
    <p class="muted"><?= e(date('M j, Y g:i A', strtotime($row['published_at']))) ?></p>
    <p><?= nl2br(e($row['body'])) ?></p>
  </section>
<?php endforeach; ?>

<h2>Frequently asked questions</h2>
<?php if (empty($faqs)): ?>
  <section class="card"><p>No questions published yet.</p></section>
<?php endif; ?>
<section class="card">
  <?php foreach ($faqs as $faq): ?>
    <details class="faq">
      <summary><?= e($faq['question']) ?></summary>
      <p><?= nl2br(e($faq['answer'])) ?></p>
    </details>
  <?php endforeach; ?>
</section>

<p class="muted">MajorCare is an administrative portal. It does not replace professional medical services.</p>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>