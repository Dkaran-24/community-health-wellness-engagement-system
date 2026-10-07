<?php
/**
 * community/profile.php — My Profile (CEP community user).
 *
 * Edit name / contact / fitness level / goal / preferred activities.
 * These preferences feed the rule-based recommendation engine, so
 * keeping them updated improves suggestions.
 */
$PAGE_TITLE = 'My Profile';
$PAGE_KEY   = 'profile';
require_once __DIR__ . '/../includes/community_auth.php';
require_community_login();
require_once __DIR__ . '/_header.php';

$db   = db();
$uid  = (int)$_SESSION['community_user_id'];
$user = current_community_user();

$levels = ['Beginner','Intermediate','Advanced'];
$goals  = ['General Fitness','Weight Loss','Strength','Flexibility','Endurance','Wellness'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $name  = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mob   = trim($_POST['mobile'] ?? '');
    $age   = (int)($_POST['age'] ?? 0);
    $gen   = $_POST['gender'] ?? 'Other';
    $area  = trim($_POST['address_area'] ?? '');
    $lvl   = $_POST['fitness_level'] ?? '';
    $goal  = $_POST['fitness_goal'] ?? '';
    $pref  = trim($_POST['preferred_activities'] ?? 'Any');

    if (mb_strlen($name) < 3 || mb_strlen($name) > 120) $errors[] = 'Full name must be 3-120 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))    $errors[] = 'Please enter a valid email address.';
    if (!preg_match('/^\d{10,15}$/', $mob))            $errors[] = 'Mobile must be 10-15 digits.';
    if ($age < 10 || $age > 100)                       $errors[] = 'Age must be between 10 and 100.';
    if (!in_array($gen, ['Male','Female','Other'], true)) $gen = 'Other';
    if (mb_strlen($area) < 3 || mb_strlen($area) > 255) $errors[] = 'Address / area must be 3-255 characters.';
    if (!in_array($lvl, $levels, true))                 $errors[] = 'Invalid fitness level.';
    if (!in_array($goal, $goals, true))                 $errors[] = 'Invalid fitness goal.';
    if (mb_strlen($pref) > 120)                         $errors[] = 'Preferred activities limited to 120 characters.';
    if ($pref === '') $pref = 'Any';

    /* uniqueness (excluding self) */
    if (!$errors) {
        $stmt = $db->prepare("SELECT id FROM community_users WHERE (email = ? OR mobile = ?) AND id <> ? LIMIT 1");
        $stmt->bind_param('ssi', $email, $mob, $uid);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) $errors[] = 'That email or mobile number is already used by another account.';
        $stmt->close();
    }

    if (!$errors) {
        $stmt = $db->prepare("UPDATE community_users SET full_name=?, email=?, mobile=?, age=?, gender=?,
                address_area=?, fitness_level=?, fitness_goal=?, preferred_activities=? WHERE id=?");
        $stmt->bind_param('ssiisssssi', $name, $email, $mob, $age, $gen, $area, $lvl, $goal, $pref, $uid);
        $stmt->execute(); $stmt->close();
        $_SESSION['community_user_name'] = $name;
        header('Location: profile.php?ok=' . urlencode('Profile updated. Your recommendations will refresh accordingly.'));
        exit;
    }
    /* repopulate form with submitted values */
    $user = array_merge($user ?: [], [
        'full_name' => $name, 'email' => $email, 'mobile' => $mob, 'age' => $age,
        'gender' => $gen, 'address_area' => $area, 'fitness_level' => $lvl,
        'fitness_goal' => $goal, 'preferred_activities' => $pref,
    ]);
}
?>
<div class="c-hero small">
  <span class="c-tag">MY ACCOUNT</span>
  <h1>My Profile</h1>
  <p>Keep your details and fitness preferences up to date — the recommendation engine
     uses them to suggest events and challenges you will actually enjoy.</p>
</div>

