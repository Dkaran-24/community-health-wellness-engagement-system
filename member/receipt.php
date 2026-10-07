<?php
/**
 * member/receipt.php — printable payment receipt for the logged-in member.
 * This is the member-accessible version of admin/fees/receipt.php.
 * Members can only view their OWN receipts (security check on member_id).
 */
$PAGE_TITLE = 'Payment Receipt';
$PAGE_KEY   = 'payments';
require_once __DIR__ . '/_header.php';

$db       = db();
$settings = get_settings();
$cur      = cur();
$base     = member_base_url();
$m        = current_member();
if (!$m) { session_destroy(); header('Location: ' . $base . '/member/login.php'); exit; }
$memberId = (int)$m['id'];
$id       = (int)($_GET['id'] ?? 0);

$f = $db->query(
  "SELECT f.*, m.name AS member_name, m.contact, m.email, p.plan_name
   FROM fees f JOIN members m ON f.member_id=m.id
   LEFT JOIN membership_plans p ON f.plan_id=p.id
   WHERE f.id=$id AND f.member_id=$memberId"
)->fetch_assoc();

if (!$f) {
    echo '<div class="alert err flash">&#9888; Receipt not found or does not belong to your account.</div>';
    echo '<p><a href="' . $base . '/member/payments.php" class="btn btn-ghost">&larr; Back to Payments</a></p>';
    require_once __DIR__ . '/_footer.php';
    exit;
}
?>

<div class="page-head">
  <div><h2>Payment Receipt</h2><p>Receipt #<?= e($f['receipt_no'] ?: 'N/A') ?></p></div>
  <button onclick="window.print()" class="btn btn-primary">&#128424; Print Receipt</button>
</div>

<div class="card" style="max-width:680px;margin:0 auto;">
  <div class="card-body" style="padding:30px;">
    <div style="text-align:center;margin-bottom:24px;">
      <?php if (!empty($settings['logo_path']) && file_exists(__DIR__ . '/../' . $settings['logo_path'])): ?>
        <img src="<?= $base ?>/<?= htmlspecialchars($settings['logo_path']) ?>" alt="logo" style="width:70px;height:70px;border-radius:50%;margin:0 auto 10px;">
      <?php endif; ?>
      <h2 style="color:var(--navy-800);"><?= e($settings['gym_name']) ?></h2>
      <p style="color:var(--muted);font-size:13px;"><?= e($settings['address'] ?: '') ?></p>
      <?php if (!empty($settings['contact'])): ?><p style="color:var(--muted);font-size:13px;">Tel: <?= e($settings['contact']) ?></p><?php endif; ?>
    </div>

    <hr style="border:none;border-top:2px solid var(--gold);margin:20px 0;">

    <table class="data member-info-table" style="margin-bottom:20px;">
      <tr><td class="k">Receipt No.</td><td><b><?= e($f['receipt_no'] ?: 'N/A') ?></b></td></tr>
      <tr><td class="k">Date</td><td><?= fmtDate($f['payment_date']) ?></td></tr>
      <tr><td class="k">Member Name</td><td><?= e($f['member_name']) ?></td></tr>
      <tr><td class="k">Contact</td><td><?= e($f['contact'] ?: '—') ?></td></tr>
      <tr><td class="k">Email</td><td><?= e($f['email'] ?: '—') ?></td></tr>
      <tr><td class="k">Membership Plan</td><td><?= e($f['plan_name'] ?: '—') ?></td></tr>
      <tr><td class="k">Payment Mode</td><td><?= e($f['payment_mode']) ?></td></tr>
      <tr><td class="k">Status</td><td><?php $s=strtolower($f['status']); echo '<span class="badge '.($s==='paid'?'green':($s==='overdue'?'red':'gold')).'">'.$f['status'].'</span>'; ?></td></tr>
      <?php if (!empty($f['notes'])): ?><tr><td class="k">Notes</td><td><?= e($f['notes']) ?></td></tr><?php endif; ?>
    </table>

    <div style="text-align:right;border-top:2px solid var(--line);padding-top:16px;">
      <div style="font-size:14px;color:var(--muted);">Total Amount Paid</div>
      <div style="font-size:28px;font-weight:800;color:var(--navy-800);"><?= fmtMoney($f['amount'], $cur) ?></div>
    </div>

    <div style="text-align:center;margin-top:30px;color:var(--muted);font-size:12px;">
      This is a computer-generated receipt.<br>
      Generated on <?= date('M j, Y \a\t g:i A') ?>
    </div>
  </div>
</div>

<style>
@media print {
  .member-topbar, .page-head, .member-nav, .member-user { display: none !important; }
  .member-content { padding: 0 !important; }
  .card { border: none !important; box-shadow: none !important; }
}
</style>

<?php require_once __DIR__ . '/_footer.php'; ?>
