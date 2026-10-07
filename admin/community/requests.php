<?php
/**
 * admin/community/requests.php — community request workflow.
 *
 * Pipeline: Submitted → Under Review → Approved → Scheduled → Completed
 *           (or Rejected at any stage)
 *
 * POST actions (CSRF protected):
 *   set_status   — move a request along the pipeline
 *   note         — add/replace the admin note shown to the requester
 *   convert      — convert an approved request into a real community event
 */
$PAGE_TITLE = 'Community Requests';
$PAGE_KEY   = 'community-requests';
require_once __DIR__ . '/../../includes/header.php';

$db  = db();
$PIPELINE = ['Submitted', 'Under Review', 'Approved', 'Scheduled', 'Completed'];

/* ------------------------------------------------------------------ */
/* POST actions                                                        */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = $_POST['action'] ?? '';
    $rid    = (int)($_POST['request_id'] ?? 0);
    $bail   = function (string $m) { header('Location: requests.php?err=' . urlencode($m)); exit; };
    if (!$rid) $bail('Invalid request id.');

    /* Fetch request */
    $stmt = $db->prepare(
        "SELECT r.*, u.full_name FROM community_requests r
         JOIN community_users u ON u.id = r.community_user_id WHERE r.id = ? LIMIT 1");
    $stmt->bind_param('i', $rid);
    $stmt->execute();
    $req = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$req) $bail('Request not found.');

    /* ---------- set_status ---------- */
    if ($action === 'set_status') {
        $st = $_POST['status'] ?? '';
        $valid = array_merge($PIPELINE, ['Rejected']);
        if (!in_array($st, $valid, true)) $bail('Invalid status.');
        if (!in_array($req['status'], $PIPELINE, true) && $st !== 'Rejected') {
            $bail('This request is already closed.');
        }
        $stmt = $db->prepare("UPDATE community_requests SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $st, $rid);
        $stmt->execute();
        $stmt->close();
        header('Location: requests.php?ok=' . urlencode("Request #{$rid} (\"{$req['title']}\") marked $st.")); exit;
    }

    /* ---------- note ---------- */
    if ($action === 'note') {
        $note = mb_substr(trim($_POST['admin_note'] ?? ''), 0, 255);
        $stmt = $db->prepare("UPDATE community_requests SET admin_note = ? WHERE id = ?");
        $stmt->bind_param('si', $note, $rid);
        $stmt->execute();
        $stmt->close();
        header('Location: requests.php?ok=' . urlencode("Note saved for request #{$rid} — the requester will see it in their portal.")); exit;
    }

    /* ---------- convert to event ---------- */
    if ($action === 'convert') {
        if ($req['status'] !== 'Approved') {
            $bail('Only Approved requests can be converted into events.');
        }
        $event_name = mb_substr(trim($_POST['event_name'] ?? ''), 0, 160);
        $event_date = trim($_POST['event_date'] ?? '');
        $start_time = trim($_POST['start_time'] ?? '06:00');
        $end_time   = trim($_POST['end_time'] ?? '08:00');
        $location   = mb_substr(trim($_POST['location'] ?? ''), 0, 200);
        $max_part   = (int)($_POST['max_participants'] ?? 50);
        $categoryMap = [
            'Yoga Sessions'           => 'Yoga',
            'Fitness Camp'            => 'Fitness Camp',
            'Health Camp'             => 'Health Awareness',
            'Nutrition Workshop'      => 'Nutrition Workshop',
            'Senior Fitness Program'  => 'Senior Fitness',
            'Women Wellness Program'  => 'Women Wellness',
            'Other'                   => 'Other',
        ];
        $category = $categoryMap[$req['request_type']] ?? 'Other';

        if ($event_name === '') $event_name = $req['title'];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date)) $bail('A valid event date (YYYY-MM-DD) is required.');
        if ($location === '') $bail('Location is required.');
        if ($max_part < 1 || $max_part > 10000) $bail('Max participants must be 1–10,000.');
        if ($start_time >= $end_time) $bail('End time must be after start time.');
        if ($event_date <= date('Y-m-d')) $bail('Converted event must be in the future.');

        $db->begin_transaction();
        try {
            $desc = "Converted from community request #{$rid} (\"{$req['title']}\", type: {$req['request_type']}) "
                  . "requested by {$req['full_name']}. Original description: " . ($req['description'] ?: '—');
            $stmt = $db->prepare(
                "INSERT INTO community_events
                   (event_name, description, category, event_date, start_time, end_time,
                    location, organizer, max_participants, status)
                 VALUES (?,?,?,?,?,?,?,?,?, 'Upcoming')");
            $organizer = 'New Life Fitness — Community Team';
            $stmt->bind_param('sssssssis',
                $event_name, $desc, $category, $event_date, $start_time, $end_time,
                $location, $organizer, $max_part);
            $stmt->execute();
            $newEventId = $stmt->insert_id;
            $stmt->close();

            $stmt = $db->prepare("UPDATE community_requests SET status = 'Scheduled', converted_event_id = ? WHERE id = ?");
            $stmt->bind_param('ii', $newEventId, $rid);
            $stmt->execute();
            $stmt->close();

            $db->commit();
            header('Location: requests.php?ok=' . urlencode("Request #{$rid} converted — event \"$event_name\" (#$newEventId) created and the request is now Scheduled.")); exit;
        } catch (Throwable $ex) {
            $db->rollback();
            $bail('Could not convert the request (database error).');
        }
    }

    $bail('Unknown action.');
}

