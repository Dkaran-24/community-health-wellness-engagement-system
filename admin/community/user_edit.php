<?php
/**
 * admin/community/user_edit.php
 *
 * Dedicated Community User profile editor.
 *
 * Features:
 * - Edit resident profile
 * - Change password
 * - Change account status
 * - Admin-only access
 */

$PAGE_TITLE = 'Edit Community User';
$PAGE_KEY   = 'community-users';

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/community_auth.php';

require_login();

$db = db();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    header('Location: users.php?err=' . urlencode('Invalid community user ID.'));
    exit;
}

$errors = [];
$user   = null;

/* --------------------------------------------------------------- */
/* Load current user                                               */
/* --------------------------------------------------------------- */
$stmt = $db->prepare(
    "SELECT
        u.id,
        u.full_name,
        u.email,
        u.mobile,
        u.age,
        u.gender,
        u.address_area,
        u.password,
        u.fitness_level,
        u.fitness_goal,
        u.preferred_activities,
        u.status,
        u.created_at,

        (SELECT COUNT(*)
         FROM event_registrations r
         WHERE r.community_user_id = u.id
           AND r.status IN ('Registered','Attended')) AS registrations,

        (SELECT COUNT(*)
         FROM event_attendance a
         WHERE a.community_user_id = u.id
           AND a.status = 'Present') AS attended,

        (SELECT COUNT(*)
         FROM challenge_participants cp
         WHERE cp.community_user_id = u.id) AS challenges,

        (SELECT COUNT(*)
         FROM community_feedback f
         WHERE f.community_user_id = u.id) AS feedback,

        (SELECT COUNT(*)
         FROM community_requests cr
         WHERE cr.community_user_id = u.id) AS requests,

        (SELECT COALESCE(v.total_hours, 0)
         FROM volunteers v
         WHERE v.community_user_id = u.id
         ORDER BY v.id DESC
         LIMIT 1) AS volunteer_hours,

        (SELECT COUNT(*)
         FROM volunteers v
         WHERE v.community_user_id = u.id
           AND v.status = 'Active') AS volunteer_active

     FROM community_users u
     WHERE u.id = ?
     LIMIT 1"
);
$stmt->bind_param('i', $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header('Location: users.php?err=' . urlencode('Community user not found.'));
    exit;
}

