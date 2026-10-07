<?php
/**
 * community/requests.php — Community Requests (submit + track).
 *
 * Residents propose new activities. Workflow:
 *   Submitted → Under Review → Approved → Scheduled → Completed
 *   (or Rejected at review stage)
 * When a request is converted to a real event by organisers, the
 * scheduled event is linked here automatically.
 */
$PAGE_TITLE = 'Community Requests';
$PAGE_KEY   = 'requests';
require_once __DIR__ . '/../includes/community_auth.php';
require_community_login();
require_once __DIR__ . '/../db_connect.php';   /* DB needed by the POST handler, which runs before the layout */

$db  = db();
$uid = (int)$_SESSION['community_user_id'];

$types = ['Yoga Sessions','Fitness Camp','Health Camp','Nutrition Workshop','Senior Fitness Program','Women Wellness Program','Other'];
$statusStyle = [
    'Submitted'     => 'gray',
    'Under Review'  => 'gold',
    'Approved'      => 'steel',
    'Scheduled'     => 'blue',
    'Completed'     => 'green',
    'Rejected'      => 'red',
];

/* ---------- POST: new request ---------- */
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $rtype = $_POST['request_type'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $desc  = trim($_POST['description'] ?? '');

    if (!in_array($rtype, $types, true)) $errors[] = 'Please choose a valid request type.';
    if (mb_strlen($title) < 5 || mb_strlen($title) > 160) $errors[] = 'Title must be 5-160 characters.';
    if (mb_strlen($desc) < 10) $errors[] = 'Please describe your request in at least 10 characters.';
    elseif (mb_strlen($desc) > 2000) $errors[] = 'Description is limited to 2000 characters.';

    /* gentle rate limit: max 5 open requests per user */
    $open = (int)$db->query("SELECT COUNT(*) c FROM community_requests WHERE community_user_id=$uid AND status NOT IN ('Completed','Rejected')")->fetch_assoc()['c'];
    if ($open >= 5) $errors[] = 'You already have 5 open requests. Please wait until some are completed.';

    if (!$errors) {
        $stmt = $db->prepare("INSERT INTO community_requests (community_user_id, request_type, title, description)
                VALUES (?, ?, ?, ?)");
        $stmt->bind_param('isss', $uid, $rtype, $title, $desc);
        $stmt->execute(); $stmt->close();
        header('Location: requests.php?ok=' . urlencode('Request submitted! Organisers will review it and update the status here.'));
        exit;
    }
}

/* ---------- layout header (renders after the POST handler; unreachable when the POST handler redirects) ---------- */
require_once __DIR__ . '/_header.php';

/* ---------- my requests ---------- */
$stmt = $db->prepare(
    "SELECT r.*, e.event_name AS conv_event, e.event_date AS conv_date, e.location AS conv_loc
     FROM community_requests r
     LEFT JOIN community_events e ON e.id = r.converted_event_id
     WHERE r.community_user_id = ?
     ORDER BY r.created_at DESC"
);
$stmt->bind_param('i', $uid); $stmt->execute();
$mine = $stmt->get_result(); $stmt->close();
$myCount = $mine ? $mine->num_rows : 0;
$mineArr = $mine ? $mine->fetch_all(MYSQLI_ASSOC) : [];

/* community-wide stats (transparency) */
$stats = $db->query("SELECT status, COUNT(*) c FROM community_requests GROUP BY status");
$statMap = [];
if ($stats) while ($r = $stats->fetch_assoc()) $statMap[$r['status']] = (int)$r['c'];
$totalReq = array_sum($statMap);
?>
<div class="c-hero small">
  <span class="c-tag">HAVE AN IDEA?</span>
  <h1>Community Requests</h1>
  <p>Want a yoga batch in your park, a health camp in your society, or a nutrition workshop
     for your street? Request it here — organisers review every submission and turn the most
     requested ideas into real events.</p>
</div>

<div class="c-grid cols-2" style="margin-bottom:22px;align-items:start;">
  <!-- ===== submit form ===== -->
  <div class="c-card">
    <h3 style="margin-top:0;">&#128172; Submit a New Request</h3>
    <?php if ($errors): ?>
      <div class="c-alert err"><?php foreach ($errors as $er) echo '&#9888; ' . e($er) . '<br>'; ?></div>
    <?php endif; ?>
    <form method="post" class="c-form-grid">
      <?= csrf_field() ?>
      <div class="c-field">
        <label>What are you requesting? <b class="c-red-star">*</b></label>
        <select name="request_type" required>
          <option value="">— Choose type —</option>
          <?php foreach ($types as $t): ?>
            <option value="<?= $t ?>" <?= (($_POST['request_type'] ?? '') === $t) ? 'selected' : '' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="c-field">
        <label>Title <b class="c-red-star">*</b></label>
        <input type="text" name="title" maxlength="160" required value="<?= e($_POST['title'] ?? '') ?>"
               placeholder="e.g. Morning yoga sessions at Central Park">
      </div>
      <div class="c-field">
        <label>Describe your request <b class="c-red-star">*</b></label>
        <textarea name="description" rows="4" maxlength="2000" required
                  placeholder="Who would benefit, preferred days/timing, a suitable location and why it matters to your neighbourhood..."><?= e($_POST['description'] ?? '') ?></textarea>
      </div>
      <button class="c-btn gold" type="submit">Submit Request</button>
    </form>
  </div>

  <!-- ===== how it works ===== -->
  <div class="c-card">
    <h3 style="margin-top:0;">&#128736; How Requests Progress</h3>
    <div class="c-tl">
      <div class="tl-item"><div class="d">STEP 1</div><div class="t">Submitted</div><div style="font-size:13px;color:var(--muted);">Your idea reaches the organisers.</div></div>
      <div class="tl-item"><div class="d">STEP 2</div><div class="t">Under Review</div><div style="font-size:13px;color:var(--muted);">Feasibility, trainer &amp; venue availability are checked.</div></div>
      <div class="tl-item"><div class="d">STEP 3</div><div class="t">Approved</div><div style="font-size:13px;color:var(--muted);">The activity is approved for scheduling.</div></div>
      <div class="tl-item"><div class="d">STEP 4</div><div class="t">Scheduled</div><div style="font-size:13px;color:var(--muted);">A real event is created and linked to your request.</div></div>
      <div class="tl-item"><div class="d">STEP 5</div><div class="t">Completed</div><div style="font-size:13px;color:var(--muted);">The activity was conducted. Look out for the next one!</div></div>
    </div>
    <div style="margin-top:12px;font-size:12.5px;color:var(--muted);">
      Community-wide request status: <b><?= $statMap['Submitted'] ?? 0 ?></b> submitted &middot;
      <b><?= $statMap['Under Review'] ?? 0 ?></b> under review &middot;
      <b><?= $statMap['Approved'] ?? 0 ?></b> approved &middot;
      <b><?= ($statMap['Scheduled'] ?? 0) + ($statMap['Completed'] ?? 0) ?></b> scheduled/completed
      (of <?= $totalReq ?> total).
    </div>
  </div>
</div>

<!-- ===== my requests list ===== -->
<div class="c-card">
  <h3 style="margin-top:0;">&#128220; My Requests (<?= $myCount ?>)</h3>
  <?php if (!$mineArr): ?>
    <p class="c-muted">You have not submitted any requests yet. Use the form above to propose an activity!</p>
  <?php else: ?>
    <?php foreach ($mineArr as $r): ?>
      <div class="c-statusline" style="align-items:flex-start;">
        <div style="flex:1;">
          <b><?= e($r['title']) ?></b>
          <span class="c-badge blue"><?= e($r['request_type']) ?></span>
          <span class="c-badge <?= $statusStyle[$r['status']] ?? 'gray' ?>"><?= e(strtoupper($r['status'])) ?></span>
          <div class="c-muted" style="font-size:12.5px;margin:4px 0;">Submitted <?= date('M j, Y', strtotime($r['created_at'])) ?></div>
          <div style="font-size:13px;"><?= e($r['description']) ?></div>
          <?php if ($r['admin_note']): ?>
            <div style="font-size:12.5px;margin-top:6px;padding:6px 10px;background:#fdf6e7;border-radius:8px;">
              <b>Organiser note:</b> <?= e($r['admin_note']) ?></div>
          <?php endif; ?>
          <?php if ($r['converted_event_id']): ?>
            <div style="font-size:12.5px;margin-top:6px;">
              &#128197; Scheduled as event: <b><?= e($r['conv_event']) ?></b>
              <?= $r['conv_date'] ? ' &middot; ' . fmtDate($r['conv_date']) : '' ?>
              <?= $r['conv_loc'] ? ' &middot; ' . e($r['conv_loc']) : '' ?> &mdash;
              <a href="events.php#event-<?= (int)$r['converted_event_id'] ?>">view event &rarr;</a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
