<?php
/**
 * admin/fees/record.php — record a new payment.
 */
$PAGE_TITLE = 'Record Payment';
$PAGE_KEY   = 'fees';
require_once __DIR__ . '/../../includes/header.php';
$db = db();
$cur = cur();

$preMember = (int)($_GET['member'] ?? 0);
$members = $db->query("SELECT id, name FROM members ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = (int)($_POST['member_id'] ?? 0);
    $plan_id   = $_POST['plan_id'] ?: null;
    $amount    = (float)($_POST['amount'] ?? 0);
    $pdate     = trim($_POST['payment_date'] ?? '');
    $mode      = $_POST['payment_mode'] ?? 'Cash';
    $status    = $_POST['status'] ?? 'Paid';
    $notes     = trim($_POST['notes'] ?? '');
    if (!$member_id || $amount<=0 || $pdate==='') { header('Location: record.php?err='.urlencode('Member, amount and date are required.')); exit; }
    $receipt = genReceiptNo();
    $stmt = $db->prepare("INSERT INTO fees (member_id,plan_id,amount,payment_date,payment_mode,status,receipt_no,notes) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->bind_param('iidsssss', $member_id,$plan_id,$amount,$pdate,$mode,$status,$receipt,$notes);
    if ($stmt->execute()) {
        $newFeeId = $stmt->insert_id;
        $stmt->close();

        /* ── Membership renewal: if Paid + plan selected → renew membership ── */
        $renewed = false;
        if ($status === 'Paid' && $plan_id) {
            $renewed = renewMembership($db, $member_id, $plan_id, $pdate);
        }

        /* ── Send email to member based on payment status ── */
        $emailMsg = '';
        require_once __DIR__ . '/../../includes/mailer.php';
        if ($status === 'Paid') {
            $emailResult = sendPaymentReceiptEmail($db, $newFeeId, $renewed);
            if ($emailResult['ok']) {
                $emailMsg = ' Receipt emailed to member.';
            } else {
                $emailMsg = ' (Receipt email not sent: ' . $emailResult['error'] . ')';
            }
        } elseif ($status === 'Pending' || $status === 'Overdue') {
            $emailResult = sendPaymentReminderEmail($db, $newFeeId);
            if ($emailResult['ok']) {
                $emailMsg = ' Payment reminder emailed to member.';
            } else {
                $emailMsg = ' (Reminder email not sent: ' . $emailResult['error'] . ')';
            }
        }

        $okMsg = 'Payment recorded. Receipt #' . $receipt . ($renewed ? '. Membership renewed.' : '') . $emailMsg;
        header('Location: receipt.php?id=' . $newFeeId . '&ok=' . urlencode($okMsg));
    } else {
        $err = $stmt->error;
        $stmt->close();
        header('Location: record.php?err=' . urlencode('Record failed: ' . $err));
    }
    exit;
}

/* Plan price helper via JS dropdown fill */
$plansJson = [];
$pq = $db->query("SELECT id, plan_name, price FROM membership_plans");
while ($pr = $pq->fetch_assoc()) $plansJson[$pr['id']] = $pr;
?>
<?= flash() ?>
<div class="page-head">
  <div><h2>Record Payment</h2><p>Log a fee payment and generate a branded receipt.</p></div>
  <a href="index.php" class="btn btn-ghost">&larr; Back to Fees</a>
</div>
<div class="card"><div class="card-body">
  <form method="post" data-validate>
    <div class="form-grid">
      <div class="form-field">
        <label>Member <span class="req">*</span></label>
        <select name="member_id" id="memberSel" required>
          <option value="">— Select member —</option>
          <?php while ($m = $members->fetch_assoc()): ?>
            <option value="<?= $m['id'] ?>" data-plan="" <?= $preMember==$m['id']?'selected':'' ?>><?= e($m['name']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="form-field">
        <label>Membership Plan</label>
        <select name="plan_id" id="planSel">
          <option value="">— Select plan —</option>
          <?php foreach ($plansJson as $pid => $pr): ?>
            <option value="<?= $pid ?>" data-price="<?= e($pr['price']) ?>"><?= e($pr['plan_name']) ?> (<?= fmtMoney($pr['price'], $cur) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-field"><label>Amount (<?= e(cur()) ?>) <span class="req">*</span></label><input type="number" step="0.01" min="0" name="amount" id="amountFld" required></div>
      <div class="form-field"><label>Payment Date <span class="req">*</span></label><input type="date" name="payment_date" required value="<?= date('Y-m-d') ?>"></div>
      <div class="form-field">
        <label>Payment Mode</label>
        <select name="payment_mode"><option>Cash</option><option>Card</option><option>Bank Transfer</option><option>UPI</option><option>Other</option></select>
      </div>
      <div class="form-field">
        <label>Status</label>
        <select name="status"><option>Paid</option><option>Pending</option><option>Overdue</option></select>
      </div>
      <div class="form-field full"><label>Notes</label><input type="text" name="notes" placeholder="Optional notes"></div>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary">&#10003; Record &amp; Generate Receipt</button>
      <a href="index.php" class="btn btn-ghost">Cancel</a>
    </div>
  </form>
</div></div>
<script>
  /* Auto-fill amount from selected plan */
  document.getElementById('planSel').addEventListener('change', function(){
    var opt = this.options[this.selectedIndex];
    var price = opt.getAttribute('data-price');
    if (price) document.getElementById('amountFld').value = price;
  });
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>