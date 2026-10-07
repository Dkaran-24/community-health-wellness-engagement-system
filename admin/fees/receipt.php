<?php
/**
 * admin/fees/receipt.php — printable branded payment receipt.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();
require_once __DIR__ . '/../../db_connect.php';

$db = db();
$settings = get_settings();
$cur = cur();
$base = base_url();
$id = (int)($_GET['id'] ?? 0);
$f = $db->query(
  "SELECT f.*, m.name AS member_name, m.contact, m.email, p.plan_name
   FROM fees f JOIN members m ON f.member_id=m.id
   LEFT JOIN membership_plans p ON f.plan_id=p.id WHERE f.id=$id"
)->fetch_assoc();
if (!$f) { header('Location: index.php?err=Receipt not found.'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= e($settings['gym_name']) ?> — Receipt #<?= e($f['receipt_no']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>/assets/css/style.css">
</head>
<body>
<div class="content no-print" style="max-width:760px">
  <div class="page-head">
    <div><h2>Payment Receipt</h2><p>Receipt #<?= e($f['receipt_no']) ?></p></div>
    <div>
      <button class="btn btn-primary" onclick="window.print()">&#128424; Print</button>
      <a href="index.php" class="btn btn-ghost">&larr; Back to Fees</a>
    </div>
  </div>
  <?php if (!empty($_GET['ok'])): ?><div class="alert ok flash">&#10003; <?= e($_GET['ok']) ?></div><?php endif; ?>
</div>

<div class="receipt card" style="padding:30px 36px; margin:0 auto; box-shadow:var(--shadow)">
  <div class="letterhead">
    <img src="<?= $base ?>/assets/images/logo.jpg" alt="New Life Fitness Club logo">
    <div>
      <div class="lh-name"><?= e($settings['gym_name']) ?></div>
      <div class="lh-meta"><?= e($settings['address']) ?></div>
      <div class="lh-meta">Tel: <?= e($settings['contact']) ?> &middot; <?= e($settings['email']) ?></div>
    </div>
  </div>

  <h2>PAYMENT RECEIPT</h2>

  <table>
    <tr><td class="k">Receipt No.</td><td><b><?= e($f['receipt_no']) ?></b></td></tr>
    <tr><td class="k">Date</td><td><?= fmtDate($f['payment_date']) ?></td></tr>
    <tr><td class="k">Member Name</td><td><b><?= e($f['member_name']) ?></b></td></tr>
    <tr><td class="k">Contact</td><td><?= e($f['contact'] ?: '—') ?></td></tr>
    <tr><td class="k">Email</td><td><?= e($f['email'] ?: '—') ?></td></tr>
    <tr><td class="k">Membership Plan</td><td><?= e($f['plan_name'] ?: '—') ?></td></tr>
    <tr><td class="k">Payment Mode</td><td><?= e($f['payment_mode']) ?></td></tr>
    <tr><td class="k">Status</td><td><?php $s=strtolower($f['status']); echo '<span class="badge '.($s==='paid'?'green':($s==='overdue'?'red':'gold')).'">'.$f['status'].'</span>'; ?></td></tr>
    <?php if ($f['notes']): ?><tr><td class="k">Notes</td><td><?= e($f['notes']) ?></td></tr><?php endif; ?>
    <tr><td class="k">Amount Paid</td><td class="total"><?= fmtMoney($f['amount'], $cur) ?></td></tr>
  </table>

  <div class="sign">
    <div>Received By</div>
    <div>Member Signature</div>
  </div>

  <div class="foot">
    Thank you for being part of <?= e($settings['gym_name']) ?>! This is a computer-generated receipt.<br>
    Generated on <?= date('l, F j, Y \a\t g:i a') ?>
  </div>
</div>

<script src="<?= $base ?>/assets/js/main.js"></script>
</body>
</html>
