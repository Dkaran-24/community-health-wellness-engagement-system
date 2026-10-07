<?php
/**
 * admin/reports/index.php — printable/exportable branded reports.
 */
$PAGE_TITLE = 'Reports';
$PAGE_KEY   = 'reports';
require_once __DIR__ . '/../../includes/header.php';
$db = db();
$cur = cur();
$settings = get_settings();

/* ---- Data for each report ---- */
/* Active = membership has NOT yet expired (computed from join_date + plan duration) */
$active = $db->query(
  "SELECT m.id, m.name, m.contact, p.plan_name, m.join_date,
          DATE_ADD(m.join_date, INTERVAL p.duration_months MONTH) AS expires_on
   FROM members m LEFT JOIN membership_plans p ON m.plan_id=p.id
   WHERE m.status='Active'
   HAVING expires_on IS NULL OR expires_on >= CURDATE()
   ORDER BY m.name"
);

/* Expired = membership end date is in the past */
$expired = $db->query(
  "SELECT m.name, p.plan_name, m.join_date,
          DATE_ADD(m.join_date, INTERVAL p.duration_months MONTH) AS expires_on
   FROM members m JOIN membership_plans p ON m.plan_id=p.id
   WHERE m.status='Active'
   HAVING expires_on < CURDATE()
   ORDER BY expires_on DESC"
);

/* Monthly revenue — last 12 months */
$revenue = $db->query(
  "SELECT DATE_FORMAT(payment_date,'%Y-%m') AS ym,
          DATE_FORMAT(payment_date,'%b %Y') AS label,
          COALESCE(SUM(CASE WHEN status='Paid' THEN amount ELSE 0 END),0) AS collected,
          COALESCE(SUM(CASE WHEN status='Pending' THEN amount ELSE 0 END),0) AS pending,
          COALESCE(SUM(CASE WHEN status='Overdue' THEN amount ELSE 0 END),0) AS overdue
   FROM fees
   GROUP BY ym ORDER BY ym DESC LIMIT 12"
);

/* Attendance summary */
$attSum = $db->query(
  "SELECT m.name,
          SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END) AS present,
          SUM(CASE WHEN a.status='Absent' THEN 1 ELSE 0 END) AS absent,
          COUNT(a.id) AS total
   FROM members m
   LEFT JOIN attendance a ON a.member_id=m.id
   GROUP BY m.id ORDER BY present DESC LIMIT 20"
);
?>
<?= flash() ?>
<div class="page-head">
  <div><h2>Reports</h2><p>Printable &amp; exportable reports on branded New Life Fitness Club letterhead.</p></div>
  <button class="btn btn-primary no-print" onclick="window.print()">&#128424; Print All</button>
</div>

<!-- Letterhead (shown on print) -->
<div class="receipt" style="display:none" id="printHead">
  <div class="letterhead">
    <img src="<?= $base ?>/assets/images/logo.jpg" alt="logo">
    <div>
      <div class="lh-name"><?= e($settings['gym_name']) ?></div>
      <div class="lh-meta"><?= e($settings['address']) ?></div>
      <div class="lh-meta">Tel: <?= e($settings['contact']) ?> &middot; <?= e($settings['email']) ?></div>
    </div>
  </div>
</div>

<div class="card" style="margin-bottom:20px">
  <div class="card-head"><h3>Active Members Report</h3><span class="badge green"><?= $active->num_rows ?> active</span></div>
  <div class="card-body" style="padding:0">
    <div class="table-wrap"><table class="data" data-sortable>
      <thead><tr><th>ID</th><th>Name</th><th>Contact</th><th>Plan</th><th>Joined</th><th>Expires</th><th>Membership</th></tr></thead>
      <tbody>
      <?php while ($r = $active->fetch_assoc()): ?>
        <tr>
          <td>#<?= $r['id'] ?></td>
          <td><?= e($r['name']) ?></td>
          <td><?= e($r['contact'] ?: '—') ?></td>
          <td><?= e($r['plan_name'] ?: '—') ?></td>
          <td><?= fmtDate($r['join_date']) ?></td>
          <td><?= fmtDate($r['expires_on']) ?></td>
          <td><?= expiryBadge($r['expires_on']) ?></td>
        </tr>
      <?php endwhile; ?>
      <?php if ($active->num_rows===0): ?><tr><td colspan="7" class="muted center">No active members.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>

<div class="card" style="margin-bottom:20px">
  <div class="card-head"><h3>Expired Memberships</h3><span class="badge red"><?= $expired->num_rows ?> expired</span></div>
  <div class="card-body" style="padding:0">
    <div class="table-wrap"><table class="data" data-sortable>
      <thead><tr><th>Name</th><th>Plan</th><th>Joined</th><th>Expired On</th></tr></thead>
      <tbody>
      <?php while ($r = $expired->fetch_assoc()): ?>
        <tr><td><?= e($r['name']) ?></td><td><?= e($r['plan_name']) ?></td><td><?= fmtDate($r['join_date']) ?></td><td><span class="badge red"><?= fmtDate($r['expires_on']) ?></span></td></tr>
      <?php endwhile; ?>
      <?php if ($expired->num_rows===0): ?><tr><td colspan="4" class="muted center">No expired memberships.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>

<div class="card" style="margin-bottom:20px">
  <div class="card-head"><h3>Monthly Revenue</h3></div>
  <div class="card-body" style="padding:0">
    <div class="table-wrap"><table class="data" data-sortable>
      <thead><tr><th>Month</th><th>Collected</th><th>Pending</th><th>Overdue</th><th>Total Billed</th></tr></thead>
      <tbody>
      <?php while ($r = $revenue->fetch_assoc()): ?>
        <tr>
          <td><?= e($r['label']) ?></td>
          <td><b><?= fmtMoney($r['collected'], $cur) ?></b></td>
          <td><?= fmtMoney($r['pending'], $cur) ?></td>
          <td><?= fmtMoney($r['overdue'], $cur) ?></td>
          <td><?= fmtMoney($r['collected']+$r['pending']+$r['overdue'], $cur) ?></td>
        </tr>
      <?php endwhile; ?>
      <?php if ($revenue->num_rows===0): ?><tr><td colspan="5" class="muted center">No revenue data.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>

<div class="card">
  <div class="card-head"><h3>Attendance Summary (Top 20)</h3></div>
  <div class="card-body" style="padding:0">
    <div class="table-wrap"><table class="data" data-sortable>
      <thead><tr><th>Member</th><th>Present</th><th>Absent</th><th>Total Days</th><th>Attendance %</th></tr></thead>
      <tbody>
      <?php while ($r = $attSum->fetch_assoc()): $pct = $r['total']>0 ? round($r['present']/$r['total']*100) : 0; ?>
        <tr>
          <td><?= e($r['name']) ?></td>
          <td><span class="badge green"><?= $r['present'] ?></span></td>
          <td><span class="badge red"><?= $r['absent'] ?></span></td>
          <td><?= $r['total'] ?></td>
          <td><?= $r['total']>0 ? '<span class="badge '.($pct>=75?'green':($pct>=50?'gold':'red')).'">'.$pct.'%</span>' : '—' ?></td>
        </tr>
      <?php endwhile; ?>
      <?php if ($attSum->num_rows===0): ?><tr><td colspan="5" class="muted center">No attendance data.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>

<script>
  /* On print, show the branded letterhead at the top */
  (function(){
    var head = document.getElementById('printHead');
    var media = window.matchMedia('print');
    function sync(){ head.style.display = media.matches ? 'block' : 'none'; }
    media.addEventListener('change', sync); sync();
  })();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
