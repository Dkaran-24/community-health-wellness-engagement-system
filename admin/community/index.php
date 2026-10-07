<?php
/**
 * admin/community/index.php — Community Engagement Project overview.
 *
 * Landing page for all CEP administration: headline counters, quick links
 * and recent activity across every community module.
 */
$PAGE_TITLE = 'Community Overview';
$PAGE_DESCRIPTION = 'Community engagement administration for events, volunteers, feedback, surveys and measurable community impact.';
$PAGE_KEY   = 'community';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/ai_feedback.php';

$db = db();

/* Headline counters ------------------------------------------------ */
$stats = [
    'users'        => (int)$db->query("SELECT COUNT(*) c FROM community_users")->fetch_assoc()['c'],
    'events'       => (int)$db->query("SELECT COUNT(*) c FROM community_events")->fetch_assoc()['c'],
    'upcoming'     => (int)$db->query("SELECT COUNT(*) c FROM community_events WHERE status IN ('Upcoming','Ongoing') AND event_date >= CURDATE()")->fetch_assoc()['c'],
    'registrations'=> (int)$db->query("SELECT COUNT(*) c FROM event_registrations WHERE status IN ('Registered','Attended')")->fetch_assoc()['c'],
    'volunteers'   => (int)$db->query("SELECT COUNT(*) c FROM volunteers WHERE status='Active'")->fetch_assoc()['c'],
    'pendingApps'  => (int)$db->query("SELECT COUNT(*) c FROM volunteer_applications WHERE status='Pending'")->fetch_assoc()['c'],
    'pendingHours' => (int)$db->query("SELECT COUNT(*) c FROM volunteer_hours WHERE status='Pending'")->fetch_assoc()['c'],
    'volHours'     => (float)$db->query("SELECT COALESCE(SUM(total_hours),0) s FROM volunteer_hours WHERE status='Approved'")->fetch_assoc()['s'],
    'feedbacks'    => (int)$db->query("SELECT COUNT(*) c FROM community_feedback")->fetch_assoc()['c'],
    'requests'     => (int)$db->query("SELECT COUNT(*) c FROM community_requests")->fetch_assoc()['c'],
    'openRequests' => (int)$db->query("SELECT COUNT(*) c FROM community_requests WHERE status NOT IN ('Completed','Rejected')")->fetch_assoc()['c'],
    'surveys'      => (int)$db->query("SELECT COUNT(*) c FROM community_surveys WHERE status='Open'")->fetch_assoc()['c'],
];

/* AI feedback summary (all events, null = global) */
$fbSummary = ai_feedback_summary(null);
$fbAvg = $fbSummary['avg_rating'] ?: 0;
$fbCounts = $fbSummary['counts'] ?: ['Positive' => 0, 'Neutral' => 0, 'Negative' => 0];
$fbTotal = $fbSummary['count'] ?: 0;

/* Recent items ------------------------------------------------------ */
$recentEvents = $db->query(
    "SELECT id, event_name, category, event_date, status,
            (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id = e.id AND r.status IN ('Registered','Attended')) AS regs
     FROM community_events e ORDER BY event_date DESC LIMIT 6"
);
$recentRequests = $db->query(
    "SELECT r.id, r.title, r.request_type, r.status, r.created_at, u.full_name
     FROM community_requests r JOIN community_users u ON u.id = r.community_user_id
     ORDER BY r.updated_at DESC LIMIT 6"
);
$recentFeedback = $db->query(
    "SELECT f.id, f.rating, f.sentiment, f.created_at, u.full_name, e.event_name
     FROM community_feedback f
     JOIN community_users u ON u.id = f.community_user_id
     JOIN community_events e ON e.id = f.event_id
     ORDER BY f.created_at DESC LIMIT 6"
);
?>
<?= flash() ?>
<div class="page-head">
  <div>
    <h1>Community Engagement Project</h1>
    <p>Programme management for community users, events, volunteers, feedback and more. <b>&#127760;</b></p>
  </div>
</div>
<?php include __DIR__ . '/_nav.php'; ?>

<div class="grid cols-4">
  <div class="stat"><div class="stat-ico navy">&#9635;</div><div><div class="stat-val"><?= $stats['users'] ?></div><div class="stat-lbl">Community Users</div></div></div>
  <div class="stat"><div class="stat-ico steel">&#128197;</div><div><div class="stat-val"><?= $stats['events'] ?></div><div class="stat-lbl">Events (<?= $stats['upcoming'] ?> upcoming)</div></div></div>
  <div class="stat"><div class="stat-ico green">&#9997;</div><div><div class="stat-val"><?= $stats['volunteers'] ?></div><div class="stat-lbl">Active Volunteers</div></div></div>
  <div class="stat"><div class="stat-ico gold">&#11088;</div><div><div class="stat-val"><?= $fbAvg ? number_format($fbAvg, 1) : '—' ?></div><div class="stat-lbl">Avg. Event Rating</div></div></div>
  <div class="stat"><div class="stat-ico navy">&#9998;</div><div><div class="stat-val"><?= $stats['registrations'] ?></div><div class="stat-lbl">Event Registrations</div></div></div>
  <div class="stat"><div class="stat-ico gold">&#9201;</div><div><div class="stat-val"><?= $stats['volHours'] ? number_format($stats['volHours'], 1) : '0' ?></div><div class="stat-lbl">Volunteer Hours</div></div></div>
  <div class="stat"><div class="stat-ico red">&#128233;</div><div><div class="stat-val"><?= $stats['openRequests'] ?></div><div class="stat-lbl">Open Community Requests</div></div></div>
  <div class="stat"><div class="stat-ico steel">&#128202;</div><div><div class="stat-val"><?= $stats['surveys'] ?></div><div class="stat-lbl">Open Surveys / Polls</div></div></div>
