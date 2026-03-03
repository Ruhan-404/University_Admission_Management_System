<?php
require_once '../includes/db.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: ../login.php"); exit;
}

$student_id = (int)$_SESSION['student_id'];
$tran_id    = $_POST['tran_id'] ?? $_GET['tran_id'] ?? '';
$status     = $_POST['status']  ?? '';
$val_id     = $_POST['val_id']  ?? '';

// Only process if SSLCommerz says VALID
if ($status !== 'VALID' || empty($tran_id)) {
    header("Location: fail.php"); exit;
}

// Validate with SSLCommerz
$store_id   = 'testbox';
$store_pass = 'qwerty';
$is_live    = false;

$val_url = ($is_live
    ? 'https://securepay.sslcommerz.com'
    : 'https://sandbox.sslcommerz.com')
    . "/validator/api/validationserverAPI.php?val_id={$val_id}&store_id={$store_id}&store_passwd={$store_pass}&v=1&format=json";

$ch = curl_init($val_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$json = curl_exec($ch);
curl_close($ch);

$val = json_decode($json, true);

if (($val['status'] ?? '') === 'VALID' || ($val['status'] ?? '') === 'VALIDATED') {
    // Mark payment as verified
    $conn->prepare("UPDATE payments SET status='verified', method='online' WHERE tran_id=?")->execute() ;

    $upPay = $conn->prepare("UPDATE payments SET status='verified', method='online' WHERE tran_id=?");
    $upPay->bind_param("s", $tran_id);
    $upPay->execute();
    $upPay->close();

    // Mark step 3 as done
    $stepStmt = $conn->prepare("SELECT id FROM admission_steps WHERE step_order = 3 AND is_active = 1 LIMIT 1");
    $stepStmt->execute();
    $stepRow = $stepStmt->get_result()->fetch_assoc();
    $stepStmt->close();

    if ($stepRow) {
        $step_id = (int)$stepRow['id'];
        $ins = $conn->prepare("INSERT IGNORE INTO student_step_status (student_id, step_id, status) VALUES (?, ?, 'waiting')");
        $ins->bind_param("ii", $student_id, $step_id);
        $ins->execute(); $ins->close();

        $up = $conn->prepare("UPDATE student_step_status SET status='done' WHERE student_id=? AND step_id=?");
        $up->bind_param("ii", $student_id, $step_id);
        $up->execute(); $up->close();
    }

    $page_title = "Payment Successful";
    include '../includes/header.php';
    ?>
    <section class="card" style="text-align:center; max-width:480px; margin:0 auto;">
      <div style="width:70px;height:70px;background:#dcfce7;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:32px;">✅</div>
      <h2 style="color:#15803d; margin-bottom:8px;">Payment Successful!</h2>
      <p style="color:var(--muted); margin-bottom:4px;">Transaction ID: <strong><?= htmlspecialchars($tran_id) ?></strong></p>
      <p style="color:var(--muted); margin-bottom:20px;">Your admission fee has been received.</p>
      <a href="../dashboard.php" class="btn" style="text-decoration:none; background:#5c6bc0;">Go to Dashboard</a>
    </section>
    <?php
    include '../includes/footer.php';
} else {
    header("Location: fail.php");
}
