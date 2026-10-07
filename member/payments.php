<?php
/**
 * member/payments.php — full payment history for the logged-in member.
 * Shows all fee records with receipt print links.
 */
$PAGE_TITLE = 'My Payments';
$PAGE_KEY   = 'payments';
require_once __DIR__ . '/_header.php';

$db  = db();
$cur = cur();
$m   = current_member();
if (!$m) { session_destroy(); header('Location: ' . member_base_url() . '/member/login.php'); exit; }
$id  = (int)$m['id'];

$paid    = (float)$db->query("SELECT COALESCE(SUM(amount),0) s FROM fees WHERE member_id=$id AND status='Paid'")->fetch_assoc()['s'];
$pending = (float)$db->query("SELECT COALESCE(SUM(amount),0) s FROM fees WHERE member_id=$id AND status='Pending'")->fetch_assoc()['s'];
$overdue = (float)$db->query("SELECT COALESCE(SUM(amount),0) s FROM fees WHERE member_id=$id AND status='Overdue'")->fetch_assoc()['s'];

$fees = $db->query("SELECT * FROM fees WHERE member_id=$id ORDER BY payment_date DESC");
?>

<?= flash() ?>

<div class="page-head">
  <div><h2>My Payments</h2><p>Your complete payment history and receipts at <?= e($settings['gym_name']) ?>.</p></div>
</div>

<!-- Payment summary stats -->
<div class="grid cols-4" style="margin-bottom:22px">
  <div class="stat">
    <div class="stat-ico green">&#8377;</div>
    <div><div class="stat-val"><?= fmtMoney($paid, $cur) ?></div><div class="stat-lbl">Total Paid</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico gold">&#8377;</div>
    <div><div class="stat-val"><?= fmtMoney($pending, $cur) ?></div><div class="stat-lbl">Pending</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico red">&#8377;</div>
    <div><div class="stat-val"><?= fmtMoney($overdue, $cur) ?></div><div class="stat-lbl">Overdue</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico steel">&#9776;</div>
    <div><div class="stat-val"><?= $fees->num_rows ?></div><div class="stat-lbl">Total Records</div></div>
  </div>
</div>

<?php if ($pending > 0 || $overdue > 0): ?>
<div class="member-callout warn">
  &#9888; You have outstanding dues of <b><?= fmtMoney($pending + $overdue, $cur) ?></b>. Please clear them at the front desk.
</div>
<?php endif; ?>

<div class="card">
  <div class="card-head"><h3>Payment History</h3></div>
  <div class="card-body" style="padding:0">
    <div class="table-wrap"><table class="data" id="payTable" data-sortable>
      <thead><tr><th>Receipt #</th><th>Date</th><th>Amount</th><th>Mode</th><th>Status</th><th>Notes</th><th class="no-sort">Receipt</th></tr></thead>
      <tbody>
      <?php while ($f = $fees->fetch_assoc()): ?>
        <tr>
          <td><?= e($f['receipt_no'] ?: '—') ?></td>
          <td><?= fmtDate($f['payment_date']) ?></td>
          <td><?= fmtMoney($f['amount'], $cur) ?></td>
          <td><?= e($f['payment_mode']) ?></td>
          <td><?php $s=strtolower($f['status']); echo '<span class="badge '.($s==='paid'?'green':($s==='overdue'?'red':'gold')).'">'.$f['status'].'</span>'; ?></td>
          <td class="muted"><?= e($f['notes'] ?: '—') ?></td>
          <td><a href="<?= $base ?>/member/receipt.php?id=<?= $f['id'] ?>" class="btn btn-ghost btn-sm">Print</a></td>
        </tr>
      <?php endwhile; ?>
      <?php if ($fees->num_rows === 0): ?>
        <tr><td colspan="7" class="muted center">No payment records yet. Your payments will appear here once recorded by the staff.</td></tr>
      <?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
