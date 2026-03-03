<?php
require_once 'includes/db.php';

if (isset($_SESSION['student_id'])) {
    header('Location: dashboard.php'); exit;
}

$error   = '';
$success = '';
$step    = 1;           // 1 = verify roll, 2 = set credentials
$gst_data = null;       // populated after step 1 verification

// ────────────────────────────────────────────────────────────
// POST: Step 1 — Verify GST Roll (prefix always GST-2025-)
// ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify') {

    // user types only last digits (e.g. 99999)
    $gst_suffix = preg_replace('/\D+/', '', $_POST['gst_suffix'] ?? '');
    $gst_suffix = substr($gst_suffix, 0, 5); // max 5 digits
    $gst_roll   = 'GST-2025-' . $gst_suffix;

    if ($gst_suffix === '' || strlen($gst_suffix) !== 5) {
        $error = 'Please enter last 5 digits of your GST roll (e.g. 99999).';
        $step  = 1;
    } else {
        $stmt = $conn->prepare("SELECT * FROM gst_rolls WHERE gst_roll = ?");
        $stmt->bind_param('s', $gst_roll);
        $stmt->execute();
        $result = $stmt->get_result();
        $row    = $result->fetch_assoc();
        $stmt->close();

        if (!$row) {
            $error = 'GST roll not found. Please check and try again.';
            $step  = 1;
        } elseif ($row['marked'] == 1) {
            $error = 'This GST roll has already been used for registration. Contact the admission office if this is an error.';
            $step  = 1;
        } else {
            // Eligible — move to step 2
            $step     = 2;
            $gst_data = $row;
            $_SESSION['verify_gst'] = $row;  // keep for step2
        }
    }
}

// ────────────────────────────────────────────────────────────
// POST: Step 2 — Register Student
// Login rule: email OR phone required + password
// ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    // Restore gst_data from session
    $gst_data = $_SESSION['verify_gst'] ?? null;

    if (!$gst_data) {
        $error = 'Session expired. Please start over.';
        $step  = 1;
    } elseif ($name === '') {
        $error = 'Please enter your full name.';
        $step  = 2;
    } elseif ($email === '' && $phone === '') {
        $error = 'Please provide at least one: Email or Phone (you will use it to log in).';
        $step  = 2;
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
        $step  = 2;
    } elseif ($phone !== '' && !preg_match('/^01[0-9]{9}$/', $phone)) {
        $error = 'Please enter a valid Bangladeshi phone number (e.g. 01XXXXXXXXX).';
        $step  = 2;
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
        $step  = 2;
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
        $step  = 2;
    } else {

        // Check duplicate email / phone
        if ($email !== '') {
            $chk = $conn->prepare("SELECT id FROM students WHERE email = ?");
            $chk->bind_param('s', $email);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $error = 'This email address is already registered.';
                $step  = 2;
            }
            $chk->close();
        }

        if ($error === '' && $phone !== '') {
            $chk = $conn->prepare("SELECT id FROM students WHERE phone = ?");
            $chk->bind_param('s', $phone);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $error = 'This phone number is already registered.';
                $step  = 2;
            }
            $chk->close();
        }

        if ($error === '') {
            $hashed = password_hash($password, PASSWORD_BCRYPT);

            // Insert student (includes name)
            $ins = $conn->prepare(
                "INSERT INTO students (name, gst_roll, email, phone, password, merit, dept)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            $em = $email !== '' ? $email : null;
            $ph = $phone !== '' ? $phone : null;

            $ins->bind_param(
                'sssssis',
                $name,
                $gst_data['gst_roll'],
                $em,
                $ph,
                $hashed,
                $gst_data['merit'],
                $gst_data['dept']
            );

            $ins->execute();
            $new_student_id = $conn->insert_id;
            $ins->close();

            // Create default admission progress rows for this student
            // ✅ step 1 remains WAITING so dashboard shows Submit button
            $mk = $conn->prepare(
                "INSERT IGNORE INTO student_step_status (student_id, step_id, status)
                 SELECT ?, id, 'waiting'
                 FROM admission_steps
                 WHERE is_active = 1"
            );
            $mk->bind_param('i', $new_student_id);
            $mk->execute();
            $mk->close();

            // Mark GST roll as used
            $upd = $conn->prepare("UPDATE gst_rolls SET marked = 1 WHERE gst_roll = ?");
            $upd->bind_param('s', $gst_data['gst_roll']);
            $upd->execute();
            $upd->close();

            unset($_SESSION['verify_gst']);

            $success  = 'registered';
            $reg_info = [
              'name' => $name,
              'gst_roll' => $gst_data['gst_roll'],
              'merit' => $gst_data['merit'],
              'dept' => $gst_data['dept'],
              'email' => $email,
              'phone' => $phone
            ];
        }
    }
}

