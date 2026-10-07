<?php
/**
 * community/events.php — Community Events listing + detail (CEP).
 *
 * Shows upcoming & past community events with category filters.
 * Each event card: full details, live capacity bar, register / cancel
 * actions (POST → events-register.php with CSRF) and feedback entry
 * for completed events the user attended.
 */
$PAGE_TITLE = 'Community Events';
$PAGE_KEY   = 'events';
require_once __DIR__ . '/../includes/community_auth.php';
require_community_login();
require_once __DIR__ . '/_header.php';

$db  = db();
$uid = (int)$_SESSION['community_user_id'];

/* ---------- filters (category + when) ---------- */
$validCats = ['Fitness Camp','Yoga','Zumba','Walking','Running','Health Awareness','Nutrition Workshop','Wellness','Senior Fitness','Women Wellness','Community Challenge','Other'];
$cat  = (isset($_GET['cat']) && in_array($_GET['cat'], $validCats, true)) ? $_GET['cat'] : '';
$when = (isset($_GET['when']) && in_array($_GET['when'], ['upcoming','past','all'], true)) ? $_GET['when'] : 'upcoming';

$where = "WHERE ce.status <> 'Cancelled'";
$params = [];
$types  = '';
if ($cat) { $where .= " AND ce.category = ?"; $params[] = $cat; $types .= 's'; }
if ($when === 'upcoming') {
    $where .= " AND ce.event_date >= CURDATE() AND ce.status IN ('Upcoming','Ongoing')";
} elseif ($when === 'past') {
    $where .= " AND (ce.event_date < CURDATE() OR ce.status = 'Completed')";
}

/* ---------- events with live capacity ---------- */
$sql = "SELECT ce.*, t.name AS trainer_name,
        (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id = ce.id AND r.status <> 'Cancelled') AS taken
        FROM community_events ce
        LEFT JOIN trainers t ON t.id = ce.trainer_id
        $where
        ORDER BY ce.event_date ASC, ce.start_time ASC";
$stmt = $db->prepare($sql);
if ($types) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$events = $stmt->get_result();
$stmt->close();

/* ---------- my registration / attendance / feedback maps ---------- */
$myReg = $myAtt = $myFb = [];
$stmt = $db->prepare("SELECT event_id, status FROM event_registrations WHERE community_user_id = ?");
$stmt->bind_param('i', $uid); $stmt->execute(); $res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $myReg[(int)$r['event_id']] = $r['status'];
$stmt->close();

$stmt = $db->prepare("SELECT event_id, status FROM event_attendance WHERE community_user_id = ?");
$stmt->bind_param('i', $uid); $stmt->execute(); $res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $myAtt[(int)$r['event_id']] = $r['status'];
$stmt->close();

$stmt = $db->prepare("SELECT event_id FROM community_feedback WHERE community_user_id = ?");
$stmt->bind_param('i', $uid); $stmt->execute(); $res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $myFb[(int)$r['event_id']] = true;
$stmt->close();

$today = date('Y-m-d');

/** Can the user register for this event right now? returns [bool, reason] */
function event_reg_problem($ev, $myReg, $today) {
    if (isset($myReg[(int)$ev['id']]) && $myReg[(int)$ev['id']] !== 'Cancelled') return 'already';
    if ($ev['status'] === 'Completed' || $ev['status'] === 'Cancelled') return 'closed';
    if ($ev['event_date'] < $today) return 'closed';
    if (!empty($ev['reg_deadline']) && $today > $ev['reg_deadline']) return 'deadline';
    if ((int)$ev['taken'] >= (int)$ev['max_participants']) return 'full';
    return '';
}
?>

<div class="c-hero small">
  <span class="c-tag">PROGRAMS &amp; EVENTS</span>
  <h1>Community Events</h1>
  <p>Free fitness, wellness and health activities organised for local residents.
     Register in one click — no membership needed. Places are limited, so book early!</p>
</div>

<!-- ===== filter bar ===== -->
<div class="c-card" style="margin-bottom:20px;">
  <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
    <b style="font-size:13px;color:var(--navy-800);">When:</b>
    <a class="c-btn sm <?= $when === 'upcoming' ? 'gold' : 'ghost' ?>" href="events.php?when=upcoming<?= $cat ? '&amp;cat=' . urlencode($cat) : '' ?>">Upcoming</a>
    <a class="c-btn sm <?= $when === 'past' ? 'gold' : 'ghost' ?>" href="events.php?when=past<?= $cat ? '&amp;cat=' . urlencode($cat) : '' ?>">Past Events</a>
    <a class="c-btn sm <?= $when === 'all' ? 'gold' : 'ghost' ?>" href="events.php?when=all<?= $cat ? '&amp;cat=' . urlencode($cat) : '' ?>">All</a>
  </div>
  <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:10px;">
    <b style="font-size:13px;color:var(--navy-800);">Category:</b>
    <a class="c-badge <?= !$cat ? 'red' : 'gray' ?>" style="padding:6px 12px;" href="events.php?when=<?= $when ?>">All</a>
    <?php foreach ($validCats as $c): ?>
      <a class="c-badge <?= $cat === $c ? 'red' : 'gray' ?>" style="padding:6px 12px;" href="events.php?when=<?= $when ?>&amp;cat=<?= urlencode($c) ?>"><?= e($c) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<!-- ===== event grid ===== -->
