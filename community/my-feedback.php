<?php
/**
 * community/my-feedback.php — My Feedback history (CEP).
 *
 * Shows every feedback the user submitted, with the sentiment label
 * the AI analyser (lexicon prototype) assigned, and links to the event.
 */
$PAGE_TITLE = 'My Feedback';
$PAGE_KEY   = 'feedback';
require_once __DIR__ . '/../includes/community_auth.php';
require_community_login();
require_once __DIR__ . '/_header.php';

$db  = db();
$uid = (int)$_SESSION['community_user_id'];

$stmt = $db->prepare(
    "SELECT f.*, e.event_name, e.event_date, e.category
     FROM community_feedback f
     JOIN community_events e ON e.id = f.event_id
     WHERE f.community_user_id = ?
     ORDER BY f.created_at DESC"
);
$stmt->bind_param('i', $uid);
$stmt->execute();
$rows = $stmt->get_result();
$stmt->close();

$list = $rows ? $rows->fetch_all(MYSQLI_ASSOC) : [];
$sentBadge = ['Positive' => 'green', 'Neutral' => 'steel', 'Negative' => 'red'];
$avg = 0;
if ($list) { $sum = 0; foreach ($list as $f) $sum += (int)$f['rating']; $avg = round($sum / count($list), 1); }
?>
<div class="c-hero small">
  <span class="c-tag">MY CONTRIBUTIONS</span>
  <h1>My Feedback</h1>
  <p>Every feedback you shared, and how our feedback analyser (lexicon-based prototype)
     classified it. Your input directly shapes future events.</p>
</div>

<?php if (!$list): ?>
<div class="c-card">
  <p class="c-muted">You haven't given any feedback yet. After you attend an event, open
     <a href="my-events.php">My Registrations</a> and click "Give Feedback".</p>
</div>
<?php else: ?>
<div class="c-grid cols-3" style="margin-bottom:20px;">
  <div class="c-card"><div class="c-stat"><div class="c-ico gold">&#11088;</div><div><div class="num"><?= count($list) ?></div><div class="lbl">Feedback Submitted</div></div></div></div>
  <div class="c-card"><div class="c-stat"><div class="c-ico green">&#128077;</div><div><div class="num"><?= $avg ?></div><div class="lbl">My Average Rating</div></div></div></div>
  <div class="c-card"><div class="c-stat"><div class="c-ico steel">&#129302;</div><div><div class="num"><?= count(array_filter($list, fn($f) => ($f['sentiment'] ?? '') === 'Positive')) ?></div><div class="lbl">Classified Positive</div></div></div></div>
</div>

<div class="c-card">
  <h3 style="margin-top:0;">&#128220; My Feedback History</h3>
  <?php foreach ($list as $f): ?>
    <div class="c-statusline" style="align-items:flex-start;">
      <div style="flex:1;">
        <b><?= e($f['event_name']) ?></b> <span class="c-badge blue"><?= e($f['category']) ?></span>
        <span class="c-muted" style="font-size:12.5px;"><?= fmtDate($f['event_date']) ?> &middot; feedback given <?= date('M j, Y', strtotime($f['created_at'])) ?></span>
        <div style="margin:4px 0;">
          <?= str_repeat('&#11088;', (int)$f['rating']) . str_repeat('&#9734;', 5 - (int)$f['rating']) ?>
          <span class="c-badge <?= $sentBadge[$f['sentiment'] ?? 'Neutral'] ?? 'steel' ?>"><?= e(strtoupper($f['sentiment'] ?: 'NEUTRAL')) ?></span>
          <span class="c-badge gray"><?= e($f['satisfaction']) ?></span>
        </div>
        <?php if ($f['comments']): ?><div style="font-size:13px;"><?= e($f['comments']) ?></div><?php endif; ?>
        <?php if ($f['suggestions']): ?>
          <div style="font-size:12.5px;color:var(--muted);margin-top:4px;"><b>Suggestion:</b> <?= e($f['suggestions']) ?></div>
        <?php endif; ?>
        <?php if ($f['topics']): ?>
          <div style="font-size:12px;margin-top:4px;">
            <?php foreach (array_filter(explode(',', $f['topics'])) as $t): ?>
              <span class="c-badge purple" style="margin-right:4px;"><?= e(ucfirst(trim($t))) ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <div class="c-muted" style="font-size:12px;margin-top:4px;">Would attend again: <b><?= e($f['would_attend_again']) ?></b></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/_footer.php'; ?>
