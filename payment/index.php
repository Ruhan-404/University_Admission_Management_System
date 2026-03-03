<?php
require_once '../includes/db.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: ../login.php"); exit;
}

$student_id   = (int)$_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? 'Student';
$student_dept = $_SESSION['student_dept'] ?? '';
$gst_roll     = $_SESSION['student_gst'] ?? '';

// Check current payment step status
$chk = $conn->prepare("
    SELECT sss.status FROM student_step_status sss
    JOIN admission_steps st ON st.id = sss.step_id
    WHERE sss.student_id = ? AND st.step_order = 3 LIMIT 1
");
$chk->bind_param("i", $student_id);
$chk->execute();
$chkRow = $chk->get_result()->fetch_assoc();
$chk->close();
$payment_status = $chkRow['status'] ?? 'waiting';

if ($payment_status === 'done') {
    header("Location: ../dashboard.php"); exit;
}

$page_title = "Bank Payment";
include '../includes/header.php';
?>

<style>
  .pay-card { background:#fff; border:1px solid var(--line); border-radius:14px; padding:28px; max-width:520px; margin:0 auto; box-shadow:0 4px 20px rgba(0,0,0,.08); }
  .pay-header { text-align:center; margin-bottom:28px; }
  .pay-header .icon { width:60px; height:60px; background:linear-gradient(135deg,#0d9488,#059669); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 14px; font-size:26px; color:#fff; }
  .pay-header h2 { margin:0 0 6px; font-size:22px; }
  .pay-header p  { margin:0; color:var(--muted); font-size:13px; }
  .amount-box { background:#f0fdf4; border:1px solid #86efac; border-radius:10px; padding:14px 20px; text-align:center; margin-bottom:24px; }
  .amount-box .lbl { font-size:12px; color:#6b7280; margin-bottom:4px; }
  .amount-box .amt { font-size:28px; font-weight:800; color:#15803d; }
  .option-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:20px; }
  .option-btn { border:2px solid var(--line); border-radius:12px; padding:20px 14px; text-align:center; cursor:pointer; transition:all .2s; background:#fff; text-decoration:none; color:var(--text); display:block; }
  .option-btn:hover { border-color:#0d9488; box-shadow:0 4px 12px rgba(13,148,136,.15); }
  .option-btn.active { border-color:#0d9488; background:#f0fdfa; }
  .option-btn .opt-icon { font-size:28px; margin-bottom:10px; }
  .option-btn .opt-title { font-weight:700; font-size:15px; margin-bottom:4px; }
  .option-btn .opt-desc  { font-size:12px; color:var(--muted); }
  .panel { display:none; margin-top:8px; }
  .panel.active { display:block; }
  .ssl-btn { width:100%; padding:14px; background:linear-gradient(135deg,#0d9488,#059669); color:#fff; border:none; border-radius:10px; font-size:16px; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:10px; transition:opacity .2s; }
  .ssl-btn:hover { opacity:.9; }
  .verify-field { margin-bottom:14px; }
  .verify-field label { display:block; font-weight:600; margin-bottom:6px; font-size:14px; }
  .verify-field input, .verify-field select { width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:8px; font-size:14px; box-sizing:border-box; }
  .verify-field input:focus, .verify-field select:focus { outline:none; border-color:#0d9488; }
  .verify-btn { width:100%; padding:12px; background:#5c6bc0; color:#fff; border:none; border-radius:10px; font-size:15px; font-weight:700; cursor:pointer; }
  .verify-btn:hover { opacity:.9; }
  .alert-info    { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; border-radius:8px; padding:10px 14px; margin-bottom:14px; font-size:13px; }
  .alert-warning { background:#fffbeb; color:#92400e; border:1px solid #fde68a; border-radius:8px; padding:10px 14px; margin-bottom:14px; font-size:13px; }
</style>

<section class="card">
  <div class="pay-card">

    <div class="pay-header">
      <div class="icon"><i class="fa-solid fa-credit-card"></i></div>
      <h2>Bank Payment</h2>
      <p>Step 3 of your admission process</p>
    </div>

    <div style="background:#f9fafb; border-radius:8px; padding:12px 16px; margin-bottom:20px; font-size:13px;">
      <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
        <span style="color:var(--muted);">Name</span><strong><?= htmlspecialchars($student_name) ?></strong>
      </div>
      <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
        <span style="color:var(--muted);">GST Roll</span><strong><?= htmlspecialchars($gst_roll) ?></strong>
      </div>
      <div style="display:flex; justify-content:space-between;">
        <span style="color:var(--muted);">Department</span><strong><?= htmlspecialchars($student_dept) ?></strong>
      </div>
    </div>

    <div class="amount-box">
      <div class="lbl">Admission Fee</div>
      <div class="amt">&#2547; 5,000</div>
    </div>

    <?php if ($payment_status === 'pending'): ?>
      <div class="alert-warning">
        <i class="fa-solid fa-clock"></i>
        Your payment is <strong>under verification</strong>. Please wait for confirmation from the admin.
      </div>
      <a href="../dashboard.php" style="display:block; text-align:center; color:#5c6bc0; font-size:14px; margin-top:10px;">&#8592; Back to Dashboard</a>

    <?php else: ?>

      <div class="option-grid">
        <a class="option-btn active" onclick="showPanel('online'); setActive(this)" href="javascript:void(0)">
          <div class="opt-icon">💳</div>
          <div class="opt-title">Pay Online</div>
          <div class="opt-desc">bKash, Nagad, Card via SSLCommerz</div>
        </a>
        <a class="option-btn" onclick="showPanel('verify'); setActive(this)" href="javascript:void(0)">
          <div class="opt-icon">🧾</div>
          <div class="opt-title">Verify Payment</div>
          <div class="opt-desc">Already paid? Enter your Transaction ID</div>
        </a>
      </div>

      <!-- Online Payment Panel -->
      <div class="panel active" id="panel-online">
        <div class="alert-info">
          <i class="fa-solid fa-info-circle"></i>
          You will be redirected to SSLCommerz secure payment gateway. Supports bKash, Nagad, Rocket, Visa, Mastercard.
        </div>
        <form method="POST" action="initiate.php">
          <button type="submit" class="ssl-btn">
            <i class="fa-solid fa-lock"></i> Pay Securely &mdash; &#2547;5,000
          </button>
        </form>
        <p style="text-align:center; font-size:11px; color:#9ca3af; margin-top:10px;">Secured by SSLCommerz 🔒</p>
      </div>

      <!-- Verify Payment Panel -->
      <div class="panel" id="panel-verify">
        <div class="alert-info">
          <i class="fa-solid fa-info-circle"></i>
          Already paid via bank or mobile banking? Enter your Transaction ID for verification.
        </div>
        <form method="POST" action="verify.php">
          <div class="verify-field">
            <label>Transaction ID</label>
            <input type="text" name="transaction_id" placeholder="e.g. TXN1234567890" required>
          </div>
          <div class="verify-field">
            <label>Payment Method</label>
            <select name="payment_method">
              <option value="bkash">bKash</option>
              <option value="nagad">Nagad</option>
              <option value="rocket">Rocket</option>
              <option value="bank">Bank Transfer</option>
              <option value="other">Other</option>
            </select>
          </div>
          <button type="submit" class="verify-btn">
            <i class="fa-solid fa-paper-plane"></i> Submit for Verification
          </button>
        </form>
      </div>

    <?php endif; ?>

  </div>
</section>

<script>
function showPanel(name) {
  document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
  document.getElementById('panel-' + name).classList.add('active');
}
function setActive(el) {
  document.querySelectorAll('.option-btn').forEach(b => b.classList.remove('active'));
  el.classList.add('active');
}
</script>

<?php include '../includes/footer.php'; ?>
