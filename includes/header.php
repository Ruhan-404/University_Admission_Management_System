<?php
// Use $page_title in each page if you want.
$page_title = $page_title ?? 'Undergraduate Admission System';

// ------------------------------------------------------------
// BASE_URL auto-detect (works for /admission/ and /admission/teacher/)
// ------------------------------------------------------------
$scriptName = $_SERVER['SCRIPT_NAME'] ?? ''; // e.g. /admission/dashboard.php or /admission/teacher/login.php
$basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

// If current file is inside /teacher or /dean, go up one level
foreach (['/teacher', '/dean', '/register', '/exam', '/itcard', '/payment'] as $sub) {
  if (substr($basePath, -strlen($sub)) === $sub) {
    $basePath = substr($basePath, 0, -strlen($sub));
    break;
  }
}

// Ensure trailing slash
$BASE_URL = $basePath . '/';

// ------------------------------------------------------------
// Simple active helper (works from teacher folder too)
// ------------------------------------------------------------
function is_active(string $file): string {
  return (basename($_SERVER['PHP_SELF']) === $file) ? 'aria-current="page"' : '';
}

$is_logged_in = isset($_SESSION['student_id']);
$is_admin     = isset($_SESSION['admin_id']);

// If you later add teacher auth:
$is_teacher   = isset($_SESSION['teacher_id']);
$is_dean      = isset($_SESSION['dean_id']);
$is_register  = isset($_SESSION['register_id']);
$is_exam      = isset($_SESSION['exam_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($page_title) ?></title>

  <!-- Icons (Font Awesome) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <!-- Custom CSS (your DU/Barishal style) -->
  <link rel="stylesheet" href="<?= htmlspecialchars($BASE_URL) ?>css/style.css" />
</head>

<body>
  <!-- TOP HEADER -->
  <header class="topbar">
    <div class="header-left">
      <div class="header-logo">
        <img src="<?= htmlspecialchars($BASE_URL) ?>assets/Logo.svg" alt="University Logo">
      </div>

      <div class="header-text">
        <div class="university-name">University of Barishal</div>
        <div class="system-name">Admission Management System</div>
      </div>
    </div>
  </header>

  <div class="wrap">
    <!-- SIDEBAR -->
    <aside class="sidebar">
      <div class="brand-panel">
        <div class="du-logo">
          <img src="<?= htmlspecialchars($BASE_URL) ?>assets/Logo.svg" alt="University Logo" onerror="this.style.display='none'">
        </div>

        <div class="auth-buttons">
          <?php if ($is_admin): ?>
            <a href="<?= htmlspecialchars($BASE_URL) ?>admin_progress.php" class="auth-btn" <?= is_active('admin_progress.php') ?>>ADMIN PANEL</a>
            <a href="<?= htmlspecialchars($BASE_URL) ?>admin_logout.php" class="auth-btn secondary">LOGOUT</a>

          <?php elseif ($is_dean): ?>
            <a href="<?= htmlspecialchars($BASE_URL) ?>dean/panel.php" class="auth-btn" <?= is_active('panel.php') ?>>DEAN PANEL</a>
            <a href="<?= htmlspecialchars($BASE_URL) ?>dean/logout.php" class="auth-btn secondary">LOGOUT</a>

          <?php elseif ($is_register): ?>
            <a href="<?= htmlspecialchars($BASE_URL) ?>register/panel.php" class="auth-btn" <?= is_active('panel.php') ?>>REGISTER PANEL</a>
            <a href="<?= htmlspecialchars($BASE_URL) ?>register/logout.php" class="auth-btn secondary">LOGOUT</a>

          <?php elseif ($is_exam): ?>
            <a href="<?= htmlspecialchars($BASE_URL) ?>exam/panel.php" class="auth-btn" <?= is_active('panel.php') ?>>EXAM PANEL</a>
            <a href="<?= htmlspecialchars($BASE_URL) ?>exam/logout.php" class="auth-btn secondary">LOGOUT</a>

          <?php elseif ($is_teacher): ?>
            <!-- Teacher buttons -->
            <a href="<?= htmlspecialchars($BASE_URL) ?>teacher/panel.php" class="auth-btn" <?= is_active('panel.php') ?>>TEACHER PANEL</a>
            <a href="<?= htmlspecialchars($BASE_URL) ?>teacher/logout.php" class="auth-btn secondary">LOGOUT</a>

          <?php elseif (!$is_logged_in): ?>
            <a href="<?= htmlspecialchars($BASE_URL) ?>login.php" class="auth-btn" <?= is_active('login.php') ?>>LOGIN</a>
            <a href="<?= htmlspecialchars($BASE_URL) ?>signup.php" class="auth-btn secondary" <?= is_active('signup.php') ?>>REGISTER</a>

          <?php else: ?>
            <a href="<?= htmlspecialchars($BASE_URL) ?>dashboard.php" class="auth-btn" <?= is_active('dashboard.php') ?>>DASHBOARD</a>
            <a href="<?= htmlspecialchars($BASE_URL) ?>logout.php" class="auth-btn secondary">LOGOUT</a>
          <?php endif; ?>
        </div>
      </div>

      <nav class="menu">
        <a class="menu-item" href="<?= htmlspecialchars($BASE_URL) ?>index.php" <?= is_active('index.php') ?>>
          <i class="fa-solid fa-house"></i>
          <span>Home Page</span>
        </a>

        <a class="menu-item" href="<?= htmlspecialchars($BASE_URL) ?>notices.php" <?= is_active('notices.php') ?>>
          <i class="fa-solid fa-list"></i>
          <span>Admission Notices</span>
        </a>
      </nav>

      <div class="sidebar-footer">
        © 2010 - <?= date('y') ?> <a href="#">Central Admission Office.</a><br/>
        <a href="#">University of Barishal</a>
      </div>
    </aside>

    <!-- MAIN -->
    <main class="main">