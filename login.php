<?php
require_once 'includes/db.php';

if (isset($_SESSION['student_id'])) {
    header('Location: dashboard.php'); exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';

    if ($identifier === '' || $password === '') {
        $error = 'Please fill in all fields.';
    } else {
        // Look up by email OR phone OR GST roll
        $stmt = $conn->prepare(
            "SELECT * FROM students WHERE email = ? OR phone = ? OR gst_roll = ? LIMIT 1"
        );
        $stmt->bind_param('sss', $identifier, $identifier, $identifier);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$student || !password_verify($password, $student['password'])) {
            $error = 'Invalid credentials. Check your email/phone and password.';
        } else {
            // ✅ Success — create session (NOW INCLUDES NAME)
            $_SESSION['student_id']     = $student['id'];
            $_SESSION['student_name']   = $student['name'];      // ✅ added
            $_SESSION['student_gst']    = $student['gst_roll'];
            $_SESSION['student_dept']   = $student['dept'];
            $_SESSION['student_merit']  = $student['merit'];
            $_SESSION['student_email']  = $student['email'];
            $_SESSION['student_phone']  = $student['phone'];

            header('Location: dashboard.php'); exit;
        }
    }
}

$page_title = 'Log in';
include 'includes/header.php';
?>

<section class="card" role="region" aria-label="Login Form">
  <h2>Log in</h2>

  <?php if ($error): ?>
    <div class="alert error" role="alert"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" autocomplete="off">
    <div class="field">
      <div class="label">Email/mobile number</div>
      <div class="control">
        <i class="fa-solid fa-user"></i>
        <input
          type="text"
          name="identifier"
          placeholder="Enter your email or mobile number"
          value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>"
          required
          autofocus
        />
      </div>
    </div>

    <div class="field">
      <div class="label">Password</div>
      <div class="control">
        <i class="fa-solid fa-lock"></i>
        <input type="password" name="password" placeholder="Enter your password" required />
      </div>
    </div>

    <div class="submit-row">
      <button class="btn" type="submit">Submit</button>
    </div>
  </form>
</section>

<?php include 'includes/footer.php'; ?>