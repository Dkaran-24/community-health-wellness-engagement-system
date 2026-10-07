<?php
/**
 * member/login.php — member self-service login for New Life Fitness Club.
 *
 * Members log in with their USERNAME + a password set by the admin (or by
 * themselves after first login). Authentication is performed against the
 * bcrypt hash stored in the members table.
 */
session_start();
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/member_auth.php';

/* Make sure the username / password columns exist (idempotent migration). */
ensure_member_login_columns();

$settings = get_settings();
$base     = member_base_url();
$error    = '';

/* Redirect to member dashboard if already logged in */
if (isset($_SESSION['member_id'])) {
    header('Location: ' . $base . '/member/dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both your username and password.';
    } else {
        /* Look up the member by username using a prepared statement. */
        $stmt = db()->prepare(
          "SELECT id, name, username, password, status FROM members WHERE username = ? LIMIT 1"
        );
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $res    = $stmt->get_result();
        $member = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        /* Use the same generic error for "no such user" and "wrong password"
           to avoid username enumeration. */
        if (!$member || empty($member['password'])) {
            $error = 'Invalid username or password.';
        } elseif ($member['status'] !== 'Active') {
            /* Block inactive/disabled members from logging in. */
            $error = 'Your membership is currently inactive. Please contact the gym to reactivate your account.';
        } elseif (!password_verify($password, $member['password'])) {
            $error = 'Invalid username or password.';
        } else {
            /* Success — regenerate the session id to prevent session fixation,
               then store only the member id + display name in the session. */
            session_regenerate_id(true);

            $_SESSION['member_id']   = $member['id'];
            $_SESSION['member_name'] = $member['name'];
            /* Lock the session to this member so an ID swapped in the URL can
               never let them see another member's data (defence in depth). */
            $_SESSION['member_ip']   = $_SERVER['REMOTE_ADDR'] ?? '';

            header('Location: ' . $base . '/member/dashboard.php');
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
<title><?= htmlspecialchars($settings['gym_name']) ?> | Member Portal</title>
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
    <div class="sub">Member Portal Sign In</div>

    <?php if ($error): ?>
      <div class="alert err flash" style="text-align:left;">&#9888; <?= htmlspecialchars($error) ?></div>
    <?php elseif (isset($_GET['expired'])): ?>
      <div class="alert warn flash" style="text-align:left;">&#128338; Your session has expired (your account was not found in the
        database). Please sign in again.</div>
    <?php endif; ?>

    <form method="post" data-validate autocomplete="on">
      <div class="form-field">
        <label>Username <span class="req">*</span></label>
        <input type="text" name="username" required autofocus placeholder="Your member username" autocomplete="username">
      </div>
      <div class="form-field">
        <label>Password <span class="req">*</span></label>
        <input type="password" name="password" required placeholder="••••••••" autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-primary">Sign In &rarr;</button>
    </form>

    <div class="cred-note">
      Don't have a username or password yet? Ask our front desk to set up your member login.
    </div>
    <div style="margin-top:14px; font-size:13px;">
      <a href="<?= $base ?>/login.php">&larr; Admin Login</a>
    </div>
  </div>
  <script src="<?= $base ?>/assets/js/main.js"></script>
</body>
</html>
