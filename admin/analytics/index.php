<?php
/**
 * admin/analytics/index.php — Live Data Analysis.
 * All figures are computed from the database on every page load, so they
 * update instantly when members are added, edited or deleted.
 *
 * Includes:
 *  - Live member overview (total / active / expired / expiring soon)
 *  - Revenue analysis: total, by month, by year
 *  - Member growth trend (last 12 months)
 *  - Plan distribution
 *  - Trainer workload (live member counts)
 *  - Payment status breakdown
 *  - Attendance rate
 */
$PAGE_TITLE = 'Analytics';
$PAGE_KEY   = 'analytics';
require_once __DIR__ . '/../../includes/header.php';

$db        = db();
$curSym    = cur();          // currency symbol (do NOT name this $cur — clashes with cur() function)
$settings  = get_settings();

/* Helper: run a query safely, return result or empty array */
function analytics_query($db, $sql) {
    $res = @$db->query($sql);
    if ($res === false) return false;   // caller handles
    return $res;
}

/* Helper: scalar count safely */
function analytics_scalar($db, $sql) {
    $res = @$db->query($sql);
    if (!$res) return 0;
    $row = $res->fetch_assoc();
    if (!$row) return 0;
    return (int)reset($row);
}

/* ---------- LIVE MEMBER OVERVIEW ---------- */
$totalMembers    = analytics_scalar($db, "SELECT COUNT(*) AS c FROM members");
$activeMembers   = analytics_scalar($db,
    "SELECT COUNT(*) AS c FROM members m LEFT JOIN membership_plans p ON m.plan_id=p.id
     WHERE m.status='Active' AND (p.id IS NULL OR DATE_ADD(m.join_date, INTERVAL p.duration_months MONTH) >= CURDATE())");
$expiredMembers  = analytics_scalar($db,
    "SELECT COUNT(*) AS c FROM members m JOIN membership_plans p ON m.plan_id=p.id
     WHERE m.status='Active' AND DATE_ADD(m.join_date, INTERVAL p.duration_months MONTH) < CURDATE()");
$expiringSoon    = analytics_scalar($db,
    "SELECT COUNT(*) AS c FROM (
        SELECT m.id, DATE_ADD(m.join_date, INTERVAL p.duration_months MONTH) AS exp_on
        FROM members m JOIN membership_plans p ON m.plan_id=p.id
        WHERE m.status='Active'
     ) t WHERE t.exp_on BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
$inactiveMembers = analytics_scalar($db, "SELECT COUNT(*) AS c FROM members WHERE status='Inactive'");

/* ---------- REVENUE ANALYSIS ---------- */
$totalRevenue = (float)analytics_scalar($db, "SELECT COALESCE(SUM(amount),0) AS s FROM fees WHERE status='Paid'");
$totalPending = (float)analytics_scalar($db, "SELECT COALESCE(SUM(amount),0) AS s FROM fees WHERE status='Pending'");
$totalOverdue = (float)analytics_scalar($db, "SELECT COALESCE(SUM(amount),0) AS s FROM fees WHERE status='Overdue'");

/* Revenue by YEAR */
$revByYearRes = analytics_query($db,
    "SELECT YEAR(payment_date) AS yr,
            COALESCE(SUM(CASE WHEN status='Paid' THEN amount ELSE 0 END),0) AS collected,
            COALESCE(SUM(CASE WHEN status='Pending' THEN amount ELSE 0 END),0) AS pending,
            COALESCE(SUM(CASE WHEN status='Overdue' THEN amount ELSE 0 END),0) AS overdue,
            COUNT(*) AS txns
     FROM fees WHERE payment_date IS NOT NULL GROUP BY YEAR(payment_date) ORDER BY yr DESC
");
/* Fetch year rows into an array so we can iterate multiple times
   (dropdown + table) without exhausting the mysqli result set */
$revByYear = [];
if ($revByYearRes) { while ($ry = $revByYearRes->fetch_assoc()) $revByYear[] = $ry; }

/* ---------- YEAR FILTER FOR MONTH ANALYSIS ---------- */
/* Available years (with revenue data) for the dropdown */
$availYears = array_map('intval', array_column($revByYear, 'yr'));
/* Read selected year from query string (?year=YYYY). 0/empty = All Years */
$selYear = isset($_GET['year']) ? (int)$_GET['year'] : 0;
if ($selYear > 0 && !in_array($selYear, $availYears, true)) { $selYear = 0; }

/* Month names for nicer labels in the specific-year view */
$monthNames = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',
               7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec'];

/* Revenue by MONTH */
if ($selYear > 0) {
    /* Specific year selected - show all 12 months Jan->Dec, including
       zero-revenue months (LEFT JOIN onto a calendar of 1..12). */
    $revByMonthRes = analytics_query($db,
        "SELECT m.mo AS mo_num,
                COALESCE(SUM(CASE WHEN f.status='Paid'    THEN f.amount ELSE 0 END),0) AS collected,
                COALESCE(SUM(CASE WHEN f.status='Pending' THEN f.amount ELSE 0 END),0) AS pending,
                COALESCE(SUM(CASE WHEN f.status='Overdue' THEN f.amount ELSE 0 END),0) AS overdue,
                COUNT(f.id) AS txns
         FROM (SELECT 1 AS mo UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
               UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9
               UNION SELECT 10 UNION SELECT 11 UNION SELECT 12) m
         LEFT JOIN fees f ON MONTH(f.payment_date)=m.mo AND YEAR(f.payment_date)=$selYear
                            AND f.payment_date IS NOT NULL
         GROUP BY m.mo ORDER BY m.mo ASC");
    $monthFilterLabel = "Calendar year $selYear (Jan – Dec)";
} else {
    /* All Years - last 12 months with data (original behaviour) */
    $revByMonthRes = analytics_query($db,
        "SELECT DATE_FORMAT(payment_date,'%Y-%m') AS ym,
                DATE_FORMAT(payment_date,'%b %Y') AS label,
                COALESCE(SUM(CASE WHEN status='Paid' THEN amount ELSE 0 END),0) AS collected,
                COALESCE(SUM(CASE WHEN status='Pending' THEN amount ELSE 0 END),0) AS pending,
                COALESCE(SUM(CASE WHEN status='Overdue' THEN amount ELSE 0 END),0) AS overdue,
                COUNT(*) AS txns
         FROM fees WHERE payment_date IS NOT NULL GROUP BY ym ORDER BY ym DESC LIMIT 12");
    $monthFilterLabel = "Last 12 months with data";
}
/* Fetch month rows into array for reliable iteration + label formatting */
$revByMonth = [];
if ($revByMonthRes) {
    while ($rm = $revByMonthRes->fetch_assoc()) {
        if ($selYear > 0) {
            $mo = (int)$rm['mo_num'];
            $rm['label'] = $monthNames[$mo] . ' ' . $selYear;
        }
        $revByMonth[] = $rm;
    }
}

/* ---------- MEMBER GROWTH (last 12 months) ---------- */
$growthRows = [];
if ($growth = analytics_query($db,
    "SELECT DATE_FORMAT(join_date,'%Y-%m') AS ym,
            DATE_FORMAT(join_date,'%b %Y') AS label,
            COUNT(*) AS joined
     FROM members WHERE join_date IS NOT NULL GROUP BY ym ORDER BY ym ASC LIMIT 12")) {
    while ($g = $growth->fetch_assoc()) $growthRows[] = $g;
}

/* ---------- PLAN DISTRIBUTION ---------- */
$planRows = [];
if ($planDist = analytics_query($db,
    "SELECT p.plan_name, COUNT(m.id) AS cnt
     FROM membership_plans p LEFT JOIN members m ON m.plan_id=p.id
     GROUP BY p.id ORDER BY cnt DESC")) {
    while ($pd = $planDist->fetch_assoc()) $planRows[] = $pd;
}

/* ---------- TRAINER WORKLOAD (live) ---------- */
$trainerLoad = analytics_query($db,
    "SELECT t.name, t.specialization,
            (SELECT COUNT(*) FROM members m WHERE m.trainer_id=t.id
             AND m.status='Active') AS active_count,
            (SELECT COUNT(*) FROM members m WHERE m.trainer_id=t.id) AS total_count
     FROM trainers t ORDER BY active_count DESC");

/* ---------- ATTENDANCE RATE ---------- */
$attPresent = analytics_scalar($db, "SELECT COUNT(*) AS c FROM attendance WHERE status='Present'");
$attAbsent  = analytics_scalar($db, "SELECT COUNT(*) AS c FROM attendance WHERE status='Absent'");
$attTotal   = $attPresent + $attAbsent;
$attRate    = $attTotal > 0 ? round($attPresent / $attTotal * 100, 1) : 0;

/* ---------- PAYMENT MODE BREAKDOWN ---------- */
$payModes = analytics_query($db,
    "SELECT payment_mode, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS amt
     FROM fees WHERE status='Paid' GROUP BY payment_mode ORDER BY cnt DESC");

/* Prepare data for JS charts */
$growthLabels = json_encode(array_column($growthRows, 'label'));
$growthData   = json_encode(array_map('intval', array_column($growthRows, 'joined')));
$planLabels   = json_encode(array_column($planRows, 'plan_name'));
$planCounts   = json_encode(array_map('intval', array_column($planRows, 'cnt')));
?>
<?= flash() ?>
<div class="page-head">
  <div><h2>Live Data Analytics</h2><p>Real-time analysis of members, revenue &amp; operations — updates instantly as data changes.</p></div>
  <button class="btn btn-primary no-print" onclick="window.print()">&#128424; Print Report</button>
</div>

<!-- ============ LIVE MEMBER OVERVIEW ============ -->
<div class="grid cols-4" style="margin-bottom:18px">
  <div class="stat">
    <div class="stat-ico navy">&#9635;</div>
    <div><div class="stat-val"><?= $totalMembers ?></div><div class="stat-lbl">Total Members</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico green">&#10003;</div>
    <div><div class="stat-val"><?= $activeMembers ?></div><div class="stat-lbl">Active (valid membership)</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico red">&#10007;</div>
    <div><div class="stat-val"><?= $expiredMembers ?></div><div class="stat-lbl">Expired Memberships</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico gold">&#9888;</div>
    <div><div class="stat-val"><?= $expiringSoon ?></div><div class="stat-lbl">Expiring &le; 30 days</div></div>
  </div>
</div>

<!-- ============ REVENUE SUMMARY ============ -->
<div class="grid cols-3" style="margin-bottom:18px">
  <div class="stat">
    <div class="stat-ico green">₹</div>
    <div><div class="stat-val"><?= fmtMoney($totalRevenue, $curSym) ?></div><div class="stat-lbl">Total Collected (All Time)</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico gold">₹</div>
    <div><div class="stat-val"><?= fmtMoney($totalPending, $curSym) ?></div><div class="stat-lbl">Total Pending</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico red">₹</div>
    <div><div class="stat-val"><?= fmtMoney($totalOverdue, $curSym) ?></div><div class="stat-lbl">Total Overdue</div></div>
  </div>
</div>

<div class="spacer"></div>

<!-- ============ CHARTS ROW ============ -->
<div class="grid cols-2">
  <!-- Member Growth Chart -->
  <div class="card">
    <div class="card-head"><h3>Member Growth Trend</h3><span class="badge steel">Last 12 months</span></div>
    <div class="card-body">
      <canvas id="growthChart" height="200"></canvas>
      <?php if (count($growthRows) === 0): ?>
        <p class="muted center" style="padding:30px">No member data yet.</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Plan Distribution Chart -->
  <div class="card">
    <div class="card-head"><h3>Plan Distribution</h3><span class="badge steel"><?= count($planRows) ?> plans</span></div>
    <div class="card-body">
      <canvas id="planChart" height="200"></canvas>
      <?php if (count($planRows) === 0): ?>
        <p class="muted center" style="padding:30px">No plans yet.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="spacer"></div>

<!-- ============ REVENUE BY MONTH ============ -->
<div class="card" style="margin-bottom:18px">
  <div class="card-head">
    <h3>Revenue Analysis by Month</h3>
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
      <span class="badge green"><?= fmtMoney($totalRevenue, $curSym) ?> total collected</span>
      <form method="get" action="" id="yearFilterForm" style="display:flex;align-items:center;gap:6px;margin:0">
        <label for="yearFilter" style="font-size:13px;color:var(--muted);font-weight:600;margin:0">Year:</label>
        <select name="year" id="yearFilter" onchange="this.form.submit()" style="padding:5px 10px;border:1px solid #d1d9e3;border-radius:6px;background:#fff;font-size:13px;cursor:pointer;font-weight:600;color:var(--navy-800)">
          <option value="0" <?= $selYear === 0 ? 'selected' : '' ?>>All Years</option>
          <?php foreach ($availYears as $y): ?>
            <option value="<?= $y ?>" <?= $selYear === $y ? 'selected' : '' ?>><?= $y ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
  </div>
  <div class="card-body" style="padding:0">
    <div style="padding:10px 16px;background:#f5f8fb;border-bottom:1px solid #e3e8ef;font-size:13px;color:var(--muted)">
      <b>Showing:</b> <?= e($monthFilterLabel) ?>
    </div>
    <div class="table-wrap"><table class="data" data-sortable>
      <thead><tr><th>Month</th><th>Collected (Paid)</th><th>Pending</th><th>Overdue</th><th>Transactions</th><th>Total Billed</th></tr></thead>
      <tbody>
      <?php $totC=0;$totP=0;$totO=0;$totT=0;
      foreach ($revByMonth as $r):
          $totC+=$r['collected']; $totP+=$r['pending']; $totO+=$r['overdue']; $totT+=$r['txns']; ?>
        <tr>
          <td><b><?= e($r['label']) ?></b></td>
          <td><b style="color:var(--green)"><?= fmtMoney($r['collected'], $curSym) ?></b></td>
          <td><?= fmtMoney($r['pending'], $curSym) ?></td>
          <td><?= fmtMoney($r['overdue'], $curSym) ?></td>
          <td><?= $r['txns'] ?></td>
          <td><?= fmtMoney($r['collected']+$r['pending']+$r['overdue'], $curSym) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (count($revByMonth) === 0): ?><tr><td colspan="6" class="muted center">No revenue data yet.</td></tr><?php else: ?>
        <tr style="background:var(--navy-800);color:#fff;font-weight:700">
          <td>TOTAL</td>
          <td><?= fmtMoney($totC, $curSym) ?></td>
          <td><?= fmtMoney($totP, $curSym) ?></td>
          <td><?= fmtMoney($totO, $curSym) ?></td>
          <td><?= $totT ?></td>
          <td><?= fmtMoney($totC+$totP+$totO, $curSym) ?></td>
        </tr>
      <?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>

<!-- ============ REVENUE BY YEAR ============ -->
<div class="card" style="margin-bottom:18px">
  <div class="card-head"><h3>Revenue Analysis by Year</h3><span class="badge steel" style="font-size:12px">Click a year to view its monthly breakdown above</span></div>
  <div class="card-body" style="padding:0">
    <div class="table-wrap"><table class="data" data-sortable>
      <thead><tr><th>Year</th><th>Collected (Paid)</th><th>Pending</th><th>Overdue</th><th>Transactions</th><th>Total Billed</th></tr></thead>
      <tbody>
      <?php foreach ($revByYear as $r): ?>
        <tr style="cursor:pointer" onclick="document.getElementById('yearFilter').value='<?= (int)$r['yr'] ?>';document.getElementById('yearFilterForm').submit()">
          <td><b><?= e($r['yr']) ?></b> <?= $selYear === (int)$r['yr'] ? '<span class="badge green" style="margin-left:6px">viewing</span>' : '' ?></td>
          <td><b style="color:var(--green)"><?= fmtMoney($r['collected'], $curSym) ?></b></td>
          <td><?= fmtMoney($r['pending'], $curSym) ?></td>
          <td><?= fmtMoney($r['overdue'], $curSym) ?></td>
          <td><?= $r['txns'] ?></td>
          <td><?= fmtMoney($r['collected']+$r['pending']+$r['overdue'], $curSym) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (count($revByYear) === 0): ?><tr><td colspan="6" class="muted center">No revenue data yet.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>

<div class="spacer"></div>

<div class="spacer"></div>

<!-- ============ TRAINER WORKLOAD + ATTENDANCE ============ -->
<div class="grid cols-2">
  <div class="card">
    <div class="card-head"><h3>Trainer Workload</h3><span class="badge steel">Live member counts</span></div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap"><table class="data" data-sortable>
        <thead><tr><th>Trainer</th><th>Specialization</th><th>Active Members</th><th>Total Assigned</th></tr></thead>
        <tbody>
        <?php if ($trainerLoad): while ($t = $trainerLoad->fetch_assoc()): ?>
          <tr>
            <td><b><?= e($t['name']) ?></b></td>
            <td><?= e($t['specialization']) ?></td>
            <td><span class="badge green"><?= $t['active_count'] ?></span></td>
            <td><span class="badge steel"><?= $t['total_count'] ?></span></td>
          </tr>
        <?php endwhile; endif; ?>
        <?php if (!$trainerLoad || $trainerLoad->num_rows === 0): ?><tr><td colspan="4" class="muted center">No trainers yet.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h3>Attendance &amp; Payments</h3></div>
    <div class="card-body">
      <div style="margin-bottom:18px">
        <h4 style="margin:0 0 10px;color:var(--navy-800)">Overall Attendance Rate</h4>
        <div style="background:#e3e8ef;border-radius:12px;height:32px;overflow:hidden;border:1px solid #d1d9e3;position:relative;box-shadow:inset 0 1px 3px rgba(0,0,0,0.08)">
          <div id="attBarFill" style="background:linear-gradient(90deg,#2e9e5b,#3eb878);height:100%;width:0%;border-radius:12px;transition:width 1.2s ease-out;display:flex;align-items:center;justify-content:flex-end;padding-right:10px;color:#fff;font-weight:700;font-size:13px;text-shadow:0 1px 2px rgba(0,0,0,0.2)">
            <span id="attBarLabel"><?= $attRate ?>%</span>
          </div>
        </div>
        <div style="display:flex;justify-content:space-between;margin-top:8px;font-size:13px;color:var(--muted)">
          <span><b style="color:var(--green)"><?= $attPresent ?></b> Present &middot; <b style="color:var(--red)"><?= $attAbsent ?></b> Absent</span>
          <span><b style="color:var(--navy-800);font-size:18px"><?= $attRate ?>%</b></span>
        </div>
      </div>
      <h4 style="margin:0 0 8px;color:var(--navy-800)">Payment Modes (Paid)</h4>
      <div class="table-wrap"><table class="data">
        <thead><tr><th>Mode</th><th>Count</th><th>Amount</th></tr></thead>
        <tbody>
        <?php if ($payModes): while ($pm = $payModes->fetch_assoc()): ?>
          <tr><td><b><?= e($pm['payment_mode']) ?></b></td><td><?= $pm['cnt'] ?></td><td><?= fmtMoney($pm['amt'], $curSym) ?></td></tr>
        <?php endwhile; endif; ?>
        <?php if (!$payModes || $payModes->num_rows === 0): ?><tr><td colspan="3" class="muted center">No paid transactions yet.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>

<div class="spacer"></div>

<!-- Insight banner -->
<div class="card">
  <div class="card-body" style="background:linear-gradient(135deg,var(--navy-900),var(--navy-700));color:#fff;border-radius:12px">
    <h3 style="color:#fff;margin:0 0 6px">&#128202; Live Insights</h3>
    <p style="margin:0;color:#cfe0ee;font-size:14px">
      <?= $activeMembers ?> of <?= $totalMembers ?> members hold valid memberships (<?= $totalMembers > 0 ? round($activeMembers/$totalMembers*100) : 0 ?>%).
      Total revenue collected: <b style="color:var(--gold)"><?= fmtMoney($totalRevenue, $curSym) ?></b> across <?= $totT ?> transactions.
      <?= $expiringSoon > 0 ? "<b style='color:var(--gold)'>" . $expiringSoon . " membership(s)</b> expire within 30 days &mdash; follow up to retain revenue." : "No memberships expiring soon." ?>
      <?= $totalOverdue > 0 ? "Overdue fees of <b style='color:#ff8a8a'>" . fmtMoney($totalOverdue, $curSym) . "</b> need collection." : "" ?>
      All figures refresh automatically whenever members or payments are added, edited or deleted.
    </p>
  </div>
</div>

<!-- Chart.js from CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function(){
  if (typeof Chart === 'undefined') return;
  var navy = '#16324A', gold = '#E8A33D', steel = '#6FA8C9', green = '#2E9E5B', red = '#D64545';

  /* Member Growth — bar chart */
  var gLabels = <?= $growthLabels ?: '[]' ?>;
  var gData   = <?= $growthData   ?: '[]' ?>;
  if (gLabels.length) {
    new Chart(document.getElementById('growthChart'), {
      type: 'bar',
      data: { labels: gLabels, datasets: [{
        label: 'New Members', data: gData,
        backgroundColor: steel, borderColor: navy, borderWidth: 1, borderRadius: 6
      }]},
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  }

  /* Plan Distribution — doughnut */
  var pLabels = <?= $planLabels ?: '[]' ?>;
  var pData   = <?= $planCounts ?: '[]' ?>;
  if (pLabels.length) {
    var palette = [navy, gold, steel, green, red, '#8e6fd6', '#e88a4a', '#5bb8a0'];
    new Chart(document.getElementById('planChart'), {
      type: 'doughnut',
      data: { labels: pLabels, datasets: [{
        data: pData, backgroundColor: palette, borderWidth: 2, borderColor: '#fff'
      }]},
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'right', labels: { boxWidth: 14, font: { size: 12 } } } }
      }
    });
  }

  /* Attendance bar — animate from 0 to actual rate */
  var attBar = document.getElementById('attBarFill');
  if (attBar) {
    var attTarget = <?= $attRate ?>;
    setTimeout(function(){ attBar.style.width = attTarget + '%'; }, 200);
  }
})();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
