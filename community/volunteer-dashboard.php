<?php
/**
 * community/volunteer-dashboard.php — Volunteer Dashboard (CEP).
 *
 * For APPROVED volunteers only (require_volunteer guard).
 * Shows: approved volunteer status, upcoming assignments, completed
 * assignments, hours log (submit own hours for organiser approval),
 * total approved hours, and open opportunities to opt-in.
 */
$PAGE_TITLE = 'Volunteer Dashboard';
$PAGE_KEY   = 'volunteer-dashboard';
require_once __DIR__ . '/../includes/community_auth.php';
require_volunteer();
require_once __DIR__ . '/_header.php';

$db    = db();
$uid   = (int)$_SESSION['community_user_id'];
$volId = current_volunteer_id();
$vol   = current_volunteer_row();
$today = date('Y-m-d');

/* ---- POST: submit hours ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $act = $_POST['action'] ?? '';
    if ($act === 'log_hours') {
        $eid  = (int)($_POST['event_id'] ?? 0);
        $wd   = $_POST['work_date'] ?? '';
        $st   = $_POST['start_time'] ?? '';
        $et   = $_POST['end_time'] ?? '';
        $role = trim($_POST['role'] ?? 'General Support');

        $err = '';
        if ($eid <= 0) $err = 'Please choose one of your assigned events.';
        elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $wd) || $wd > $today) $err = 'Work date must be today or earlier.';
        elseif (!preg_match('/^\d{2}:\d{2}/', $st) || !preg_match('/^\d{2}:\d{2}/', $et)) $err = 'Please enter valid start/end times.';
        else {
            $sh = strtotime("2000-01-01 $st"); $eh = strtotime("2000-01-01 $et");
            if ($eh <= $sh) $err = 'End time must be after start time.';
            elseif (($eh - $sh) > 12 * 3600) $err = 'Max 12 hours per entry.';
            elseif (mb_strlen($role) < 2 || mb_strlen($role) > 80) $err = 'Role must be 2-80 characters.';
        }

        /* Server-side rule (never trust the UI): hours can only be logged
           for an event the volunteer has a COMPLETED assignment for. */
        if ($err === '') {
            $chk = $db->prepare(
                "SELECT id FROM volunteer_event_assignments
                 WHERE volunteer_id = ? AND event_id = ? AND status = 'Completed' LIMIT 1");
            $chk->bind_param('ii', $volId, $eid);
            $chk->execute();
            $own = $chk->get_result()->fetch_assoc();
            $chk->close();
            if (!$own) $err = 'You can only log hours for events where your assignment is marked completed.';
        }

        if ($err) { header('Location: volunteer-dashboard.php?err=' . urlencode($err)); exit; }

        $hours = round(($eh - $sh) / 3600, 2);
        $stmt = $db->prepare("INSERT INTO volunteer_hours
                (volunteer_id, event_id, work_date, role, start_time, end_time, total_hours, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')");
        $stmt->bind_param('iissssd', $volId, $eid, $wd, $role, $st, $et, $hours);
        $stmt->execute(); $stmt->close();
        header('Location: volunteer-dashboard.php?ok=' . urlencode("Hours entry ($hours h) submitted for organiser approval."));
        exit;
    }
    if ($act === 'optin') {
        $eid = (int)($_POST['event_id'] ?? 0);
        /* only allow opting into upcoming, non-full events with an assignment slot */
        $stmt = $db->prepare("SELECT ce.id FROM community_events ce
                LEFT JOIN volunteer_event_assignments a ON a.event_id = ce.id AND a.volunteer_id = ?
                WHERE ce.id = ? AND ce.status='Upcoming' AND ce.event_date >= CURDATE() AND a.id IS NULL LIMIT 1");
        $stmt->bind_param('ii', $volId, $eid); $stmt->execute();
        $ok = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($ok) {
            $adminId = $_SESSION['admin_id'] ?? null; // null → recorded as unassigned/self-optin
            $role = 'General Support';
            $stmt = $db->prepare("INSERT INTO volunteer_event_assignments (volunteer_id, event_id, role, assigned_by) VALUES (?, ?, ?, NULL)");
            $stmt->bind_param('iis', $volId, $eid, $role);
            $stmt->execute(); $stmt->close();
            header('Location: volunteer-dashboard.php?ok=' . urlencode('You are signed up as a volunteer for this event.'));
            exit;
        }
        header('Location: volunteer-dashboard.php?err=' . urlencode('This event is not available for volunteer sign-up.'));
        exit;
    }
}

/* ---- data ---- */
$assignments = $db->query(
    "SELECT a.id, a.role, a.status AS a_status, e.id AS event_id, e.event_name, e.event_date,
            e.start_time, e.end_time, e.location, e.status AS e_status
     FROM volunteer_event_assignments a
     JOIN community_events e ON e.id = a.event_id
     WHERE a.volunteer_id = $volId
     ORDER BY e.event_date DESC"
);
$hoursRows = $db->query(
    "SELECT h.*, e.event_name FROM volunteer_hours h
     JOIN community_events e ON e.id = h.event_id
     WHERE h.volunteer_id = $volId ORDER BY h.work_date DESC"
);
$approvedHours = (float)$db->query("SELECT COALESCE(SUM(total_hours),0) s FROM volunteer_hours WHERE volunteer_id=$volId AND status='Approved'")->fetch_assoc()['s'];
$pendingHours  = (float)$db->query("SELECT COALESCE(SUM(total_hours),0) s FROM volunteer_hours WHERE volunteer_id=$volId AND status='Pending'")->fetch_assoc()['s'];

/* open opportunities: upcoming events with no assignment yet for this volunteer */
$openings = $db->query(
    "SELECT ce.id, ce.event_name, ce.event_date, ce.start_time, ce.location, ce.category
     FROM community_events ce
     LEFT JOIN volunteer_event_assignments a ON a.event_id = ce.id AND a.volunteer_id = $volId
     WHERE ce.status='Upcoming' AND ce.event_date >= CURDATE() AND a.id IS NULL
     ORDER BY ce.event_date ASC LIMIT 6"
);
$assignedEventIds = [];
if ($assignments) {
    $assignments->data_seek(0);
    while ($a = $assignments->fetch_assoc()) {
        /* only COMPLETED assignments are eligible for hours logging */
        if ($a['a_status'] === 'Completed') $assignedEventIds[] = (int)$a['event_id'];
    }
    $assignments->data_seek(0);   /* rewind so the assignments list below still shows all rows */
}
?>
<div class="c-hero small">
  <span class="c-tag">VOLUNTEER DASHBOARD</span>
  <h1>Welcome, Volunteer <?= e($vol['full_name'] ?? '') ?> &#127891;</h1>
  <p>Your assignments, contribution hours and open volunteer opportunities.</p>
</div>

<?php if (($_GET['need'] ?? '') === '1'): ?>
<div class="c-alert warn">&#9888; This page is for approved volunteers. To get access, submit an application
  from the <a href="volunteer.php">Become a Volunteer</a> page.</div>
<?php endif; ?>

<div class="c-grid cols-3" style="margin-bottom:22px;">
  <div class="c-card"><div class="c-stat"><div class="c-ico green">&#127891;</div><div><div class="num"><?= $approvedHours ? number_format($approvedHours, 1) : '0' ?>h</div><div class="lbl">Approved Volunteer Hours</div></div></div></div>
  <div class="c-card"><div class="c-stat"><div class="c-ico gold">&#9203;</div><div><div class="num"><?= $pendingHours ? number_format($pendingHours, 1) : '0' ?>h</div><div class="lbl">Hours Pending Approval</div></div></div></div>
  <div class="c-card"><div class="c-stat"><div class="c-ico navy">&#128197;</div><div><div class="num"><?= $assignments ? $assignments->num_rows : 0 ?></div><div class="lbl">Event Assignments</div></div></div></div>
</div>

<div class="c-grid cols-2" style="margin-bottom:22px;">
  <!-- ==== assignments ==== -->
  <div class="c-card c-vol-col">
    <h3 style="margin-top:0;">&#128197; My Assignments</h3>
    <?php if ($assignments && $assignments->num_rows):
      $assignments->data_seek(0);
      while ($a = $assignments->fetch_assoc()): $upcoming = ($a['event_date'] >= $today && $a['e_status'] !== 'Completed'); ?>
      <div class="c-statusline">
        <div style="flex:1;">
          <b><?= e($a['event_name']) ?></b>
          <div class="c-muted" style="font-size:12.5px;"><?= fmtDate($a['event_date']) ?> &middot; <?= substr($a['start_time'],0,5) ?> &middot; <?= e($a['location']) ?></div>
          <div style="margin-top:4px;">
            <span class="c-badge blue"><?= e($a['role']) ?></span>
            <?php if ($a['a_status'] === 'Completed'): ?><span class="c-badge green">COMPLETED</span>
            <?php elseif ($a['a_status'] === 'Withdrawn'): ?><span class="c-badge gray">WITHDRAWN</span>
            <?php elseif ($upcoming): ?><span class="c-badge gold">UPCOMING</span>
            <?php else: ?><span class="c-badge steel">IN PROGRESS</span><?php endif; ?>
          </div>
        </div>
      </div>
    <?php endwhile; else: ?>
      <p class="c-muted">No assignments yet. Check the open opportunities below and sign up.</p>
    <?php endif; ?>
  </div>

  <!-- ==== hours log form ==== -->
  <div class="c-card c-vol-col">
    <h3 style="margin-top:0;">&#9200; Log Volunteer Hours</h3>
    <?php if (!$assignedEventIds): ?>
      <p class="c-muted">Hours open for logging once an organiser marks one of your event assignments as <b>completed</b>.</p>
    <?php else: ?>
    <form method="post" class="c-hours-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="log_hours">
      <div class="c-field">
        <label>Event <b class="c-red-star">*</b></label>
        <select name="event_id" required>
          <option value="">— Choose assigned event —</option>
          <?php foreach ($assignedEventIds as $aeid): 
            $stmt = $db->prepare("SELECT event_name, event_date FROM community_events WHERE id=?"); 
            $stmt->bind_param('i',$aeid); $stmt->execute(); $ae = $stmt->get_result()->fetch_assoc(); $stmt->close(); ?>
            <option value="<?= $aeid ?>"><?= e($ae['event_name']) ?> (<?= fmtDate($ae['event_date']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="c-form-grid" style="grid-template-columns:1fr 1fr;">
        <div class="c-field"><label>Work date <b class="c-red-star">*</b></label>
          <input type="date" name="work_date" max="<?= $today ?>" required></div>
        <div class="c-field"><label>Role</label>
          <input type="text" name="role" maxlength="80" value="General Support"></div>
        <div class="c-field"><label>Start time <b class="c-red-star">*</b></label>
          <input type="time" name="start_time" required></div>
        <div class="c-field"><label>End time <b class="c-red-star">*</b></label>
          <input type="time" name="end_time" required></div>
      </div>
      <button class="c-btn gold sm" type="submit">Submit Hours for Approval</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<!-- ==== hours history ==== -->
<div class="c-card" style="margin-bottom:22px;">
  <h3 style="margin-top:0;">&#128337; My Hours History</h3>
  <?php if ($hoursRows && $hoursRows->num_rows): ?>
  <div class="c-table-wrap">
  <table class="c-table">
    <thead><tr><th>Event</th><th>Date</th><th>Role</th><th>Time</th><th>Hours</th><th>Status</th></tr></thead>
    <tbody>
    <?php while ($h = $hoursRows->fetch_assoc()): ?>
      <tr>
        <td><?= e($h['event_name']) ?></td>
        <td><?= fmtDate($h['work_date']) ?></td>
        <td><?= e($h['role']) ?></td>
        <td><?= substr($h['start_time'],0,5) ?>–<?= substr($h['end_time'],0,5) ?></td>
        <td><b><?= number_format($h['total_hours'], 2) ?></b></td>
        <td><?php if ($h['status'] === 'Approved'): ?><span class="c-badge green">APPROVED</span>
            <?php else: ?><span class="c-badge gold">PENDING</span><?php endif; ?></td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?><p class="c-muted">No hours logged yet.</p><?php endif; ?>
</div>

<!-- ==== open opportunities ==== -->
<div class="c-card">
  <h3 style="margin-top:0;">&#128161; Open Volunteer Opportunities</h3>
  <p class="c-muted" style="font-size:12.5px;">Upcoming events that still need volunteer support. Sign up and the organisers will confirm.</p>
  <?php if ($openings && $openings->num_rows): while ($o = $openings->fetch_assoc()): ?>
    <div class="c-statusline">
      <div style="flex:1;">
        <b><?= e($o['event_name']) ?></b> <span class="c-badge blue"><?= e($o['category']) ?></span>
        <div class="c-muted" style="font-size:12.5px;"><?= fmtDate($o['event_date']) ?> &middot; <?= substr($o['start_time'],0,5) ?> &middot; <?= e($o['location']) ?></div>
      </div>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="optin">
        <input type="hidden" name="event_id" value="<?= (int)$o['id'] ?>">
        <button class="c-btn gold sm">Sign Up</button>
      </form>
    </div>
  <?php endwhile; else: ?>
    <p class="c-muted">No open opportunities right now — new events appear here as they are scheduled.</p>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