</div>

<?php if ($stats['pendingApps'] || $stats['pendingHours']): ?>
<div class="alert warn">&#9888; <div><b>Awaiting action:</b>
  <?= $stats['pendingApps'] ? '<a href="volunteers.php"><b>' . $stats['pendingApps'] . ' volunteer application(s)</b></a> need review. ' : '' ?>
  <?= $stats['pendingHours'] ? '<a href="volunteers.php"><b>' . $stats['pendingHours'] . ' hour log(s)</b></a> awaiting approval. ' : '' ?>
</div></div>
<?php endif; ?>

<div class="grid cols-2" style="margin-top:18px">
  <div class="card">
    <div class="card-head"><h3>Recent Events</h3><a class="btn btn-ghost btn-sm" href="events.php">Manage &rarr;</a></div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap">
        <table class="data" aria-label="Recent community events">
          <thead><tr><th>Event</th><th>Date</th><th>Regs</th><th>Status</th></tr></thead>
          <tbody>
          <?php while ($ev = $recentEvents->fetch_assoc()): ?>
            <tr>
              <td><b><?= e($ev['event_name']) ?></b><br><small class="muted"><?= e($ev['category']) ?></small></td>
              <td><?= fmtDate($ev['event_date']) ?></td>
              <td><?= (int)$ev['regs'] ?></td>
              <td>
                <?php
                  $badgeMap = ['Upcoming' => 'gold', 'Ongoing' => 'steel', 'Completed' => 'green', 'Cancelled' => 'red'];
                  $b = $badgeMap[$ev['status']] ?? 'gray';
                ?>
                <span class="badge <?= $b ?>"><?= e($ev['status']) ?></span>
              </td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div>
    <div class="card">
      <div class="card-head"><h3>AI Feedback Analysis</h3><a class="btn btn-ghost btn-sm" href="feedback.php">All feedback &rarr;</a></div>
      <div class="card-body">
        <div class="comm-note" style="margin-bottom:12px">
          <b>&#129302; Honest AI disclosure:</b> sentiment &amp; topic analysis is produced by the
          <b>Rule-Based Feedback Analysis Prototype</b> (lexicon keyword matching) — not a trained
          machine-learning model. See <b>docs/AI_DOCUMENTATION.md</b>.
        </div>
        <?php if ($fbTotal): ?>
          <div class="comm-bars">
            <?php foreach ($fbCounts as $senti => $n):
              $pct = $fbTotal ? round($n / $fbTotal * 100) : 0; ?>
              <div class="bar-row">
                <div class="lbl"><?= e($senti) ?></div>
                <div class="track"><div class="fill" style="width: <?= $pct ?>%"></div></div>
                <div class="val"><?= $n ?> (<?= $pct ?>%)</div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="muted" style="margin:0">No feedback submitted yet.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="card" style="margin-top:18px">
      <div class="card-head"><h3>Latest Community Requests</h3><a class="btn btn-ghost btn-sm" href="requests.php">Workflow &rarr;</a></div>
      <div class="card-body" style="padding:0">
        <div class="table-wrap">
          <table class="data" aria-label="Latest community requests">
            <thead><tr><th>Request</th><th>By</th><th>Status</th></tr></thead>
            <tbody>
            <?php while ($r = $recentRequests->fetch_assoc()): ?>
              <tr>
                <td><b><?= e($r['title']) ?></b><br><small class="muted"><?= e($r['request_type']) ?></small></td>
                <td><?= e($r['full_name']) ?></td>
                <td><span class="badge <?= $r['status'] === 'Completed' ? 'green' : ($r['status'] === 'Rejected' ? 'red' : ($r['status'] === 'Submitted' ? 'gray' : 'gold')) ?>"><?= e($r['status']) ?></span></td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card" style="margin-top:18px">
  <div class="card-head"><h3>Recent Feedback (AI-classified)</h3></div>
  <div class="card-body" style="padding:0">
    <div class="table-wrap">
      <table class="data" aria-label="Recent community feedback">
        <thead><tr><th>User</th><th>Event</th><th>Rating</th><th>AI Sentiment</th><th>When</th></tr></thead>
        <tbody>
        <?php while ($f = $recentFeedback->fetch_assoc()): ?>
          <tr>
            <td><?= e($f['full_name']) ?></td>
            <td><?= e($f['event_name']) ?></td>
            <td><span class="comm-stars"><?= str_repeat('&#9733;', (int)$f['rating']) . str_repeat('&#9734;', 5 - (int)$f['rating']) ?></span></td>
            <td>
              <?php $cls = ['Positive' => 'pos', 'Neutral' => 'neu', 'Negative' => 'neg']; ?>
              <span class="comm-senti <?= $cls[$f['sentiment']] ?? 'neu' ?>"><?= $f['sentiment'] ?: '—' ?></span>
            </td>
            <td><?= fmtDate(substr($f['created_at'], 0, 10)) ?></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
