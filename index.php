<?php
require_once 'includes/db.php';

// Redirect if already logged in
if (isset($_SESSION['student_id'])) {
    header('Location: dashboard.php');
    exit;
}

$page_title = 'Home';
include 'includes/header.php';
?>

<section class="card" role="region" aria-label="Welcome">
  <h2>Welcome</h2>
  <div class="alert">
    Students selected through the GST Admission Test can complete their university registration here.
    If you already registered, log in with your Registration ID (GST Roll) and password.
  </div>

  <div class="submit-row" style="gap:12px;">
    <a class="btn" href="login.php" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">
      Login
    </a>
    <a class="btn" href="signup.php" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center; background:#5c6bc0;">
      Register
    </a>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
