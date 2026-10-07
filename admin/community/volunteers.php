<?php
/**
 * admin/community/volunteers.php — volunteer management.
 *
 * Tabs:
 *   apps   (default) — review Pending / Approved / Rejected applications
 *   assign           — assign volunteers to events, manage assignments
 *   hours            — approve / reject submitted volunteer hours
 *
 * POST actions (CSRF protected):
 *   review_app   — approve / reject an application
 *   assign       — assign volunteer to an upcoming event
 *   unassign     — withdraw an assignment
 *   complete     — mark an assignment completed
 *   review_hours — approve / reject a hours log (Approved adds to total_hours)
 *   deactivate   — deactivate a volunteer record
 */
$PAGE_TITLE = 'Volunteers';
$PAGE_KEY   = 'community-volunteers';
require_once __DIR__ . '/../../includes/header.php';

$db = db();
$adminId = (int)($_SESSION['admin_id'] ?? 0);

/* ------------------------------------------------------------------ */
/* POST actions                                                        */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = $_POST['action'] ?? '';

    /* ---------- review_app ---------- */
    if ($action === 'review_app') {
        $appId = (int)($_POST['app_id'] ?? 0);
        $dec   = $_POST['decision'] ?? '';
        if ($dec !== 'Approved' && $dec !== 'Rejected') {
            header('Location: volunteers.php?err=' . urlencode('Invalid decision.')); exit;
        }
        $stmt = $db->prepare(
            "SELECT va.*, u.full_name FROM volunteer_applications va
             JOIN community_users u ON u.id = va.community_user_id
             WHERE va.id = ? LIMIT 1");
        $stmt->bind_param('i', $appId);
        $stmt->execute();
        $app = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$app) { header('Location: volunteers.php?err=' . urlencode('Application not found.')); exit; }

        $db->begin_transaction();
        try {
            $stmt = $db->prepare("UPDATE volunteer_applications SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
            $stmt->bind_param('sii', $dec, $adminId, $appId);
            $stmt->execute();
            $stmt->close();

            if ($dec === 'Approved') {
                $stmt = $db->prepare(
                    "INSERT INTO volunteers (community_user_id, application_id, status, total_hours)
                     VALUES (?, ?, 'Active', 0)
                     ON DUPLICATE KEY UPDATE application_id = VALUES(application_id), status = 'Active'");
                $stmt->bind_param('ii', $app['community_user_id'], $appId);
                $stmt->execute();
                $stmt->close();
            }
            $db->commit();
            $msg = $dec === 'Approved'
                ? "Application #{$appId} approved — \"{$app['full_name']}\" is now a volunteer. Assign them to an event from the Assignments tab."
                : "Application #{$appId} rejected.";
            header('Location: volunteers.php?ok=' . urlencode($msg)); exit;
        } catch (Throwable $ex) {
            $db->rollback();
            header('Location: volunteers.php?err=' . urlencode('Could not process the application (database error).')); exit;
        }
    }

    /* ---------- assign ---------- */
    if ($action === 'assign') {
        $volId = (int)($_POST['volunteer_id'] ?? 0);
        $evId  = (int)($_POST['event_id'] ?? 0);
        $role  = mb_substr(trim($_POST['role'] ?? ''), 0, 80);
        if ($role === '') $role = 'General Support';

        if (!$volId || !$evId) { header('Location: volunteers.php?tab=assign&err=' . urlencode('Choose a volunteer and an event.')); exit; }

        $stmt = $db->prepare("SELECT v.id, u.full_name FROM volunteers v JOIN community_users u ON u.id = v.community_user_id WHERE v.id = ? AND v.status = 'Active' LIMIT 1");
        $stmt->bind_param('i', $volId);
        $stmt->execute();
        $vol = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$vol) { header('Location: volunteers.php?tab=assign&err=' . urlencode('Volunteer not found or inactive.')); exit; }

        $stmt = $db->prepare("SELECT id, event_name FROM community_events WHERE id = ? AND event_date >= CURDATE() AND status IN ('Upcoming','Ongoing') LIMIT 1");
        $stmt->bind_param('i', $evId);
        $stmt->execute();
        $ev = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$ev) { header('Location: volunteers.php?tab=assign&err=' . urlencode('Event must be upcoming / ongoing to accept assignments.')); exit; }

        $stmt = $db->prepare("SELECT id FROM volunteer_event_assignments WHERE volunteer_id = ? AND event_id = ? AND status <> 'Withdrawn' LIMIT 1");
        $stmt->bind_param('ii', $volId, $evId);
        $stmt->execute();
        $dup = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($dup) { header('Location: volunteers.php?tab=assign&err=' . urlencode('This volunteer is already assigned to that event.')); exit; }

        $stmt = $db->prepare("INSERT INTO volunteer_event_assignments (volunteer_id, event_id, role, assigned_by) VALUES (?,?,?,?)");
        $stmt->bind_param('iisi', $volId, $evId, $role, $adminId);
        $stmt->execute();
        $stmt->close();
        header('Location: volunteers.php?tab=assign&ok=' . urlencode("Volunteer \"{$vol['full_name']}\" assigned to \"{$ev['event_name']}\" as \"$role\".")); exit;
    }

    /* ---------- unassign ---------- */
    if ($action === 'unassign') {
        $aid = (int)($_POST['assignment_id'] ?? 0);
        $stmt = $db->prepare("UPDATE volunteer_event_assignments SET status = 'Withdrawn' WHERE id = ?");
        $stmt->bind_param('i', $aid);
        $stmt->execute();
        $stmt->close();
        header('Location: volunteers.php?tab=assign&ok=' . urlencode("Assignment #$aid withdrawn.")); exit;
    }

    /* ---------- complete ---------- */
    if ($action === 'complete') {
        $aid = (int)($_POST['assignment_id'] ?? 0);
        $stmt = $db->prepare("UPDATE volunteer_event_assignments SET status = 'Completed' WHERE id = ? AND status IN ('Assigned','In Progress')");
        $stmt->bind_param('i', $aid);
        $stmt->execute();
        $stmt->close();
        header('Location: volunteers.php?tab=assign&ok=' . urlencode("Assignment #$aid marked completed — the volunteer can now log their hours.")); exit;
    }

    /* ---------- review_hours ---------- */
    if ($action === 'review_hours') {
        $hid = (int)($_POST['hours_id'] ?? 0);
        $dec = $_POST['decision'] ?? '';
        if ($dec !== 'Approved' && $dec !== 'Rejected') { header('Location: volunteers.php?tab=hours&err=' . urlencode('Invalid decision.')); exit; }

        $stmt = $db->prepare(
            "SELECT vh.*, u.full_name FROM volunteer_hours vh
             JOIN volunteers v ON v.id = vh.volunteer_id
             JOIN community_users u ON u.id = v.community_user_id
             WHERE vh.id = ? LIMIT 1");
        $stmt->bind_param('i', $hid);
        $stmt->execute();
        $h = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$h) { header('Location: volunteers.php?tab=hours&err=' . urlencode('Hours log not found.')); exit; }

        $db->begin_transaction();
        try {
            if ($dec === 'Approved') {
                $stmt = $db->prepare("UPDATE volunteer_hours SET status = 'Approved' WHERE id = ? AND status = 'Pending'");
                $stmt->bind_param('i', $hid);
                $stmt->execute();
                $changed = $stmt->affected_rows;
                $stmt->close();
                if ($changed > 0) {
                    $stmt = $db->prepare("UPDATE volunteers SET total_hours = total_hours + ? WHERE id = ?");
                    $stmt->bind_param('di', $h['total_hours'], $h['volunteer_id']);
                    $stmt->execute();
                    $stmt->close();
                }
                $db->commit();
                header('Location: volunteers.php?tab=hours&ok=' . urlencode("Hours log #$hid approved — {$h['total_hours']} h added to \"{$h['full_name']}\".")); exit;
            }
            $stmt = $db->prepare("UPDATE volunteer_hours SET status = 'Rejected' WHERE id = ? AND status = 'Pending'");
            $stmt->bind_param('i', $hid);
            $stmt->execute();
            $stmt->close();
            $db->commit();
            header('Location: volunteers.php?tab=hours&ok=' . urlencode("Hours log #$hid rejected.")); exit;
        } catch (Throwable $ex) {
            $db->rollback();
            header('Location: volunteers.php?tab=hours&err=' . urlencode('Could not review hours (database error).')); exit;
        }
    }

    /* ---------- deactivate ---------- */
    if ($action === 'deactivate') {
        $volId = (int)($_POST['volunteer_id'] ?? 0);
        $stmt = $db->prepare("UPDATE volunteers SET status = 'Inactive' WHERE id = ?");
        $stmt->bind_param('i', $volId);
        $stmt->execute();
        $stmt->close();
        header('Location: volunteers.php?tab=assign&ok=' . urlencode("Volunteer #$volId deactivated. They can re-apply through the community portal.")); exit;
    }

    header('Location: volunteers.php?err=' . urlencode('Unknown action.')); exit;
}

