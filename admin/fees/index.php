<?php
/**
 * admin/fees/index.php — fee/payment list with status tracking.
 */
$PAGE_TITLE = 'Fee Management';
$PAGE_KEY   = 'fees';
require_once __DIR__ . '/../../includes/header.php';
$db = db();
$cur = cur();
$fees = $db->query(
  "SELECT f.*, m.name AS member_name, m.contact AS member_phone, p.plan_name
   FROM fees f
   JOIN members m ON f.member_id=m.id
   LEFT JOIN membership_plans p ON f.plan_id=p.id
   ORDER BY f.payment_date DESC, f.id DESC"
);
$paid = $db->query("SELECT COALESCE(SUM(amount),0) s FROM fees WHERE status='Paid'")->fetch_assoc()['s'];
$pend = $db->query("SELECT COALESCE(SUM(amount),0) s FROM fees WHERE status='Pending'")->fetch_assoc()['s'];
$over = $db->query("SELECT COALESCE(SUM(amount),0) s FROM fees WHERE status='Overdue'")->fetch_assoc()['s'];


?>
<?= flash() ?>
<div class="page-head">
  <div><h2>Fee Management</h2><p>Track payments, receipts and dues for New Life Fitness Club.</p></div>
  <a href="record.php" class="btn btn-primary">&#43; Record Payment</a>
</div>

<div class="grid cols-3" style="margin-bottom:18px">
  <div class="stat"><div class="stat-ico green">₹</div><div><div class="stat-val"><?= fmtMoney($paid, $cur) ?></div><div class="stat-lbl">Total Collected</div></div></div>
  <div class="stat"><div class="stat-ico gold">₹</div><div><div class="stat-val"><?= fmtMoney($pend, $cur) ?></div><div class="stat-lbl">Pending</div></div></div>
  <div class="stat"><div class="stat-ico red">₹</div><div><div class="stat-val"><?= fmtMoney($over, $cur) ?></div><div class="stat-lbl">Overdue</div></div></div>
</div>

<div class="toolbar">
  <div class="search">
    <span class="ico">&#128269;</span>
    <input type="text" id="searchInput" placeholder="Search by member, receipt no, plan…">
  </div>
</div>

<div class="card">
  <div class="card-body" style="padding:0">
    <div class="table-wrap">
      <table class="data" id="feesTable" data-sortable>
        <thead><tr><th>Receipt #</th><th>Member</th><th>Plan</th><th>Amount</th><th>Date</th><th>Mode</th><th>Status</th><th>Phone</th><th class="no-sort">Actions</th></tr></thead>
        <tbody>
        <?php while ($f = $fees->fetch_assoc()): ?>
          <tr>
            <td><?= e($f['receipt_no'] ?: '—') ?></td>
            <td><b><?= e($f['member_name']) ?></b></td>
            <td><?= e($f['plan_name'] ?: '—') ?></td>
            <td><?= fmtMoney($f['amount'], $cur) ?></td>
            <td><?= fmtDate($f['payment_date']) ?></td>
            <td><?= e($f['payment_mode']) ?></td>
            <td><?php $s=strtolower($f['status']); echo '<span class="badge '.($s==='paid'?'green':($s==='overdue'?'red':'gold')).'">'.$f['status'].'</span>'; ?></td>
            <td style="font-size:13px;color:#555"><?= e($f['member_phone'] ?: '—') ?></td>
            <td style="white-space:nowrap">
              <a href="receipt.php?id=<?= $f['id'] ?>" class="btn btn-ghost btn-sm">Print</a>
              <?php if ($s === 'pending' || $s === 'overdue'): ?>
                <a href="send-reminder.php?id=<?= $f['id'] ?>" class="btn btn-warning btn-sm" data-confirm="Send a payment reminder email to <?= e($f['member_name']) ?>?">✉ Remind</a>
              <?php endif; ?>
              <a href="edit.php?id=<?= $f['id'] ?>" class="btn btn-navy btn-sm">Edit</a>
              <a href="delete.php?id=<?= $f['id'] ?>" class="btn btn-danger btn-sm" data-confirm="Delete payment <?= e($f['receipt_no'] ?: '#'.$f['id']) ?> for <?= e($f['member_name']) ?>?">Delete</a>
            </td>
          </tr>
        <?php endwhile; ?>
        <?php if ($fees->num_rows===0): ?><tr><td colspan="9" class="empty"><span class="ico">₹</span>No payment records yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    if (typeof initTableSearch === 'function') initTableSearch('searchInput','feesTable');
  });
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
