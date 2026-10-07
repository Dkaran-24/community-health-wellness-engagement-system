<?php
/**
 * community/register.php — COMMUNITY REGISTER (New Life Fitness CEP).
 *
 * Any local resident can create a FREE community account — no gym
 * membership required. All inputs validated server-side, password
 * stored as a bcrypt hash (password_hash).
 */
session_start();
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/community_auth.php';

$base  = community_base_url();
$error = '';

/* Already logged in? Go straight to the dashboard. */
if (is_community_logged_in()) {
    header('Location: ' . $base . '/community/dashboard.php');
    exit;
}

$LEVELS = ['Beginner', 'Intermediate', 'Advanced'];
$GOALS  = ['General Fitness', 'Weight Loss', 'Strength', 'Flexibility', 'Endurance', 'Wellness'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $full_name  = trim($_POST['full_name'] ?? '');
    $email      = strtolower(trim($_POST['email'] ?? ''));
    $mobile     = preg_replace('/\D+/', '', $_POST['mobile'] ?? '');
    $age        = (int)($_POST['age'] ?? 0);
    $gender     = $_POST['gender'] ?? 'Other';
    $address    = trim($_POST['address_area'] ?? '');
    $password   = $_POST['password'] ?? '';
    $password2  = $_POST['password2'] ?? '';
    $level      = in_array($_POST['fitness_level'] ?? '', $LEVELS, true) ? $_POST['fitness_level'] : 'Beginner';
    $goal       = in_array($_POST['fitness_goal'] ?? '', $GOALS, true) ? $_POST['fitness_goal'] : 'General Fitness';
    $activities = trim($_POST['preferred_activities'] ?? 'Any');

    /* ---- server-side validation ---- */
    $errs = [];
    if (mb_strlen($full_name) < 3 || mb_strlen($full_name) > 120)      $errs[] = 'Please enter your full name (3-120 characters).';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))                     $errs[] = 'Please enter a valid email address.';
    if (strlen($mobile) < 10 || strlen($mobile) > 15)                   $errs[] = 'Please enter a valid mobile number (10-15 digits).';
    if ($age < 10 || $age > 100)                                        $errs[] = 'Age must be between 10 and 100.';
    if (!in_array($gender, ['Male', 'Female', 'Other'], true))          $errs[] = 'Please select a gender.';
    if (mb_strlen($address) < 3)                                        $errs[] = 'Please enter your address / area.';
    if (strlen($password) < 8)                                          $errs[] = 'Password must be at least 8 characters.';
    if ($password !== $password2)                                       $errs[] = 'Passwords do not match.';

    if (!$errs) {
        /* Uniqueness checks (prepared statements — SQL-injection safe). */
        $stmt = db()->prepare("SELECT id FROM community_users WHERE email = ? OR mobile = ? LIMIT 1");
        $stmt->bind_param('ss', $email, $mobile);
        $stmt->execute();
        $res = $stmt->get_result();
        $dup = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if ($dup) $errs[] = 'An account with this email or mobile number already exists. Try logging in instead.';
    }

    if (!$errs) {
        /* Hash the password with bcrypt (PHP password_hash). */
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = db()->prepare(
            "INSERT INTO community_users
               (full_name, email, mobile, age, gender, address_area, password,
                fitness_level, fitness_goal, preferred_activities)
             VALUES (?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->bind_param('sssissssss', $full_name, $email, $mobile, $age, $gender,
                          $address, $hash, $level, $goal, $activities);
        if ($stmt->execute()) {
            $newId = $stmt->insert_id;
            $stmt->close();

            session_regenerate_id(true);
            $_SESSION['community_user_id']   = $newId;
            $_SESSION['community_user_name'] = $full_name;
            $_SESSION['community_ip']        = $_SERVER['REMOTE_ADDR'] ?? '';
            $_SESSION['community_role']      = 'community';

            header('Location: ' . $base . '/community/dashboard.php?ok=' . urlencode('Welcome to the New Life Fitness community, ' . $full_name . '!'));
            exit;
        }
        $stmt->close();
        $errs[] = 'Could not create your account due to a server error. Please try again.';
    }
    $error = implode('<br>', $errs);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Community Register | New Life Fitness — Community Platform</title>
<link rel="icon" href="<?= $base ?>/assets/images/favicon.ico" type="image/x-icon">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>/assets/css/community.css">
</head>
<body class="c-auth">
  <div class="c-auth-card wide">
    <div class="logo-row">
      <img src="<?= $base ?>/assets/images/logo.jpg" alt="New Life Fitness logo">
      <div>
        <h1>Join the Community — it's free!</h1>
        <div class="sub">No gym membership needed. Open to every local resident.</div>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="c-alert err">&#9888; <?= $error ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <div class="c-form-grid">
        <div class="c-field">
          <label>Full Name <span class="req">*</span></label>
          <input type="text" name="full_name" required maxlength="120" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
        </div>
        <div class="c-field">
          <label>Email <span class="req">*</span></label>
          <input type="email" name="email" required maxlength="160" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="c-field">
          <label>Mobile Number <span class="req">*</span></label>
          <input type="tel" name="mobile" required maxlength="15" pattern="[0-9+\s-]{10,15}" value="<?= htmlspecialchars($_POST['mobile'] ?? '') ?>">
        </div>
        <div class="c-field">
          <label>Age <span class="req">*</span></label>
          <input type="number" name="age" required min="10" max="100" value="<?= htmlspecialchars($_POST['age'] ?? '') ?>">
        </div>
        <div class="c-field">
          <label>Gender <span class="req">*</span></label>
          <select name="gender" required>
            <?php foreach (['Male', 'Female', 'Other'] as $g): ?>
              <option value="<?= $g ?>" <?= (($_POST['gender'] ?? '') === $g) ? 'selected' : '' ?>><?= $g ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="c-field">
          <label>Address / Area <span class="req">*</span></label>
          <input type="text" name="address_area" required maxlength="255" value="<?= htmlspecialchars($_POST['address_area'] ?? '') ?>">
        </div>
        <div class="c-field">
          <label>Password <span class="req">*</span> <span class="hint">(min 8 characters)</span></label>
          <input type="password" name="password" required minlength="8">
        </div>
        <div class="c-field">
          <label>Confirm Password <span class="req">*</span></label>
          <input type="password" name="password2" required minlength="8">
        </div>
        <div class="c-field">
          <label>Fitness Level</label>
          <select name="fitness_level">
            <?php foreach ($LEVELS as $l): ?>
              <option value="<?= $l ?>" <?= (($_POST['fitness_level'] ?? 'Beginner') === $l) ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
          <div class="hint">Used by our recommendation engine to suggest suitable activities.</div>
        </div>
        <div class="c-field">
          <label>Fitness Goal</label>
          <select name="fitness_goal">
            <?php foreach ($GOALS as $g): ?>
              <option value="<?= $g ?>" <?= (($_POST['fitness_goal'] ?? 'General Fitness') === $g) ? 'selected' : '' ?>><?= $g ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="c-field" style="grid-column: 1 / -1;">
          <label>Preferred Activities</label>
          <input type="text" name="preferred_activities" maxlength="120" placeholder="e.g. Yoga, Walking, Zumba, Running" value="<?= htmlspecialchars($_POST['preferred_activities'] ?? '') ?>">
          <div class="hint">Comma separated. Helps us recommend community activities you will enjoy.</div>
        </div>
      </div>
      <div style="margin-top:20px; text-align:center;">
        <button type="submit" class="c-btn gold" style="width:100%; padding:13px;">CREATE MY COMMUNITY ACCOUNT</button>
      </div>
    </form>

    <div class="alt">
      <div class="register-login-options">

  <a class="register-login-card community-login"
     href="<?= $base ?>/community/login.php">
    <span class="login-icon">👤</span>
    <span>
      <strong>Community Login</strong>
      <small>Already have a community account?</small>
    </span>
    <span class="login-arrow">→</span>
  </a>

  

  <a class="register-login-card"
     href="<?= $base ?>/login.php">
    <span class="login-icon">🔐</span>
    <span>
      <strong>Administrator Login</strong>
      <small>Access the admin panel</small>
    </span>
    <span class="login-arrow">→</span>
  </a>

</div>
    </div>
    <div class="c-auth-note">
      Your password is stored securely as a bcrypt hash. Community accounts are separate
      from gym member accounts — they give access to community events, challenges,
      resources, surveys and volunteering, not to gym membership features.
    </div>
  </div>
</body>
</html>
