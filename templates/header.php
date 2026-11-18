<?php
// File: templates/header.php
// Top of every page. Pages set $pageTitle before they include this file.
// Logged-in users get the application shell (sidebar + topbar); guests get the auth layout.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/icons.php';

start_session();
$navUser = current_user();
$flash = get_flash();
$pageTitle = $pageTitle ?? APP_NAME;

// Unread count shown next to the Notifications link
$unreadCount = $navUser !== null
    ? unread_notification_count(getDatabase(), (int) $navUser['id'])
    : 0;

$isGuest = ($navUser === null);
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');

// Brand artwork:
//   majorcare-logo.png      = owl mark only (paired with a text wordmark below)
//   majorcare-logo-full.png = complete lockup with the MAJORCARE wordmark built in
// If neither file exists, a clean text fallback is shown.
$hasMark     = is_file(__DIR__ . '/../assets/img/majorcare-logo.png');
$hasFullLogo = is_file(__DIR__ . '/../assets/img/majorcare-logo-full.png');
$markUrl     = e(url('assets/img/majorcare-logo.png'));
$fullLogoUrl = e(url('assets/img/majorcare-logo-full.png'));

// Which sidebar item should be highlighted on this page.
$activeFile = $currentScript;
if (in_array($currentScript, [
    'student_profile.php', 'student_health.php', 'student_visits.php',
    'student_assessments.php', 'student_results.php',
    'visit_edit.php', 'assessment_edit.php',
], true)) {
    $activeFile = 'students.php';
} elseif ($currentScript === 'message_thread.php') {
    $activeFile = 'messages.php';
}

// Navigation definitions. These mirror the existing role-based links exactly.
$studentNav = [
    ['section' => 'My care', 'label' => 'Dashboard',        'file' => 'dashboard.php',    'href' => url('student/dashboard.php'),    'icon' => 'dashboard'],
    ['section' => 'My care', 'label' => 'Appointments',      'file' => 'appointments.php',  'href' => url('student/appointments.php'),  'icon' => 'calendar'],
    ['section' => 'My care', 'label' => 'Book appointment',  'file' => 'book.php',          'href' => url('student/book.php'),          'icon' => 'plus'],
    ['section' => 'My care', 'label' => 'Visit history',     'file' => 'visits.php',        'href' => url('student/visits.php'),        'icon' => 'activity'],
    ['section' => 'My care', 'label' => 'Check-up results',  'file' => 'results.php',       'href' => url('student/results.php'),       'icon' => 'file'],
    ['section' => 'My care', 'label' => 'Health information','file' => 'health.php',        'href' => url('student/health.php'),        'icon' => 'heart'],
    ['section' => 'My care', 'label' => 'Medical documents', 'file' => 'documents.php',     'href' => url('student/documents.php'),     'icon' => 'file'],
    ['section' => 'Clinic',  'label' => 'Clinic information','file' => 'clinic.php',        'href' => url('student/clinic.php'),        'icon' => 'map-pin'],
    ['section' => 'Clinic',  'label' => 'Staff availability','file' => 'availability.php',  'href' => url('student/availability.php'),  'icon' => 'clock'],
    ['section' => 'Clinic',  'label' => 'Messages',          'file' => 'messages.php',      'href' => url('student/messages.php'),      'icon' => 'message'],
    ['section' => 'Account', 'label' => 'Notifications',     'file' => 'notifications.php', 'href' => url('public/notifications.php'), 'icon' => 'bell'],
    ['section' => 'Account', 'label' => 'My profile',        'file' => 'profile.php',       'href' => url('student/profile.php'),       'icon' => 'user'],
];

$staffNav = [
    ['section' => 'Clinic management', 'label' => 'Dashboard',        'file' => 'dashboard.php',      'href' => url('staff/dashboard.php'),      'icon' => 'dashboard'],
    ['section' => 'Clinic management', 'label' => 'Registrations',    'file' => 'registrations.php',  'href' => url('staff/registrations.php'),  'icon' => 'user-plus'],
    ['section' => 'Clinic management', 'label' => 'Students',         'file' => 'students.php',       'href' => url('staff/students.php'),       'icon' => 'users'],
    ['section' => 'Clinic management', 'label' => 'Appointments',     'file' => 'appointments.php',   'href' => url('staff/appointments.php'),   'icon' => 'calendar'],
    ['section' => 'Clinic management', 'label' => 'Appointment slots','file' => 'slots.php',          'href' => url('staff/slots.php'),          'icon' => 'clock'],
    ['section' => 'Clinic management', 'label' => 'Documents',        'file' => 'documents.php',      'href' => url('staff/documents.php'),      'icon' => 'file'],
    ['section' => 'Clinic management', 'label' => 'Messages',         'file' => 'messages.php',       'href' => url('staff/messages.php'),       'icon' => 'message'],
    ['section' => 'Clinic management', 'label' => 'Availability',     'file' => 'availability.php',   'href' => url('staff/availability.php'),   'icon' => 'clock'],
    ['section' => 'Clinic management', 'label' => 'Clinic info',      'file' => 'clinic_info.php',    'href' => url('staff/clinic_info.php'),    'icon' => 'map-pin'],
    ['section' => 'Clinic management', 'label' => 'FAQs',             'file' => 'faqs.php',           'href' => url('staff/faqs.php'),           'icon' => 'help'],
    ['section' => 'Clinic management', 'label' => 'Announcements',    'file' => 'announcements.php',  'href' => url('staff/announcements.php'),  'icon' => 'megaphone'],
    ['section' => 'Notifications',     'label' => 'Notifications',    'file' => 'notifications.php',  'href' => url('public/notifications.php'), 'icon' => 'bell'],
];