<?php if ($events && $events->num_rows): while ($ev = $events->fetch_assoc()):
    $id    = (int)$ev['id'];
    $taken = (int)$ev['taken'];
    $cap   = max(1, (int)$ev['max_participants']);
    $full  = $taken >= $cap;
    $mine  = isset($myReg[$id]) && $myReg[$id] !== 'Cancelled';
    $prob  = event_reg_problem($ev, $myReg, $today);
    $isPast = ($ev['event_date'] < $today || $ev['status'] === 'Completed');
    $attended = ($myAtt[$id] ?? '') === 'Present';
?>
<div class="c-card" id="event-<?= $id ?>" style="margin-bottom:16px;">
  <div style="display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap;align-items:flex-start;">
    <div style="flex:1;min-width:260px;">
      <h3 style="margin:0 0 6px;"><?= e($ev['event_name']) ?></h3>
      <span class="c-badge blue"><?= e($ev['category']) ?></span>
      <?php if ($ev['status'] === 'Completed'): ?><span class="c-badge gray">COMPLETED</span>
      <?php elseif ($ev['status'] === 'Ongoing'): ?><span class="c-badge green">ONGOING</span>
      <?php elseif ($isPast): ?><span class="c-badge gray">PAST</span>
      <?php else: ?><span class="c-badge green">UPCOMING</span><?php endif; ?>
      <?php if ($mine): ?><span class="c-badge gold">&#10003; YOU ARE REGISTERED</span><?php endif; ?>
      <p class="c-desc" style="margin:10px 0 12px;color:var(--ink);"><?= e($ev['description'] ?? 'Details will be shared soon.') ?></p>
      <div class="c-meta" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px;font-size:13px;">
        <div><b>&#128197; Date:</b> <?= fmtDate($ev['event_date']) ?></div>
        <div><b>&#128336; Time:</b> <?= substr($ev['start_time'],0,5) ?> – <?= substr($ev['end_time'],0,5) ?></div>
        <div><b>&#128205; Location:</b> <?= e($ev['location']) ?></div>
        <div><b>&#128100; Organizer:</b> <?= e($ev['organizer']) ?></div>
        <?php if (!empty($ev['trainer_name'])): ?><div><b>&#127947; Trainer:</b> <?= e($ev['trainer_name']) ?></div><?php endif; ?>
        <?php if (!empty($ev['reg_deadline'])): ?><div><b>&#9200; Register by:</b> <?= fmtDate($ev['reg_deadline']) ?></div><?php endif; ?>
      </div>
    </div>

    <div style="width:250px;">
      <div class="c-capbar" style="margin-top:4px;"><span style="width:<?= min(100, (int)($taken * 100 / $cap)) ?>%"></span></div>
      <div style="font-size:12.5px;color:var(--muted);margin:5px 0 10px;">
        <?= $taken ?> / <?= $cap ?> places filled
        <?php if ($full): ?><span class="c-badge red" style="margin-left:6px;">FULL</span><?php endif; ?>
      </div>

      <?php if (!$isPast && $ev['status'] !== 'Completed'): ?>
        <?php if ($mine): ?>
          <form method="post" action="events-register.php" style="margin-bottom:8px;">
            <?= csrf_field() ?>
            <input type="hidden" name="event_id" value="<?= $id ?>">
            <input type="hidden" name="action" value="register">
            <button class="c-btn gold sm" style="width:100%;" disabled>&#10003; Registered</button>
          </form>
          <form method="post" action="events-register.php" onsubmit="return confirm('Cancel your registration for this event?');">
            <?= csrf_field() ?>
            <input type="hidden" name="event_id" value="<?= $id ?>">
            <input type="hidden" name="action" value="cancel">
            <button class="c-btn ghost sm" style="width:100%;">Cancel Registration</button>
          </form>
        <?php elseif ($prob === 'full'): ?>
          <button class="c-btn ghost sm" style="width:100%;" disabled>Capacity Reached</button>
        <?php elseif ($prob === 'deadline'): ?>
          <button class="c-btn ghost sm" style="width:100%;" disabled>Registration Closed</button>
        <?php else: ?>
          <form method="post" action="events-register.php">
            <?= csrf_field() ?>
            <input type="hidden" name="event_id" value="<?= $id ?>">
            <input type="hidden" name="action" value="register">
            <button class="c-btn gold sm" style="width:100%;">Register Free &rarr;</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($isPast && ($mine || $attended)):
        if (isset($myFb[$id])): ?>
          <span class="c-badge green" style="display:block;text-align:center;padding:8px;">&#11088; FEEDBACK SUBMITTED</span>
        <?php elseif ($attended || $mine): ?>
          <a class="c-btn green sm" style="width:100%;text-align:center;display:block;" href="event-feedback.php?event_id=<?= $id ?>">&#11088; Give Feedback</a>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endwhile; else: ?>
<div class="c-card"><p class="c-muted">No events found for this filter. <a href="events.php?when=all">Show all events &rarr;</a></p></div>
<?php endif; ?>

<?php require_once __DIR__ . '/_footer.php'; ?>
