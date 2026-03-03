<?php
require_once '../includes/db.php';

if (!isset($_SESSION['teacher_id'])) {
  header("Location: login.php"); exit;
}

$teacher_id   = (int)$_SESSION['teacher_id'];
$teacher_name = $_SESSION['teacher_name'] ?? '';
$teacher_dept = $_SESSION['teacher_dept'] ?? '';

$success = '';
$error   = '';
$vivaStep = 2;

// ── Handle Viva POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'viva') {
  $student_id = (int)$_POST['student_id'];
  $new_status = $_POST['status'] ?? '';

  if (!in_array($new_status, ['approved', 'rejected'], true)) {
    $error = "Invalid status.";
  } else {
    // Get step_id for viva (step_order=2)
    $st = $conn->prepare("SELECT id FROM admission_steps WHERE step_order=? AND is_active=1 LIMIT 1");
    $st->bind_param("i", $vivaStep);
    $st->execute();
    $stepRow = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$stepRow) {
      $error = "Viva step not found.";
    } else {
      $step_id = (int)$stepRow['id'];

      // Confirm student belongs to this teacher's dept
      $chk = $conn->prepare("SELECT id FROM students WHERE id=? AND dept=? LIMIT 1");
      $chk->bind_param("is", $student_id, $teacher_dept);
      $chk->execute();
      $ok = $chk->get_result()->num_rows > 0;
      $chk->close();

      if (!$ok) {
        $error = "You can only update students from your department.";
      } else {
        // Map approved->done, rejected->pending for ENUM
        $db_status = $new_status === 'approved' ? 'done' : 'pending';

        $up = $conn->prepare("
          INSERT INTO student_step_status (student_id, step_id, status, updated_by)
          VALUES (?, ?, ?, ?)
          ON DUPLICATE KEY UPDATE status=VALUES(status), updated_by=VALUES(updated_by)
        ");
        $up->bind_param("iisi", $student_id, $step_id, $db_status, $teacher_id);
        $up->execute();
        $up->close();
        $success = "Viva status updated successfully.";
      }
    }
  }
}

// ── Handle Payment Verification POST ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify_payment') {
  $pay_student_id = (int)$_POST['pay_student_id'];
  $tran_id        = trim($_POST['tran_id'] ?? '');
  $pay_action     = $_POST['pay_action'] ?? '';

  $chk2 = $conn->prepare("SELECT id FROM students WHERE id=? AND dept=? LIMIT 1");
  $chk2->bind_param("is", $pay_student_id, $teacher_dept);
  $chk2->execute();
  $ok2 = $chk2->get_result()->num_rows > 0;
  $chk2->close();

  if (!$ok2) {
    $error = "That student is not in your department.";
  } elseif (!in_array($pay_action, ['approve', 'reject'], true)) {
    $error = "Invalid action.";
  } else {
    $new_pay_status  = $pay_action === 'approve' ? 'verified' : 'failed';
    $new_step_status = $pay_action === 'approve' ? 'done'     : 'waiting';

    $upPay = $conn->prepare("UPDATE payments SET status=? WHERE tran_id=? AND student_id=?");
    $upPay->bind_param("ssi", $new_pay_status, $tran_id, $pay_student_id);
    $upPay->execute(); $upPay->close();

    $stepQ = $conn->prepare("SELECT id FROM admission_steps WHERE step_order=3 AND is_active=1 LIMIT 1");
    $stepQ->execute();
    $stepR = $stepQ->get_result()->fetch_assoc();
    $stepQ->close();

    if ($stepR) {
      $sid3 = (int)$stepR['id'];
      $ins3 = $conn->prepare("INSERT IGNORE INTO student_step_status (student_id, step_id, status) VALUES (?,?,'waiting')");
      $ins3->bind_param("ii", $pay_student_id, $sid3);
      $ins3->execute(); $ins3->close();

      $upStep = $conn->prepare("UPDATE student_step_status SET status=? WHERE student_id=? AND step_id=?");
      $upStep->bind_param("sii", $new_step_status, $pay_student_id, $sid3);
      $upStep->execute(); $upStep->close();
    }

    $success = $pay_action === 'approve'
      ? "Payment approved — TXN: " . htmlspecialchars($tran_id)
      : "Payment rejected — TXN: " . htmlspecialchars($tran_id);
  }
}