/* ------------------------------------------------------------------ */
/* GET page                                                            */
/* ------------------------------------------------------------------ */
$tab = $_GET['tab'] ?? 'apps';
if (!in_array($tab, ['apps', 'assign', 'hours'], true)) $tab = 'apps';

$apps = $db->query(
    "SELECT va.*, u.full_name, u.email, u.mobile, v.id AS vol_id
     FROM volunteer_applications va
     JOIN community_users u ON u.id = va.community_user_id
     LEFT JOIN volunteers v ON v.application_id = va.id
     ORDER BY FIELD(va.status,'Pending','Approved','Rejected'), va.created_at DESC"
);

$volunteers = $db->query(
    "SELECT v.*, u.full_name,
            (SELECT COUNT(*) FROM volunteer_event_assignments a
              WHERE a.volunteer_id = v.id AND a.status IN ('Assigned','In Progress','Completed')) AS assignments
     FROM volunteers v JOIN community_users u ON u.id = v.community_user_id
     ORDER BY v.status = 'Active' DESC, v.total_hours DESC"
);

$assignments = $db->query(
    "SELECT a.*, u.full_name, e.event_name, e.event_date, e.status AS event_status
     FROM volunteer_event_assignments a
     JOIN volunteers v ON v.id = a.volunteer_id
     JOIN community_users u ON u.id = v.community_user_id
     JOIN community_events e ON e.id = a.event_id
     ORDER BY e.event_date DESC, a.id DESC"
);

