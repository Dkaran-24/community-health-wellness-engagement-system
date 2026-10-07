<?php
/**
 * login.php — branded admin login for New Life Fitness Club.
 */
session_start();
require_once __DIR__ . '/db_connect.php';

$settings = get_settings();
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$error = '';

/* Redirect away if already logged in */
if (isset($_SESSION['admin_id'])) {
    header('Location: ' . $base . '/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = db()->prepare("SELECT id, username, password, full_name FROM admins WHERE username = ? LIMIT 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $res = $stmt->get_result();
        $admin = $res->fetch_assoc();
        $stmt->close();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id']   = $admin['id'];
            $_SESSION['admin_name'] = $admin['full_name'];
            header('Location: ' . $base . '/admin/dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($settings['gym_name']) ?> | Admin Panel</title>
<link rel="icon" href="<?= $base ?>/assets/images/favicon.ico" type="image/x-icon">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>/assets/css/style.css">
</head>
<body class="login-page">
  <div class="login-card">
    <img src="<?= $base ?>/assets/images/logo.jpg" alt="New Life Fitness Club logo" class="logo">
    <h1>New Life Fitness Club</h1>
    <div class="sub">Administrator Sign In</div>

    <?php if ($error): ?>
      <div class="alert err flash" style="text-align:left;">&#9888; <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" data-validate>
      <div class="form-field">
        <label>Username <span class="req">*</span></label>
        <input type="text" name="username" required autofocus placeholder="admin">
      </div>
      <div class="form-field">
        <label>Password <span class="req">*</span></label>
        <input type="password" name="password" required placeholder="••••••••">
      </div>
      <button type="submit" class="btn btn-primary">Sign In &rarr;</button>
    </form>
    <div style="margin-top:18px; font-size:13px;">
      <a href="<?= $base ?>/member/login.php">Member Portal Login &rarr;</a>
    </div>
  </div>
  <script src="<?= $base ?>/assets/js/main.js"></script>
</body>
</html>
