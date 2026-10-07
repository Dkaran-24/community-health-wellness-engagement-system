<?php
/**
 * admin/members/profile.php — individual member profile with attendance & fee history.
 */
$PAGE_TITLE = 'Member Profile';
$PAGE_KEY   = 'members';
require_once __DIR__ . '/../../includes/header.php';

$db = db();
$cur = cur();
$id = (int)($_GET['id'] ?? 0);

/* Make sure the username/password columns exist before we read them. */
require_once __DIR__ . '/../../includes/member_auth.php';
ensure_member_login_columns();

$m = $db->query(
  "SELECT m.*, p.plan_name, p.duration_months, p.price, t.name AS trainer_name,
          DATE_ADD(m.join_date, INTERVAL p.duration_months MONTH) AS expires_on
   FROM members m
   LEFT JOIN membership_plans p ON m.plan_id=p.id
   LEFT JOIN trainers t ON m.trainer_id=t.id
   WHERE m.id=$id"
)->fetch_assoc();
if (!$m) { header('Location: index.php?err=Member not found.'); exit; }

$att  = $db->query("SELECT attend_date, status FROM attendance WHERE member_id=$id ORDER BY attend_date DESC LIMIT 20");
$fees = $db->query("SELECT * FROM fees WHERE member_id=$id ORDER BY payment_date DESC");
$present = (int)$db->query("SELECT COUNT(*) c FROM attendance WHERE member_id=$id AND status='Present'")->fetch_assoc()['c'];
$total   = (int)$db->query("SELECT COUNT(*) c FROM attendance WHERE member_id=$id")->fetch_assoc()['c'];
$paid    = $db->query("SELECT COALESCE(SUM(amount),0) s FROM fees WHERE member_id=$id AND status='Paid'")->fetch_assoc()['s'];
?>
<?= flash() ?>
<div class="page-head">
  <div style="display:flex;align-items:center;gap:18px">
    <?= photoImg($m['photo'], $m['name'], 'member-photo-lg') ?>
    <div><h2><?= e($m['name']) ?></h2><p>Member #<?= $m['id'] ?> &middot; joined <?= fmtDate($m['join_date']) ?></p></div>
  </div>
  <div>
    <a href="edit.php?id=<?= $id ?>" class="btn btn-navy btn-sm">Edit</a>
    <a href="index.php" class="btn btn-ghost btn-sm">&larr; All Members</a>
  </div>
</div>

<div class="grid cols-3" style="margin-bottom:20px">
  <div class="stat"><div class="stat-ico gold">&#9733;</div><div><div class="stat-val"><?= e($m['plan_name'] ?: 'None') ?></div><div class="stat-lbl">Membership Plan</div></div></div>
  <div class="stat"><div class="stat-ico <?= $m['status']==='Active'?'green':'red' ?>">&#10003;</div><div><div class="stat-val"><?= e($m['status']) ?></div><div class="stat-lbl">Status &middot; <?= expiryBadge($m['expires_on']) ?></div></div></div>
  <div class="stat"><div class="stat-ico steel">&#10003;</div><div><div class="stat-val"><?= $total ? round($present/$total*100).'%' : '—' ?></div><div class="stat-lbl">Attendance (<?= $present ?>/<?= $total ?>)</div></div></div>
</div>

<div class="grid cols-2">
  <div class="card">
    <div class="card-head"><h3>Personal Details</h3></div>
    <div class="card-body">
      <table class="data" style="width:100%">
        <tr><td class="k muted">Contact</td><td><?= e($m['contact'] ?: '—') ?></td></tr>
        <tr><td class="k muted">Email</td><td><?= e($m['email'] ?: '—') ?></td></tr>
        <tr><td class="k muted">Portal Username</td><td>
          <?php if (!empty($m['username'])): ?>
            <?= e($m['username']) ?>
            <?php if (!empty($m['password'])): ?>
              <span class="badge green">Login enabled</span>
            <?php else: ?>
              <span class="badge gold">No password set</span>
            <?php endif; ?>
          <?php else: ?>
            <span class="badge gray">No portal login</span> — <a href="edit.php?id=<?= $id ?>">set credentials</a>
          <?php endif; ?>
        </td></tr>
        <tr><td class="k muted">Gender</td><td><?= e($m['gender']) ?></td></tr>
        <tr><td class="k muted">Date of Birth</td><td><?= fmtDate($m['dob']) ?></td></tr>
        <tr><td class="k muted">Address</td><td><?= e($m['address'] ?: '—') ?></td></tr>
        <tr><td class="k muted">Trainer</td><td><?= e($m['trainer_name'] ?: '—') ?></td></tr>
        <tr><td class="k muted">Plan Price</td><td><?= fmtMoney($m['price'], $cur) ?></td></tr>
        <tr><td class="k muted">Membership Expires</td><td><?= fmtDate($m['expires_on']) ?></td></tr>
        <tr><td class="k muted">Total Paid</td><td><b><?= fmtMoney($paid, $cur) ?></b></td></tr>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h3>Recent Attendance</h3><a href="<?= $base ?>/admin/attendance/index.php?member=<?= $id ?>" class="btn btn-ghost btn-sm">Full log</a></div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap"><table class="data">
        <thead><tr><th>Date</th><th class="no-sort">Status</th></tr></thead>
        <tbody>
        <?php while ($a = $att->fetch_assoc()): ?>
          <tr><td><?= fmtDate($a['attend_date']) ?></td><td><?= $a['status']==='Present'?'<span class="badge green">Present</span>':'<span class="badge red">Absent</span>' ?></td></tr>
        <?php endwhile; ?>
        <?php if ($att->num_rows===0): ?><tr><td colspan="2" class="muted center">No attendance records.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>

<div class="spacer"></div>
<div class="card">
  <div class="card-head"><h3>Fee / Payment History</h3><a href="<?= $base ?>/admin/fees/record.php?member=<?= $id ?>" class="btn btn-primary btn-sm">&#43; Record Payment</a></div>
  <div class="card-body" style="padding:0">
    <div class="table-wrap"><table class="data" id="feeTable" data-sortable>
      <thead><tr><th>Receipt</th><th>Date</th><th>Amount</th><th>Mode</th><th>Status</th><th class="no-sort">Receipt</th></tr></thead>
      <tbody>
      <?php while ($f = $fees->fetch_assoc()): ?>
        <tr>
          <td><?= e($f['receipt_no'] ?: '—') ?></td>
          <td><?= fmtDate($f['payment_date']) ?></td>
          <td><?= fmtMoney($f['amount'], $cur) ?></td>
          <td><?= e($f['payment_mode']) ?></td>
          <td><?php $s=strtolower($f['status']); echo '<span class="badge '.($s==='paid'?'green':($s==='overdue'?'red':'gold')).'">'.$f['status'].'</span>'; ?></td>
          <td><a href="<?= $base ?>/admin/fees/receipt.php?id=<?= $f['id'] ?>" class="btn btn-ghost btn-sm">Print</a></td>
        </tr>
      <?php endwhile; ?>
      <?php if ($fees->num_rows===0): ?><tr><td colspan="6" class="muted center">No payment records.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