/* ------------------------------------------------------------------ */
/* GET page                                                            */
/* ------------------------------------------------------------------ */
$statusFilter = $_GET['status'] ?? '';
$validStatuses = array_merge($PIPELINE, ['Rejected']);
if (!in_array($statusFilter, $validStatuses, true)) $statusFilter = '';

$reqs = null;
if ($statusFilter) {
    $stmt = $db->prepare(
        "SELECT r.*, u.full_name, u.email, u.mobile, e.event_name AS converted_event
         FROM community_requests r
         JOIN community_users u ON u.id = r.community_user_id
         LEFT JOIN community_events e ON e.id = r.converted_event_id
         WHERE r.status = ?
         ORDER BY r.created_at DESC");
    $stmt->bind_param('s', $statusFilter);
    $stmt->execute();
    $reqs = $stmt->get_result();
    $stmt->close();
} else {
    $reqs = $db->query(
        "SELECT r.*, u.full_name, u.email, u.mobile, e.event_name AS converted_event
         FROM community_requests r
         JOIN community_users u ON u.id = r.community_user_id
         LEFT JOIN community_events e ON e.id = r.converted_event_id
         ORDER BY FIELD(r.status,'Submitted','Under Review','Approved','Scheduled','Completed','Rejected'), r.created_at DESC");
}

/* Pipeline counts for the visual stepper */
$counts = ['Submitted' => 0, 'Under Review' => 0, 'Approved' => 0, 'Scheduled' => 0, 'Completed' => 0, 'Rejected' => 0];
$res = $db->query("SELECT status, COUNT(*) c FROM community_requests GROUP BY status");
while ($row = $res->fetch_assoc()) $counts[$row['status']] = (int)$row['c'];
?>
<?= flash() ?>
<div class="page-head">
  <div>
    <h2>Community Requests</h2>
    <p>Requests from residents for new programmes. Review, approve and convert them into real events.</p>
  </div>
</div>
<?php include __DIR__ . '/_nav.php'; ?>

<div class="comm-pipeline">
  <?php foreach ($PIPELINE as $i => $stage): ?>
    <span class="pl-step <?= $counts[$stage] ? 'done' : '' ?>">
      <b><?= $i + 1 ?></b> <?= e($stage) ?> <span class="badge gray"><?= $counts[$stage] ?></span>
    </span>
    <?php if ($i < count($PIPELINE) - 1): ?><span class="pl-arrow">&rarr;</span><?php endif; ?>
  <?php endforeach; ?>
  <span class="pl-step" style="border-color:#f0c9c9;color:#a52a2a">&#10007; Rejected <span class="badge red"><?= $counts['Rejected'] ?></span></span>
</div>

<div class="toolbar">
  <div class="search" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
    <b style="font-size:12px;color:var(--muted)">STATUS:</b>
    <a class="btn btn-ghost btn-sm <?= $statusFilter === '' ? 'btn-navy' : '' ?>" href="requests.php">All</a>
    <?php foreach ($validStatuses as $s): ?>
      <a class="btn btn-ghost btn-sm <?= $statusFilter === $s ? 'btn-navy' : '' ?>" href="requests.php?status=<?= urlencode($s) ?>"><?= $s ?></a>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($reqs && $reqs->num_rows): ?>
  <?php while ($r = $reqs->fetch_assoc()):
      $idx = array_search($r['status'], $PIPELINE, true);
      $nextStage = ($idx !== false && $idx < count($PIPELINE) - 1) ? $PIPELINE[$idx + 1] : null;
  ?>
  <div class="card" style="margin-bottom:14px">
    <div class="card-head">
      <h3>#<?= $r['id'] ?> &middot; <?= e($r['title']) ?></h3>
      <div style="display:flex;gap:8px;align-items:center">
        <span class="badge steel"><?= e($r['request_type']) ?></span>
        <?php
          $badgeMap = ['Submitted' => 'gray', 'Under Review' => 'gold', 'Approved' => 'green',
                       'Scheduled' => 'steel', 'Completed' => 'green', 'Rejected' => 'red'];
        ?>
        <span class="badge <?= $badgeMap[$r['status']] ?? 'gray' ?>"><?= e($r['status']) ?></span>
      </div>
    </div>
    <div class="card-body">
      <div style="font-size:13px;color:var(--muted);margin-bottom:8px">
        By <b><?= e($r['full_name']) ?></b> (<?= e($r['email']) ?>, <?= e($r['mobile']) ?>) &middot; submitted <?= fmtDate(substr($r['created_at'], 0, 10)) ?>
      </div>
      <p style="margin:0 0 12px"><?= e($r['description'] ?: '—') ?></p>

      <?php if ($r['converted_event']): ?>
        <div class="comm-note" style="margin-bottom:12px">
          &#127881; Converted to event: <b><?= e($r['converted_event']) ?></b> — manage it on the
          <a href="events.php">Events</a> page.
        </div>
      <?php endif; ?>

      <?php if (in_array($r['status'], $PIPELINE, true)): ?>
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
        <?php if ($nextStage): ?>
          <form method="post" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="set_status">
            <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
            <input type="hidden" name="status" value="<?= $nextStage ?>">
            <button class="btn btn-primary btn-sm" type="submit">&#8594; Mark <?= e($nextStage) ?></button>
          </form>
        <?php endif; ?>

        <form method="post" style="display:inline" data-confirm="Reject this request?">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="set_status">
          <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
          <input type="hidden" name="status" value="Rejected">
          <button class="btn btn-danger btn-sm" type="submit">&#10007; Reject</button>
        </form>

        <?php if ($r['status'] === 'Approved' && !$r['converted_event_id']): ?>
          <button class="btn btn-navy btn-sm" type="button" onclick="toggleConvert(<?= $r['id'] ?>)">&#128197; Convert to Event&hellip;</button>
        <?php endif; ?>
      </div>

      <?php if ($r['status'] === 'Approved' && !$r['converted_event_id']): ?>
      <form method="post" id="convert-<?= $r['id'] ?>" style="display:none;margin-top:14px;border-top:1px dashed var(--line);padding-top:14px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="convert">
        <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
        <div class="form-grid">
          <div class="form-field">
            <label>Event name <span class="req">*</span></label>
            <input type="text" name="event_name" maxlength="160" value="<?= e($r['title']) ?>">
          </div>
          <div class="form-field">
            <label>Event date <span class="req">*</span> <small class="muted">(future)</small></label>
            <input type="date" name="event_date" required min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
          </div>
          <div class="form-field">
            <label>Start / End time</label>
            <div style="display:flex;gap:8px">
              <input type="time" name="start_time" value="06:00">
              <input type="time" name="end_time" value="08:00">
            </div>
          </div>
          <div class="form-field">
            <label>Location <span class="req">*</span></label>
            <input type="text" name="location" maxlength="200" required placeholder="Park / hall address">
          </div>
          <div class="form-field">
            <label>Max participants</label>
            <input type="number" name="max_participants" min="1" max="10000" value="50">
          </div>
        </div>
        <div class="form-actions" style="margin-top:10px">
          <button class="btn btn-primary btn-sm" type="submit">Create Event &amp; Mark Scheduled</button>
        </div>
      </form>
      <?php endif; ?>
      <?php endif; ?>

      <form method="post" style="margin-top:14px;display:flex;gap:10px;align-items:flex-end;max-width:640px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="note">
        <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
        <div class="form-field" style="flex:1">
          <label>Admin note <small class="muted">(visible to the requester in their portal)</small></label>
          <input type="text" name="admin_note" maxlength="255" value="<?= e($r['admin_note'] ?? '') ?>" placeholder="e.g. We are arranging a trainer for this…">
        </div>
        <button class="btn btn-ghost btn-sm" type="submit">Save Note</button>
      </form>
    </div>
  </div>
  <?php endwhile; ?>
<?php else: ?>
  <div class="card"><div class="card-body"><p class="muted" style="margin:0">No requests <?= $statusFilter ? "with status &ldquo;$statusFilter&rdquo;" : 'yet' ?>.</p></div></div>
<?php endif; ?>

<script>
function toggleConvert(id) {
  var f = document.getElementById('convert-' + id);
  f.style.display = f.style.display === 'none' ? 'block' : 'none';
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