/* --------------------------------------------------------------- */
/* Update profile                                                   */
/* --------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $full_name            = trim($_POST['full_name'] ?? '');
    $email                = trim($_POST['email'] ?? '');
    $mobile               = trim($_POST['mobile'] ?? '');
    $age                  = (int)($_POST['age'] ?? 0);
    $gender               = trim($_POST['gender'] ?? 'Other');
    $address_area         = trim($_POST['address_area'] ?? '');
    $fitness_level        = trim($_POST['fitness_level'] ?? 'Beginner');
    $fitness_goal         = trim($_POST['fitness_goal'] ?? 'General Fitness');
    $preferred_activities = trim($_POST['preferred_activities'] ?? 'Any');
    $status               = trim($_POST['status'] ?? 'Active');
    $new_password         = (string)($_POST['new_password'] ?? '');
    $confirm_password     = (string)($_POST['confirm_password'] ?? '');

    $allowedGenders = ['Male', 'Female', 'Other'];
    $allowedLevels  = ['Beginner', 'Intermediate', 'Advanced'];
    $allowedGoals   = ['General Fitness', 'Weight Loss', 'Strength', 'Flexibility', 'Endurance', 'Wellness'];
    $allowedStatus  = ['Active', 'Blocked'];

    if (mb_strlen($full_name) < 3 || mb_strlen($full_name) > 120) {
        $errors[] = 'Full name must be between 3 and 120 characters.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (!preg_match('/^\d{10,15}$/', $mobile)) {
        $errors[] = 'Mobile number must contain 10 to 15 digits.';
    }

    if ($age < 10 || $age > 100) {
        $errors[] = 'Age must be between 10 and 100.';
    }

    if (!in_array($gender, $allowedGenders, true)) {
        $errors[] = 'Please select a valid gender.';
    }

    if (mb_strlen($address_area) < 3 || mb_strlen($address_area) > 255) {
        $errors[] = 'Address / area must be between 3 and 255 characters.';
    }

    if (!in_array($fitness_level, $allowedLevels, true)) {
        $errors[] = 'Please select a valid fitness level.';
    }

    if (!in_array($fitness_goal, $allowedGoals, true)) {
        $errors[] = 'Please select a valid fitness goal.';
    }

    if (mb_strlen($preferred_activities) > 120) {
        $errors[] = 'Preferred activities must be 120 characters or fewer.';
    }

    if (!in_array($status, $allowedStatus, true)) {
        $errors[] = 'Please select a valid account status.';
    }

    if ($new_password !== '' || $confirm_password !== '') {
        if (strlen($new_password) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        }

        if ($new_password !== $confirm_password) {
            $errors[] = 'New password and confirm password do not match.';
        }
    }

    /* Uniqueness: email and mobile. */
    if (!$errors) {
        $stmt = $db->prepare(
            'SELECT id
             FROM community_users
             WHERE (email = ? OR mobile = ?)
               AND id <> ?
             LIMIT 1'
        );
        $stmt->bind_param('ssi', $email, $mobile, $id);
        $stmt->execute();
        $duplicate = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($duplicate) {
            $errors[] = 'That email address or mobile number is already used by another community account.';
        }
    }

    /* Preserve entered values if validation fails. */
    $user['full_name']            = $full_name;
    $user['email']                = $email;
    $user['mobile']               = $mobile;
    $user['age']                  = $age;
    $user['gender']               = $gender;
    $user['address_area']         = $address_area;
    $user['fitness_level']        = $fitness_level;
    $user['fitness_goal']         = $fitness_goal;
    $user['preferred_activities'] = $preferred_activities;
    $user['status']               = $status;

    if (!$errors) {
        $passwordHash = $user['password'];

        if ($new_password !== '') {
            $passwordHash = password_hash($new_password, PASSWORD_DEFAULT);
            if ($passwordHash === false) {
                $errors[] = 'The password could not be securely generated.';
            }
        }

        if (!$errors) {
            $stmt = $db->prepare(
                'UPDATE community_users
                 SET full_name = ?,
                     email = ?,
                     mobile = ?,
                     age = ?,
                     gender = ?,
                     address_area = ?,
                     password = ?,
                     fitness_level = ?,
                     fitness_goal = ?,
                     preferred_activities = ?,
                     status = ?
                 WHERE id = ?'
            );

            $stmt->bind_param(
                'sssisssssssi',
                $full_name,
                $email,
                $mobile,
                $age,
                $gender,
                $address_area,
                $passwordHash,
                $fitness_level,
                $fitness_goal,
                $preferred_activities,
                $status,
                $id
            );

            try {
                $stmt->execute();
                $stmt->close();

                $message = 'Profile updated successfully for "' . $full_name . '".';
                if ($new_password !== '') {
                    $message .= ' Password changed successfully.';
                }

                header('Location: users.php?ok=' . urlencode($message));
                exit;
            } catch (mysqli_sql_exception $ex) {
                $stmt->close();
                $errors[] = 'The profile could not be updated because of a database error.';
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
/* ================================================================
   COMMUNITY USER EDIT - MODERN PROFILE UI
   ================================================================ */
.user-edit-page {
    --ue-ink: #17372d;
    --ue-ink-2: #335449;
    --ue-muted: #73847d;
    --ue-green: #17624d;
    --ue-green-dark: #0e4939;
    --ue-soft: #eef7f3;
    --ue-soft-2: #f8fbf9;
    --ue-line: #dce8e2;
    --ue-danger: #a42c24;
    --ue-danger-bg: #fff1ef;
    --ue-shadow: 0 12px 30px rgba(20, 54, 43, .08);
    max-width: 1120px;
    margin: 0 auto;
}

.ue-topbar {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:16px;
    margin-bottom:16px;
}

.ue-back {
    display:inline-flex;
    align-items:center;
    gap:7px;
    border:1px solid var(--ue-line);
    background:#fff;
    color:var(--ue-ink-2);
    text-decoration:none;
    border-radius:10px;
    padding:10px 13px;
    font-size:12.5px;
    font-weight:800;
}

.ue-back:hover { background:var(--ue-soft-2); }

.ue-id {
    color:var(--ue-muted);
    font-size:12px;
    font-weight:700;
}

.ue-hero {
    background:linear-gradient(135deg,#0e3b30 0%,#17624d 58%,#2b7d65 100%);
    color:#fff;
    border-radius:22px;
    padding:26px;
    margin-bottom:18px;
    box-shadow:0 16px 35px rgba(14,59,48,.18);
}

.ue-hero-row {
    display:flex;
    align-items:center;
    gap:17px;
}

.ue-avatar {
    width:72px;
    height:72px;
    flex:0 0 72px;
    display:grid;
    place-items:center;
    border-radius:20px;
    background:rgba(255,255,255,.13);
    border:1px solid rgba(255,255,255,.18);
    font-size:28px;
    font-weight:900;
}

.ue-kicker {
    font-size:10px;
    font-weight:800;
    letter-spacing:.14em;
    text-transform:uppercase;
    opacity:.76;
    margin-bottom:4px;
}

.ue-hero h2 {
    color:#fff;
    margin:0;
    font-size:28px;
}

.ue-hero p {
    margin:6px 0 0;
    color:rgba(255,255,255,.83);
    font-size:12.5px;
}

.ue-layout {
    display:grid;
    grid-template-columns:minmax(0,1fr);
    gap:18px;
    align-items:start;
}

.ue-card {
    background:#fff;
    border:1px solid var(--ue-line);
    border-radius:17px;
    box-shadow:var(--ue-shadow);
    overflow:hidden;
    margin-bottom:18px;
}

.ue-card-head {
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:12px;
    padding:17px 20px;
    border-bottom:1px solid var(--ue-line);
    background:linear-gradient(180deg,#fbfdfc,#f8fbf9);
}

.ue-card-head h3 {
    margin:0;
    font-size:17px;
    color:var(--ue-ink);
}

.ue-card-head p {
    margin:4px 0 0;
    color:var(--ue-muted);
    font-size:11.5px;
}

.ue-card-body { padding:20px; }

.ue-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:16px;
}

.ue-field {
    display:flex;
    flex-direction:column;
    gap:7px;
}

.ue-field.full { grid-column:1/-1; }

.ue-field label {
    color:var(--ue-ink-2);
    font-size:12px;
    font-weight:800;
}

.ue-field input,
.ue-field select,
.ue-field textarea {
    width:100%;
    border:1px solid #cbdad3;
    border-radius:10px;
    padding:11px 12px;
    background:#fbfdfc;
    color:#233b32;
    font:inherit;
    outline:none;
    transition:.16s ease;
}

.ue-field input:focus,
.ue-field select:focus,
.ue-field textarea:focus {
    border-color:#5a957f;
    background:#fff;
    box-shadow:0 0 0 3px rgba(90,149,127,.12);
}

.ue-field textarea {
    min-height:84px;
    resize:vertical;
}

.ue-help {
    color:#7d8e87;
    font-size:10.5px;
    line-height:1.45;
}

.ue-password {
    margin-top:18px;
    border:1px solid #d3e6dc;
    border-radius:13px;
    padding:16px;
    background:linear-gradient(180deg,#f6fbf8,#eef7f3);
}

.ue-password-title {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    margin-bottom:13px;
}

.ue-password-title strong {
    color:var(--ue-ink);
    font-size:14px;
}

.ue-password-title span {
    color:var(--ue-muted);
    font-size:10.5px;
}

.ue-password-grid {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:14px;
}

.ue-password-wrap { position:relative; }

.ue-password-wrap input { padding-right:72px; }

.ue-password-toggle {
    position:absolute;
    right:8px;
    top:50%;
    transform:translateY(-50%);
    border:0;
    background:transparent;
    color:var(--ue-green);
    font-size:11px;
    font-weight:800;
    cursor:pointer;
}

.ue-alert {
    border-radius:12px;
    padding:13px 15px;
    margin-bottom:18px;
}

.ue-alert-error {
    background:var(--ue-danger-bg);
    border:1px solid #edc8c4;
    color:#8d2e27;
}

.ue-alert-error strong { display:block; margin-bottom:5px; }
.ue-alert-error ul { margin:4px 0 0 17px; padding:0; }

.ue-actions {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    padding:16px 20px;
    border-top:1px solid var(--ue-line);
    background:#f9fcfa;
}

.ue-actions-left,
.ue-actions-right {
    display:flex;
    gap:9px;
    align-items:center;
}

.ue-btn {
    display:inline-flex;
    justify-content:center;
    align-items:center;
    gap:7px;
    min-height:38px;
    padding:10px 14px;
    border-radius:10px;
    border:1px solid transparent;
    text-decoration:none;
    font:800 12px/1 Arial,sans-serif;
    cursor:pointer;
}

.ue-btn-primary { background:var(--ue-green); color:#fff; }
.ue-btn-primary:hover { background:var(--ue-green-dark); }
.ue-btn-secondary { background:#fff; color:var(--ue-ink-2); border-color:var(--ue-line); }
.ue-btn-secondary:hover { background:var(--ue-soft-2); }

@media (max-width: 700px) {
    .ue-grid,
    .ue-password-grid { grid-template-columns:1fr; }
    .ue-field.full { grid-column:auto; }
    .ue-actions { flex-direction:column; align-items:stretch; }
    .ue-actions-left,
    .ue-actions-right { width:100%; }
    .ue-actions .ue-btn { flex:1; }
    .ue-hero-row { align-items:flex-start; }
}
</style>

<div class="user-edit-page">

    <div class="ue-topbar">
        <a href="users.php" class="ue-back">← Back to Community Users</a>
        <div class="ue-id">Community User #<?= (int)$user['id'] ?></div>
    </div>

    <section class="ue-hero">
        <div class="ue-hero-row">
            <div class="ue-avatar">
                <?= e(strtoupper(mb_substr(trim($user['full_name']), 0, 1)) ?: '?') ?>
            </div>
            <div>
                <div class="ue-kicker">Community Profile</div>
                <h2><?= e($user['full_name']) ?></h2>
                <p><?= e($user['email']) ?> · <?= e($user['address_area']) ?></p>
            </div>
        </div>
    </section>

    <?php if ($errors): ?>
        <div class="ue-alert ue-alert-error">
            <strong>Please correct the following:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="ue-layout">

        <div>
            <form method="post" id="community-user-form">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">

                <section class="ue-card">
                    <div class="ue-card-head">
                        <div>
                            <h3>Personal Information</h3>
                            <p>Update the resident's main profile details.</p>
                        </div>
                    </div>

                    <div class="ue-card-body">
                        <div class="ue-grid">
                            <div class="ue-field">
                                <label for="full_name">Full Name</label>
                                <input id="full_name" name="full_name" type="text" maxlength="120" required value="<?= e($user['full_name']) ?>">
                            </div>

                            <div class="ue-field">
                                <label for="email">Email Address</label>
                                <input id="email" name="email" type="email" maxlength="160" required value="<?= e($user['email']) ?>">
                            </div>

                            <div class="ue-field">
                                <label for="mobile">Mobile Number</label>
                                <input id="mobile" name="mobile" type="text" inputmode="numeric" maxlength="15" required value="<?= e($user['mobile']) ?>">
                            </div>

                            <div class="ue-field">
                                <label for="age">Age</label>
                                <input id="age" name="age" type="number" min="10" max="100" required value="<?= e($user['age']) ?>">
                            </div>

                            <div class="ue-field">
                                <label for="gender">Gender</label>
                                <select id="gender" name="gender">
                                    <?php foreach (['Male','Female','Other'] as $g): ?>
                                        <option value="<?= e($g) ?>" <?= $user['gender'] === $g ? 'selected' : '' ?>><?= e($g) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="ue-field">
                                <label for="status">Account Status</label>
                                <select id="status" name="status">
                                    <option value="Active" <?= $user['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
                                    <option value="Blocked" <?= $user['status'] === 'Blocked' ? 'selected' : '' ?>>Blocked</option>
                                </select>
                            </div>

                            <div class="ue-field full">
                                <label for="address_area">Address / Area</label>
                                <input id="address_area" name="address_area" type="text" maxlength="255" required value="<?= e($user['address_area']) ?>">
                            </div>
                        </div>
                    </div>
                </section>

                <section class="ue-card">
                    <div class="ue-card-head">
                        <div>
                            <h3>Fitness &amp; Activity Preferences</h3>
                            <p>These values are used to personalize community recommendations.</p>
                        </div>
                    </div>

                    <div class="ue-card-body">
                        <div class="ue-grid">
                            <div class="ue-field">
                                <label for="fitness_level">Fitness Level</label>
                                <select id="fitness_level" name="fitness_level">
                                    <?php foreach (['Beginner','Intermediate','Advanced'] as $level): ?>
                                        <option value="<?= e($level) ?>" <?= $user['fitness_level'] === $level ? 'selected' : '' ?>><?= e($level) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="ue-field">
                                <label for="fitness_goal">Fitness Goal</label>
                                <select id="fitness_goal" name="fitness_goal">
                                    <?php foreach (['General Fitness','Weight Loss','Strength','Flexibility','Endurance','Wellness'] as $goal): ?>
                                        <option value="<?= e($goal) ?>" <?= $user['fitness_goal'] === $goal ? 'selected' : '' ?>><?= e($goal) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="ue-field full">
                                <label for="preferred_activities">Preferred Activities</label>
                                <textarea id="preferred_activities" name="preferred_activities" maxlength="120"><?= e($user['preferred_activities']) ?></textarea>
                                <div class="ue-help">Example: Yoga, Walking, Zumba, Nutrition Workshops</div>
                            </div>
                        </div>

                        <div class="ue-password">
                            <div class="ue-password-title">
                                <strong>🔐 Change Login Password</strong>
                                <span>Leave both fields blank to keep the current password.</span>
                            </div>

                            <div class="ue-password-grid">
                                <div class="ue-field">
                                    <label for="new_password">New Password</label>
                                    <div class="ue-password-wrap">
                                        <input id="new_password" name="new_password" type="password" minlength="8" autocomplete="new-password" placeholder="Minimum 8 characters">
                                        <button class="ue-password-toggle" type="button" data-target="new_password">Show</button>
                                    </div>
                                </div>

                                <div class="ue-field">
                                    <label for="confirm_password">Confirm Password</label>
                                    <div class="ue-password-wrap">
                                        <input id="confirm_password" name="confirm_password" type="password" minlength="8" autocomplete="new-password" placeholder="Re-enter password">
                                        <button class="ue-password-toggle" type="button" data-target="confirm_password">Show</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ue-actions">
                        <div class="ue-actions-left">
                            <a href="users.php" class="ue-btn ue-btn-secondary">Cancel</a>
                        </div>
                        <div class="ue-actions-right">
                            <button type="submit" class="ue-btn ue-btn-primary">💾 Save Profile Changes</button>
                        </div>
                    </div>
                </section>
            </form>
        </div>


    </div>
</div>
<script src="<?= $base ?>/assets/js/user_edit.js" defer></script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