$hoursLogs = $db->query(
    "SELECT vh.*, u.full_name, e.event_name
     FROM volunteer_hours vh
     JOIN volunteers v ON v.id = vh.volunteer_id
     JOIN community_users u ON u.id = v.community_user_id
     LEFT JOIN community_events e ON e.id = vh.event_id
     ORDER BY FIELD(vh.status,'Pending','Approved','Rejected'), vh.created_at DESC"
);

$upcomingEvents = $db->query(
    "SELECT id, event_name, event_date FROM community_events
     WHERE event_date >= CURDATE() AND status IN ('Upcoming','Ongoing')
     ORDER BY event_date"
);
?>
<?= flash() ?>
<div class="page-head">
  <div>
    <h2>Volunteers</h2>
    <p>Review applications, assign volunteers to events and approve their logged hours.</p>
  </div>
</div>
<?php include __DIR__ . '/_nav.php'; ?>

<div class="comm-tabs" style="margin-bottom:16px">
  <a href="volunteers.php?tab=apps"   class="<?= $tab === 'apps' ? 'on' : '' ?>">&#9997; Applications</a>
  <a href="volunteers.php?tab=assign" class="<?= $tab === 'assign' ? 'on' : '' ?>">&#128197; Assignments</a>
  <a href="volunteers.php?tab=hours"  class="<?= $tab === 'hours' ? 'on' : '' ?>">&#9201; Hours Approval</a>
</div>

