<?php
require_once 'includes/db.php';

// Admin auth
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$msg = '';
$error = '';

// Handle status update (single step)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_step') {
    $student_id = (int)($_POST['student_id'] ?? 0);
    $step_id    = (int)($_POST['step_id'] ?? 0);
    $status     = trim($_POST['status'] ?? 'waiting');

    if (!in_array($status, ['waiting', 'pending', 'done'], true)) {
        $status = 'waiting';
    }

    if ($student_id <= 0 || $step_id <= 0) {
        $error = 'Invalid input.';
    } else {
        // Ensure rows exist for this student (safe for older accounts)
        $ensure = $conn->prepare(
            "INSERT IGNORE INTO student_step_status (student_id, step_id, status)
             SELECT ?, id, 'waiting'
             FROM admission_steps
             WHERE is_active = 1"
        );
        $ensure->bind_param('i', $student_id);
        $ensure->execute();
        $ensure->close();

        // Upsert the status
        $upd = $conn->prepare(
            "INSERT INTO student_step_status (student_id, step_id, status, updated_by)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               status = VALUES(status),
               updated_by = VALUES(updated_by),
               updated_at = CURRENT_TIMESTAMP"
        );
        $admin_id = (int)$_SESSION['admin_id'];
        $upd->bind_param('iisi', $student_id, $step_id, $status, $admin_id);
        $upd->execute();
        $upd->close();

        $msg = 'Updated!';
    }
}

// Handle payment verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify_payment') {
    $pay_student_id = (int)$_POST['pay_student_id'];
    $tran_id        = trim($_POST['tran_id'] ?? '');
    $pay_action     = $_POST['pay_action'] ?? ''; // approve or reject

    if ($pay_student_id > 0 && $tran_id !== '' && in_array($pay_action, ['approve','reject'])) {
        $new_pay_status  = $pay_action === 'approve' ? 'verified' : 'failed';
        $new_step_status = $pay_action === 'approve' ? 'done'     : 'waiting';

        // Update payments table
        $upPay = $conn->prepare("UPDATE payments SET status=? WHERE tran_id=? AND student_id=?");
        $upPay->bind_param("ssi", $new_pay_status, $tran_id, $pay_student_id);
        $upPay->execute(); $upPay->close();

        // Update step 3 status
        $stepQ = $conn->prepare("SELECT id FROM admission_steps WHERE step_order=3 AND is_active=1 LIMIT 1");
        $stepQ->execute();
        $stepR = $stepQ->get_result()->fetch_assoc();
        $stepQ->close();

        if ($stepR) {
            $sid3 = (int)$stepR['id'];
            $upStep = $conn->prepare("UPDATE student_step_status SET status=? WHERE student_id=? AND step_id=?");
            $upStep->bind_param("sii", $new_step_status, $pay_student_id, $sid3);
            $upStep->execute(); $upStep->close();
        }

        $msg = $pay_action === 'approve'
            ? "Payment approved for Transaction ID: $tran_id"
            : "Payment rejected for Transaction ID: $tran_id";
    }
}

// Load students + steps
$students = $conn->query("SELECT id, name, gst_roll, dept, merit FROM students ORDER BY id DESC")
                ->fetch_all(MYSQLI_ASSOC);
$steps = $conn->query("SELECT id, step_order, title FROM admission_steps WHERE is_active = 1 ORDER BY step_order ASC")
             ->fetch_all(MYSQLI_ASSOC);

