<?php
/**
 * admin/members/add.php — create a new member.
 *
 * The admin can (and must) set the member's portal login credentials here:
 *   - Username  (required, unique, validated)
 *   - Password  (required, min 6 chars, stored as a bcrypt hash)
 */
$PAGE_TITLE = 'Add Member';
$PAGE_KEY   = 'members';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/member_auth.php';

$db = db();

/* Make sure the username / password columns exist (idempotent migration). */
ensure_member_login_columns();

$plans    = $db->query("SELECT id, plan_name FROM membership_plans ORDER BY id");
$trainers = $db->query("SELECT id, name FROM trainers ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name'] ?? '');
    $contact   = trim($_POST['contact'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $address   = trim($_POST['address'] ?? '');
    $dob       = $_POST['dob'] ?? null;
    if ($dob === '') $dob = null;
    $gender    = $_POST['gender'] ?? 'Male';
    $join_date = trim($_POST['join_date'] ?? '');
    $plan_id   = $_POST['plan_id'] ?? null;
    if ($plan_id === '') $plan_id = null;
    $trainer_id= $_POST['trainer_id'] ?? null;
    if ($trainer_id === '') $trainer_id = null;
    $status    = $_POST['status'] ?? 'Active';

    /* Member login credentials */
    $username  = trim($_POST['username'] ?? '');
    $password  = $_POST['password'] ?? '';

    /* Basic required-field validation */
    if ($name === '' || $join_date === '') {
        header('Location: add.php?err=' . urlencode('Name and join date are required.'));
        exit;
    }

    /* Username validation (length, charset, uniqueness) */
    $usernameErr = validate_member_username($username, $db, 0);
    if ($usernameErr) {
        header('Location: add.php?err=' . urlencode($usernameErr));
        exit;
    }

    /* Password validation */
    if ($password === '') {
        header('Location: add.php?err=' . urlencode('Password is required when creating a member.'));
        exit;
    }
    if (strlen($password) < 6) {
        header('Location: add.php?err=' . urlencode('Password must be at least 6 characters.'));
        exit;
    }

    /* Handle optional photo upload */
    $photoErr = '';
    $photoPath = handlePhotoUpload($photoErr);
    if ($photoErr) {
        header('Location: add.php?err=' . urlencode($photoErr));
        exit;
    }

    /* Hash the password with bcrypt (PHP password_hash). Never store plaintext. */
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    /* Insert with prepared statement (includes username + password). */
    $stmt = $db->prepare(
      "INSERT INTO members
         (name, contact, email, username, password, password_set_at,
          address, dob, gender, join_date, photo, plan_id, trainer_id, status)
       VALUES (?,?,?,?,?,NOW(),?,?,?,?,?,?,?,?)"
    );
    $stmt->bind_param(
      'ssssssssssiss',
      $name, $contact, $email, $username, $passwordHash,
      $address, $dob, $gender, $join_date, $photoPath, $plan_id, $trainer_id, $status
    );

    if ($stmt->execute()) {
        header('Location: index.php?ok=' . urlencode('Member "' . $name . '" added successfully. Login credentials have been set.'));
    } else {
        /* Defensive: if the unique index rejected a race-condition duplicate,
           give a friendly message instead of a raw SQL error. */
        $msg = $stmt->error;
        if (strpos($msg, 'uniq_members_username') !== false || stripos($msg, 'Duplicate') !== false) {
            $msg = 'That username is already taken. Please choose a different one.';
        }
        header('Location: add.php?err=' . urlencode('Could not add member: ' . $msg));
    }
    $stmt->close();
    exit;
}
?>
<?= flash() ?>
<div class="page-head">
  <div><h2>Add New Member</h2><p>Register a new member at New Life Fitness Club.</p></div>
  <a href="index.php" class="btn btn-ghost">&larr; Back to Members</a>
</div>

<div class="card">
  <div class="card-body">
    <form method="post" data-validate enctype="multipart/form-data">
      <div class="form-grid">
        <div class="form-field">
          <label>Full Name <span class="req">*</span></label>
          <input type="text" name="name" required value="<?= e($_POST['name'] ?? '') ?>">
        </div>
        <div class="form-field">
          <label>Contact Number</label>
          <input type="text" name="contact" value="<?= e($_POST['contact'] ?? '') ?>">
        </div>
        <div class="form-field">
          <label>Email</label>
          <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-field">
          <label>Date of Birth</label>
          <input type="date" name="dob" max="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-field">
          <label>Gender</label>
          <select name="gender">
            <option>Male</option><option>Female</option><option>Other</option>
          </select>
        </div>
        <div class="form-field">
          <label>Join Date <span class="req">*</span></label>
          <input type="date" name="join_date" required value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-field">
          <label>Membership Plan</label>
          <select name="plan_id">
            <option value="">— Select plan —</option>
            <?php while ($p = $plans->fetch_assoc()): ?>
              <option value="<?= $p['id'] ?>"><?= e($p['plan_name']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-field">
          <label>Assigned Trainer</label>
          <select name="trainer_id">
            <option value="">— None —</option>
            <?php while ($t = $trainers->fetch_assoc()): ?>
              <option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-field">
          <label>Status</label>
          <select name="status"><option>Active</option><option>Inactive</option></select>
        </div>
        <div class="form-field">
          <label>Member Photo</label>
          <input type="file" name="photo" accept="image/jpeg,image/png,image/gif,image/webp">
          <span class="hint">JPG, PNG, GIF or WebP — max 5 MB. Optional.</span>
        </div>
        <div class="form-field full">
          <label>Address</label>
          <textarea name="address"><?= e($_POST['address'] ?? '') ?></textarea>
        </div>
      </div>