$navItems = ($navUser !== null && $navUser['role'] === 'student') ? $studentNav : $staffNav;
$navSection = null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0b1f3a">
  <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
  <link rel="icon" type="image/png" href="<?= e(url('assets/img/favicon.png')) ?>">
  <link rel="apple-touch-icon" href="<?= e(url('assets/img/favicon.png')) ?>">
  <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="<?= $isGuest ? 'auth-page' : 'app-page' ?>">

<?php if ($isGuest): ?>
  <!-- Public pages: a calm, focused authentication layout. -->
  <main class="auth-main">
    <div class="auth-brand">
      <?php if ($hasFullLogo): ?>
        <img class="auth-logo" src="<?= $fullLogoUrl ?>" alt="MajorCare">
      <?php elseif ($hasMark): ?>
        <img class="auth-logo auth-logo-mark" src="<?= $markUrl ?>" alt="MajorCare owl mascot">
        <span class="auth-wordmark">MajorCare</span>
      <?php else: ?>
        <span class="brand-mark brand-mark-lg" aria-hidden="true">MC</span>
        <span class="auth-wordmark">MajorCare</span>
      <?php endif; ?>
      <p class="auth-tagline">University Campus Clinic Portal</p>
    </div>

    <?php if ($flash): ?>
      <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

<?php else: ?>
  <!-- Signed-in pages: fixed sidebar + topbar + scrolling content. -->
  <div class="app-shell">

    <aside class="sidebar" id="sidebar" aria-label="Main navigation">
      <div class="sidebar-top">
        <a class="brand" href="<?= e(url(dashboard_path($navUser['role']))) ?>">
          <span class="brand-lockup">
            <?php if ($hasFullLogo): ?>
              <img class="brand-logo" src="<?= $fullLogoUrl ?>" alt="MajorCare">
            <?php elseif ($hasMark): ?>
              <img class="brand-mark-img" src="<?= $markUrl ?>" alt="MajorCare owl mascot">
              <span class="brand-name">MajorCare</span>
            <?php else: ?>
              <span class="brand-mark" aria-hidden="true">MC</span>
              <span class="brand-name">MajorCare</span>
            <?php endif; ?>
          </span>
        </a>
        <button type="button" class="sidebar-close" id="sidebarClose" aria-label="Close navigation">
          <?= mc_icon('x', 20) ?>
        </button>
      </div>

      <nav class="side-nav">
        <?php foreach ($navItems as $item): ?>
          <?php if ($navSection !== $item['section']): ?>
            <?php $navSection = $item['section']; ?>
            <p class="nav-label"><?= e($navSection) ?></p>
          <?php endif; ?>
          <a class="nav-item<?= $item['file'] === $activeFile ? ' active' : '' ?>"
             href="<?= e($item['href']) ?>"<?= $item['file'] === $activeFile ? ' aria-current="page"' : '' ?>>
            <?= mc_icon($item['icon']) ?>
            <span class="nav-text"><?= e($item['label']) ?></span>
            <?php if ($item['file'] === 'notifications.php' && $unreadCount > 0): ?>
              <span class="count"><?= $unreadCount ?></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </nav>

      <div class="sidebar-bottom">
        <div class="user-card">
          <span class="user-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $navUser['full_name'], 0, 1))) ?></span>
          <span class="user-details">
            <span class="user-name"><?= e($navUser['full_name']) ?></span>
            <span class="user-role"><?= e(ucfirst($navUser['role'])) ?></span>
          </span>
        </div>
        <form method="post" action="<?= e(url('public/logout.php')) ?>" class="logout-form">
          <?= csrf_field() ?>
          <button type="submit" class="logout-button">
            <?= mc_icon('log-out', 17) ?> <span>Log out</span>
          </button>
        </form>
      </div>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

    <div class="app-main">
      <header class="topbar">
        <button type="button" class="menu-toggle" id="menuToggle" aria-label="Open navigation" aria-controls="sidebar" aria-expanded="false">
          <?= mc_icon('menu', 21) ?>
        </button>
        <div class="topbar-heading">
          <span class="topbar-eyebrow"><?= $navUser['role'] === 'staff' ? 'Clinic management' : 'My clinic' ?></span>
          <span class="topbar-title"><?= e($pageTitle) ?></span>
        </div>
        <div class="topbar-actions">
          <span class="topbar-pill"><?= e(ucfirst($navUser['role'])) ?></span>
          <a class="topbar-icon" href="<?= e(url('public/notifications.php')) ?>" aria-label="Notifications">
            <?= mc_icon('bell', 19) ?>
            <?php if ($unreadCount > 0): ?><span class="count"><?= $unreadCount ?></span><?php endif; ?>
          </a>
        </div>
      </header>

      <main class="content">
        <?php if ($flash): ?>
          <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>
<?php endif; ?>
