<?php
// File: templates/header.php
// Top of every page. Pages set $pageTitle before they include this file.

require_once __DIR__ . '/../includes/auth.php';

start_session();
$navUser = current_user();
$flash = get_flash();
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
  <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="<?= e(url('public/index.php')) ?>"><?= e(APP_NAME) ?></a>
    <nav>
      <?php if ($navUser === null): ?>
        <a href="<?= e(url('public/index.php')) ?>">Log in</a>
        <a href="<?= e(url('public/register.php')) ?>">Register</a>
      <?php elseif ($navUser['role'] === 'student'): ?>
        <a href="<?= e(url('student/dashboard.php')) ?>">Dashboard</a>
        <a href="<?= e(url('student/profile.php')) ?>">Profile</a>
        <a href="<?= e(url('student/health.php')) ?>">Health</a>
        <a href="<?= e(url('student/appointments.php')) ?>">Appointments</a>
        <a href="<?= e(url('student/book.php')) ?>">Book</a>
        <a href="<?= e(url('student/visits.php')) ?>">Visits</a>
        <a href="<?= e(url('student/documents.php')) ?>">Documents</a>
        <form method="post" action="<?= e(url('public/logout.php')) ?>" class="inline-form">
          <?= csrf_field() ?>
          <button type="submit" class="link-button">Log out</button>
        </form>
      <?php else: ?>
        <a href="<?= e(url('staff/dashboard.php')) ?>">Dashboard</a>
        <a href="<?= e(url('staff/registrations.php')) ?>">Registrations</a>
        <a href="<?= e(url('staff/students.php')) ?>">Students</a>
        <a href="<?= e(url('staff/appointments.php')) ?>">Appointments</a>
        <a href="<?= e(url('staff/slots.php')) ?>">Slots</a>
        <a href="<?= e(url('staff/documents.php')) ?>">Documents</a>
        <form method="post" action="<?= e(url('public/logout.php')) ?>" class="inline-form">
          <?= csrf_field() ?>
          <button type="submit" class="link-button">Log out</button>
        </form>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="container">
<?php if ($flash): ?>
  <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>
