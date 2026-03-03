<?php
require_once '../includes/db.php';
$page_title = "Payment Cancelled";
include '../includes/header.php';
?>
<section class="card" style="text-align:center; max-width:480px; margin:0 auto;">
  <div style="width:70px;height:70px;background:#f3f4f6;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:32px;">🚫</div>
  <h2 style="color:#374151; margin-bottom:8px;">Payment Cancelled</h2>
  <p style="color:var(--muted); margin-bottom:20px;">You cancelled the payment. You can try again anytime.</p>
  <a href="index.php" class="btn" style="text-decoration:none; background:#0d9488;">Try Again</a>
  &nbsp;
  <a href="../dashboard.php" class="btn" style="text-decoration:none; background:#6b7280;">Dashboard</a>
</section>
<?php include '../includes/footer.php'; ?>
