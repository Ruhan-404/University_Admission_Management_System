<?php
require_once 'includes/db.php';

if (!isset($_SESSION['student_id'])) {
  header('Location: login.php'); exit;
}

$student_id = (int)$_SESSION['student_id'];

$ensure = $conn->prepare("INSERT IGNORE INTO student_step_status (student_id, step_id, status) SELECT ?, st.id, 'waiting' FROM admission_steps st WHERE st.is_active = 1");
$ensure->bind_param('i', $student_id);
$ensure->execute();
$ensure->close();

$student = [
  'gst_roll' => $_SESSION['student_gst']   ?? '',
  'dept'     => $_SESSION['student_dept']  ?? '',
  'merit'    => $_SESSION['student_merit'] ?? '',
];

$existing = $conn->prepare("SELECT * FROM admission_forms WHERE student_id = ? LIMIT 1");
$existing->bind_param('i', $student_id);
$existing->execute();
$form = $existing->get_result()->fetch_assoc() ?: [];
$existing->close();

$error = '';

function save_photo($oldValue = null) {
  if (!isset($_FILES['photo']) || $_FILES['photo']['error'] === UPLOAD_ERR_NO_FILE) return $oldValue ?: null;
  if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) return $oldValue ?: null;
  $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, ['jpg','jpeg','png'], true)) return $oldValue ?: null;
  if (!is_dir('uploads')) @mkdir('uploads', 0755, true);
  $newName = 'photo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
  $dest = 'uploads/' . $newName;
  if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) return $dest;
  return $oldValue ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $full_name   = trim($_POST['full_name']   ?? '');
  $dob         = trim($_POST['dob']         ?? '');
  $gender      = trim($_POST['gender']      ?? '');
  $nationality = trim($_POST['nationality'] ?? '');

  if ($full_name === '' || $dob === '' || $gender === '' || $nationality === '') {
    $error = "Please fill all required fields.";
  } else {
    $photo = save_photo($form['photo'] ?? null);
    $sql = "INSERT INTO admission_forms (student_id, full_name, dob, gender, nationality, photo) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE full_name=VALUES(full_name), dob=VALUES(dob), gender=VALUES(gender), nationality=VALUES(nationality), photo=VALUES(photo)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
      $error = "SQL error: " . $conn->error;
    } else {
      $stmt->bind_param('isssss', $student_id, $full_name, $dob, $gender, $nationality, $photo);
      $stmt->execute();
      $stmt->close();
      $upd = $conn->prepare("UPDATE student_step_status ss JOIN admission_steps st ON st.id = ss.step_id SET ss.status = 'done' WHERE ss.student_id = ? AND st.step_order = 1");
      $upd->bind_param('i', $student_id);
      $upd->execute();
      $upd->close();
      header("Location: dashboard.php"); exit;
    }
  }
}

$page_title = 'Form Submission';
include 'includes/header.php';

function val($key, $form) {
  return htmlspecialchars($_POST[$key] ?? ($form[$key] ?? ''), ENT_QUOTES);
}
?>

<section class="card">
  <h2>Admission Form</h2>

  <div class="alert" style="margin-bottom:14px;">
    <b>GST Roll:</b> <?= htmlspecialchars($student['gst_roll']) ?> &middot;
    <b>Dept:</b> <?= htmlspecialchars($student['dept']) ?> &middot;
    <b>Merit:</b> #<?= (int)$student['merit'] ?>
  </div>

  <?php if ($error): ?>
    <div class="alert error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" autocomplete="off">

    <div class="field">
      <div class="label">Full Name <span class="req">*</span></div>
      <div class="control">
        <i class="fa-solid fa-user"></i>
        <input type="text" name="full_name" value="<?= val('full_name', $form) ?>" required>
      </div>
    </div>

    <div class="field">
      <div class="label">Date of Birth <span class="req">*</span></div>
      <div class="control">
        <i class="fa-solid fa-calendar"></i>
        <input type="date" name="dob" value="<?= val('dob', $form) ?>" required>
      </div>
    </div>

    <div class="field">
      <div class="label">Gender <span class="req">*</span></div>
      <div class="control">
        <i class="fa-solid fa-venus-mars"></i>
        <?php $g = $_POST['gender'] ?? ($form['gender'] ?? ''); ?>
        <div class="select-wrap">
          <select name="gender" required>
            <option value="" disabled <?= $g===''?'selected':''; ?>>Select</option>
            <option value="Male"   <?= $g==='Male'  ?'selected':''; ?>>Male</option>
            <option value="Female" <?= $g==='Female'?'selected':''; ?>>Female</option>
            <option value="Other"  <?= $g==='Other' ?'selected':''; ?>>Other</option>
          </select>
        </div>
      </div>
    </div>

    <div class="field">
      <div class="label">Nationality <span class="req">*</span></div>
      <div class="control">
        <i class="fa-solid fa-flag"></i>
        <input type="text" name="nationality" value="<?= htmlspecialchars($_POST['nationality'] ?? ($form['nationality'] ?? 'Bangladeshi'), ENT_QUOTES) ?>" required>
      </div>
    </div>

    <div class="field">
      <div class="label">Student Photo <span style="color:var(--muted); font-weight:400; font-size:13px;">(jpg / png)</span></div>
      <div class="control">
        <i class="fa-solid fa-image"></i>
        <input type="file" name="photo" accept=".jpg,.jpeg,.png">
      </div>
      <?php if (!empty($form['photo'])): ?>
        <div style="margin-top:10px;">
          <span style="color:var(--muted); font-size:13px;">Current photo:</span><br>
          <img src="<?= htmlspecialchars($form['photo']) ?>" style="margin-top:6px; max-width:120px; border-radius:8px; border:1px solid #e5e7eb;">
        </div>
      <?php endif; ?>
    </div>

    <div class="submit-row">
      <button class="btn" type="submit">Save &amp; Continue</button>
    </div>

  </form>
</section>

<?php include 'includes/footer.php'; ?>
