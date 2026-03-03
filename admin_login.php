<?php
require_once 'includes/db.php';

// Already logged in
if (isset($_SESSION['admin_id'])) {
    header('Location: admin_progress.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $conn->prepare('SELECT id, username, password, name FROM admins WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$admin || !password_verify($password, $admin['password'])) {
            $error = 'Invalid admin credentials.';
        } else {
            $_SESSION['admin_id'] = (int)$admin['id'];
            $_SESSION['admin_name'] = $admin['name'] ?: $admin['username'];
            header('Location: admin_progress.php');
            exit;
        }
    }
}

$page_title = 'Admin Login';
include 'includes/header.php';
?>

<section class="card" role="region" aria-label="Admin Login">
  <h2>Admin Login</h2>

  <?php if ($error): ?>
    <div class="alert error" role="alert"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" autocomplete="off">
    <div class="field">
      <div class="label">Username</div>
      <div class="control">
        <i class="fa-solid fa-user"></i>
        <input type="text" name="username" placeholder="Enter admin username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
      </div>
    </div>

    <div class="field">
      <div class="label">Password</div>
      <div class="control">
        <i class="fa-solid fa-lock"></i>
        <input type="password" name="password" placeholder="Enter password" required>
      </div>
    </div>

    <div class="submit-row">
      <button class="btn" type="submit">Login</button>
    </div>
  </form>
</section>

<?php include 'includes/footer.php'; ?>