$page_title = 'Sign Up';
include 'includes/header.php';
?>

<?php if ($success === 'registered'): ?>
  <section class="card" role="region" aria-label="Registration Successful">
    <h2>Registered!</h2>
    <div class="alert success">Your account has been created. Save your details below.</div>

    <div style="border:1px solid var(--line); border-radius:4px; padding:14px 16px; margin-top:14px;">
      <?php foreach ([
        ['Name',           $reg_info['name']],
        ['GST Roll',       $reg_info['gst_roll']],
        ['Department',     $reg_info['dept']],
        ['Merit Position', '#' . $reg_info['merit']],
        ['Login Email',    $reg_info['email'] ?: '—'],
        ['Login Phone',    $reg_info['phone'] ?: '—'],
      ] as [$k, $v]): ?>
        <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid var(--line);">
          <span style="color:var(--muted); font-size:14px;"><?= htmlspecialchars($k) ?></span>
          <span style="font-weight:700;"><?= htmlspecialchars($v) ?></span>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="submit-row" style="margin-top:18px;">
      <a class="btn" href="login.php" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">Go to Login</a>
    </div>
  </section>

<?php elseif ($step === 1): ?>
  <section class="card" role="region" aria-label="Verify GST Roll">
    <h2>Register</h2>

    <?php if ($error): ?>
      <div class="alert error" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="alert" style="margin-bottom:14px;">
      Demo: <b>GST-2025-99999</b> or <b>GST-2025-88888</b>
    </div>

    <form method="POST" autocomplete="off">
      <input type="hidden" name="action" value="verify">

      <div class="field">
        <div class="label">GST Roll Number</div>

        <div class="control" style="gap:10px;">
          <span style="padding:10px 12px; border:1px solid var(--line); border-radius:6px; background:#f3f4f6; font-weight:700;">
            GST-2025-
          </span>

          <input
            type="text"
            name="gst_suffix"
            maxlength="5"
            pattern="[0-9]{5}"
            inputmode="numeric"
            placeholder="99999"
            value="<?= htmlspecialchars($_POST['gst_suffix'] ?? '') ?>"
            required
            style="flex:1;"
          >
        </div>
      </div>

      <div class="submit-row">
        <button class="btn" type="submit">Verify</button>
      </div>
    </form>
  </section>

<?php elseif ($step === 2 && $gst_data): ?>
  <section class="card" role="region" aria-label="Set Credentials">
    <h2>Register</h2>

    <div class="alert" style="margin-bottom:14px;">
      <b>Eligible:</b> <?= htmlspecialchars($gst_data['gst_roll']) ?> · Merit #<?= (int)$gst_data['merit'] ?> · Dept <?= htmlspecialchars($gst_data['dept']) ?>
    </div>

    <div class="alert" style="margin-bottom:14px;">
      Provide at least <b>one</b>: Email or Phone. You will use it to log in.
    </div>

    <?php if ($error): ?>
      <div class="alert error" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <input type="hidden" name="action" value="register">

      <div class="field">
        <div class="label">Name</div>
        <div class="control">
          <i class="fa-solid fa-user"></i>
          <input type="text" name="name" placeholder="Enter your name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
        </div>
      </div>

      <div class="field">
        <div class="label">Email</div>
        <div class="control">
          <i class="fa-solid fa-envelope"></i>
          <input type="email" name="email" placeholder="you@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
      </div>

      <div class="field">
        <div class="label">Phone</div>
        <div class="control">
          <i class="fa-solid fa-phone"></i>
          <input type="text" name="phone" placeholder="01XXXXXXXXX" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
        </div>
      </div>

      <div class="field">
        <div class="label">Password</div>
        <div class="control">
          <i class="fa-solid fa-lock"></i>
          <input type="password" name="password" placeholder="Minimum 6 characters" required>
        </div>
      </div>

      <div class="field">
        <div class="label">Confirm Password</div>
        <div class="control">
          <i class="fa-solid fa-lock"></i>
          <input type="password" name="confirm" placeholder="Re-enter password" required>
        </div>
      </div>

      <div class="submit-row">
        <button class="btn" type="submit">Complete Registration</button>
      </div>
    </form>
  </section>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>