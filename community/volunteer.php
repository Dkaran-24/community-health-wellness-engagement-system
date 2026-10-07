<?php
/**
 * community/volunteer.php — Become a Volunteer (application form) + status.
 * Application → admin review (Approved/Rejected) → volunteer record.
 */
$PAGE_TITLE = 'Become a Volunteer';
$PAGE_KEY   = 'volunteer';
require_once __DIR__ . '/../includes/community_auth.php';
require_community_login();
require_once __DIR__ . '/../db_connect.php';   /* DB needed by the POST handler, which runs before the layout */

$db  = db();
$uid = (int)$_SESSION['community_user_id'];

/* existing application / volunteer record */
$stmt = $db->prepare("SELECT * FROM volunteer_applications WHERE community_user_id = ? LIMIT 1");
$stmt->bind_param('i', $uid); $stmt->execute();
$app = $stmt->get_result()->fetch_assoc(); $stmt->close();
$volId = community_is_volunteer($uid);
$errors = [];

/* ---- POST: submit application ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$app && !$volId) {
    csrf_require();
    $skills   = trim($_POST['skills'] ?? '');
    $interest = trim($_POST['interest_areas'] ?? '');
    $avail    = trim($_POST['availability'] ?? '');
    $exp      = trim($_POST['previous_experience'] ?? '');
    $reason   = trim($_POST['reason'] ?? '');

    if (mb_strlen($skills) < 3 || mb_strlen($skills) > 255)   $errors[] = 'Please list your skills (3-255 characters).';
    if (mb_strlen($interest) < 3 || mb_strlen($interest) > 255) $errors[] = 'Please tell us your interest areas (3-255 characters).';
    if (mb_strlen($avail) < 3 || mb_strlen($avail) > 120)     $errors[] = 'Please describe your availability (e.g. Weekends, mornings).';
    if (mb_strlen($exp) > 255)     $errors[] = 'Previous experience is limited to 255 characters.';
    if (mb_strlen($reason) > 1000) $errors[] = 'Reason is limited to 1000 characters.';

    if (!$errors) {
        $stmt = $db->prepare("INSERT INTO volunteer_applications
                (community_user_id, skills, interest_areas, availability, previous_experience, reason)
                VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('isssss', $uid, $skills, $interest, $avail, $exp, $reason);
        $stmt->execute(); $stmt->close();
        header('Location: volunteer.php?ok=' . urlencode('Application submitted! The organisers will review it and notify you.'));
        exit;
    }
}

/* ---------- layout header (renders after the POST handler; unreachable when the POST handler redirects) ---------- */
require_once __DIR__ . '/_header.php';
?>
<div class="c-hero small">
  <span class="c-tag">VOLUNTEER PROGRAM</span>
  <h1>Become a Community Volunteer</h1>
  <p>Help run free community health &amp; fitness activities — guide participants, manage
     registrations, support trainers and earn recognised volunteer hours.</p>
</div>

<?php if ($volId): ?>
  <div class="c-card">
    <span class="c-badge green">&#127891; APPROVED VOLUNTEER</span>
    <p class="c-muted">Your volunteer application was approved. Head to your volunteer dashboard
       to see assignments and log hours.</p>
    <a class="c-btn gold" href="volunteer-dashboard.php">Open Volunteer Dashboard &rarr;</a>
  </div>

<?php elseif ($app): ?>
  <div class="c-card">
    <h3 style="margin-top:0;">My Application #<?= (int)$app['id'] ?></h3>
    <div class="c-statusline">
      <div><b>Status:</b>
        <?php if ($app['status'] === 'Approved'): ?><span class="c-badge green">APPROVED</span>
        <?php elseif ($app['status'] === 'Rejected'): ?><span class="c-badge red">REJECTED</span>
        <?php else: ?><span class="c-badge gold">PENDING REVIEW</span><?php endif; ?>
      </div>
      <div class="c-muted">Submitted <?= date('M j, Y', strtotime($app['created_at'])) ?></div>
    </div>
    <div class="c-meta" style="display:grid;gap:8px;font-size:13.5px;">
      <div><b>Skills:</b> <?= e($app['skills']) ?></div>
      <div><b>Interest areas:</b> <?= e($app['interest_areas']) ?></div>
      <div><b>Availability:</b> <?= e($app['availability']) ?></div>
      <?php if ($app['previous_experience']): ?><div><b>Experience:</b> <?= e($app['previous_experience']) ?></div><?php endif; ?>
      <?php if ($app['reason']): ?><div><b>Reason:</b> <?= e($app['reason']) ?></div><?php endif; ?>
    </div>
    <?php if ($app['status'] === 'Pending'): ?>
      <p class="c-muted" style="font-size:12.5px;">Your application is awaiting organiser review. This page updates automatically once reviewed.</p>
    <?php elseif ($app['status'] === 'Rejected'): ?>
      <p class="c-muted" style="font-size:12.5px;">Unfortunately this application was not approved
        <?php if ($app['reviewed_at']): ?>on <?= date('M j, Y', strtotime($app['reviewed_at'])) ?><?php endif; ?>.
        You may contact the organisers if you would like to discuss it.</p>
    <?php endif; ?>
  </div>

<?php else: ?>
  <?php if ($errors): ?>
    <div class="c-alert err"><?php foreach ($errors as $er) echo '&#9888; ' . e($er) . '<br>'; ?></div>
  <?php endif; ?>
  <div class="c-card" style="max-width:760px;">
    <form method="post" class="c-form-grid">
      <?= csrf_field() ?>
      <div class="c-field">
        <label>Skills you can contribute <b class="c-red-star">*</b></label>
        <input type="text" name="skills" maxlength="255" required
               value="<?= e($_POST['skills'] ?? '') ?>" placeholder="e.g. Crowd management, first aid, photography, teaching yoga">
      </div>
      <div class="c-field">
        <label>Interest areas <b class="c-red-star">*</b></label>
        <input type="text" name="interest_areas" maxlength="255" required
               value="<?= e($_POST['interest_areas'] ?? '') ?>" placeholder="e.g. Yoga events, health camps, senior fitness, registration desk">
      </div>
      <div class="c-field">
        <label>Availability <b class="c-red-star">*</b></label>
        <input type="text" name="availability" maxlength="120" required
               value="<?= e($_POST['availability'] ?? '') ?>" placeholder="e.g. Weekends and early mornings">
      </div>
      <div class="c-field">
        <label>Previous volunteer experience (optional)</label>
        <input type="text" name="previous_experience" maxlength="255"
               value="<?= e($_POST['previous_experience'] ?? '') ?>" placeholder="e.g. Helped at a local marathon in 2024">
      </div>
      <div class="c-field">
        <label>Why do you want to volunteer? (optional)</label>
        <textarea name="reason" rows="4" maxlength="1000"
                  placeholder="Tell us a little about your motivation..."><?= e($_POST['reason'] ?? '') ?></textarea>
      </div>
      <button class="c-btn gold" type="submit">&#129309; Submit Application</button>
    </form>
    <p class="c-muted" style="font-size:12px;margin-top:12px;">
      Applications are reviewed by the community organisers. Once approved, a Volunteer Dashboard
      appears in your menu with assignments and an hours log.
    </p>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/_footer.php'; ?>
