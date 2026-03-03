<?php
require_once '../includes/db.php';
$page_title = "Payment Failed";
include '../includes/header.php';
?>
<section class="card" style="text-align:center; max-width:480px; margin:0 auto;">
  <div style="width:70px;height:70px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:32px;">❌</div>
  <h2 style="color:#b91c1c; margin-bottom:8px;">Payment Failed</h2>
  <p style="color:var(--muted); margin-bottom:20px;">Your payment could not be processed. Please try again.</p>
  <a href="index.php" class="btn" style="text-decoration:none; background:#0d9488;">Try Again</a>
  &nbsp;
  <a href="../dashboard.php" class="btn" style="text-decoration:none; background:#6b7280;">Dashboard</a>
</section>
<?php include '../includes/footer.php'; ?>
