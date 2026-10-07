<?php
/**
 * admin/fees/edit.php — edit an existing fee / payment record.
 * Lets admin change status (Pending → Paid), amount, date, mode, notes.
 */
$PAGE_TITLE = 'Edit Payment';
$PAGE_KEY   = 'fees';
require_once __DIR__ . '/../../includes/header.php';
$db = db();
$cur = cur();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php?err=' . urlencode('Invalid payment id.')); exit; }

/* Load the fee record (with member + plan for display) */
$fee = $db->query(
  "SELECT f.*, m.name AS member_name, p.plan_name
   FROM fees f
   JOIN members m ON f.member_id = m.id
   LEFT JOIN membership_plans p ON f.plan_id = p.id
   WHERE f.id = $id"
)->fetch_assoc();

if (!$fee) { header('Location: index.php?err=' . urlencode('Payment record not found.')); exit; }

/* Handle form submission */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)($_POST['amount'] ?? 0);
    $pdate  = trim($_POST['payment_date'] ?? '');
    $mode   = $_POST['payment_mode'] ?? 'Cash';
    $status = $_POST['status'] ?? 'Paid';
    $notes  = trim($_POST['notes'] ?? '');

    if ($amount <= 0 || $pdate === '') {
        header('Location: edit.php?id=' . $id . '&err=' . urlencode('Amount and date are required.'));
        exit;
    }

    $stmt = $db->prepare(
      "UPDATE fees SET amount=?, payment_date=?, payment_mode=?, status=?, notes=? WHERE id=?"
    );
    $stmt->bind_param('dssssi', $amount, $pdate, $mode, $status, $notes, $id);
    if ($stmt->execute()) {
        $stmt->close();

        /* ── Membership renewal: if changed to Paid + plan exists → renew ── */
        $renewed = false;
        if ($status === 'Paid' && !empty($fee['plan_id'])) {
            $renewed = renewMembership($db, (int)$fee['member_id'], (int)$fee['plan_id'], $pdate);
        }

        /* ── Send email to member based on payment status ── */
        $emailMsg = '';
        require_once __DIR__ . '/../../includes/mailer.php';
        if ($status === 'Paid') {
            $emailResult = sendPaymentReceiptEmail($db, $id, $renewed);
            if ($emailResult['ok']) {
                $emailMsg = ' Receipt emailed to member.';
            } else {
                $emailMsg = ' (Receipt email not sent: ' . $emailResult['error'] . ')';
            }
        } elseif ($status === 'Pending' || $status === 'Overdue') {
            $emailResult = sendPaymentReminderEmail($db, $id);
            if ($emailResult['ok']) {
                $emailMsg = ' Payment reminder emailed to member.';
            } else {
                $emailMsg = ' (Reminder email not sent: ' . $emailResult['error'] . ')';
            }
        }

        $msg = 'Payment updated — ' . e($fee['member_name']) . ' (' . $status . ').';
        if ($renewed) $msg .= ' Membership renewed — expiry updated.';
        $msg .= $emailMsg;
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
  <div><h2>Edit Payment</h2><p>Update payment details for <b><?= e($fee['member_name']) ?></b> &middot; Receipt #<?= e($fee['receipt_no'] ?: '—') ?></p></div>
  <a href="index.php" class="btn btn-ghost">&larr; Back to Fees</a>
</div>

<div class="card">
  <div class="card-body">
    <form method="post" data-validate>
      <div class="form-grid">
        <!-- Read-only info -->
        <div class="form-field">
          <label>Member</label>
          <input type="text" value="<?= e($fee['member_name']) ?>" disabled style="background:var(--bg);color:var(--muted)">
        </div>
        <div class="form-field">
          <label>Membership Plan</label>
          <input type="text" value="<?= e($fee['plan_name'] ?: '—') ?>" disabled style="background:var(--bg);color:var(--muted)">
        </div>
        <div class="form-field">
          <label>Receipt No.</label>
          <input type="text" value="<?= e($fee['receipt_no'] ?: '—') ?>" disabled style="background:var(--bg);color:var(--muted)">
        </div>

        <!-- Editable fields -->
        <div class="form-field">
          <label>Amount (<?= e($cur) ?>) <span class="req">*</span></label>
          <input type="number" step="0.01" min="0" name="amount" required value="<?= e($fee['amount']) ?>">
        </div>
        <div class="form-field">
          <label>Payment Date <span class="req">*</span></label>
          <input type="date" name="payment_date" required value="<?= e($fee['payment_date']) ?>">
        </div>
        <div class="form-field">
          <label>Payment Mode</label>
          <select name="payment_mode">
            <?php foreach (['Cash','Card','Bank Transfer','UPI','Other'] as $opt): ?>
              <option <?= $fee['payment_mode'] === $opt ? 'selected' : '' ?>><?= $opt ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field">
          <label>Status</label>
          <select name="status">
            <?php foreach (['Paid','Pending','Overdue'] as $opt):
              $badge = $opt === 'Paid' ? 'green' : ($opt === 'Overdue' ? 'red' : 'gold'); ?>
              <option value="<?= $opt ?>" <?= $fee['status'] === $opt ? 'selected' : '' ?>>
                <?= $opt ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field full">
          <label>Notes</label>
          <input type="text" name="notes" placeholder="Optional notes" value="<?= e($fee['notes'] ?? '') ?>">
        </div>
      </div>
      <div class="form-actions">
        <button class="btn btn-primary">&#10003; Save Changes</button>
        <a href="index.php" class="btn btn-ghost">Cancel</a>
        <a href="receipt.php?id=<?= $id ?>" class="btn btn-navy" target="_blank">&#128424; View Receipt</a>
      </div>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>