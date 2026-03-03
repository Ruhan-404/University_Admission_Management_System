<?php
require_once '../includes/db.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: ../login.php"); exit;
}

$student_id    = (int)$_SESSION['student_id'];
$tran_id       = trim($_POST['transaction_id'] ?? '');
$method        = trim($_POST['payment_method'] ?? 'other');

if (empty($tran_id)) {
    header("Location: index.php"); exit;
}

// Ensure payments table exists
$conn->query("
    CREATE TABLE IF NOT EXISTS payments (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        student_id   INT NOT NULL,
        tran_id      VARCHAR(60) NOT NULL UNIQUE,
        amount       DECIMAL(10,2) NOT NULL DEFAULT 5000.00,
        method       VARCHAR(30) DEFAULT NULL,
        status       ENUM('pending','verified','failed') DEFAULT 'pending',
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// Save payment record as pending
$ins = $conn->prepare("
    INSERT INTO payments (student_id, tran_id, amount, method, status)
    VALUES (?, ?, 5000.00, ?, 'pending')
    ON DUPLICATE KEY UPDATE method=VALUES(method), status='pending'
");
$ins->bind_param("iss", $student_id, $tran_id, $method);
$ins->execute();
$ins->close();

// Mark step 3 as pending (waiting for admin verification)
$stepStmt = $conn->prepare("SELECT id FROM admission_steps WHERE step_order = 3 AND is_active = 1 LIMIT 1");
$stepStmt->execute();
$stepRow = $stepStmt->get_result()->fetch_assoc();
$stepStmt->close();

if ($stepRow) {
    $step_id = (int)$stepRow['id'];
    $ins2 = $conn->prepare("INSERT IGNORE INTO student_step_status (student_id, step_id, status) VALUES (?, ?, 'waiting')");
    $ins2->bind_param("ii", $student_id, $step_id);
    $ins2->execute(); $ins2->close();

    $up = $conn->prepare("UPDATE student_step_status SET status='pending' WHERE student_id=? AND step_id=?");
    $up->bind_param("ii", $student_id, $step_id);
    $up->execute(); $up->close();
}

$page_title = "Verification Submitted";
include '../includes/header.php';
?>

<section class="card" style="text-align:center; max-width:480px; margin:0 auto;">
  <div style="width:70px;height:70px;background:#fef3c7;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:32px;">🧾</div>
  <h2 style="color:#92400e; margin-bottom:8px;">Submitted for Verification</h2>
  <p style="color:var(--muted); margin-bottom:4px;">
    Transaction ID: <strong style="font-family:monospace;"><?= htmlspecialchars($tran_id) ?></strong>
  </p>
  <p style="color:var(--muted); margin-bottom:4px;">
    Method: <strong><?= htmlspecialchars(ucfirst($method)) ?></strong>
  </p>
  <p style="color:var(--muted); margin-bottom:20px; font-size:13px;">
    Your payment is under review. You will see the status update on your dashboard once verified by the admin.
  </p>
  <a href="../dashboard.php" class="btn" style="text-decoration:none; background:#5c6bc0;">Go to Dashboard</a>
</section>

<?php include '../includes/footer.php'; ?>
