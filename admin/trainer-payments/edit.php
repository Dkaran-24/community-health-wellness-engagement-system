<?php
/**
 * admin/trainer-payments/edit.php — edit an existing trainer payment.
 */
$PAGE_TITLE = 'Edit Trainer Payment';
$PAGE_KEY   = 'trainer-payments';
require_once __DIR__ . '/../../includes/header.php';

$db  = db();
$cur = cur();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php?err=' . urlencode('Invalid payment id.')); exit; }

/* Load the payment record (with trainer name for display) */
$pay = $db->query(
  "SELECT tp.*, t.name AS trainer_name
   FROM trainer_payments tp
   JOIN trainers t ON tp.trainer_id = t.id
   WHERE tp.id = $id"
)->fetch_assoc();

if (!$pay) { header('Location: index.php?err=' . urlencode('Payment record not found.')); exit; }

/* Handle POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)($_POST['amount'] ?? 0);
    $pdate  = trim($_POST['payment_date'] ?? '');
    $period = trim($_POST['pay_period'] ?? '');
    $mode   = $_POST['payment_mode'] ?? 'Cash';
    $refNo  = trim($_POST['reference_no'] ?? '');
    $notes  = trim($_POST['notes'] ?? '');

    if ($amount <= 0 || $pdate === '') {
        header('Location: edit.php?id=' . $id . '&err=' . urlencode('Amount and date are required.'));
        exit;
    }

    $stmt = $db->prepare(
      "UPDATE trainer_payments SET amount=?, payment_date=?, pay_period=?, payment_mode=?, reference_no=?, notes=? WHERE id=?"
    );
    $stmt->bind_param('dsssssi', $amount, $pdate, $period, $mode, $refNo, $notes, $id);
    if ($stmt->execute()) {
        $stmt->close();
        $msg = 'Payment updated — ' . e($pay['trainer_name']) . ' (' . fmtMoney($amount, $cur) . ').';
        header('Location: index.php?ok=' . urlencode($msg));
    } else {
        $err = $stmt->error;
        $stmt->close();
        header('Location: edit.php?id=' . $id . '&err=' . urlencode('Update failed: ' . $err));
    }
    exit;
}
?>
<?= flash() ?>
<div class="page-head">
  <div><h2>Edit Trainer Payment</h2><p>Update payment details for <b><?= e($pay['trainer_name']) ?></b></p></div>
  <a href="index.php" class="btn btn-ghost">&larr; Back to Payments</a>
</div>

<div class="card">
  <div class="card-body">
    <form method="post" data-validate>
      <div class="form-grid">
        <!-- Read-only -->
        <div class="form-field">
          <label>Trainer</label>
          <input type="text" value="<?= e($pay['trainer_name']) ?>" disabled style="background:var(--bg);color:var(--muted)">
        </div>

        <!-- Editable -->
        <div class="form-field">
          <label>Amount (<?= e($cur) ?>) <span class="req">*</span></label>
          <input type="number" step="0.01" min="0" name="amount" required value="<?= e($pay['amount']) ?>">
        </div>
        <div class="form-field">
          <label>Pay Period</label>
          <input type="text" name="pay_period" value="<?= e($pay['pay_period'] ?? '') ?>" placeholder="e.g. Salary — August 2026">
        </div>
        <div class="form-field">
          <label>Payment Date <span class="req">*</span></label>
          <input type="date" name="payment_date" required value="<?= e($pay['payment_date']) ?>">
        </div>
        <div class="form-field">
          <label>Payment Mode</label>
          <select name="payment_mode">
            <?php foreach (['Cash','Bank Transfer','UPI','Cheque','Other'] as $opt): ?>
              <option <?= $pay['payment_mode'] === $opt ? 'selected' : '' ?>><?= $opt ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field">
          <label>Reference No.</label>
          <input type="text" name="reference_no" value="<?= e($pay['reference_no'] ?? '') ?>" placeholder="Transaction ID / cheque no">
        </div>
        <div class="form-field full">
          <label>Notes</label>
          <input type="text" name="notes" value="<?= e($pay['notes'] ?? '') ?>" placeholder="Optional notes">
        </div>
      </div>
      <div class="form-actions">
        <button class="btn btn-primary">&#10003; Save Changes</button>
        <a href="index.php" class="btn btn-ghost">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