<?php if ($tab === 'apps'): ?>
  <?php if ($apps && $apps->num_rows): ?>
    <div class="grid cols-2">
    <?php while ($a = $apps->fetch_assoc()): ?>
      <div class="card comm-app-card">
        <div class="card-head">
          <h3><?= e($a['full_name']) ?></h3>
          <span class="badge <?= $a['status'] === 'Pending' ? 'gold' : ($a['status'] === 'Approved' ? 'green' : 'red') ?>"><?= e($a['status']) ?></span>
        </div>
        <div class="card-body">
          <div style="font-size:13px;color:var(--muted);margin-bottom:10px">
            <?= e($a['email']) ?> &middot; <?= e($a['mobile']) ?> &middot; applied <?= fmtDate(substr($a['created_at'], 0, 10)) ?>
          </div>
          <table class="data" style="font-size:13px">
            <tr><th style="width:32%">Skills</th><td><?= e($a['skills']) ?></td></tr>
            <tr><th>Interest areas</th><td><?= e($a['interest_areas']) ?></td></tr>
            <tr><th>Availability</th><td><?= e($a['availability']) ?></td></tr>
            <?php if ($a['previous_experience']): ?><tr><th>Experience</th><td><?= e($a['previous_experience']) ?></td></tr><?php endif; ?>
            <?php if ($a['reason']): ?><tr><th>Reason</th><td><?= e($a['reason']) ?></td></tr><?php endif; ?>
          </table>
          <?php if ($a['status'] === 'Pending'): ?>
            <div class="form-actions" style="margin-top:14px">
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="review_app">
                <input type="hidden" name="app_id" value="<?= $a['id'] ?>">
                <input type="hidden" name="decision" value="Approved">
                <button class="btn btn-primary btn-sm" type="submit">&#10003; Approve as Volunteer</button>
              </form>
              <form method="post" style="display:inline" data-confirm="Reject this volunteer application?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="review_app">
                <input type="hidden" name="app_id" value="<?= $a['id'] ?>">
                <input type="hidden" name="decision" value="Rejected">
                <button class="btn btn-danger btn-sm" type="submit">&#10007; Reject</button>
              </form>
            </div>
          <?php elseif ($a['status'] === 'Approved' && $a['vol_id']): ?>
            <div class="form-actions" style="margin-top:14px">
              <a class="btn btn-navy btn-sm" href="volunteers.php?tab=assign">&#128197; Assign to an event &rarr;</a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endwhile; ?>
    </div>
  <?php else: ?>
    <div class="card"><div class="card-body"><p class="muted" style="margin:0">No volunteer applications yet. Residents apply from <b>community/volunteer.php</b>.</p></div></div>
  <?php endif; ?>

