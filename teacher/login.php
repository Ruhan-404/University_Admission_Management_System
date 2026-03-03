<?php
$BASE = '../';
require_once '../includes/db.php';

if (isset($_SESSION['teacher_id'])) {
    header("Location: panel.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = "Email and password required.";
    } else {

        $stmt = $conn->prepare("SELECT * FROM teachers WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $teacher = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$teacher || !password_verify($password, $teacher['password'])) {
            $error = "Invalid email or password.";
        } else {

            $_SESSION['teacher_id'] = $teacher['id'];
            $_SESSION['teacher_name'] = $teacher['name'];
            $_SESSION['teacher_dept'] = $teacher['department'];

            header("Location: panel.php");
            exit;
        }
    }
}

$page_title = "Teacher Login";
include '../includes/header.php';
?>

<section class="card">
  <h2>Teacher Login</h2>

  <?php if ($error): ?>
    <div class="alert error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="field">
      <div class="label">Email</div>
      <div class="control">
        <i class="fa-solid fa-envelope"></i>
        <input type="email" name="email" required>
      </div>
    </div>

    <div class="field">
      <div class="label">Password</div>
      <div class="control">
        <i class="fa-solid fa-lock"></i>
        <input type="password" name="password" required>
      </div>
    </div>

    <div class="submit-row">
      <button class="btn" type="submit">Login</button>
    </div>
  </form>
</section>

<?php include '../includes/footer.php'; ?>