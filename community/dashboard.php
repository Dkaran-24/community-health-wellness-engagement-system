<?php
/**
 * community/dashboard.php — COMMUNITY DASHBOARD (New Life Fitness CEP).
 *
 * After community login a resident lands here. The dashboard shows:
 *   - personalised AI (rule-based) activity recommendations
 *   - upcoming community events (with quick register)
 *   - open surveys & polls
 *   - active challenges
 *   - announcements
 *   - the user's own participation snapshot
 *   - a public community impact strip
 */
$PAGE_TITLE = 'Community Dashboard';
$PAGE_KEY   = 'dashboard';
require_once __DIR__ . '/../includes/community_auth.php';
require_once __DIR__ . '/../includes/ai_recommendation.php';
require_community_login();
require_once __DIR__ . '/_header.php';

$db    = db();
$uid   = (int)$_SESSION['community_user_id'];
$user  = current_community_user();

/* ---- AI recommendations (rule-based prototype, honestly labelled) ---- */
$recs = ai_recommendations_for($uid, 4);
ai_log_recommendations($uid, $recs);

/* ---- Upcoming events (next 4) ---- */
$upcoming = $db->query(
    "SELECT ce.*, 
            (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id = ce.id AND r.status <> 'Cancelled') AS taken
       FROM community_events ce
      WHERE ce.status = 'Upcoming' AND ce.event_date >= CURDATE()
      ORDER BY ce.event_date ASC LIMIT 4"
);

/* ---- Which of those am I already registered for? ---- */
$mineReg = [];
$stmt = $db->prepare("SELECT event_id FROM event_registrations WHERE community_user_id = ? AND status <> 'Cancelled'");
$stmt->bind_param('i', $uid);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $mineReg[(int)$r['event_id']] = true;
$stmt->close();

/* ---- My stats ---- */
$myEvents = (int)$db->query("SELECT COUNT(*) c FROM event_registrations WHERE community_user_id=$uid AND status <> 'Cancelled'")->fetch_assoc()['c'];
$myAttended = (int)$db->query("SELECT COUNT(*) c FROM event_attendance a JOIN event_registrations r ON r.event_id=a.event_id AND r.community_user_id=a.community_user_id WHERE a.community_user_id=$uid AND a.status='Present'")->fetch_assoc()['c'];
$myChallenges = (int)$db->query("SELECT COUNT(*) c FROM challenge_participants WHERE community_user_id=$uid")->fetch_assoc()['c'];
$myFeedback = (int)$db->query("SELECT COUNT(*) c FROM community_feedback WHERE community_user_id=$uid")->fetch_assoc()['c'];

/* ---- Open surveys ---- */
$surveys = $db->query("SELECT * FROM community_surveys WHERE status='Open' ORDER BY created_at DESC LIMIT 3");
$surveyCount = $surveys ? $surveys->num_rows : 0;

/* ---- Active challenges ---- */
$challenges = $db->query("SELECT * FROM fitness_challenges WHERE status IN ('Active','Upcoming') ORDER BY start_date ASC LIMIT 3");

/* ---- Announcements (latest 3 active) ---- */
$anns = $db->query("SELECT * FROM community_announcements WHERE status='Active' ORDER BY created_at DESC LIMIT 3");

/* ---- Community impact (public counters, live from DB) ---- */
$impact = [
    'participants' => (int)$db->query("SELECT COUNT(*) c FROM community_users WHERE status='Active'")->fetch_assoc()['c'],
    'events'       => (int)$db->query("SELECT COUNT(*) c FROM community_events")->fetch_assoc()['c'],
    'registrations'=> (int)$db->query("SELECT COUNT(*) c FROM event_registrations WHERE status <> 'Cancelled'")->fetch_assoc()['c'],
    'attendance'   => (int)$db->query("SELECT COUNT(*) c FROM event_attendance WHERE status='Present'")->fetch_assoc()['c'],
    'volunteers'   => (int)$db->query("SELECT COUNT(*) c FROM volunteers WHERE status='Active'")->fetch_assoc()['c'],
    'vol_hours'    => (float)$db->query("SELECT COALESCE(SUM(total_hours),0) s FROM volunteer_hours WHERE status='Approved'")->fetch_assoc()['s'],
];
?>

