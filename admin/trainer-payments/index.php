<?php
/**
 * admin/trainer-payments/index.php — Trainer Payment History.
 *
 * Lists every payment made to trainers.  Supports filtering by trainer
 * and by date range, shows running totals, and links to the Pay / Edit /
 * Delete / per-trainer view pages.
 */
$PAGE_TITLE = 'Trainer Payments';
$PAGE_KEY   = 'trainer-payments';
require_once __DIR__ . '/../../includes/header.php';

$db  = db();
$cur = cur();

/* ── Filters from the query string ── */
$fTrainer = (int)($_GET['trainer'] ?? 0);
$fFrom    = trim($_GET['from'] ?? '');
$fTo      = trim($_GET['to'] ?? '');

$where  = [];
$params = '';
$types  = '';

if ($fTrainer) {
    $where[] = 'tp.trainer_id = ' . $fTrainer;
}
if ($fFrom !== '') {
    $where[] = "tp.payment_date >= '" . $db->real_escape_string($fFrom) . "'";
}
if ($fTo !== '') {
    $where[] = "tp.payment_date <= '" . $db->real_escape_string($fTo) . "'";
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* ── Main list query (join trainer name + salary) ── */
$payments = $db->query(
  "SELECT tp.*, t.name AS trainer_name, t.specialization, t.salary
   FROM trainer_payments tp
   JOIN trainers t ON tp.trainer_id = t.id
   $whereSql
   ORDER BY tp.payment_date DESC, tp.id DESC"
);

/* ── Summary stats (respect the same filter) ── */
$totalPaid = $db->query(
  "SELECT COALESCE(SUM(tp.amount),0) AS s FROM trainer_payments tp $whereSql"
)->fetch_assoc()['s'];

$totalCount = $db->query(
  "SELECT COUNT(*) AS c FROM trainer_payments tp $whereSql"
)->fetch_assoc()['c'];

/* Distinct trainers paid (respect filter) */
$trainersPaid = $db->query(
  "SELECT COUNT(DISTINCT tp.trainer_id) AS c FROM trainer_payments tp $whereSql"
)->fetch_assoc()['c'];

/* This-month total (ignores filter so it's always "current month") */
$monthStart = date('Y-m-01');
$monthTotal = $db->query(
  "SELECT COALESCE(SUM(amount),0) AS s FROM trainer_payments WHERE payment_date >= '$monthStart'"
)->fetch_assoc()['s'];

/* Trainer list for the filter dropdown */
$trainers = $db->query("SELECT id, name FROM trainers ORDER BY name");
?>
<?= flash() ?>
<div class="page-head">
  <div><h2>Trainer Payments</h2><p>Pay trainers and keep a full history of every salary / payment.</p></div>
  <a href="pay.php" class="btn btn-primary">&#43; Pay Trainer</a>
</div>

<div class="grid cols-4" style="margin-bottom:18px">
  <div class="stat"><div class="stat-ico navy">&#8377;</div><div><div class="stat-val"><?= fmtMoney($totalPaid, $cur) ?></div><div class="stat-lbl">Total Paid<?= $where ? ' (filtered)' : '' ?></div></div></div>
  <div class="stat"><div class="stat-ico green">&#8377;</div><div><div class="stat-val"><?= fmtMoney($monthTotal, $cur) ?></div><div class="stat-lbl">This Month</div></div></div>
  <div class="stat"><div class="stat-ico steel">&#9679;</div><div><div class="stat-val"><?= (int)$trainersPaid ?></div><div class="stat-lbl">Trainers Paid</div></div></div>
  <div class="stat"><div class="stat-ico gold">&#9776;</div><div><div class="stat-val"><?= (int)$totalCount ?></div><div class="stat-lbl">Payment Records</div></div></div>
</div>

<!-- Filter bar -->
<div class="card" style="margin-bottom:18px">
  <div class="card-body" style="padding:14px 20px">
    <form method="get" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
      <div class="form-field" style="min-width:200px">
        <label>Trainer</label>
        <select name="trainer">
          <option value="">All trainers</option>
          <?php while ($t = $trainers->fetch_assoc()): ?>
            <option value="<?= $t['id'] ?>" <?= $fTrainer == $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="form-field">
        <label>From Date</label>
        <input type="date" name="from" value="<?= e($fFrom) ?>">
      </div>
      <div class="form-field">
        <label>To Date</label>
        <input type="date" name="to" value="<?= e($fTo) ?>">
      </div>
      <div style="display:flex;gap:8px">
        <button class="btn btn-navy btn-sm" type="submit">&#128269; Filter</button>
        <a href="index.php" class="btn btn-ghost btn-sm">Clear</a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body" style="padding:0">
    <div class="table-wrap">
      <table class="data" id="payTable" data-sortable>
        <thead><tr><th>#</th><th>Trainer</th><th>Specialization</th><th>Amount</th><th>Pay Period</th><th>Date</th><th>Mode</th><th>Ref / Notes</th><th class="no-sort">Actions</th></tr></thead>
        <tbody>
        <?php $i = 1; while ($p = $payments->fetch_assoc()): ?>
          <tr>
            <td><?= $i++ ?></td>
            <td><b><?= e($p['trainer_name']) ?></b></td>
            <td><?= e($p['specialization']) ?></td>
            <td><b><?= fmtMoney($p['amount'], $cur) ?></b></td>
            <td><?= e($p['pay_period'] ?: '—') ?></td>
            <td><?= fmtDate($p['payment_date']) ?></td>
            <td><?= e($p['payment_mode']) ?></td>
            <td>
              <?php if ($p['reference_no']): ?><span class="badge steel"><?= e($p['reference_no']) ?></span><?php endif; ?>
              <?= e($p['notes'] ?: '') ?>
            </td>
            <td style="white-space:nowrap">
              <a href="edit.php?id=<?= $p['id'] ?>" class="btn btn-navy btn-sm">Edit</a>
              <a href="delete.php?id=<?= $p['id'] ?>" class="btn btn-danger btn-sm" data-confirm="Delete this payment of <?= fmtMoney($p['amount'], $cur) ?> to <?= e($p['trainer_name']) ?>?">Delete</a>
            </td>
          </tr>
        <?php endwhile; ?>
        <?php if ($payments->num_rows === 0): ?>
          <tr><td colspan="9" class="empty"><span class="ico">&#8377;</span>No trainer payments yet. <a href="pay.php">Pay a trainer &rarr;</a></td></tr>
        <?php endif; ?>
        </tbody>
        <?php if ($payments->num_rows > 0): ?>
        <tfoot><tr><td colspan="3" style="text-align:right;font-weight:700">Total</td><td style="font-weight:800;color:var(--navy-800)"><?= fmtMoney($totalPaid, $cur) ?></td><td colspan="5"></td></tr></tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>
</div>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    if (typeof initTableSearch === 'function') initTableSearch('searchInput','payTable');
  });
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
