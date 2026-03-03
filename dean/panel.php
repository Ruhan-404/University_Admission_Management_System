<?php
require_once '../includes/db.php';

if (!isset($_SESSION['dean_id'])) {
    header("Location: login.php");
    exit;
}

$dean_id   = (int)$_SESSION['dean_id'];
$dean_name = $_SESSION['dean_name'] ?? 'Dean';

$success = '';
$error   = '';

$stepOrder = 4;

// DB ENUM only has: waiting, pending, done
// We map: approved -> done  |  rejected -> pending
function dean_to_db(string $s): string {
    return match($s) { 'approved' => 'done', 'rejected' => 'pending', default => 'waiting' };
}
function db_to_dean(string $s): string {
    return match($s) { 'done' => 'approved', 'pending' => 'rejected', default => 'waiting' };
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id'], $_POST['status'])) {
    $student_id  = (int)$_POST['student_id'];
    $status_disp = $_POST['status'];

    if (!in_array($status_disp, ['approved', 'rejected'], true)) {
        $error = "Invalid status value.";
    } else {
        $db_status = dean_to_db($status_disp);

        $st = $conn->prepare("SELECT id FROM admission_steps WHERE step_order = ? AND is_active = 1 LIMIT 1");
        $st->bind_param("i", $stepOrder);
        $st->execute();
        $stepRow = $st->get_result()->fetch_assoc();
        $st->close();

        if (!$stepRow) {
            $error = "Dean's Office step not found.";
        } else {
            $step_id = (int)$stepRow['id'];
            // Step 1: ensure row exists
            $ins = $conn->prepare("
                INSERT IGNORE INTO student_step_status (student_id, step_id, status, updated_by)
                VALUES (?, ?, 'waiting', ?)
            ");
            $ins->bind_param("iii", $student_id, $step_id, $dean_id);
            $ins->execute();
            $ins->close();

            // Step 2: always update
            $up = $conn->prepare("
                UPDATE student_step_status
                SET status = ?, updated_by = ?
                WHERE student_id = ? AND step_id = ?
            ");
            $up->bind_param("siii", $db_status, $dean_id, $student_id, $step_id);
            $up->execute();
            $up->close();
            $success = "Status set to <strong>" . htmlspecialchars($status_disp) . "</strong> successfully.";
        }
    }
}

$list = $conn->query("
    SELECT s.id, s.name, s.gst_roll, s.merit, s.dept,
           COALESCE(dean_s.status, 'waiting') AS dean_db_status,
           COALESCE(viva_s.status, 'waiting')  AS viva_db_status
    FROM students s
    LEFT JOIN admission_steps dean_st ON dean_st.step_order = 4 AND dean_st.is_active = 1
    LEFT JOIN student_step_status dean_s ON dean_s.student_id = s.id AND dean_s.step_id = dean_st.id
    LEFT JOIN admission_steps viva_st ON viva_st.step_order = 2 AND viva_st.is_active = 1
    LEFT JOIN student_step_status viva_s ON viva_s.student_id = s.id AND viva_s.step_id = viva_st.id
    ORDER BY s.dept ASC, s.merit ASC
");
$all_rows = $list->fetch_all(MYSQLI_ASSOC);

foreach ($all_rows as &$r) {
    $r['dean_status'] = db_to_dean($r['dean_db_status']);
    $r['viva_status'] = db_to_dean($r['viva_db_status']);
}
unset($r);

$by_dept = [];
foreach ($all_rows as $r) { $by_dept[$r['dept']][] = $r; }
ksort($by_dept);

$total    = count($all_rows);
$approved = count(array_filter($all_rows, fn($r) => $r['dean_status'] === 'approved'));
$rejected = count(array_filter($all_rows, fn($r) => $r['dean_status'] === 'rejected'));
$waiting  = $total - $approved - $rejected;

$page_title = "Dean's Office Panel";
include "../includes/header.php";
?>

<style>
  .dean-header { display:flex; align-items:center; gap:14px; margin-bottom:22px; padding-bottom:16px; border-bottom:2px solid var(--line); }
  .dean-avatar { width:52px; height:52px; background:linear-gradient(135deg,#0d3b66,#3f51b5); border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-size:22px; }
  .dean-info h2 { margin:0 0 3px; font-size:20px; }
  .dean-info p  { margin:0; color:var(--muted); font-size:13px; }
  .stats-bar { display:flex; gap:12px; margin-bottom:24px; flex-wrap:wrap; }
  .stat-card { flex:1; min-width:110px; background:#fff; border:1px solid var(--line); border-radius:10px; padding:14px 18px; text-align:center; box-shadow:0 2px 6px rgba(0,0,0,.05); }
  .stat-card .num { font-size:26px; font-weight:700; }
  .stat-card .lbl { font-size:12px; color:var(--muted); margin-top:3px; }
  .stat-card.green .num { color:#16a34a; }
  .stat-card.red   .num { color:#dc2626; }
  .stat-card.blue  .num { color:#2563eb; }
  .dept-section { margin-bottom:28px; }
  .dept-title { font-size:15px; font-weight:700; background:#f0f4ff; padding:8px 14px; border-left:4px solid #3f51b5; border-radius:0 6px 6px 0; margin-bottom:10px; color:#1e3a5f; }
  .student-row { display:flex; align-items:center; gap:12px; padding:11px 14px; border:1px solid var(--line); border-radius:8px; margin-bottom:8px; background:#fff; transition:box-shadow .15s; }
  .student-row:hover { box-shadow:0 3px 10px rgba(0,0,0,.08); }
  .student-info { flex:1; }
  .student-info .s-name { font-weight:700; font-size:15px; }
  .student-info .s-meta { font-size:12px; color:var(--muted); margin-top:2px; }
  .badge { font-size:11px; font-weight:600; padding:3px 9px; border-radius:20px; white-space:nowrap; }
  .badge.approved { background:#dcfce7; color:#15803d; }
  .badge.rejected { background:#fee2e2; color:#b91c1c; }
  .badge.waiting  { background:#f3f4f6; color:#6b7280; }
  .row-actions { display:flex; align-items:center; gap:8px; }
  .row-actions select { padding:6px 10px; border:1px solid var(--line); border-radius:6px; font-size:13px; background:#fff; cursor:pointer; }
  .row-actions .btn-sm { padding:6px 14px; font-size:13px; background:var(--btn); color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:600; }
  .alert.success { background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; border-radius:8px; padding:10px 14px; margin-bottom:16px; }
  .alert.error   { background:#fee2e2; color:#b91c1c; border:1px solid #fecaca; border-radius:8px; padding:10px 14px; margin-bottom:16px; }
  .alert.info    { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; border-radius:8px; padding:10px 14px; margin-bottom:16px; }
</style>

<section class="card">
  <div class="dean-header">
    <div class="dean-avatar"><i class="fa-solid fa-user-tie"></i></div>
    <div class="dean-info">
      <h2>Dean's Office Panel</h2>
      <p>Logged in as <strong><?= htmlspecialchars($dean_name) ?></strong> · All Departments</p>
    </div>
    <a class="btn" href="logout.php" style="margin-left:auto; text-decoration:none;"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
  </div>

  <div class="stats-bar">
    <div class="stat-card blue"><div class="num"><?= $total ?></div><div class="lbl">Total Students</div></div>
    <div class="stat-card green"><div class="num"><?= $approved ?></div><div class="lbl">Approved</div></div>
    <div class="stat-card red"><div class="num"><?= $rejected ?></div><div class="lbl">Rejected</div></div>
    <div class="stat-card"><div class="num"><?= $waiting ?></div><div class="lbl">Waiting</div></div>
    <div class="stat-card"><div class="num"><?= count($by_dept) ?></div><div class="lbl">Departments</div></div>
  </div>

  <?php if ($success): ?><div class="alert success"><i class="fa-solid fa-circle-check"></i> <?= $success ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert error"><i class="fa-solid fa-circle-xmark"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if (empty($all_rows)): ?><div class="alert info">No students found.</div><?php endif; ?>

  <?php foreach ($by_dept as $dept => $students): ?>
    <div class="dept-section">
      <div class="dept-title">
        <i class="fa-solid fa-building-columns"></i>
        <?= htmlspecialchars($dept) ?>
        <span style="font-weight:400; font-size:13px; color:#555;"> — <?= count($students) ?> student(s)</span>
      </div>
      <?php foreach ($students as $r): ?>
        <form method="POST">
          <input type="hidden" name="student_id" value="<?= (int)$r['id'] ?>">
          <div class="student-row">
            <div class="student-info">
              <div class="s-name"><?= htmlspecialchars($r['name'] ?: 'Student') ?></div>
              <div class="s-meta">
                Roll: <?= htmlspecialchars($r['gst_roll']) ?> &nbsp;·&nbsp;
                Merit #<?= (int)$r['merit'] ?> &nbsp;·&nbsp;
                Viva: <span class="badge <?= $r['viva_status'] ?>"><?= ucfirst($r['viva_status']) ?></span>
              </div>
            </div>
            <span class="badge <?= $r['dean_status'] ?>">Dean: <?= ucfirst($r['dean_status']) ?></span>
            <div class="row-actions">
              <select name="status">
                <option value="approved" <?= $r['dean_status'] === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="rejected" <?= $r['dean_status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
              </select>
              <button class="btn-sm" type="submit">Update</button>
            </div>
          </div>
        </form>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</section>

<?php include "../includes/footer.php"; ?>
