<?php
/**
 * admin/community/attendance.php — mark COMMUNITY EVENT attendance.
 *
 * For a selected event: lists every registration, lets the admin mark each
 * participant Present/Absent, and shows live statistics. Marking updates
 * both event_attendance AND the registration status (Attended/Missed).
 *
 * GET:  ?event=<id>          — select an event
 * POST: action = save (bulk) — CSRF protected
 */
$PAGE_TITLE = 'Event Attendance';
$PAGE_KEY   = 'community-attendance';
require_once __DIR__ . '/../../includes/header.php';

$db = db();
$adminId = (int)($_SESSION['admin_id'] ?? 0);

/* ------------------------------------------------------------------ */
/* POST — save attendance                                              */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action  = $_POST['action'] ?? '';
    $eventId = (int)($_POST['event_id'] ?? 0);
    $next    = 'attendance.php' . ($eventId ? "?event=$eventId" : '');

    if ($action === 'save' && $eventId) {
        $marks = $_POST['mark'] ?? [];   /* mark[<registration_id>] = Present|Absent */

        /* Event must exist */
        $stmt = $db->prepare("SELECT id, event_name FROM community_events WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $ev = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$ev) { header('Location: attendance.php?err=' . urlencode('Event not found.')); exit; }

        $db->begin_transaction();
        try {
            $ins = $db->prepare(
                "INSERT INTO event_attendance (event_id, community_user_id, status, marked_by)
                 VALUES (?,?,?,?)
                 ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by), marked_at = NOW()"
            );
            $updReg = $db->prepare(
                "UPDATE event_registrations SET status = ? WHERE community_user_id = ? AND event_id = ?"
            );
            $getReg = $db->prepare(
                "SELECT community_user_id FROM event_registrations WHERE id = ? AND event_id = ? LIMIT 1"
            );

            $present = 0; $absent = 0;
            foreach ($marks as $regId => $val) {
                $regId = (int)$regId;
                if ($val !== 'Present' && $val !== 'Absent') continue;
                $getReg->bind_param('ii', $regId, $eventId);
                $getReg->execute();
                $reg = $getReg->get_result()->fetch_assoc();
                if (!$reg) continue;
                $uid = (int)$reg['community_user_id'];

                $ins->bind_param('iisi', $eventId, $uid, $val, $adminId);
                $ins->execute();
                $regStatus = $val === 'Present' ? 'Attended' : 'Missed';
                $updReg->bind_param('sii', $regStatus, $uid, $eventId);
                $updReg->execute();
                $val === 'Present' ? $present++ : $absent++;
            }
            $ins->close(); $updReg->close(); $getReg->close();
            $db->commit();
            header('Location: ' . $next . '&ok=' . urlencode("Attendance saved for \"{$ev['event_name']}\": $present present, $absent absent."));
            exit;
        } catch (Throwable $ex) {
            $db->rollback();
            header('Location: ' . $next . '&err=' . urlencode('Could not save attendance (database error).'));
            exit;
        }
    }
    header('Location: attendance.php?err=' . urlencode('Unknown action.')); exit;
}

/* ------------------------------------------------------------------ */
/* GET page                                                            */
/* ------------------------------------------------------------------ */
/* Events with at least one active registration, newest first */
$events = $db->query(
    "SELECT e.id, e.event_name, e.event_date, e.status,
            COUNT(r.id) AS regs
     FROM community_events e
     JOIN event_registrations r ON r.event_id = e.id AND r.status IN ('Registered','Attended','Missed')
     GROUP BY e.id, e.event_name, e.event_date, e.status
     ORDER BY e.event_date DESC"
);

$sel = (int)($_GET['event'] ?? 0);
$event = null; $rows = null; $stats = null;

if ($sel) {
    $stmt = $db->prepare("SELECT * FROM community_events WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $sel);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($event) {
        /* All active (non-cancelled) registrations + existing attendance rows */
        $stmt = $db->prepare(
            "SELECT r.id AS reg_id, r.status AS reg_status, u.id AS uid, u.full_name, u.mobile, a.status AS att
             FROM event_registrations r
             JOIN community_users u ON u.id = r.community_user_id
             LEFT JOIN event_attendance a ON a.event_id = r.event_id AND a.community_user_id = r.community_user_id
             WHERE r.event_id = ? AND r.status IN ('Registered','Attended','Missed')
             ORDER BY u.full_name"
        );
        $stmt->bind_param('i', $sel);
        $stmt->execute();
        $rows = $stmt->get_result();
        $stmt->close();

        /* Stats for this event */
        $stmt = $db->prepare(
            "SELECT
               COUNT(*) AS total,
               SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) AS present,
               SUM(CASE WHEN a.status = 'Absent'  THEN 1 ELSE 0 END) AS absent
             FROM event_registrations r
             LEFT JOIN event_attendance a ON a.event_id = r.event_id AND a.community_user_id = r.community_user_id
             WHERE r.event_id = ? AND r.status IN ('Registered','Attended','Missed')"
        );
        $stmt->bind_param('i', $sel);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}
