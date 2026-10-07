<?php
/**
 * admin/community/feedback.php — view community event feedback with the
 * Rule-Based AI feedback analysis (sentiment + topics).
 *
 * GET ?event=<id> — filter by event
 */
$PAGE_TITLE = 'Community Feedback';
$PAGE_KEY   = 'community-feedback';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/ai_feedback.php';

$db = db();

/* Events that have feedback, for the filter dropdown */
$eventsWithFb = $db->query(
    "SELECT e.id, e.event_name, e.event_date,
            COUNT(f.id) AS fbs,
            ROUND(AVG(f.rating), 1) AS avg_rating
     FROM community_events e
     JOIN community_feedback f ON f.event_id = e.id
     GROUP BY e.id, e.event_name, e.event_date
     ORDER BY e.event_date DESC"
);

$sel = (int)($_GET['event'] ?? 0);
$rows = null;
if ($sel) {
    $stmt = $db->prepare(
        "SELECT f.*, u.full_name, u.email, e.event_name
         FROM community_feedback f
         JOIN community_users u ON u.id = f.community_user_id
         JOIN community_events e ON e.id = f.event_id
         WHERE f.event_id = ?
         ORDER BY f.created_at DESC"
    );
    $stmt->bind_param('i', $sel);
    $stmt->execute();
    $rows = $stmt->get_result();
    $stmt->close();
} else {
    $rows = $db->query(
        "SELECT f.*, u.full_name, u.email, e.event_name
         FROM community_feedback f
         JOIN community_users u ON u.id = f.community_user_id
         JOIN community_events e ON e.id = f.event_id
         ORDER BY f.created_at DESC"
    );
}

/* AI summary for the current scope (null = all events) */
$fbSummary = ai_feedback_summary($sel ?: null);
$fbAvg     = $fbSummary['avg_rating'] ?: 0;
$fbCounts  = $fbSummary['counts'] ?: ['Positive' => 0, 'Neutral' => 0, 'Negative' => 0];
$fbPercent = $fbSummary['percent'] ?: [];
$fbTopics  = $fbSummary['topics'] ?: [];
$fbTotal   = $fbSummary['count'] ?: 0;
?>
<?= flash() ?>
<div class="page-head">
  <div>
    <h2>Community Feedback</h2>
    <p>Event feedback submitted by residents, analysed by the feedback analysis module.</p>
  </div>
</div>
<?php include __DIR__ . '/_nav.php'; ?>

<div class="comm-note">
  <b>&#129302; Honest AI disclosure:</b> the sentiment &amp; topic labels below are produced by the
  <b>Rule-Based Feedback Analysis Prototype</b> — a lexicon (keyword dictionary) classifier built
  for this CEP, <b>not a trained machine-learning model</b>. It is deterministic and explainable
  (the same comment always yields the same label, based on matched dictionary words). See
  <b>docs/AI_DOCUMENTATION.md</b> for the methodology, limitations and the upgrade path to a
  trained ML classifier once real feedback volume is available.
</div>

<?php if ($eventsWithFb && $eventsWithFb->num_rows): ?>
<div class="card" style="margin-bottom:18px">
  <div class="card-head"><h3>Filter by Event</h3></div>
  <div class="card-body">
    <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
      <div class="form-field" style="flex:1;min-width:280px">
        <label>Event</label>
        <select name="event" onchange="this.form.submit()">
          <option value="0">— All events (<?= $fbTotal ?> feedback) —</option>
          <?php $eventsWithFb->data_seek(0); while ($ev = $eventsWithFb->fetch_assoc()): ?>
            <option value="<?= $ev['id'] ?>" <?= $sel === (int)$ev['id'] ? 'selected' : '' ?>>
              <?= e($ev['event_name']) ?> — <?= (int)$ev['fbs'] ?> fb, avg <?= $ev['avg_rating'] ?: '—' ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
      <button class="btn btn-navy" type="submit">Apply</button>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if ($fbTotal): ?>
