<?php
/**
 * community/event-feedback.php — Give feedback for an attended/completed event.
 *
 * One feedback per user per event (UNIQUE constraint + server checks).
 * On submission the AI feedback analysis prototype (lexicon-based
 * sentiment + topics) automatically classifies the text and stores
 * sentiment/topics for the admin analytics view.
 */
$PAGE_TITLE = 'Event Feedback';
$PAGE_KEY   = 'feedback';
require_once __DIR__ . '/../includes/community_auth.php';
require_once __DIR__ . '/../includes/ai_feedback.php';
require_community_login();
require_once __DIR__ . '/_header.php';

$db  = db();
$uid = (int)$_SESSION['community_user_id'];
$eid = (int)($_GET['event_id'] ?? 0);

/* ---- fetch event ---- */
$stmt = $db->prepare("SELECT * FROM community_events WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $eid);
$stmt->execute();
$ev = $stmt->get_result()->fetch_assoc();
$stmt->close();

$notFound   = !$ev;
$alreadyFb  = false;
$eligible   = false;
$reason     = '';

if ($ev) {
    $today = date('Y-m-d');
    $isPast = ($ev['event_date'] < $today || $ev['status'] === 'Completed');

    /* already submitted? */
    $stmt = $db->prepare("SELECT id FROM community_feedback WHERE community_user_id = ? AND event_id = ? LIMIT 1");
    $stmt->bind_param('ii', $uid, $eid);
    $stmt->execute();
    $fbRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $alreadyFb = (bool)$fbRow;

    /* registered or attendance record? */
    $stmt = $db->prepare("SELECT status FROM event_registrations WHERE community_user_id = ? AND event_id = ? LIMIT 1");
    $stmt->bind_param('ii', $uid, $eid);
    $stmt->execute();
    $reg = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $db->prepare("SELECT status FROM event_attendance WHERE community_user_id = ? AND event_id = ? LIMIT 1");
    $stmt->bind_param('ii', $uid, $eid);
    $stmt->execute();
    $att = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$isPast) { $reason = 'Feedback opens after the event is completed.'; }
    elseif ($reg === null && $att === null) { $reason = 'You were not registered for this event, so feedback is not available.'; }
    elseif (($reg['status'] ?? '') === 'Cancelled' && ($att['status'] ?? '') !== 'Present') { $reason = 'Your registration was cancelled — feedback is not available.'; }
    else { $eligible = true; }
}

/* ---- handle POST ---- */
$errors = [];
/* POST while a feedback already exists -> PRG redirect (form card stays for GET) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $alreadyFb && $ev) {
    header('Location: my-events.php?warn=' . urlencode('You have already given feedback for this event.'));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $eligible && !$alreadyFb) {
    csrf_require();

    $rating = (int)($_POST['rating'] ?? 0);
    $satisfaction = $_POST['satisfaction'] ?? '';
    $comments = trim($_POST['comments'] ?? '');
    $suggestions = trim($_POST['suggestions'] ?? '');
    $again = (($_POST['would_attend_again'] ?? '') === 'No') ? 'No' : 'Yes';

    $validSat = ['Very Satisfied','Satisfied','Neutral','Dissatisfied','Very Dissatisfied'];
    if ($rating < 1 || $rating > 5) $errors[] = 'Please choose a star rating from 1 to 5.';
    if (!in_array($satisfaction, $validSat, true)) $errors[] = 'Please choose your satisfaction level.';
    if ($comments === '' ) $errors[] = 'Please write a few words about your experience.';
    elseif (mb_strlen($comments) > 1000) $errors[] = 'Comments are limited to 1000 characters.';
    if (mb_strlen($suggestions) > 500) $errors[] = 'Suggestions are limited to 500 characters.';

    if (!$errors) {
        /* one per user+event (UNIQUE backstop) */
        $stmt = $db->prepare("SELECT id FROM community_feedback WHERE community_user_id = ? AND event_id = ? LIMIT 1");
        $stmt->bind_param('ii', $uid, $eid);
        $stmt->execute();
        $dup = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($dup) {
            header('Location: my-events.php?warn=' . urlencode('You have already given feedback for this event.'));
            exit;
        }

        $stmt = $db->prepare("INSERT INTO community_feedback
                (event_id, community_user_id, rating, satisfaction, comments, suggestions, would_attend_again)
                VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('iiissss', $eid, $uid, $rating, $satisfaction, $comments, $suggestions, $again);
        $stmt->execute();
        $fbId = $stmt->insert_id;
        $stmt->close();

        /* ---- AI (lexicon prototype) sentiment + topics ---- */
        $sentiment = ai_apply_feedback_analysis($fbId);

        header('Location: my-events.php?ok=' . urlencode('Thank you! Your feedback was recorded'
            . ($sentiment ? ' and auto-classified as ' . $sentiment . ' by our feedback analyser (prototype)' : '')
            . '.'));
        exit;
    }
}
?>
<div class="c-hero small">
  <span class="c-tag">EVENT FEEDBACK</span>
  <h1>Feedback — <?= $notFound ? 'Unknown Event' : e($ev['event_name']) ?></h1>
  <p>Your feedback helps organisers improve community activities. It takes less than a minute.</p>
