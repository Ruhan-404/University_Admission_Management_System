<?php
$BASE = '../';
require_once '../includes/db.php';

if (isset($_SESSION['dean_id'])) {
    header("Location: panel.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = "Username and password required.";
    } else {

        $stmt = $conn->prepare("SELECT * FROM deans WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $dean = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$dean || !password_verify($password, $dean['password'])) {
            $error = "Invalid username or password.";
        } else {
            $_SESSION['dean_id']   = $dean['id'];
            $_SESSION['dean_name'] = $dean['name'];

            header("Location: panel.php");
            exit;
        }
    }
}

$page_title = "Dean's Office Login";
include '../includes/header.php';
?>

<section class="card">
  <h2>Dean's Office Login</h2>

  <?php if ($error): ?>
    <div class="alert error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="field">
      <div class="label">Username</div>
      <div class="control">
        <i class="fa-solid fa-user-tie"></i>
        <input type="text" name="username" required autocomplete="username">
      </div>
    </div>

    <div class="field">
      <div class="label">Password</div>
      <div class="control">
        <i class="fa-solid fa-lock"></i>
        <input type="password" name="password" required autocomplete="current-password">
      </div>
    </div>

    <div class="submit-row">
      <button class="btn" type="submit">Login</button>
    </div>
  </form>
</section>

<?php include '../includes/footer.php'; ?>
