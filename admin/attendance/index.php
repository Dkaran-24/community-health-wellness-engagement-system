<?php
/**
 * admin/attendance/index.php — mark daily attendance & view by date.
 */
$PAGE_TITLE = 'Attendance';
$PAGE_KEY   = 'attendance';
require_once __DIR__ . '/../../includes/header.php';
$db = db();

/* Handle bulk mark submission */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['att_date'])) {
    $att_date = $_POST['att_date'];
    $rows = $_POST['status'] ?? [];
    foreach ($rows as $member_id => $status) {
        $mid = (int)$member_id;
        $st = ($status === 'Present') ? 'Present' : 'Absent';
        $stmt = $db->prepare("INSERT INTO attendance (member_id, attend_date, status) VALUES (?,?,?)
                             ON DUPLICATE KEY UPDATE status=VALUES(status)");
        $stmt->bind_param('iss', $mid, $att_date, $st);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: index.php?date=' . urlencode($att_date) . '&ok=' . urlencode('Attendance for ' . date('M j, Y', strtotime($att_date)) . ' saved.'));
    exit;
}

$view_date = $_GET['date'] ?? date('Y-m-d');
/* Show members whose membership is still valid (expiry >= today) OR who have no plan yet.
   This ensures newly added members always appear in the attendance sheet. */
$members = $db->query(
  "SELECT m.id, m.name
   FROM members m
   LEFT JOIN membership_plans p ON m.plan_id = p.id
   WHERE m.status = 'Active'
     AND (p.id IS NULL
          OR DATE_ADD(m.join_date, INTERVAL p.duration_months MONTH) >= CURDATE())
   ORDER BY m.name"
);

/* Existing marks for the selected date */
$marks = [];
$res = $db->query("SELECT member_id, status FROM attendance WHERE attend_date='" . $db->real_escape_string($view_date) . "'");
while ($r = $res->fetch_assoc()) $marks[$r['member_id']] = $r['status'];

/* Quick stats */
$presentToday = (int)$db->query("SELECT COUNT(*) c FROM attendance WHERE attend_date='$view_date' AND status='Present'")->fetch_assoc()['c'];
$absentToday  = (int)$db->query("SELECT COUNT(*) c FROM attendance WHERE attend_date='$view_date' AND status='Absent'")->fetch_assoc()['c'];
?>
<?= flash() ?>
<div class="page-head">
  <div><h2>Attendance Management</h2><p>Mark daily present/absent for active members.</p></div>
</div>

<div class="grid cols-3" style="margin-bottom:18px">
  <div class="stat"><div class="stat-ico green">&#10003;</div><div><div class="stat-val"><?= $presentToday ?></div><div class="stat-lbl">Present on <?= fmtDate($view_date) ?></div></div></div>
  <div class="stat"><div class="stat-ico red">&#10007;</div><div><div class="stat-val"><?= $absentToday ?></div><div class="stat-lbl">Absent</div></div></div>
  <div class="stat"><div class="stat-ico steel">&#9635;</div><div><div class="stat-val"><?= $members->num_rows ?></div><div class="stat-lbl">Active Members</div></div></div>
</div>

<form method="get" class="toolbar no-print">
  <label style="font-weight:600;color:var(--navy-800)">Select date:</label>
  <input type="date" name="date" value="<?= e($view_date) ?>" class="form-field" style="padding:9px;border:1px solid var(--line);border-radius:9px">
  <button class="btn btn-navy">View</button>
</form>

<form method="post" data-validate>
  <input type="hidden" name="att_date" value="<?= e($view_date) ?>">
  <div class="card">
    <div class="card-head"><h3>Attendance Sheet — <?= fmtDate($view_date) ?></h3>
      <button class="btn btn-primary no-print">&#10003; Save Attendance</button>
    </div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>Member</th><th class="no-sort">Mark Present</th><th class="no-sort">Mark Absent</th><th class="no-sort">Current</th></tr></thead>
          <tbody>
          <?php while ($m = $members->fetch_assoc()): $cur_st = $marks[$m['id']] ?? null; ?>
            <tr>
              <td><b><?= e($m['name']) ?></b></td>
              <td><label><input type="radio" name="status[<?= $m['id'] ?>]" value="Present" <?= $cur_st==='Present'?'checked':'' ?>></label></td>
              <td><label><input type="radio" name="status[<?= $m['id'] ?>]" value="Absent" <?= $cur_st==='Absent'?'checked':'' ?>></label></td>
              <td><?php if ($cur_st==='Present') echo '<span class="badge green">Present</span>'; elseif($cur_st==='Absent') echo '<span class="badge red">Absent</span>'; else echo '<span class="badge gray">Not marked</span>'; ?></td>
            </tr>
          <?php endwhile; ?>
          <?php if ($members->num_rows===0): ?><tr><td colspan="4" class="empty">No active members.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</form>

<div class="spacer"></div>
<div class="card">
  <div class="card-head"><h3>Recent Attendance Log</h3></div>
  <div class="card-body" style="padding:0">
    <div class="table-wrap"><table class="data" data-sortable>
      <thead><tr><th>Date</th><th>Member</th><th>Status</th></tr></thead>
      <tbody>
      <?php
      $log = $db->query("SELECT a.attend_date, a.status, m.name FROM attendance a JOIN members m ON a.member_id=m.id ORDER BY a.attend_date DESC, m.name LIMIT 30");
      while ($l = $log->fetch_assoc()):
      ?>
        <tr><td><?= fmtDate($l['attend_date']) ?></td><td><?= e($l['name']) ?></td><td><?= $l['status']==='Present'?'<span class="badge green">Present</span>':'<span class="badge red">Absent</span>' ?></td></tr>
      <?php endwhile; ?>
      <?php if ($log->num_rows===0): ?><tr><td colspan="3" class="muted center">No attendance recorded yet.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
