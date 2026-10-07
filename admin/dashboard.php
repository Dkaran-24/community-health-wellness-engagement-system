<?php
/**
 * admin/dashboard.php — Admin dashboard (final integration point).
 * Branded welcome header + live summary cards + quick nav.
 */
$PAGE_TITLE = 'Dashboard';
$PAGE_KEY   = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/helpers.php';

$db = db();
$cur = cur();
$today = date('Y-m-d');

/* Summary metrics (prepared / safe queries) */
$totalMembers   = (int)$db->query("SELECT COUNT(*) c FROM members")->fetch_assoc()['c'];
/* Active = status Active AND membership not yet expired (join_date + plan duration >= today) */
$activeMembers  = (int)$db->query(
    "SELECT COUNT(*) c FROM members m LEFT JOIN membership_plans p ON m.plan_id=p.id
     WHERE m.status='Active' AND (p.id IS NULL OR DATE_ADD(m.join_date, INTERVAL p.duration_months MONTH) >= CURDATE())"
)->fetch_assoc()['c'];
$totalTrainers  = (int)$db->query("SELECT COUNT(*) c FROM trainers")->fetch_assoc()['c'];
$todayAtt       = (int)$db->query("SELECT COUNT(*) c FROM attendance WHERE attend_date='$today' AND status='Present'")->fetch_assoc()['c'];
$pendingFees    = (int)$db->query("SELECT COUNT(*) c FROM fees WHERE status<>'Paid'")->fetch_assoc()['c'];
$monthRevenue   = $db->query("SELECT COALESCE(SUM(amount),0) s FROM fees WHERE status='Paid' AND MONTH(payment_date)=MONTH(CURDATE()) AND YEAR(payment_date)=YEAR(CURDATE())")->fetch_assoc()['s'];

/* Recent members & expiring soon (membership start + plan duration) */
$recentMembers = $db->query("SELECT m.name, m.join_date, p.plan_name FROM members m LEFT JOIN membership_plans p ON m.plan_id=p.id ORDER BY m.created_at DESC LIMIT 5");
$expiring = $db->query("SELECT m.name, m.join_date, p.plan_name, p.duration_months, DATE_ADD(m.join_date, INTERVAL p.duration_months MONTH) AS expires_on FROM members m JOIN membership_plans p ON m.plan_id=p.id WHERE m.status='Active' HAVING expires_on BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) ORDER BY expires_on ASC LIMIT 5");
?>
<div class="welcome">
  <img src="<?= $base ?>/assets/images/logo.jpg" alt="logo">
  <div class="w-text">
    <h1>Welcome to New Life Fitness Club Admin Panel</h1>
    <p>Manage members, trainers, attendance, membership plans and fee payments — all in one branded dashboard built for reliable, paper-free club administration.</p>
  </div>
  <div class="w-date">Today<br><b style="color:#fff;font-size:16px"><?= $cur_date ?></b></div>
</div>

<!-- Summary cards -->
<div class="grid cols-4">
  <div class="stat">
    <div class="stat-ico navy">&#9635;</div>
    <div><div class="stat-val"><?= $totalMembers ?></div><div class="stat-lbl">Total Members</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico green">&#10003;</div>
    <div><div class="stat-val"><?= $activeMembers ?></div><div class="stat-lbl">Active Memberships</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico steel">&#9679;</div>
    <div><div class="stat-val"><?= $totalTrainers ?></div><div class="stat-lbl">Trainers</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico gold">&#10003;</div>
    <div><div class="stat-val"><?= $todayAtt ?></div><div class="stat-lbl">Today's Attendance</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico red">₹</div>
    <div><div class="stat-val"><?= $pendingFees ?></div><div class="stat-lbl">Pending Fees</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico gold">₹</div>
    <div><div class="stat-val"><?= fmtMoney($monthRevenue, $cur) ?></div><div class="stat-lbl">Revenue This Month</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico steel">&#9733;</div>
    <div><div class="stat-val"><?= (int)$db->query("SELECT COUNT(*) c FROM membership_plans")->fetch_assoc()['c'] ?></div><div class="stat-lbl">Membership Plans</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico navy">&#9776;</div>
    <div><div class="stat-val"><?= (int)$db->query("SELECT COUNT(*) c FROM fees")->fetch_assoc()['c'] ?></div><div class="stat-lbl">Total Transactions</div></div>
  </div>
</div>

<div class="spacer"></div>

<div class="grid cols-2">
  <div class="card">
    <div class="card-head"><h3>Newest Members</h3><a href="<?= $base ?>/admin/members/index.php" class="btn btn-ghost btn-sm">View all</a></div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>Name</th><th>Plan</th><th>Joined</th></tr></thead>
          <tbody>
          <?php while ($r = $recentMembers->fetch_assoc()): ?>
            <tr><td><?= htmlspecialchars($r['name']) ?></td><td><?= htmlspecialchars($r['plan_name'] ?? '—') ?></td><td><?= fmtDate($r['join_date']) ?></td></tr>
          <?php endwhile; ?>
          <?php if ($recentMembers->num_rows === 0): ?><tr><td colspan="3" class="muted center">No members yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h3>Expiring Within 30 Days</h3><a href="<?= $base ?>/admin/members/index.php" class="btn btn-ghost btn-sm">Members</a></div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>Name</th><th>Plan</th><th>Expires</th></tr></thead>
          <tbody>
          <?php while ($r = $expiring->fetch_assoc()): ?>
            <tr><td><?= htmlspecialchars($r['name']) ?></td><td><?= htmlspecialchars($r['plan_name']) ?></td><td><span class="badge gold"><?= fmtDate($r['expires_on']) ?></span></td></tr>
          <?php endwhile; ?>
          <?php if ($expiring->num_rows === 0): ?><tr><td colspan="3" class="muted center">No memberships expiring soon.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="spacer"></div>
<div class="card">
  <div class="card-head"><h3>Quick Navigation</h3></div>
  <div class="card-body">
    <div class="grid cols-4">
      <a href="<?= $base ?>/admin/members/add.php" class="btn btn-primary">&#43; Add Member</a>
      <a href="<?= $base ?>/admin/trainers/add.php" class="btn btn-navy">&#43; Add Trainer</a>
      <a href="<?= $base ?>/admin/attendance/index.php" class="btn btn-ghost">Mark Attendance</a>
      <a href="<?= $base ?>/admin/fees/record.php" class="btn btn-ghost">Record Payment</a>
      <a href="<?= $base ?>/admin/plans/index.php" class="btn btn-ghost">Manage Plans</a>
      <a href="<?= $base ?>/admin/offers/index.php" class="btn btn-ghost">&#127873; Offers</a>
      <a href="<?= $base ?>/admin/offers/dashboard.php" class="btn btn-ghost">&#128231; Campaigns</a>
      <a href="<?= $base ?>/admin/analytics/index.php" class="btn btn-ghost">&#128202; Live Analytics</a>
      <a href="<?= $base ?>/admin/reports/index.php" class="btn btn-ghost">View Reports</a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