<?php elseif ($tab === 'assign'): ?>
  <?php
  $activeVols = [];
  if ($volunteers) { $volunteers->data_seek(0); while ($v = $volunteers->fetch_assoc()) { if ($v['status'] === 'Active') $activeVols[] = $v; } }
  ?>
  <div class="card" style="margin-bottom:18px">
    <div class="card-head"><h3>Assign Volunteer to Event</h3></div>
    <div class="card-body">
      <?php if ($activeVols && $upcomingEvents && $upcomingEvents->num_rows): ?>
        <form method="post" class="form-grid">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="assign">
          <div class="form-field">
            <label>Volunteer <span class="req">*</span></label>
            <select name="volunteer_id" required>
              <option value="">— Choose an active volunteer —</option>
              <?php foreach ($activeVols as $v): ?>
                <option value="<?= $v['id'] ?>"><?= e($v['full_name']) ?> — <?= number_format((float)$v['total_hours'], 1) ?> h</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-field">
            <label>Event <span class="req">*</span></label>
            <select name="event_id" required>
              <option value="">— Choose an upcoming event —</option>
              <?php while ($ev = $upcomingEvents->fetch_assoc()): ?>
                <option value="<?= $ev['id'] ?>"><?= e($ev['event_name']) ?> — <?= fmtDate($ev['event_date']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="form-field full">
            <label>Role</label>
            <input type="text" name="role" maxlength="80" placeholder="General Support" value="General Support">
            <span class="hint">e.g. Registration Desk, Setup Crew, Refreshments, Route Marshal</span>
          </div>
          <div class="form-actions"><button class="btn btn-primary" type="submit">Assign</button></div>
        </form>
      <?php else: ?>
        <p class="muted" style="margin:0">
          <?php if (!$activeVols): ?>No active volunteers — approve an application from the <a href="volunteers.php?tab=apps">Applications</a> tab first.<?php else: ?>No upcoming events — create one on the <a href="events.php">Events</a> page.<?php endif; ?>
        </p>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($volunteers && $volunteers->num_rows): ?>
  <div class="card" style="margin-bottom:18px">
    <div class="card-head"><h3>Volunteer Roster</h3></div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>ID</th><th>Volunteer</th><th>Status</th><th>Assignments</th><th>Total Hours</th><th class="no-sort">Actions</th></tr></thead>
          <tbody>
          <?php $volunteers->data_seek(0); while ($v = $volunteers->fetch_assoc()): ?>
            <tr>
              <td>#<?= $v['id'] ?></td>
              <td><b><?= e($v['full_name']) ?></b></td>
              <td><?= $v['status'] === 'Active' ? '<span class="badge green">Active</span>' : '<span class="badge gray">Inactive</span>' ?></td>
              <td><?= (int)$v['assignments'] ?></td>
              <td><?= number_format((float)$v['total_hours'], 1) ?> h</td>
              <td class="row-actions">
                <?php if ($v['status'] === 'Active'): ?>
                  <form method="post" style="display:inline" data-confirm="Deactivate this volunteer?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="deactivate">
                    <input type="hidden" name="volunteer_id" value="<?= $v['id'] ?>">
                    <button class="btn btn-warning btn-sm" type="submit">Deactivate</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($assignments && $assignments->num_rows): ?>
  <div class="card">
    <div class="card-head"><h3>All Assignments</h3></div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>ID</th><th>Volunteer</th><th>Event</th><th>Date</th><th>Role</th><th>Status</th><th class="no-sort">Actions</th></tr></thead>
          <tbody>
          <?php while ($a = $assignments->fetch_assoc()): ?>
            <tr>
              <td>#<?= $a['id'] ?></td>
              <td><b><?= e($a['full_name']) ?></b></td>
              <td><?= e($a['event_name']) ?></td>
              <td><?= fmtDate($a['event_date']) ?></td>
              <td><?= e($a['role']) ?></td>
              <td><span class="badge <?= $a['status'] === 'Completed' ? 'green' : ($a['status'] === 'Withdrawn' ? 'red' : 'steel') ?>"><?= e($a['status']) ?></span></td>
              <td class="row-actions">
                <?php if ($a['status'] === 'Assigned'): ?>
                  <form method="post" style="display:inline" data-confirm="Withdraw this assignment?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="unassign">
                    <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
                    <button class="btn btn-danger btn-sm" type="submit">Withdraw</button>
                  </form>
                <?php elseif ($a['status'] === 'In Progress'): ?>
                  <form method="post" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="complete">
                    <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
                    <button class="btn btn-navy btn-sm" type="submit">Mark Completed</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>

<?php else: /* hours tab */ ?>
  <?php if ($hoursLogs && $hoursLogs->num_rows): ?>
  <div class="card">
    <div class="card-head"><h3>Volunteer Hours Logs</h3></div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>ID</th><th>Volunteer</th><th>Event</th><th>Work Date</th><th>Time</th><th>Hours</th><th>Status</th><th class="no-sort">Actions</th></tr></thead>
          <tbody>
          <?php while ($h = $hoursLogs->fetch_assoc()): ?>
            <tr>
              <td>#<?= $h['id'] ?></td>
              <td><b><?= e($h['full_name']) ?></b></td>
              <td><?= e($h['event_name'] ?: '—') ?></td>
              <td><?= fmtDate($h['work_date']) ?></td>
              <td><?= e(substr($h['start_time'], 0, 5)) ?>&ndash;<?= e(substr($h['end_time'], 0, 5)) ?></td>
              <td><b><?= number_format((float)$h['total_hours'], 1) ?> h</b></td>
              <td><span class="badge <?= $h['status'] === 'Approved' ? 'green' : ($h['status'] === 'Rejected' ? 'red' : 'gold') ?>"><?= e($h['status']) ?></span></td>
              <td class="row-actions">
                <?php if ($h['status'] === 'Pending'): ?>
                  <form method="post" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="review_hours">
                    <input type="hidden" name="hours_id" value="<?= $h['id'] ?>">
                    <input type="hidden" name="decision" value="Approved">
                    <button class="btn btn-primary btn-sm" type="submit">&#10003; Approve</button>
                  </form>
                  <form method="post" style="display:inline" data-confirm="Reject these logged hours?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="review_hours">
                    <input type="hidden" name="hours_id" value="<?= $h['id'] ?>">
                    <input type="hidden" name="decision" value="Rejected">
                    <button class="btn btn-danger btn-sm" type="submit">&#10007; Reject</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php else: ?>
    <div class="card"><div class="card-body"><p class="muted" style="margin:0">No hours logged yet. Volunteers log hours from their dashboard after an assignment is completed.</p></div></div>
  <?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