</div>

<?php if ($notFound): ?>
<div class="c-card"><p class="c-muted">Event not found. <a href="events.php">Back to events &rarr;</a></p></div>

<?php elseif ($alreadyFb): ?>
<div class="c-card">
  <span class="c-badge green">&#11088; FEEDBACK ALREADY SUBMITTED</span>
  <p class="c-muted">Thanks for sharing your experience — only one feedback per event is allowed.</p>
  <a class="c-btn" href="my-events.php">Back to My Registrations &rarr;</a>
</div>

<?php elseif (!$eligible): ?>
<div class="c-card">
  <span class="c-badge gray">NOT AVAILABLE</span>
  <p class="c-muted"><?= e($reason) ?></p>
  <a class="c-btn" href="events.php">Back to events &rarr;</a>
</div>

<?php else: ?>
<div class="c-card" style="max-width:760px;">
  <div class="c-item" style="margin-bottom:14px;">
    <h4><?= e($ev['event_name']) ?></h4>
    <div class="c-meta">
      <div><b>&#128197;</b> <?= fmtDate($ev['event_date']) ?> &middot; <?= substr($ev['start_time'],0,5) ?>–<?= substr($ev['end_time'],0,5) ?></div>
      <div><b>&#128205;</b> <?= e($ev['location']) ?></div>
    </div>
  </div>

  <?php if ($errors): ?>
    <div class="c-alert err">
      <?php foreach ($errors as $er) echo '&#9888; ' . e($er) . '<br>'; ?>
    </div>
  <?php endif; ?>

  <form method="post" action="event-feedback.php?event_id=<?= $eid ?>" class="c-form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="event_id" value="<?= $eid ?>">

    <div class="c-field">
      <label>Overall rating <b class="red">*</b></label>
      <div class="rating-row">
        <?php for ($i = 1; $i <= 5; $i++): ?>
          <label class="rate-opt"><input type="radio" name="rating" value="<?= $i ?>"
              <?= (int)($_POST['rating'] ?? 0) === $i ? 'checked' : '' ?>>
            <?= str_repeat('&#11088;', $i) ?></label>
        <?php endfor; ?>
      </div>
    </div>

    <div class="c-field">
      <label>Satisfaction level <b class="red">*</b></label>
      <select name="satisfaction" required>
        <option value="">— Choose —</option>
        <?php foreach (['Very Satisfied','Satisfied','Neutral','Dissatisfied','Very Dissatisfied'] as $s): ?>
          <option value="<?= $s ?>" <?= (($_POST['satisfaction'] ?? '') === $s) ? 'selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="c-field">
      <label>Your experience — what went well / what could improve? <b class="red">*</b></label>
      <textarea name="comments" rows="5" maxlength="1000" required placeholder="e.g. The trainer was excellent and the morning timing suited me, but the hall was a bit crowded..."><?= e($_POST['comments'] ?? '') ?></textarea>
    </div>

    <div class="c-field">
      <label>Suggestions for future events (optional)</label>
      <textarea name="suggestions" rows="3" maxlength="500" placeholder="e.g. Please organise this every month and add a short meditation session."><?= e($_POST['suggestions'] ?? '') ?></textarea>
    </div>

    <div class="c-field">
      <label>Would you attend a similar event again?</label>
      <div class="rating-row">
        <label class="rate-opt"><input type="radio" name="would_attend_again" value="Yes" checked> Yes</label>
        <label class="rate-opt"><input type="radio" name="would_attend_again" value="No"> No</label>
      </div>
    </side>
    </div>

    <button class="c-btn gold" type="submit">&#128483; Submit Feedback</button>
    <a class="c-btn ghost" href="my-events.php">Cancel</a>
  </form>

  <p class="c-muted" style="font-size:12px;margin-top:12px;">
    &#129302; Note: our <b>feedback analyser</b> (lexicon-based prototype — not a trained ML model) will
    automatically classify your comment as Positive / Neutral / Negative and extract discussion topics,
    which appear in the organiser's analytics view.
  </p>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/_footer.php'; ?>
