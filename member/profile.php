<?php
/**
 * member/profile.php — view and edit own profile.
 * Members can update: name, contact, email, dob, gender, address, photo.
 * Members CANNOT change: plan, trainer, status, join_date (admin-only).
 * Members can change their own password.
 */
$PAGE_TITLE = 'My Profile';
$PAGE_KEY   = 'profile';
require_once __DIR__ . '/_header.php';

$db  = db();
$m   = current_member();
if (!$m) { session_destroy(); header('Location: ' . member_base_url() . '/member/login.php'); exit; }
$id = (int)$m['id'];
$cur = cur();
$ok = '';
$err = '';

/* Handle profile update */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $name    = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $dob     = $_POST['dob'] ?? null;
    if ($dob === '') $dob = null;
    $gender  = $_POST['gender'] ?? 'Male';
    $address = trim($_POST['address'] ?? '');
    $removePhoto = isset($_POST['remove_photo']);

    if ($name === '') {
        $err = 'Name cannot be empty.';
    } else {
        $photoErr = '';
        $newPhoto = handlePhotoUpload($photoErr);
        if ($photoErr) {
            $err = $photoErr;
        } else {
            if ($removePhoto) {
                $photoPath = null;
                if ($m['photo'] && file_exists(__DIR__ . '/../' . $m['photo'])) {
                    @unlink(__DIR__ . '/../' . $m['photo']);
                }
            } elseif ($newPhoto) {
                $photoPath = $newPhoto;
                if ($m['photo'] && file_exists(__DIR__ . '/../' . $m['photo'])) {
                    @unlink(__DIR__ . '/../' . $m['photo']);
                }
            } else {
                $photoPath = $m['photo'];
            }
            $stmt = $db->prepare("UPDATE members SET name=?, contact=?, email=?, dob=?, gender=?, address=?, photo=? WHERE id=$id");
            $stmt->bind_param('sssssss', $name, $contact, $email, $dob, $gender, $address, $photoPath);
            if ($stmt->execute()) {
                $_SESSION['member_name'] = $name;
                $ok = 'Profile updated successfully.';
                // Reload member data
                $m = current_member();
            } else {
                $err = 'Update failed: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
}

/* Handle password change */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($current === '' || $new === '' || $confirm === '') {
        $err = 'All password fields are required.';
    } elseif (strlen($new) < 6) {
        $err = 'New password must be at least 6 characters long.';
    } elseif ($new !== $confirm) {
        $err = 'New password and confirmation do not match.';
    } elseif (!password_verify($current, $m['password'])) {
        $err = 'Your current password is incorrect.';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE members SET password=?, password_set_at=NOW() WHERE id=$id");
        $stmt->bind_param('s', $hash);
        if ($stmt->execute()) {
            $ok = 'Password changed successfully.';
            $m = current_member();
        } else {
            $err = 'Password change failed: ' . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<?php if ($ok): ?><div class="alert ok flash">&#10003; <?= e($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert err flash">&#9888; <?= e($err) ?></div><?php endif; ?>

<div class="page-head">
  <div><h2>My Profile</h2><p>Update your personal details and password.</p></div>
</div>

<div class="grid cols-2">
  <!-- Personal details form -->
  <div class="card">
    <div class="card-head"><h3>Personal Details</h3></div>
    <div class="card-body">
      <form method="post" data-validate enctype="multipart/form-data">
        <input type="hidden" name="action" value="update_profile">
        <div class="form-grid">
          <div class="form-field full">
            <label>Profile Photo</label>
            <div class="photo-current" style="margin-bottom:8px;">
              <?= photoImg($m['photo'], $m['name'], 'member-photo') ?>
              <?php if ($m['photo']): ?>
                <label class="photo-remove" style="display:inline-flex;align-items:center;gap:6px;margin-left:12px;">
                  <input type="checkbox" name="remove_photo" value="1"> Remove photo
                </label>
              <?php endif; ?>
            </div>
            <input type="file" name="photo" accept="image/jpeg,image/png,image/gif,image/webp">
            <span class="hint">JPG, PNG, GIF or WebP — max 5 MB.</span>
          </div>
          <div class="form-field">
            <label>Full Name <span class="req">*</span></label>
            <input type="text" name="name" required value="<?= e($m['name']) ?>">
          </div>
          <div class="form-field">
            <label>Contact Number</label>
            <input type="text" name="contact" value="<?= e($m['contact']) ?>">
          </div>
          <div class="form-field">
            <label>Email Address</label>
            <input type="email" name="email" value="<?= e($m['email']) ?>">
          </div>
          <div class="form-field">
            <label>Portal Username</label>
            <input type="text" value="<?= e($m['username'] ?? '—') ?>" disabled style="background:#f4f6f9;color:var(--muted);">
            <span class="hint">Your login username. Managed by gym staff — contact the front desk to change it.</span>
          </div>
          <div class="form-field">
            <label>Date of Birth</label>
            <input type="date" name="dob" max="<?= date('Y-m-d') ?>" value="<?= e($m['dob']) ?>">
          </div>
          <div class="form-field">
            <label>Gender</label>
            <select name="gender">
              <?php foreach (['Male','Female','Other'] as $g): ?>
                <option <?= $m['gender']===$g?'selected':'' ?>><?= $g ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-field">
            <label>Membership Plan</label>
            <input type="text" value="<?= e($m['plan_name'] ?: '—') ?>" disabled style="background:#f4f6f9;color:var(--muted);">
            <span class="hint">Managed by gym staff.</span>
          </div>
          <div class="form-field full">
            <label>Address</label>
            <textarea name="address"><?= e($m['address']) ?></textarea>
          </div>
        </div>
        <div class="form-actions" style="margin-top:16px;">
          <button type="submit" class="btn btn-primary">&#10003; Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Password change + read-only membership info -->
  <div>
    <div class="card" style="margin-bottom:18px;">
      <div class="card-head"><h3>Change Password</h3></div>
      <div class="card-body">
        <?php if (empty($m['password'])): ?>
          <div class="member-callout warn" style="margin-bottom:16px;">
            You don't have a password set yet. Set one below to enable your login. You'll sign in with your username + this password.
          </div>
          <form method="post" data-validate>
            <input type="hidden" name="action" value="change_password">
            <input type="hidden" name="current_password" value="">
            <div class="form-field" style="margin-bottom:14px;">
              <label>New Password <span class="req">*</span></label>
              <input type="password" name="new_password" required minlength="6" placeholder="At least 6 characters">
            </div>
            <div class="form-field" style="margin-bottom:14px;">
              <label>Confirm Password <span class="req">*</span></label>
              <input type="password" name="confirm_password" required placeholder="Re-type new password">
            </div>
            <button type="submit" class="btn btn-primary">Set Password</button>
          </form>
        <?php else: ?>
          <form method="post" data-validate>
            <input type="hidden" name="action" value="change_password">
            <div class="form-field" style="margin-bottom:14px;">
              <label>Current Password <span class="req">*</span></label>
              <input type="password" name="current_password" required placeholder="Your current password">
            </div>
            <div class="form-field" style="margin-bottom:14px;">
              <label>New Password <span class="req">*</span></label>
              <input type="password" name="new_password" required minlength="6" placeholder="At least 6 characters">
            </div>
            <div class="form-field" style="margin-bottom:14px;">
              <label>Confirm New Password <span class="req">*</span></label>
              <input type="password" name="confirm_password" required placeholder="Re-type new password">
            </div>
            <button type="submit" class="btn btn-primary">Change Password</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h3>Membership Info</h3></div>
      <div class="card-body" style="padding:0">
        <table class="data member-info-table">
          <tr><td class="k">Member ID</td><td>#<?= $m['id'] ?></td></tr>
          <tr><td class="k">Plan</td><td><?= e($m['plan_name'] ?: '—') ?></td></tr>
          <tr><td class="k">Plan Price</td><td><?= fmtMoney($m['price'], $cur) ?></td></tr>
          <tr><td class="k">Join Date</td><td><?= fmtDate($m['join_date']) ?></td></tr>
          <tr><td class="k">Expires On</td><td><?= fmtDate($m['expires_on']) ?></td></tr>
          <tr><td class="k">Status</td><td><?= $m['status']==='Active' ? '<span class="badge green">Active</span>' : '<span class="badge gray">Inactive</span>' ?></td></tr>
          <tr><td class="k">Trainer</td><td><?= e($m['trainer_name'] ?: '—') ?></td></tr>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
