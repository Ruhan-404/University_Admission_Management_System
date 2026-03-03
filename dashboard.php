<?php
require_once 'includes/db.php';

// Must be logged in
if (!isset($_SESSION['student_id'])) {
    header('Location: login.php'); exit;
}

$student_id = (int)($_SESSION['student_id'] ?? 0);

$student = [
    'name'     => $_SESSION['student_name']  ?? '',
    'gst_roll' => $_SESSION['student_gst']   ?? '',
    'dept'     => $_SESSION['student_dept']  ?? '',
    'merit'    => $_SESSION['student_merit'] ?? '',
    'email'    => $_SESSION['student_email'] ?? '',
    'phone'    => $_SESSION['student_phone'] ?? '',
];

// Display priority: name -> email -> phone -> gst_roll
$display = $student['name'] ?: ($student['email'] ?: ($student['phone'] ?: $student['gst_roll']));

// Load reg_number from DB (not stored in session)
$regStmt = $conn->prepare("SELECT reg_number FROM students WHERE id = ? LIMIT 1");
$regStmt->bind_param("i", $student_id);
$regStmt->execute();
$regRow = $regStmt->get_result()->fetch_assoc();
$regStmt->close();
$reg_number = $regRow['reg_number'] ?? null;

// Check if Register Office (step 5) approved this student
$regCheckStmt = $conn->prepare("
    SELECT sss.status FROM student_step_status sss
    JOIN admission_steps st ON st.id = sss.step_id
    WHERE sss.student_id = ? AND st.step_order = 5 LIMIT 1
");
$regCheckStmt->bind_param("i", $student_id);
$regCheckStmt->execute();
$regCheckRow = $regCheckStmt->get_result()->fetch_assoc();
$regCheckStmt->close();
$register_approved = ($regCheckRow['status'] ?? '') === 'done';

// Ensure student has rows for all steps (older accounts safety)
$ensure = $conn->prepare(
    "INSERT IGNORE INTO student_step_status (student_id, step_id, status)
     SELECT ?, st.id, 'waiting'
     FROM admission_steps st
     WHERE st.is_active = 1"
);
$ensure->bind_param('i', $student_id);
$ensure->execute();
$ensure->close();

// Load progress steps from DB (with step_order + step_id)
$progress_steps = [];
$stmt = $conn->prepare(
    "SELECT st.id AS step_id, st.step_order, st.title, COALESCE(ss.status,'waiting') AS status
     FROM admission_steps st
     LEFT JOIN student_step_status ss
       ON ss.step_id = st.id AND ss.student_id = ?
     WHERE st.is_active = 1
     ORDER BY st.step_order ASC"
);
$stmt->bind_param('i', $student_id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $progress_steps[] = [
        'step_id'    => (int)$r['step_id'],
        'step_order' => (int)$r['step_order'],
        'title'      => $r['title'],
        'status'     => strtolower(trim($r['status'] ?? 'waiting')),
    ];
}
$stmt->close();

function status_text(string $status): string {
    return match ($status) {
        'done'     => 'Done',
        'pending'  => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        default    => 'Waiting',
    };
}

$page_title = 'Dashboard';
include 'includes/header.php';
?>

<section class="card" role="region" aria-label="Dashboard">
  <h2>Dashboard</h2>

  <div class="alert" style="text-align:center;">
    Welcome, <b><?= htmlspecialchars($display) ?></b><br>
    Your department is <b><?= htmlspecialchars($student['dept']) ?></b><br>
    Your merit position is <b>#<?= (int)$student['merit'] ?></b>.
  </div>

  <div style="border:1px solid var(--line); border-radius:4px; padding:14px 16px; margin:14px 0;">
    <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid var(--line);">
      <span style="color:var(--muted); font-size:14px;">GST Roll</span>
      <span style="font-weight:700;"><?= htmlspecialchars($student['gst_roll']) ?></span>
    </div>

    <?php if ($student['name']): ?>
      <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid var(--line);">
        <span style="color:var(--muted); font-size:14px;">Name</span>
        <span style="font-weight:700;"><?= htmlspecialchars($student['name']) ?></span>
      </div>
    <?php endif; ?>

    <?php if ($student['email']): ?>
      <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid var(--line);">
        <span style="color:var(--muted); font-size:14px;">Email</span>
        <span style="font-weight:700;"><?= htmlspecialchars($student['email']) ?></span>
      </div>
    <?php endif; ?>

    <?php if ($student['phone']): ?>
      <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid var(--line);">
        <span style="color:var(--muted); font-size:14px;">Phone</span>
        <span style="font-weight:700;"><?= htmlspecialchars($student['phone']) ?></span>
      </div>
    <?php endif; ?>

    <?php if ($reg_number): ?>
      <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0;">
        <span style="color:var(--muted); font-size:14px;">Registration No.</span>
        <span style="font-weight:700; font-family:monospace; font-size:15px; background:#f0fdf4; border:1px solid #86efac; color:#15803d; padding:4px 12px; border-radius:6px; letter-spacing:.5px;">
          <i class="fa-solid fa-id-card" style="margin-right:5px;"></i><?= htmlspecialchars($reg_number) ?>
        </span>
      </div>
    <?php endif; ?>
  </div>

  <div class="label" style="margin-top:10px;">Admission Progress</div>

  <div style="margin-top:8px; display:flex; flex-direction:column; gap:8px;">
    <?php foreach ($progress_steps as $step): ?>
      <?php
        $order  = (int)$step['step_order'];
        $label  = $step['title'];
        $status = $step['status'];

        $circleBg = '#f3f4f6';
        if ($status === 'approved') $circleBg = '#e8f7ee';
        if ($status === 'rejected') $circleBg = '#fdecec';
        if ($status === 'done')     $circleBg = '#e8f7ee';

        // Steps 4,5,7 use done=Approved / pending=Rejected mapping
        if (in_array($order, [4, 5, 7])) {
            $statusText = match($status) { 'done' => 'Approved', 'pending' => 'Rejected', default => 'Waiting' };
            if ($status === 'done')    $circleBg = '#e8f7ee';
            if ($status === 'pending') $circleBg = '#fdecec';
        } else {
            $statusText = status_text($status);
        }
      ?>

      <div style="display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid var(--line);">

        <!-- LEFT: serial circle -->
        <span style="width:30px; height:30px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:700; background:<?= $circleBg ?>;">
          <?= ($status === 'done' || $status === 'approved') ? '✓' : $order ?>
        </span>

        <!-- MIDDLE: step title -->
        <span style="font-weight:700; font-family: Georgia, 'Times New Roman', serif;">
          <?= htmlspecialchars($label) ?>
        </span>

        <!-- RIGHT: action area -->
        <span style="margin-left:auto; color:var(--muted); font-size:14px;">
          <?php if ($order === 1): ?>
            <?php if ($status === 'done'): ?>
              <a href="form_submission.php"
                 style="background:#5c6bc0; color:#fff; padding:5px 12px; border-radius:4px; text-decoration:none;">
                Edit Form
              </a>
            <?php else: ?>
              <a href="form_submission.php"
                 style="background:#5c6bc0; color:#fff; padding:5px 12px; border-radius:4px; text-decoration:none;">
                Submit
              </a>
            <?php endif; ?>
          <?php elseif ($order === 3): ?>
            <?php if ($status === 'done'): ?>
              <span style="color:#16a34a; font-weight:600;"><i class="fa-solid fa-circle-check"></i> Paid</span>
            <?php elseif ($status === 'pending'): ?>
              <span style="color:#d97706; font-weight:600;"><i class="fa-solid fa-clock"></i> Verifying</span>
            <?php else: ?>
              <a href="payment/index.php"
                 style="background:#059669; color:#fff; padding:5px 14px; border-radius:4px; text-decoration:none; font-weight:600; font-size:13px;">
                Proceed
              </a>
            <?php endif; ?>
          <?php elseif ($order === 3): ?>
            <?php if ($status === 'done'): ?>
              <span style="color:#16a34a; font-weight:600;"><i class="fa-solid fa-circle-check"></i> Paid</span>
            <?php elseif ($status === 'pending'): ?>
              <span style="color:#d97706; font-weight:600;"><i class="fa-solid fa-clock"></i> Verifying</span>
            <?php else: ?>
              <a href="payment/index.php"
                 style="background:#0d9488; color:#fff; padding:5px 14px; border-radius:4px; text-decoration:none; font-weight:600; font-size:13px;">
                Proceed
              </a>
            <?php endif; ?>
          <?php elseif ($order === 6): ?>
            <?php if ($register_approved && $reg_number): ?>
              <button onclick="document.getElementById('id-modal').style.display='flex'"
                style="background:#0d9488; color:#fff; padding:5px 14px; border-radius:4px; border:none; cursor:pointer; font-weight:600; font-size:13px;">
                <i class="fa-solid fa-id-card"></i> Get ID
              </button>
            <?php else: ?>
              Waiting
            <?php endif; ?>
          <?php else: ?>
            <?= htmlspecialchars($statusText) ?>
          <?php endif; ?>
        </span>

      </div>
    <?php endforeach; ?>
  </div>

  <div class="submit-row" style="margin-top:18px;">
    <a class="btn" href="logout.php"
       style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center; background:#5c6bc0;">
      Logout
    </a>
  </div>
</section>

<!-- Registration ID Modal -->
<?php if ($reg_number): ?>
<div id="id-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:9999; align-items:center; justify-content:center;">
  <div style="background:#fff; border-radius:16px; padding:36px 32px; max-width:400px; width:90%; text-align:center; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.25);">
    <button onclick="document.getElementById('id-modal').style.display='none'"
      style="position:absolute; top:12px; right:16px; background:none; border:none; font-size:20px; cursor:pointer; color:#6b7280;">&#x2715;</button>

    <div style="width:64px; height:64px; background:linear-gradient(135deg,#0d9488,#059669); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 18px;">
      <i class="fa-solid fa-id-card" style="color:#fff; font-size:28px;"></i>
    </div>

    <h3 style="margin:0 0 6px; font-size:20px; color:#1f2937;">Your Registration ID</h3>
    <p style="color:#6b7280; font-size:13px; margin:0 0 20px;">University of Barishal — Official Registration</p>

    <div style="background:#f0fdf4; border:2px solid #86efac; border-radius:10px; padding:16px 20px; margin-bottom:20px;">
      <div style="font-family:monospace; font-size:22px; font-weight:800; color:#15803d; letter-spacing:1.5px;">
        <?= htmlspecialchars($reg_number) ?>
      </div>
    </div>

    <div style="font-size:12px; color:#9ca3af; margin-bottom:20px;">
      Issued to: <strong><?= htmlspecialchars($student['name']) ?></strong><br>
      Department: <strong><?= htmlspecialchars($student['dept']) ?></strong>
    </div>

    <button onclick="document.getElementById('id-modal').style.display='none'"
      style="background:#5c6bc0; color:#fff; border:none; padding:10px 28px; border-radius:8px; cursor:pointer; font-size:14px; font-weight:600;">
      Close
    </button>
  </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>