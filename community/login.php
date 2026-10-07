<?php
/**
 * community/login.php — COMMUNITY LOGIN (New Life Fitness CEP).
 *
 * Authenticated against community_users (bcrypt). Completely separate
 * from admin (admins table) and gym member (members table) logins.
 */
session_start();
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/community_auth.php';

$base  = community_base_url();
$error = '';

/* Already logged in -> dashboard. */
if (is_community_logged_in()) {
    header('Location: ' . $base . '/community/dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter both your email and password.';
    } else {
        $stmt = db()->prepare("SELECT id, full_name, password, status FROM community_users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res  = $stmt->get_result();
        $user = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        /* Same generic error for unknown user / wrong password (no enumeration). */
        if (!$user || !password_verify($password, $user['password'])) {
            $error = 'Invalid email or password.';
        } elseif ($user['status'] !== 'Active') {
            $error = 'Your community account is blocked. Please contact the community team.';
        } else {
            session_regenerate_id(true);
            $_SESSION['community_user_id']   = $user['id'];
            $_SESSION['community_user_name'] = $user['full_name'];
            $_SESSION['community_ip']        = $_SERVER['REMOTE_ADDR'] ?? '';
            $_SESSION['community_role']      = community_is_volunteer($user['id']) ? 'volunteer' : 'community';

            /* Honour ?next= redirect if it stays inside this project. */
            $next = $_POST['next'] ?? $_GET['next'] ?? '';
            if ($next && strpos($next, $base . '/') === 0 && strpos($next, '://') === false) {
                header('Location: ' . $next);
            } else {
                header('Location: ' . $base . '/community/dashboard.php');
            }
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Community Login | New Life Fitness — Community Platform</title>
<link rel="icon" href="<?= $base ?>/assets/images/favicon.ico" type="image/x-icon">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>/assets/css/community.css">
</head>
<body class="c-auth">
  <div class="c-auth-card">
    <div class="logo-row">
      <img src="<?= $base ?>/assets/images/logo.jpg" alt="New Life Fitness logo">
      <div>
        <h1>Community Login</h1>
        <div class="sub">New Life Fitness — Community Health, Fitness &amp; Wellness Platform</div>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="c-alert err">&#9888; <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (!$error && isset($_GET['expired'])): ?>
      <div class="c-alert warn">&#128338; Your session has expired (your account was not found in the
        database). Please sign in again.</div>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <?php $n = $_GET['next'] ?? ''; if ($n): ?>
        <input type="hidden" name="next" value="<?= htmlspecialchars($n, ENT_QUOTES, 'UTF-8') ?>">
      <?php endif; ?>
      <div class="c-field" style="margin-bottom:14px;">
        <label>Email <span class="req">*</span></label>
        <input type="email" name="email" required autofocus placeholder="you@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="c-field" style="margin-bottom:18px;">
        <label>Password <span class="req">*</span></label>
        <input type="password" name="password" required placeholder="&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;">
      </div>
      <button type="submit" class="c-btn gold" style="width:100%; padding:13px;">SIGN IN TO COMMUNITY</button>
    </form>

    <div class="alt">
      New to the community? <a href="<?= $base ?>/community/register.php"><b>Create a free community account &rarr;</b></a><br><br>

      Administrator? <a href="<?= $base ?>/login.php">Admin Login</a>
    </div>
    <div class="c-auth-note">
      Community accounts are free and separate from gym memberships.
    </div>
  </div>
</body>
</html>