<div class="grid cols-2" style="margin-bottom:18px">
  <div class="card">
    <div class="card-head"><h3>AI Sentiment Summary</h3></div>
    <div class="card-body">
      <div class="stat" style="margin-bottom:14px;position:relative">
        <div class="stat-ico gold">&#11088;</div>
        <div><div class="stat-val"><?= number_format($fbAvg, 1) ?>/5</div><div class="stat-lbl">Average rating (<?= $fbTotal ?> feedback)</div></div>
      </div>
      <div class="comm-bars">
        <?php foreach (['Positive', 'Neutral', 'Negative'] as $senti):
          $n = (int)($fbCounts[$senti] ?? 0);
          $pct = $fbTotal ? round($n / $fbTotal * 100) : 0; ?>
          <div class="bar-row">
            <div class="lbl"><?= $senti ?></div>
            <div class="track"><div class="fill" style="width: <?= $pct ?>%"></div></div>
            <div class="val"><?= $n ?> (<?= $pct ?>%)</div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h3>Detected Topics</h3></div>
    <div class="card-body">
      <?php if ($fbTopics): ?>
        <div class="comm-bars">
          <?php foreach ($fbTopics as $topic => $n): $pct = $fbTotal ? round($n / $fbTotal * 100) : 0; ?>
            <div class="bar-row">
              <div class="lbl"><?= e($topic) ?></div>
              <div class="track"><div class="fill" style="width: <?= $pct ?>%"></div></div>
              <div class="val"><?= (int)$n ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="muted" style="margin:0">No topics detected in this feedback yet.</p>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($rows && $rows->num_rows): ?>
<div class="card">
  <div class="card-head"><h3>All Feedback (<?= (int)$rows->num_rows ?>)</h3></div>
  <div class="card-body" style="padding:0">
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr><th>ID</th><th>User</th><th>Event</th><th>Rating</th><th>Satisfaction</th><th>Comment</th><th>AI Sentiment</th><th>AI Topics</th><th>Again?</th><th>When</th></tr>
        </thead>
        <tbody>
        <?php while ($f = $rows->fetch_assoc()): ?>
          <tr>
            <td>#<?= $f['id'] ?></td>
            <td><b><?= e($f['full_name']) ?></b><br><small class="muted"><?= e($f['email']) ?></small></td>
            <td><?= e($f['event_name']) ?></td>
            <td><span class="comm-stars"><?= str_repeat('&#9733;', (int)$f['rating']) . str_repeat('&#9734;', 5 - (int)$f['rating']) ?></span></td>
            <td><span class="badge steel"><?= e($f['satisfaction']) ?></span></td>
            <td style="max-width:260px"><?= e($f['comments'] ?: '—') ?><?= $f['suggestions'] ? '<br><small class="muted"><b>Suggests:</b> ' . e($f['suggestions']) . '</small>' : '' ?></td>
            <td>
              <?php $cls = ['Positive' => 'pos', 'Neutral' => 'neu', 'Negative' => 'neg']; ?>
              <span class="comm-senti <?= $cls[$f['sentiment']] ?? 'neu' ?>"><?= $f['sentiment'] ?: '—' ?></span>
            </td>
            <td>
              <?php if ($f['topics']): foreach (explode(',', $f['topics']) as $t): ?>
                <span class="badge gray" style="margin:1px"><?= e(trim($t)) ?></span>
              <?php endforeach; else: ?>—<?php endif; ?>
            </td>
            <td><?= $f['would_attend_again'] === 'Yes' ? '<span class="badge green">Yes</span>' : '<span class="badge red">No</span>' ?></td>
            <td><?= fmtDate(substr($f['created_at'], 0, 10)) ?></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php else: ?>
  <div class="card"><div class="card-body"><p class="muted" style="margin:0">No feedback yet for this selection.</p></div></div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