<div class="c-hero">
  <span class="c-tag">COMMUNITY PLATFORM</span>
  <h1>Hello, <?= htmlspecialchars($user['full_name'] ?? 'Community Member') ?> &#128075;</h1>
  <p>Stronger Together, Healthier Community! Browse free community events, join fitness challenges,
     learn from wellness resources, share your feedback and volunteer to help your neighbourhood stay healthy.</p>
</div>


<!-- ================= MY SNAPSHOT ================= -->
<div class="c-grid cols-4" style="margin-bottom:22px;">
  <div class="c-card"><div class="c-stat"><div class="c-ico navy">&#128197;</div><div><div class="num"><?= $myEvents ?></div><div class="lbl">My Event Registrations</div></div></div></div>
  <div class="c-card"><div class="c-stat"><div class="c-ico green">&#9989;</div><div><div class="num"><?= $myAttended ?></div><div class="lbl">Events Attended</div></div></div></div>
  <div class="c-card"><div class="c-stat"><div class="c-ico gold">&#127942;</div><div><div class="num"><?= $myChallenges ?></div><div class="lbl">Challenges Joined</div></div></div></div>
  <div class="c-card"><div class="c-stat"><div class="c-ico steel">&#11088;</div><div><div class="num"><?= $myFeedback ?></div><div class="lbl">Feedback Given</div></div></div></div>
</div>

<!-- ================= UPCOMING EVENTS ================= -->
<div class="c-card" style="margin-bottom:22px;">
  <h3>&#128197; Upcoming Community Events <a class="c-more" href="<?= $base ?>/community/events.php">View all &rarr;</a></h3>
  <div class="c-item-grid">
    <?php if ($upcoming && $upcoming->num_rows): while ($ev = $upcoming->fetch_assoc()):
        $taken = (int)$ev['taken']; $cap = max(1, (int)$ev['max_participants']);
        $full = $taken >= $cap;
        $mine = isset($mineReg[(int)$ev['id']]);
    ?>
      <div class="c-item">
        <h4><?= e($ev['event_name']) ?></h4>
        <span class="c-badge blue"><?= e($ev['category']) ?></span>
        <div class="c-desc"><?= e(mb_strimwidth($ev['description'] ?? '', 0, 110, '...')) ?></div>
        <div class="c-meta">
          <div><b>&#128197;</b> <?= fmtDate($ev['event_date']) ?> &middot; <?= substr($ev['start_time'], 0, 5) ?>–<?= substr($ev['end_time'], 0, 5) ?></div>
          <div><b>&#128205;</b> <?= e($ev['location']) ?></div>
          <div><b>&#128100;</b> <?= e($ev['organizer']) ?></div>
        </div>
        <div class="c-capbar"><span style="width:<?= min(100, (int)($taken * 100 / $cap)) ?>%"></span></div>
        <div style="font-size:12px;color:var(--muted);"><?= $taken ?>/<?= $cap ?> registered <?= $full ? '&middot; <span class="c-badge red">FULL</span>' : '' ?></div>
        <div class="c-actions">
          <?php if ($mine): ?>
            <span class="c-badge green">&#10003; REGISTERED</span>
            <a class="c-btn ghost sm" href="<?= $base ?>/community/my-events.php">My Events</a>
          <?php elseif ($full): ?>
            <span class="c-badge gray">Capacity reached</span>
          <?php else: ?>
            <a class="c-btn gold sm" href="<?= $base ?>/community/events.php#event-<?= (int)$ev['id'] ?>">Register &rarr;</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endwhile; else: ?>
      <p class="c-muted" style="grid-column:1/-1;">No upcoming events right now. Check back soon!</p>
    <?php endif; ?>
  </div>
</div>

