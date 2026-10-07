<?php
/**
 * community/impact.php — Community Impact Dashboard (CEP).
 *
 * Full analytics view of community engagement: participation,
 * events, attendance, volunteers, feedback sentiment, requests,
 * challenges. All values are computed live from platform records.
 *
 * NOTE: In development/demo, the numbers below come from the clearly
 * labelled demo seed data — they are NOT real community results.
 */
$PAGE_TITLE = 'Community Impact';
$PAGE_KEY   = 'impact';
require_once __DIR__ . '/../includes/community_auth.php';
require_once __DIR__ . '/../includes/ai_feedback.php';
require_community_login();
require_once __DIR__ . '/_header.php';

$db = db();

/* ---------- headline counters ---------- */
$impact = [
    'participants' => (int)$db->query("SELECT COUNT(*) c FROM community_users WHERE status='Active'")->fetch_assoc()['c'],
    'events'       => (int)$db->query("SELECT COUNT(*) c FROM community_events WHERE status <> 'Cancelled'")->fetch_assoc()['c'],
    'registrations'=> (int)$db->query("SELECT COUNT(*) c FROM event_registrations WHERE status <> 'Cancelled'")->fetch_assoc()['c'],
    'attendance'   => (int)$db->query("SELECT COUNT(*) c FROM event_attendance WHERE status='Present'")->fetch_assoc()['c'],
    'volunteers'   => (int)$db->query("SELECT COUNT(*) c FROM volunteers WHERE status='Active'")->fetch_assoc()['c'],
    'vol_hours'    => (float)$db->query("SELECT COALESCE(SUM(total_hours),0) s FROM volunteer_hours WHERE status='Approved'")->fetch_assoc()['s'],
    'feedback'     => (int)$db->query("SELECT COUNT(*) c FROM community_feedback")->fetch_assoc()['c'],
    'requests'     => (int)$db->query("SELECT COUNT(*) c FROM community_requests")->fetch_assoc()['c'],
];

/* attendance rate */
$attRate = $impact['registrations'] ? round($impact['attendance'] * 100 / max(1, $impact['registrations']), 1) : 0;

/* ---------- event category mix (chart) ---------- */
$catRows = $db->query("SELECT category, COUNT(*) c FROM community_events WHERE status <> 'Cancelled' GROUP BY category ORDER BY c DESC");
$cats = [];
if ($catRows) while ($r = $catRows->fetch_assoc()) $cats[] = [$r['category'], (int)$r['c']];
$catMax = 0; foreach ($cats as $c) $catMax = max($catMax, $c[1]);

/* ---------- monthly activity (last 6 months) ---------- */
$monthRows = $db->query(
    "SELECT DATE_FORMAT(event_date,'%b %y') m, COUNT(*) c FROM community_events
     WHERE event_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND status <> 'Cancelled'
     GROUP BY YEAR(event_date), MONTH(event_date) ORDER BY event_date ASC"
);
$months = [];
if ($monthRows) while ($r = $monthRows->fetch_assoc()) $months[] = [$r['m'], (int)$r['c']];
$monthMax = 0; foreach ($months as $m) $monthMax = max($monthMax, $m[1]);

/* ---------- feedback sentiment (AI prototype output) ---------- */
$fbSummary = ai_feedback_summary(null);
$fbAvg     = $fbSummary['avg_rating'] ?: 0;
$fbCounts  = $fbSummary['counts']     ?: ['Positive' => 0, 'Neutral' => 0, 'Negative' => 0];
$fbPct     = $fbSummary['percent']    ?: [];
$fbTopics  = $fbSummary['topics']     ?: [];
$fbTotal   = $fbSummary['count'] ?: 0;

/* ---------- requests pipeline ---------- */
$reqRows = $db->query("SELECT status, COUNT(*) c FROM community_requests GROUP BY status");
$reqMap = [];
if ($reqRows) while ($r = $reqRows->fetch_assoc()) $reqMap[$r['status']] = (int)$r['c'];