// ── Load pending payments for this dept ───────────────────────
$payRes = $conn->prepare("
  SELECT p.*, s.name, s.gst_roll, s.merit
  FROM payments p
  JOIN students s ON s.id = p.student_id
  WHERE p.status = 'pending' AND s.dept = ?
  ORDER BY p.created_at ASC
");
$payRes->bind_param("s", $teacher_dept);
$payRes->execute();
$pending_payments = $payRes->get_result()->fetch_all(MYSQLI_ASSOC);
$payRes->close();

// ── Load students for viva (map done=approved, pending=rejected) ──
$list = $conn->prepare("
  SELECT s.id, s.name, s.gst_roll, s.merit,
         COALESCE(sss.status,'waiting') AS raw_status
  FROM students s
  LEFT JOIN admission_steps st ON st.step_order=? AND st.is_active=1
  LEFT JOIN student_step_status sss ON sss.student_id=s.id AND sss.step_id=st.id
  WHERE s.dept = ?
  ORDER BY s.merit ASC
");
$list->bind_param("is", $vivaStep, $teacher_dept);
$list->execute();
$rows_raw = $list->get_result()->fetch_all(MYSQLI_ASSOC);
$list->close();

// Map DB status back to display labels
$rows = array_map(function($r) {
  $r['viva_status'] = match($r['raw_status']) {
    'done'    => 'approved',
    'pending' => 'rejected',
    default   => 'waiting',
  };
  return $r;
}, $rows_raw);

$page_title = "Teacher Panel";
include "../includes/header.php";
?>

<style>
  .panel-header { display:flex; align-items:center; gap:12px; margin-bottom:20px; padding-bottom:16px; border-bottom:2px solid var(--line); }
  .panel-avatar { width:46px; height:46px; background:linear-gradient(135deg,#3f51b5,#5c6bc0); border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-size:20px; }
  .section-heading { font-size:15px; font-weight:700; margin:0 0 12px; display:flex; align-items:center; gap:8px; }
  .badge-count { font-size:12px; padding:2px 9px; border-radius:20px; font-weight:700; background:#fef3c7; color:#92400e; }
  .badge-count.blue  { background:#eff6ff; color:#1d4ed8; }
  .badge-count.green { background:#dcfce7; color:#15803d; }
  .pay-card { border:1px solid #fde68a; border-radius:10px; padding:14px 16px; margin-bottom:10px; background:#fffbeb; }
  .pay-name { font-weight:700; font-size:15px; }
  .pay-meta { font-size:12px; color:#6b7280; margin-top:3px; }
  .pay-detail { font-size:13px; margin-top:6px; }
  .pay-detail span { color:#6b7280; }
  .pay-detail strong { font-family:monospace; font-size:14px; color:#1f2937; }
  .pay-actions { display:flex; gap:8px; margin-top:12px; }
  .btn-approve { background:#16a34a; color:#fff; border:none; padding:7px 18px; border-radius:7px; font-weight:700; cursor:pointer; font-size:13px; }
  .btn-approve:hover { opacity:.88; }
  .btn-reject  { background:#dc2626; color:#fff; border:none; padding:7px 18px; border-radius:7px; font-weight:700; cursor:pointer; font-size:13px; }
  .btn-reject:hover { opacity:.88; }
  .viva-row { display:flex; align-items:center; gap:12px; padding:11px 0; border-bottom:1px solid var(--line); }
  .viva-info { flex:1; }
  .viva-name { font-weight:700; }
  .viva-meta { font-size:12px; color:var(--muted); margin-top:2px; }
  .viva-row select { padding:6px 10px; border:1px solid var(--line); border-radius:6px; font-size:13px; background:#fff; cursor:pointer; }
  .btn-sm { padding:6px 14px; font-size:13px; background:var(--btn); color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:600; }
  .badge { font-size:11px; font-weight:600; padding:3px 9px; border-radius:20px; white-space:nowrap; }
  .badge.approved { background:#dcfce7; color:#15803d; }
  .badge.rejected { background:#fee2e2; color:#b91c1c; }
  .badge.waiting  { background:#f3f4f6; color:#6b7280; }
  .divider { border:none; border-top:2px solid var(--line); margin:24px 0; }
  .alert.success { background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; border-radius:8px; padding:10px 14px; margin-bottom:14px; }
  .alert.error   { background:#fee2e2; color:#b91c1c; border:1px solid #fecaca; border-radius:8px; padding:10px 14px; margin-bottom:14px; }
  .no-pending { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:10px 14px; font-size:13px; color:#15803d; }
</style>

<section class="card">

  <div class="panel-header">
    <div class="panel-avatar"><i class="fa-solid fa-chalkboard-teacher"></i></div>
    <div>
      <div style="font-weight:700; font-size:18px;">Teacher Panel</div>
      <div style="font-size:13px; color:var(--muted);">
        <?= htmlspecialchars($teacher_name) ?> &nbsp;&middot;&nbsp; <strong><?= htmlspecialchars($teacher_dept) ?></strong>
      </div>
    </div>
    <a href="logout.php" class="btn" style="margin-left:auto; text-decoration:none; font-size:13px;">
      <i class="fa-solid fa-right-from-bracket"></i> Logout
    </a>
  </div>

  <?php if ($success): ?>
    <div class="alert success"><i class="fa-solid fa-circle-check"></i> <?= $success ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert error"><i class="fa-solid fa-circle-xmark"></i> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Payment Verification -->
  <div class="section-heading">
    <i class="fa-solid fa-money-bill-wave" style="color:#d97706;"></i>
    Payment Verification
    <?php if (!empty($pending_payments)): ?>
      <span class="badge-count"><?= count($pending_payments) ?> pending</span>
    <?php else: ?>
      <span class="badge-count green">All clear</span>
    <?php endif; ?>
  </div>

  <?php if (empty($pending_payments)): ?>
    <div class="no-pending"><i class="fa-solid fa-circle-check"></i> No pending payment verifications.</div>
  <?php else: ?>
    <?php foreach ($pending_payments as $pay): ?>
      <div class="pay-card">
        <div class="pay-name"><?= htmlspecialchars($pay['name'] ?: $pay['gst_roll']) ?></div>
        <div class="pay-meta">Roll: <?= htmlspecialchars($pay['gst_roll']) ?> &nbsp;&middot;&nbsp; Merit #<?= (int)$pay['merit'] ?></div>
        <div class="pay-detail"><span>Transaction ID: </span><strong><?= htmlspecialchars($pay['tran_id']) ?></strong></div>
        <div class="pay-detail">
          <span>Method: </span><strong style="font-family:sans-serif;"><?= htmlspecialchars(ucfirst($pay['method'] ?? 'N/A')) ?></strong>
          &nbsp;&nbsp;<span>Amount: </span><strong style="font-family:sans-serif;">&#2547;<?= number_format((float)$pay['amount'], 2) ?></strong>
          &nbsp;&nbsp;<span>Submitted: </span><strong style="font-family:sans-serif;"><?= date('d M Y, h:i A', strtotime($pay['created_at'])) ?></strong>
        </div>
        <div class="pay-actions">
          <form method="POST" style="display:inline;">
            <input type="hidden" name="action"         value="verify_payment">
            <input type="hidden" name="pay_student_id" value="<?= (int)$pay['student_id'] ?>">
            <input type="hidden" name="tran_id"        value="<?= htmlspecialchars($pay['tran_id']) ?>">
            <input type="hidden" name="pay_action"     value="approve">
            <button type="submit" class="btn-approve"><i class="fa-solid fa-check"></i> Approve</button>
          </form>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="action"         value="verify_payment">
            <input type="hidden" name="pay_student_id" value="<?= (int)$pay['student_id'] ?>">
            <input type="hidden" name="tran_id"        value="<?= htmlspecialchars($pay['tran_id']) ?>">
            <input type="hidden" name="pay_action"     value="reject">
            <button type="submit" class="btn-reject"><i class="fa-solid fa-xmark"></i> Reject</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <hr class="divider">

  <!-- Department Viva -->
  <div class="section-heading">
    <i class="fa-solid fa-clipboard-check" style="color:#3f51b5;"></i>
    Department Viva
    <span class="badge-count blue"><?= count($rows) ?> students</span>
  </div>

  <?php if (empty($rows)): ?>
    <div style="color:var(--muted); font-size:14px; padding:8px 0;">No students in your department yet.</div>
  <?php endif; ?>

  <?php foreach ($rows as $r): ?>
    <form method="POST">
      <input type="hidden" name="action"     value="viva">
      <input type="hidden" name="student_id" value="<?= (int)$r['id'] ?>">
      <div class="viva-row">
        <div class="viva-info">
          <div class="viva-name"><?= htmlspecialchars($r['name'] ?: 'Student') ?></div>
          <div class="viva-meta"><?= htmlspecialchars($r['gst_roll']) ?> &nbsp;&middot;&nbsp; Merit #<?= (int)$r['merit'] ?></div>
        </div>
        <span class="badge <?= $r['viva_status'] === 'approved' ? 'approved' : ($r['viva_status'] === 'rejected' ? 'rejected' : 'waiting') ?>">
          <?= ucfirst($r['viva_status']) ?>
        </span>
        <select name="status">
          <option value="approved" <?= $r['viva_status']==='approved'?'selected':'' ?>>Approved</option>
          <option value="rejected" <?= $r['viva_status']==='rejected'?'selected':'' ?>>Rejected</option>
        </select>
        <button class="btn-sm" type="submit">Update</button>
      </div>
    </form>
  <?php endforeach; ?>

</section>

<?php include "../includes/footer.php"; ?>