<div class="c-grid cols-2" style="margin-bottom:22px;">
  <!-- ================= SURVEYS ================= -->
  <div class="c-card">
    <h3>&#128202; Open Surveys &amp; Polls <a class="c-more" href="<?= $base ?>/community/surveys.php">Participate &rarr;</a></h3>
    <?php if ($surveyCount): while ($s = $surveys->fetch_assoc()): ?>
      <div class="c-item" style="margin-bottom:10px;">
        <h4><?= e($s['title']) ?> <span class="c-badge <?= $s['type'] === 'Poll' ? 'gold' : 'steel' ?>"><?= e($s['type']) ?></span></h4>
        <div class="c-desc"><?= e($s['description'] ?? '') ?></div>
        <div class="c-actions"><a class="c-btn sm" href="<?= $base ?>/community/surveys.php#survey-<?= (int)$s['id'] ?>">Take <?= e($s['type']) ?> &rarr;</a></div>
      </div>
    <?php endwhile; else: ?>
      <p class="c-muted">No open surveys right now.</p>
    <?php endif; ?>
  </div>

  <!-- ================= CHALLENGES ================= -->
  <div class="c-card">
    <h3>&#127942; Fitness Challenges <a class="c-more" href="<?= $base ?>/community/challenges.php">Join &rarr;</a></h3>
    <?php if ($challenges && $challenges->num_rows): while ($c = $challenges->fetch_assoc()): ?>
      <div class="c-item" style="margin-bottom:10px;">
        <h4><?= e($c['challenge_name']) ?> <span class="c-badge <?= $c['status'] === 'Active' ? 'green' : 'gray' ?>"><?= e($c['status']) ?></span></h4>
        <div class="c-desc"><?= e(mb_strimwidth($c['description'] ?? '', 0, 90, '...')) ?></div>
        <div class="c-meta"><div><b>Target:</b> <?= (int)$c['target_value'] ?> <?= e($c['target_unit']) ?> &middot; <b>Ends:</b> <?= fmtDate($c['end_date']) ?></div></div>
        <div class="c-actions"><a class="c-btn sm" href="<?= $base ?>/community/challenges.php#chal-<?= (int)$c['id'] ?>">View &rarr;</a></div>
      </div>
    <?php endwhile; else: ?>
      <p class="c-muted">No active challenges right now.</p>
    <?php endif; ?>
  </div>
</div>

<!-- ================= ANNOUNCEMENTS ================= -->
<div class="c-card" style="margin-bottom:22px;">
  <h3>&#128227; Community Announcements</h3>
  <div class="c-tl">
    <?php if ($anns && $anns->num_rows): while ($a = $anns->fetch_assoc()): ?>
      <div class="tl-item">
        <div class="d"><?= date('M j, Y', strtotime($a['created_at'])) ?> &middot; <span class="c-badge purple"><?= e($a['type']) ?></span></div>
        <div class="t"><?= e($a['title']) ?></div>
        <div style="font-size:13px;color:var(--muted);"><?= e($a['message']) ?></div>
      </div>
    <?php endwhile; else: ?>
      <p class="c-muted">No announcements yet.</p>
    <?php endif; ?>
  </div>
</div>

<!-- ================= COMMUNITY IMPACT STRIP ================= -->
<div class="c-card">
  <h3>&#128200; Our Community Impact <span class="c-badge steel">LIVE FROM PLATFORM DATA</span></h3>
  <p class="c-muted" style="font-size:12.5px;margin-bottom:14px;">
    These counters are computed from real platform records (registrations, attendance, volunteers, feedback).
    Development/demo seed data is clearly labelled as such — see the full Community Impact page.
  </p>
  <div class="c-impact-grid">
    <div class="c-impact"><div class="num"><?= $impact['participants'] ?></div><div class="lbl">Community Participants</div></div>
    <div class="c-impact"><div class="num"><?= $impact['events'] ?></div><div class="lbl">Events Organised</div></div>
    <div class="c-impact"><div class="num"><?= $impact['registrations'] ?></div><div class="lbl">Event Registrations</div></div>
    <div class="c-impact"><div class="num"><?= $impact['attendance'] ?></div><div class="lbl">Event Attendances</div></div>
    <div class="c-impact"><div class="num"><?= $impact['volunteers'] ?></div><div class="lbl">Active Volunteers</div></div>
    <div class="c-impact"><div class="num"><?= number_format($impact['vol_hours'], 1) ?>h</div><div class="lbl">Volunteer Hours</div></div>
    <div class="c-impact"><div class="num"><?= $db->query("SELECT ROUND(AVG(rating),1) r FROM community_feedback")->fetch_assoc()['r'] ?: '0.0' ?></div><div class="lbl">Avg Feedback Rating</div></div>
    <div class="c-impact"><div class="num"><?= (int)$db->query("SELECT COUNT(*) c FROM community_requests WHERE status IN ('Approved','Scheduled','Completed')")->fetch_assoc()['c'] ?></div><div class="lbl">Requests Addressed</div></div>
  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