/* ---------- top events by attendance ---------- */
$topRows = $db->query(
    "SELECT e.event_name, e.category, e.event_date,
            (SELECT COUNT(*) FROM event_attendance a WHERE a.event_id = e.id AND a.status='Present') AS present
     FROM community_events e
     ORDER BY present DESC, e.event_date DESC LIMIT 5"
);
$top = [];
if ($topRows) while ($r = $topRows->fetch_assoc()) $top[] = $r;

/* ---------- challenge engagement ---------- */
$chalRows = $db->query(
    "SELECT c.challenge_name, c.target_value, c.target_unit, c.status,
            COUNT(p.id) participants, SUM(p.completed) completed
     FROM fitness_challenges c LEFT JOIN challenge_participants p ON p.challenge_id = c.id
     GROUP BY c.id ORDER BY participants DESC LIMIT 5"
);
$chals = [];
if ($chalRows) while ($r = $chalRows->fetch_assoc()) $chals[] = $r;

/* demo-data disclosure */
$demoNote = 'Development/demo platform: current figures reflect clearly-labelled demo seed data, not real community results.';
?>
<div class="c-hero small">
  <span class="c-tag">OUR IMPACT</span>
  <h1>Community Impact Dashboard</h1>
  <p>Live view of how the New Life Fitness community program is engaging local residents —
     participation, attendance, volunteering, feedback and more.</p>
  <p class="c-muted" style="font-size:12px;margin:8px 0 0;">&#9888; <?= e($demoNote) ?></p>
</div>

<!-- ===== headline counters ===== -->
<div class="c-impact-grid" style="margin-bottom:22px;">
  <div class="c-impact"><div class="num"><?= $impact['participants'] ?></div><div class="lbl">Community Participants</div></div>
  <div class="c-impact"><div class="num"><?= $impact['events'] ?></div><div class="lbl">Events Organised</div></div>
  <div class="c-impact"><div class="num"><?= $impact['registrations'] ?></div><div class="lbl">Event Registrations</div></div>
  <div class="c-impact"><div class="num"><?= $attRate ?>%</div><div class="lbl">Attendance Rate</div></div>
  <div class="c-impact"><div class="num"><?= $impact['volunteers'] ?></div><div class="lbl">Active Volunteers</div></div>
  <div class="c-impact"><div class="num"><?= number_format($impact['vol_hours'], 1) ?>h</div><div class="lbl">Volunteer Hours</div></div>
  <div class="c-impact"><div class="num"><?= $fbAvg ?: '0.0' ?></div><div class="lbl">Avg Feedback Rating</div></div>
  <div class="c-impact"><div class="num"><?= ($reqMap['Completed'] ?? 0) ?></div><div class="lbl">Requests Completed</div></div>
</div>

<div class="c-grid cols-2" style="margin-bottom:22px;align-items:stretch;">
  <!-- ===== event categories ===== -->
  <div class="c-card">
    <h3 style="margin-top:0;">&#128197; Events by Category</h3>
    <?php if ($cats): ?>
    <div class="c-chart">
      <?php foreach ($cats as [$name, $c]): ?>
        <div class="row"><span><?= e($name) ?></span>
          <div class="bar"><span style="width:<?= (int)($c * 100 / max(1, $catMax)) ?>%"></span></div>
          <span class="val"><?= $c ?></span></div>
      <?php endforeach; ?>
    </div>
    <?php else: ?><p class="c-muted">No events yet.</p><?php endif; ?>
  </div>

  <!-- ===== monthly activity ===== -->
  <div class="c-card">
    <h3 style="margin-top:0;">&#128200; Events per Month (last 6)</h3>
    <?php if ($months): ?>
    <div class="c-chart">
      <?php foreach ($months as [$m, $c]): ?>
        <div class="row"><span><?= e($m) ?></span>
          <div class="bar gold"><span style="width:<?= (int)($c * 100 / max(1, $monthMax)) ?>%"></span></div>
          <span class="val"><?= $c ?></span></div>
      <?php endforeach; ?>
    </div>
    <?php else: ?><p class="c-muted">No events in the last 6 months.</p><?php endif; ?>
  </div>
</div>