?>
<?= flash() ?>
<div class="page-head">
  <div>
    <h2>Event Attendance</h2>
    <p>Mark community participants present or absent after conducting an event. Attendance powers event impact statistics.</p>
  </div>
</div>
<?php include __DIR__ . '/_nav.php'; ?>

<?php if (!$events || $events->num_rows === 0): ?>
  <div class="card"><div class="card-body">
    <p class="muted" style="margin:0">No events with registrations yet. Events appear here once residents start registering.</p>
  </div></div>
<?php else: ?>
<div class="card" style="margin-bottom:18px">
  <div class="card-head"><h3>1 &middot; Select Event</h3></div>
  <div class="card-body">
    <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
      <div class="form-field" style="flex:1;min-width:280px">
        <label>Event</label>
        <select name="event" onchange="this.form.submit()">
          <?php
          $events->data_seek(0);
          while ($ev = $events->fetch_assoc()): ?>
            <option value="<?= $ev['id'] ?>" <?= $sel === (int)$ev['id'] ? 'selected' : '' ?>>
              #<?= $ev['id'] ?> &middot; <?= e($ev['event_name']) ?> &middot; <?= fmtDate($ev['event_date']) ?> &middot; <?= (int)$ev['regs'] ?> reg.
            </option>
          <?php endwhile; ?>
        </select>
      </div>
      <button class="btn btn-navy" type="submit">Load</button>
    </form>
  </div>
</div>

<?php if ($event && $rows): $total = (int)$stats['total']; $present = (int)$stats['present']; $absent = (int)$stats['absent']; $pct = $total ? round($present / $total * 100) : 0; ?>
<div class="grid cols-3" style="margin-bottom:18px">
  <div class="stat"><div class="stat-ico navy">&#9635;</div><div><div class="stat-val"><?= $total ?></div><div class="stat-lbl">Participants</div></div></div>
  <div class="stat"><div class="stat-ico green">&#10003;</div><div><div class="stat-val"><?= $present ?></div><div class="stat-lbl">Present</div></div></div>
  <div class="stat"><div class="stat-ico red">&#10007;</div><div><div class="stat-val"><?= $absent ?></div><div class="stat-lbl">Absent</div></div></div>
</div>

<div class="card">
  <div class="card-head">
    <h3>2 &middot; Mark Attendance — <?= e($event['event_name']) ?></h3>
    <span class="badge <?= $event['status'] === 'Completed' ? 'green' : 'gold' ?>"><?= e($event['status']) ?></span>
  </div>
  <div class="card-body">
    <form method="post" id="attForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="event_id" value="<?= $event['id'] ?>">

      <div class="comm-note" style="margin-bottom:14px">
        <b>Tip:</b> use <b>Mark all Present</b> then switch absentees to <b>Absent</b>. Registration
        status is updated automatically (Attended / Missed). Saving again overwrites previous marks.
      </div>

      <div class="table-wrap">
        <table class="data" id="attTable">
          <thead>
            <tr><th>#</th><th>Participant</th><th>Registration</th><th>Attendance</th></tr>
          </thead>
          <tbody>
          <?php $i = 0; $rows->data_seek(0); while ($r = $rows->fetch_assoc()): $i++; ?>
            <tr <?= $r['att'] ? 'class="comm-mark-row"' : '' ?>>
              <td><?= $i ?></td>
              <td><b><?= e($r['full_name']) ?></b><br><small class="muted"><?= e($r['mobile']) ?></small></td>
              <td>
                <?php $rmap = ['Registered' => 'gray', 'Attended' => 'green', 'Missed' => 'red', 'Cancelled' => 'red']; ?>
                <span class="badge <?= $rmap[$r['reg_status']] ?? 'gray' ?>"><?= e($r['reg_status']) ?></span>
              </td>
              <td>
                <label style="margin-right:14px;font-size:13px">
                  <input type="radio" name="mark[<?= $r['reg_id'] ?>]" value="Present" <?= $r['att'] === 'Present' ? 'checked' : '' ?>> Present
                </label>
                <label style="font-size:13px">
                  <input type="radio" name="mark[<?= $r['reg_id'] ?>]" value="Absent" <?= $r['att'] === 'Absent' ? 'checked' : '' ?>> Absent
                </label>
              </td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>

      <div class="form-actions" style="margin-top:16px">
        <button type="button" class="btn btn-ghost" onclick="markAll('Present')">Mark all Present</button>
        <button type="button" class="btn btn-ghost" onclick="markAll('Absent')">Mark all Absent</button>
        <button type="submit" class="btn btn-primary">Save Attendance</button>
      </div>
    </form>
  </div>
</div>

<script>
function markAll(v) {
  document.querySelectorAll('#attTable input[type=radio][value="' + v + '"]').forEach(function (r) { r.checked = true; });
}
</script>
<?php elseif ($event): ?>
  <div class="card"><div class="card-body"><p class="muted" style="margin:0">No active registrations for this event.</p></div></div>
<?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
