<?php
/**
 * admin/members/edit.php — edit an existing member.
 *
 * Also lets the admin:
 *   - set / change the member's portal username (must remain unique)
 *   - set / reset the member's portal password (stored as a bcrypt hash)
 * The existing password is never displayed.
 */
$PAGE_TITLE = 'Edit Member';
$PAGE_KEY   = 'members';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/member_auth.php';

$db = db();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php?err=Invalid member id.'); exit; }

/* Auto-migrate: ensure username / password columns exist (idempotent). */
ensure_member_login_columns();

$m = $db->query("SELECT * FROM members WHERE id=$id")->fetch_assoc();
if (!$m) { header('Location: index.php?err=Member not found.'); exit; }

$plans    = $db->query("SELECT id, plan_name FROM membership_plans ORDER BY id");
$trainers = $db->query("SELECT id, name FROM trainers ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name'] ?? '');
    $contact   = trim($_POST['contact'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $address   = trim($_POST['address'] ?? '');
    $dob       = $_POST['dob'] ?: null;
    $gender    = $_POST['gender'] ?? 'Male';
    $join_date = trim($_POST['join_date'] ?? '');
    $plan_id   = $_POST['plan_id'] ?: null;
    $trainer_id= $_POST['trainer_id'] ?: null;
    $status    = $_POST['status'] ?? 'Active';
    $removePhoto = isset($_POST['remove_photo']);

    /* --- Username (optional to change, but if provided must be valid+unique) --- */
    $username = trim($_POST['username'] ?? '');
    $usernameErr = null;
    if ($username !== '') {
        $usernameErr = validate_member_username($username, $db, $id);
        if ($usernameErr) {
            header('Location: edit.php?id=' . $id . '&err=' . urlencode($usernameErr));
            exit;
        }
    }

    /* --- Password (optional to set; never displayed, never overwritten if blank) --- */
    $memberPassword = $_POST['member_password'] ?? '';
    $memberPassword = trim($memberPassword);
    if ($memberPassword !== '' && strlen($memberPassword) < 6) {
        header('Location: edit.php?id=' . $id . '&err=' . urlencode('Member password must be at least 6 characters.'));
        exit;
    }

    // Handle optional new photo upload
    $photoErr = '';
    $newPhoto = handlePhotoUpload($photoErr);
    if ($photoErr) {
        header('Location: edit.php?id=' . $id . '&err=' . urlencode($photoErr));
        exit;
    }
    // Decide final photo path
    if ($removePhoto) {
        $photoPath = null;
        if ($m['photo'] && file_exists(__DIR__ . '/../../' . $m['photo'])) {
            @unlink(__DIR__ . '/../../' . $m['photo']);
        }
    } elseif ($newPhoto) {
        $photoPath = $newPhoto;
        if ($m['photo'] && file_exists(__DIR__ . '/../../' . $m['photo'])) {
            @unlink(__DIR__ . '/../../' . $m['photo']);
        }
    } else {
        $photoPath = $m['photo'];
    }

    /* Update the core member fields via a prepared statement. */
    $stmt = $db->prepare("UPDATE members SET name=?,contact=?,email=?,address=?,dob=?,gender=?,join_date=?,photo=?,plan_id=?,trainer_id=?,status=? WHERE id=?");
    $stmt->bind_param('ssssssssissi', $name,$contact,$email,$address,$dob,$gender,$join_date,$photoPath,$plan_id,$trainer_id,$status,$id);
    $ok = $stmt->execute();
    $stmtErr = $stmt->error;
    $stmt->close();

    if ($ok) {
        /* Apply username update separately if a valid one was provided. */
        if ($username !== '' && !$usernameErr) {
            $u = $db->prepare("UPDATE members SET username=? WHERE id=$id");
            $u->bind_param('s', $username);
            $uOk = $u->execute();
            $uErr = $u->error;
            $u->close();
            if (!$uOk) {
                $msg = (strpos($uErr, 'uniq_members_username') !== false || stripos($uErr, 'Duplicate') !== false)
                    ? 'That username is already taken. Please choose a different one.'
                    : 'Could not update username: ' . $uErr;
                header('Location: edit.php?id=' . $id . '&err=' . urlencode($msg));
                exit;
            }
        }

        /* Apply password update separately if a new one was provided. */
        if ($memberPassword !== '') {
            $pwdHash = password_hash($memberPassword, PASSWORD_DEFAULT);
            $p = $db->prepare("UPDATE members SET password=?, password_set_at=NOW() WHERE id=$id");
            $p->bind_param('s', $pwdHash);
            $p->execute();
            $p->close();
        }

        header('Location: index.php?ok=' . urlencode('Member "' . $name . '" updated.'));
    } else {
        header('Location: edit.php?id=' . $id . '&err=' . urlencode('Update failed: ' . $stmtErr));
    }
    exit;
}
?>
<?= flash() ?>
<div class="page-head">
  <div><h2>Edit Member</h2><p>Update details for <b><?= e($m['name']) ?></b>.</p></div>
  <a href="index.php" class="btn btn-ghost">&larr; Back to Members</a>
</div>

<div class="card">
  <div class="card-body">
    <form method="post" data-validate enctype="multipart/form-data">
      <div class="form-grid">
        <div class="form-field full">
          <label>Member Photo</label>
          <div class="photo-current">
            <?= photoImg($m['photo'], $m['name'], 'member-photo') ?>
            <?php if ($m['photo']): ?>
              <label class="photo-remove"><input type="checkbox" name="remove_photo" value="1"> Remove current photo</label>
            <?php endif; ?>
          </div>
          <input type="file" name="photo" accept="image/jpeg,image/png,image/gif,image/webp">
          <span class="hint">Choose a new image to replace the current photo. JPG, PNG, GIF or WebP — max 5 MB.</span>
        </div>
        <div class="form-field"><label>Full Name <span class="req">*</span></label><input type="text" name="name" required value="<?= e($m['name']) ?>"></div>
        <div class="form-field"><label>Contact Number</label><input type="text" name="contact" value="<?= e($m['contact']) ?>"></div>
        <div class="form-field"><label>Email</label><input type="email" name="email" value="<?= e($m['email']) ?>"></div>
        <div class="form-field"><label>Date of Birth</label><input type="date" name="dob" max="<?= date('Y-m-d') ?>" value="<?= e($m['dob']) ?>"></div>
        <div class="form-field">
          <label>Gender</label>
          <select name="gender">
            <?php foreach(['Male','Female','Other'] as $g): ?>
              <option <?= $m['gender']===$g?'selected':'' ?>><?= $g ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field"><label>Join Date <span class="req">*</span></label><input type="date" name="join_date" required value="<?= e($m['join_date']) ?>"></div>
        <div class="form-field">
          <label>Membership Plan</label>
          <select name="plan_id">
            <option value="">— Select plan —</option>
            <?php while ($p = $plans->fetch_assoc()): ?>
              <option value="<?= $p['id'] ?>" <?= $m['plan_id']==$p['id']?'selected':'' ?>><?= e($p['plan_name']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-field">
          <label>Assigned Trainer</label>
          <select name="trainer_id">
            <option value="">— None —</option>
            <?php while ($t = $trainers->fetch_assoc()): ?>
              <option value="<?= $t['id'] ?>" <?= $m['trainer_id']==$t['id']?'selected':'' ?>><?= e($t['name']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-field">
          <label>Status</label>
          <select name="status">
            <option <?= $m['status']==='Active'?'selected':'' ?>>Active</option>
            <option <?= $m['status']==='Inactive'?'selected':'' ?>>Inactive</option>
          </select>
        </div>
        <div class="form-field full"><label>Address</label><textarea name="address"><?= e($m['address']) ?></textarea></div>