<div class="c-grid cols-2" style="margin-bottom:22px;align-items:stretch;">
  <!-- ===== feedback sentiment ===== -->
  <div class="c-card">
    <h3 style="margin-top:0;">&#11088; Feedback Sentiment <span class="c-badge steel">LEXICON-BASED PROTOTYPE</span></h3>
    <?php if ($fbTotal > 1): ?>
    <div class="c-chart">
      <?php foreach (['Positive' => 'green', 'Neutral' => 'steel', 'Negative' => 'red'] as $s => $cls): ?>
        <div class="row"><span><?= $s ?> (<?= $fbPct[$s] ?? 0 ?>%)</span>
          <div class="bar <?= $cls === 'green' ? 'green' : '' ?>"><span style="width:<?= (int)($fbPct[$s] ?? 0) ?>%"></span></div>
          <span class="val"><?= $fbCounts[$s] ?? 0 ?></span></div>
      <?php endforeach; ?>
    </div>
    <?php else: ?><p class="c-muted">No feedback analysed yet.</p><?php endif; ?>
    <?php if ($fbTopics): ?>
      <div style="margin-top:12px;font-size:12.5px;">
        <b>Most discussed topics:</b>
        <?php $i = 0; foreach ($fbTopics as $t => $n): if ($i++ >= 4) break; ?>
          <span class="c-badge purple"><?= e(ucfirst($t)) ?> (<?= $n ?>)</span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- ===== requests pipeline ===== -->
  <div class="c-card">
    <h3 style="margin-top:0;">&#128172; Community Requests Pipeline</h3>
    <?php $reqOrder = ['Submitted','Under Review','Approved','Scheduled','Completed','Rejected']; ?>
    <div class="c-chart">
      <?php foreach ($reqOrder as $st): $v = $reqMap[$st] ?? 0; if (!$v && !in_array($st, ['Completed','Under Review'])) continue; ?>
        <div class="row"><span><?= e($st) ?></span>
          <div class="bar"><span style="width:<?= (int)($v * 100 / max(1, max($reqMap ?: [1]))) ?>%"></span></div>
          <span class="val"><?= $v ?></span></div>
      <?php endforeach; ?>
    </div>
    <p class="c-muted" style="font-size:12px;margin-top:10px;"><?= $impact['requests'] ?> requests received —
      <?= ($reqMap['Scheduled'] ?? 0) + ($reqMap['Completed'] ?? 0) ?> became real events.</p>
  </div>
</div>

<div class="c-grid cols-2" style="align-items:stretch;">
  <!-- ===== top events ===== -->
  <div class="c-card">
    <h3 style="margin-top:0;">&#127941; Most Attended Events</h3>
    <?php if ($top): ?>
    <div class="c-table-wrap">
    <table class="c-table">
      <thead><tr><th>Event</th><th>Category</th><th>Date</th><th>Attended</th></tr></thead>
      <tbody>
      <?php foreach ($top as $t): ?>
        <tr><td><b><?= e($t['event_name']) ?></b></td><td><span class="c-badge blue"><?= e($t['category']) ?></span></td>
            <td><?= fmtDate($t['event_date']) ?></td><td><b><?= (int)$t['present'] ?></b></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php else: ?><p class="c-muted">No attendance data yet.</p><?php endif; ?>
  </div>

  <!-- ===== challenge engagement ===== -->
  <div class="c-card">
    <h3 style="margin-top:0;">&#127942; Challenge Engagement</h3>
    <?php if ($chals): ?>
    <div class="c-table-wrap">
    <table class="c-table">
      <thead><tr><th>Challenge</th><th>Target</th><th>Joined</th><th>Completed</th></tr></thead>
      <tbody>
      <?php foreach ($chals as $c): ?>
        <tr><td><b><?= e($c['challenge_name']) ?></b></td>
            <td><?= (int)$c['target_value'] ?> <?= e($c['target_unit']) ?></td>
            <td><?= (int)$c['participants'] ?></td>
            <td><?= (int)$c['completed'] ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php else: ?><p class="c-muted">No challenges yet.</p><?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
