<?php
/**
 * member/attendance.php — full attendance history for the logged-in member.
 */
$PAGE_TITLE = 'My Attendance';
$PAGE_KEY   = 'attendance';
require_once __DIR__ . '/_header.php';

$db = db();
$m  = current_member();
if (!$m) { session_destroy(); header('Location: ' . member_base_url() . '/member/login.php'); exit; }
$id = (int)$m['id'];

$present = (int)$db->query("SELECT COUNT(*) c FROM attendance WHERE member_id=$id AND status='Present'")->fetch_assoc()['c'];
$absent  = (int)$db->query("SELECT COUNT(*) c FROM attendance WHERE member_id=$id AND status='Absent'")->fetch_assoc()['c'];
$total   = $present + $absent;
$attPct  = $total ? round($present / $total * 100) : 0;

$att = $db->query("SELECT attend_date, status, marked_at FROM attendance WHERE member_id=$id ORDER BY attend_date DESC");
?>

<?= flash() ?>

<div class="page-head">
  <div><h2>My Attendance</h2><p>Your complete check-in history at <?= e($settings['gym_name']) ?>.</p></div>
</div>

<!-- Attendance summary stats -->
<div class="grid cols-4" style="margin-bottom:22px">
  <div class="stat">
    <div class="stat-ico steel">&#9776;</div>
    <div><div class="stat-val"><?= $total ?></div><div class="stat-lbl">Total Days</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico green">&#10003;</div>
    <div><div class="stat-val"><?= $present ?></div><div class="stat-lbl">Days Present</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico red">&#10007;</div>
    <div><div class="stat-val"><?= $absent ?></div><div class="stat-lbl">Days Absent</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico <?= $attPct >= 75 ? 'green' : ($attPct >= 50 ? 'gold' : 'red') ?>">&#9733;</div>
    <div><div class="stat-val"><?= $total ? $attPct.'%' : '—' ?></div><div class="stat-lbl">Attendance Rate</div></div>
  </div>
</div>

<div class="card">
  <div class="card-head"><h3>Attendance Log</h3></div>
  <div class="card-body" style="padding:0">
    <div class="table-wrap"><table class="data" id="attTable" data-sortable>
      <thead><tr><th>Date</th><th>Status</th><th class="no-sort">Marked At</th></tr></thead>
      <tbody>
      <?php while ($a = $att->fetch_assoc()): ?>
        <tr>
          <td><?= fmtDate($a['attend_date']) ?></td>
          <td><?= $a['status']==='Present' ? '<span class="badge green">Present</span>' : '<span class="badge red">Absent</span>' ?></td>
          <td class="muted"><?= $a['marked_at'] ? date('M j, Y g:i A', strtotime($a['marked_at'])) : '—' ?></td>
        </tr>
      <?php endwhile; ?>
      <?php if ($att->num_rows === 0): ?>
        <tr><td colspan="3" class="muted center">No attendance records yet. Your check-ins will appear here once the staff marks your attendance.</td></tr>
      <?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    if (typeof initTableSearch === 'function') {
      // no search input on this page, but sortable table still works
    }
  });
</script>
<?php require_once __DIR__ . '/_footer.php'; ?>