<?php if ($errors): ?>
<div class="c-alert err"><?php foreach ($errors as $er) echo '&#9888; ' . e($er) . '<br>'; ?></div>
<?php endif; ?>

<div class="c-grid cols-2" style="align-items:start;">
  <div class="c-card">
    <h3 style="margin-top:0;">&#9881; Personal Details</h3>
    <form method="post" class="c-form-grid">
      <?= csrf_field() ?>
      <div class="c-field"><label>Full name <b class="c-red-star">*</b></label>
        <input type="text" name="full_name" maxlength="120" required value="<?= e($user['full_name'] ?? '') ?>"></div>
      <div class="c-field"><label>Email <b class="c-red-star">*</b></label>
        <input type="email" name="email" maxlength="160" required value="<?= e($user['email'] ?? '') ?>"></div>
      <div class="c-field"><label>Mobile <b class="c-red-star">*</b></label>
        <input type="text" name="mobile" maxlength="15" required value="<?= e($user['mobile'] ?? '') ?>"></div>
      <div class="c-form-grid" style="grid-template-columns:1fr 1fr;">
        <div class="c-field"><label>Age <b class="c-red-star">*</b></label>
          <input type="number" name="age" min="10" max="100" required value="<?= (int)($user['age'] ?? 18) ?>"></div>
        <div class="c-field"><label>Gender</label>
          <select name="gender">
            <?php foreach (['Male','Female','Other'] as $g): ?>
              <option value="<?= $g ?>" <?= ($user['gender'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
            <?php endforeach; ?>
          </select></div>
      </div>
      <div class="c-field"><label>Address / area <b class="c-red-star">*</b></label>
        <input type="text" name="address_area" maxlength="255" required value="<?= e($user['address_area'] ?? '') ?>"></div>
      <div class="c-field"><label>Current fitness level <b class="c-red-star">*</b></label>
        <select name="fitness_level" required>
          <?php foreach ($levels as $l): ?>
            <option value="<?= $l ?>" <?= ($user['fitness_level'] ?? '') === $l ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="c-field"><label>Primary fitness goal <b class="c-red-star">*</b></label>
        <select name="fitness_goal" required>
          <?php foreach ($goals as $g): ?>
            <option value="<?= $g ?>" <?= ($user['fitness_goal'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="c-field"><label>Preferred activities (comma separated)</label>
        <input type="text" name="preferred_activities" maxlength="120" value="<?= e($user['preferred_activities'] ?? 'Any') ?>"
               placeholder="e.g. Yoga, Walking, Zumba"></div>
      <div class="c-field"><label>Account status</label>
        <input type="text" value="<?= e($user['status'] ?? 'Active') ?>" disabled></div>
      <button class="c-btn gold" type="submit">Save Changes</button>
      <a class="c-btn ghost" href="dashboard.php">Back to Dashboard</a>
    </form>
  </div>

  <div class="c-card">
    <h3 style="margin-top:0;">&#127947; Fitness Preferences <span class="c-badge steel">USED BY RECOMMENDATION ENGINE</span></h3>
    <p class="c-muted" style="font-size:12.5px;">
      Your fitness level, primary goal and preferred activities are edited in the form on the left
      and are saved together with your personal details.
    </p>
    <div class="c-item">
      <h4>Current preferences</h4>
      <div class="c-meta" style="display:grid;gap:8px;font-size:13.5px;">
        <div><b>Level:</b> <?= e($user['fitness_level'] ?? 'Beginner') ?></div>
        <div><b>Goal:</b> <?= e($user['fitness_goal'] ?? 'General Fitness') ?></div>
        <div><b>Preferred activities:</b> <?= e($user['preferred_activities'] ?? 'Any') ?></div>
      </div>
    </div>
    <p class="c-muted" style="font-size:12px;">
      &#129302; The recommendation engine (rule-based prototype) reads these fields plus your past
      participation to rank events &amp; challenges on your dashboard.
    </p>
  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
