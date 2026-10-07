<?php
/**
 * community/my-events.php — My Event Registrations (CEP).
 *
 * Lists every event the user registered for with:
 *   - registration status (Registered / Attended / Missed / Cancelled)
 *   - attendance status marked by organisers
 *   - feedback entry point for completed events
 *   - cancel option before the event date
 */
$PAGE_TITLE = 'My Registrations';
$PAGE_KEY   = 'my-events';
require_once __DIR__ . '/../includes/community_auth.php';
require_community_login();
require_once __DIR__ . '/_header.php';

$db  = db();
$uid = (int)$_SESSION['community_user_id'];
$today = date('Y-m-d');

/* my registrations with event details + attendance + feedback flag */
$stmt = $db->prepare(
    "SELECT r.id AS reg_id, r.status AS reg_status, r.reg_date,
            e.id AS event_id, e.event_name, e.category, e.event_date, e.start_time, e.end_time,
            e.location, e.organizer, e.status AS event_status, e.max_participants,
            a.status AS att_status,
            (SELECT COUNT(*) FROM event_registrations r2 WHERE r2.event_id = e.id AND r2.status <> 'Cancelled') AS taken,
            f.id AS fb_id
     FROM event_registrations r
     JOIN community_events e ON e.id = r.event_id
     LEFT JOIN event_attendance a ON a.event_id = e.id AND a.community_user_id = r.community_user_id
     LEFT JOIN community_feedback f ON f.event_id = e.id AND f.community_user_id = r.community_user_id
     WHERE r.community_user_id = ?
     ORDER BY e.event_date DESC"
);
$stmt->bind_param('i', $uid);
$stmt->execute();
$rows = $stmt->get_result();
$stmt->close();

$counts = ['active' => 0, 'attended' => 0, 'missed' => 0, 'cancelled' => 0];
$all = [];
if ($rows) { while ($r = $rows->fetch_assoc()) {
    $all[] = $r;
    if ($r['reg_status'] === 'Cancelled') $counts['cancelled']++;
    elseif (($r['att_status'] ?? '') === 'Present') $counts['attended']++;
    elseif (($r['att_status'] ?? '') === 'Absent') $counts['missed']++;
    else $counts['active']++;
} }
?>

<div class="c-hero small">
  <span class="c-tag">MY PARTICIPATION</span>
  <h1>My Event Registrations</h1>
  <p>All the community events you have registered for, with your attendance record
     and feedback status in one place.</p>
</div>

<div class="c-grid cols-4" style="margin-bottom:22px;">
  <div class="c-card"><div class="c-stat"><div class="c-ico navy">&#128197;</div><div><div class="num"><?= $counts['active'] ?></div><div class="lbl">Upcoming / Active</div></div></div></div>
  <div class="c-card"><div class="c-stat"><div class="c-ico green">&#9989;</div><div><div class="num"><?= $counts['attended'] ?></div><div class="lbl">Attended</div></div></div></div>
  <div class="c-card"><div class="c-stat"><div class="c-ico red">&#10060;</div><div><div class="num"><?= $counts['missed'] ?></div><div class="lbl">Missed</div></div></div></div>
  <div class="c-card"><div class="c-stat"><div class="c-ico steel">&#128683;</div><div><div class="num"><?= $counts['cancelled'] ?></div><div class="lbl">Cancelled</div></div></div></div>
</div>

<?php if (!$all): ?>
<div class="c-card">
  <p class="c-muted">You haven't registered for any events yet.</p>
  <a class="c-btn gold" href="events.php">Browse Community Events &rarr;</a>
</div>
<?php else: ?>
<div class="c-table-wrap">
<table class="c-table">
  <thead><tr>
    <th>Event</th><th>Date &amp; Time</th><th>Location</th>
    <th>Registration</th><th>Attendance</th><th>Feedback</th><th>Action</th>
  </tr></thead>
  <tbody>
  <?php foreach ($all as $r):
      $eid = (int)$r['event_id'];
      $isPast = ($r['event_date'] < $today || $r['event_status'] === 'Completed');
      $cancelled = $r['reg_status'] === 'Cancelled';
  ?>
    <tr<?= $cancelled ? ' style="opacity:.55;"' : '' ?>>
      <td><b><?= e($r['event_name']) ?></b><br><span class="c-badge blue"><?= e($r['category']) ?></span></td>
      <td><?= fmtDate($r['event_date']) ?><br><span class="c-muted"><?= substr($r['start_time'],0,5) ?>–<?= substr($r['end_time'],0,5) ?></span></td>
      <td><?= e($r['location']) ?></td>
      <td>
        <?php if ($cancelled): ?><span class="c-badge gray">CANCELLED</span>
        <?php elseif ($r['reg_status'] === 'Attended'): ?><span class="c-badge green">ATTENDED</span>
        <?php elseif ($r['reg_status'] === 'Missed'): ?><span class="c-badge red">MISSED</span>
        <?php else: ?><span class="c-badge gold">REGISTERED</span><?php endif; ?>
      </td>
      <td>
        <?php $a = $r['att_status'] ?? ''; ?>
        <?php if ($a === 'Present'): ?><span class="c-badge green">PRESENT</span>
        <?php elseif ($a === 'Absent'): ?><span class="c-badge red">ABSENT</span>
        <?php elseif ($isPast): ?><span class="c-badge gray">NOT MARKED</span>
        <?php else: ?><span class="c-muted">After event</span><?php endif; ?>
      </td>
      <td>
        <?php if ($r['fb_id']): ?><span class="c-badge green">&#11088; SUBMITTED</span>
        <?php elseif ($isPast && !$cancelled && ($a === 'Present' || $r['reg_status'] !== 'Cancelled')): ?>
          <a class="c-btn green sm" href="event-feedback.php?event_id=<?= $eid ?>">Give Feedback</a>
        <?php else: ?><span class="c-muted">—</span><?php endif; ?>
      </td>
      <td>
        <?php if (!$cancelled && !$isPast): ?>
          <form method="post" action="events-register.php" onsubmit="return confirm('Cancel your registration?');" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="event_id" value="<?= $eid ?>">
            <input type="hidden" name="action" value="cancel">
            <button class="c-btn ghost sm">Cancel</button>
          </form>
        <?php elseif ($cancelled && $r['event_date'] >= $today && $r['event_status'] !== 'Completed'): ?>
          <a class="c-btn gold sm" href="events.php#event-<?= $eid ?>">Re-register</a>
        <?php else: ?>
          <a class="c-btn ghost sm" href="events.php#event-<?= $eid ?>">View</a>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<p class="c-muted" style="font-size:12.5px;">Attendance is marked by organisers after each event. A "Missed" badge means you
registered but did not attend.</p>
<?php endif; ?>

<?php require_once __DIR__ . '/_footer.php'; ?>