// Load pending payments for verification
$conn->query("
    CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        tran_id VARCHAR(60) NOT NULL UNIQUE,
        amount DECIMAL(10,2) NOT NULL DEFAULT 5000.00,
        method VARCHAR(30) DEFAULT NULL,
        status ENUM('pending','verified','failed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");
$pending_payments = $conn->query("
    SELECT p.*, s.name, s.gst_roll, s.dept
    FROM payments p
    JOIN students s ON s.id = p.student_id
    WHERE p.status = 'pending'
    ORDER BY p.created_at ASC
")->fetch_all(MYSQLI_ASSOC);

$selected_student_id = (int)($_GET['student_id'] ?? ($_POST['student_id'] ?? 0));
if ($selected_student_id <= 0 && !empty($students)) {
    $selected_student_id = (int)$students[0]['id'];
}

// Get current statuses for selected student
$current = [];
if ($selected_student_id > 0) {
    // Ensure rows exist
    $ensure = $conn->prepare(
        "INSERT IGNORE INTO student_step_status (student_id, step_id, status)
         SELECT ?, id, 'waiting'
         FROM admission_steps
         WHERE is_active = 1"
    );
    $ensure->bind_param('i', $selected_student_id);
    $ensure->execute();
    $ensure->close();

    $st = $conn->prepare(
        "SELECT st.id AS step_id, st.step_order, st.title, ss.status
         FROM admission_steps st
         LEFT JOIN student_step_status ss
           ON ss.step_id = st.id AND ss.student_id = ?
         WHERE st.is_active = 1
         ORDER BY st.step_order ASC"
    );
    $st->bind_param('i', $selected_student_id);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $current[] = $row;
    }
    $st->close();
}

$page_title = 'Admin Panel';
include 'includes/header.php';
?>

<section class="card" role="region" aria-label="Admin Panel">
  <h2>Admin Panel</h2>

  <div class="alert" style="margin-bottom:14px;">
    Logged in as <b><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></b>
  </div>

  <?php if ($msg): ?>
    <div class="alert success" role="alert"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert error" role="alert"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- ── Payment Verifications ─────────────────── -->
  <?php if (!empty($pending_payments)): ?>
  <div style="margin-bottom:24px;">
    <h3 style="margin:0 0 12px; font-size:16px; color:#92400e; display:flex; align-items:center; gap:8px;">
      <i class="fa-solid fa-clock" style="color:#d97706;"></i>
      Pending Payment Verifications
      <span style="background:#fef3c7; color:#92400e; font-size:12px; padding:2px 8px; border-radius:20px; font-weight:700;"><?= count($pending_payments) ?></span>
    </h3>
    <?php foreach ($pending_payments as $pay): ?>
      <div style="border:1px solid #fde68a; border-radius:10px; padding:14px 16px; margin-bottom:10px; background:#fffbeb;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px;">
          <div>
            <div style="font-weight:700; font-size:15px;"><?= htmlspecialchars($pay['name'] ?: $pay['gst_roll']) ?></div>
            <div style="font-size:12px; color:#6b7280; margin-top:2px;">
              <?= htmlspecialchars($pay['gst_roll']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($pay['dept']) ?>
            </div>
            <div style="margin-top:8px; font-size:13px;">
              <span style="color:#6b7280;">Transaction ID:</span>
              <strong style="font-family:monospace; color:#1f2937;"><?= htmlspecialchars($pay['tran_id']) ?></strong>
            </div>
            <div style="font-size:13px; margin-top:3px;">
              <span style="color:#6b7280;">Method:</span>
              <strong><?= htmlspecialchars(ucfirst($pay['method'] ?? 'N/A')) ?></strong>
              &nbsp;·&nbsp;
              <span style="color:#6b7280;">Amount:</span>
              <strong>৳<?= number_format($pay['amount'], 2) ?></strong>
              &nbsp;·&nbsp;
              <span style="color:#6b7280;">Submitted:</span>
              <strong><?= date('d M Y, h:i A', strtotime($pay['created_at'])) ?></strong>
            </div>
          </div>
          <div style="display:flex; gap:8px; align-items:center;">
            <form method="POST" style="display:inline;">
              <input type="hidden" name="action" value="verify_payment">
              <input type="hidden" name="pay_student_id" value="<?= (int)$pay['student_id'] ?>">
              <input type="hidden" name="tran_id" value="<?= htmlspecialchars($pay['tran_id']) ?>">
              <input type="hidden" name="pay_action" value="approve">
              <button type="submit" style="background:#16a34a; color:#fff; border:none; padding:8px 16px; border-radius:8px; font-weight:700; cursor:pointer; font-size:13px;">
                <i class="fa-solid fa-check"></i> Approve
              </button>
            </form>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="action" value="verify_payment">
              <input type="hidden" name="pay_student_id" value="<?= (int)$pay['student_id'] ?>">
              <input type="hidden" name="tran_id" value="<?= htmlspecialchars($pay['tran_id']) ?>">
              <input type="hidden" name="pay_action" value="reject">
              <button type="submit" style="background:#dc2626; color:#fff; border:none; padding:8px 16px; border-radius:8px; font-weight:700; cursor:pointer; font-size:13px;">
                <i class="fa-solid fa-xmark"></i> Reject
              </button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php elseif (isset($pending_payments)): ?>
  <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:10px 14px; margin-bottom:20px; font-size:13px; color:#15803d;">
    <i class="fa-solid fa-circle-check"></i> No pending payment verifications.
  </div>
  <?php endif; ?>

  <hr style="border:none; border-top:1px solid var(--line); margin-bottom:20px;">

  <!-- ── Student Step Control ───────────────────── -->
  <h3 style="margin:0 0 14px; font-size:16px;">Student Step Control</h3>

  <form method="GET" style="margin-bottom:14px;">
    <div class="field">
      <div class="label">Select Student</div>
      <div class="control">
        <i class="fa-solid fa-user"></i>
        <div class="select-wrap">
          <select name="student_id" onchange="this.form.submit()" required>
            <?php if (empty($students)): ?>
              <option value="" selected disabled>No students found</option>
            <?php else: ?>
              <?php foreach ($students as $s): ?>
                <?php $sid = (int)$s['id']; ?>
                <option value="<?= $sid ?>" <?= $sid === $selected_student_id ? 'selected' : '' ?>>
                  <?= htmlspecialchars(($s['name'] ?: $s['gst_roll']) . ' — ' . $s['gst_roll']) ?>
                </option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
        </div>
      </div>
    </div>
  </form>

  <?php if ($selected_student_id > 0): ?>
    <div class="label" style="margin-top:6px;">Admission Progress</div>
    <div style="margin-top:8px; display:flex; flex-direction:column; gap:8px;">
      <?php foreach ($current as $row): ?>
        <?php
          $st_id = (int)$row['step_id'];
          $status = $row['status'] ?? 'waiting';
          $badge = ($status === 'done') ? 'Done' : (($status === 'pending') ? 'Pending' : 'Waiting');
        ?>
        <div style="display:flex; align-items:center; gap:10px; padding:10px 0; border-bottom:1px solid var(--line);">
          <span style="width:28px; height:28px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:700; background:#f3f4f6;">
            <?= $status === 'done' ? '✓' : (int)$row['step_order'] ?>
          </span>

          <span style="font-weight:700; font-family: Georgia, 'Times New Roman', serif;">
            <?= htmlspecialchars($row['title']) ?>
          </span>

          <span style="margin-left:auto; color:var(--muted); font-size:14px; min-width:70px; text-align:right;">
            <?= $badge ?>
          </span>
        </div>

        <form method="POST" style="display:flex; gap:10px; align-items:center; padding:10px 0;">
          <input type="hidden" name="action" value="update_step">
          <input type="hidden" name="student_id" value="<?= $selected_student_id ?>">
          <input type="hidden" name="step_id" value="<?= $st_id ?>">

          <div class="control" style="flex:1; margin:0;">
            <i class="fa-solid fa-flag"></i>
            <div class="select-wrap">
              <select name="status" required>
                <option value="waiting" <?= $status === 'waiting' ? 'selected' : '' ?>>Waiting</option>
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="done" <?= $status === 'done' ? 'selected' : '' ?>>Done</option>
              </select>
            </div>
          </div>

          <button class="btn" type="submit">Save</button>
        </form>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</section>

<?php include 'includes/footer.php'; ?>
