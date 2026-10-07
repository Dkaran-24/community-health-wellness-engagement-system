<?php
/**
 * admin/trainer-payments/pay.php — Record a new payment to a trainer.
 *
 * Form fields: trainer, amount, pay period, payment date, payment mode,
 * reference no, notes.  The trainer's monthly salary is auto-filled as
 * the suggested amount when a trainer is selected.
 */
$PAGE_TITLE = 'Pay Trainer';
$PAGE_KEY   = 'trainer-payments';
require_once __DIR__ . '/../../includes/header.php';

$db  = db();
$cur = cur();

/* Pre-selected trainer (from ?trainer= on the URL) */
$preTrainer = (int)($_GET['trainer'] ?? 0);

/* Handle POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $trainer_id = (int)($_POST['trainer_id'] ?? 0);
    $amount     = (float)($_POST['amount'] ?? 0);
    $pdate      = trim($_POST['payment_date'] ?? '');
    $period     = trim($_POST['pay_period'] ?? '');
    $mode       = $_POST['payment_mode'] ?? 'Cash';
    $refNo      = trim($_POST['reference_no'] ?? '');
    $notes      = trim($_POST['notes'] ?? '');

    if (!$trainer_id || $amount <= 0 || $pdate === '') {
        header('Location: pay.php?err=' . urlencode('Trainer, amount and date are required.'));
        exit;
    }

    $stmt = $db->prepare(
      "INSERT INTO trainer_payments (trainer_id, amount, payment_date, pay_period, payment_mode, reference_no, notes)
       VALUES (?,?,?,?,?,?,?)"
    );
    $stmt->bind_param('idsssss', $trainer_id, $amount, $pdate, $period, $mode, $refNo, $notes);
    if ($stmt->execute()) {
        $newId = $stmt->insert_id;
        $stmt->close();

        /* Fetch trainer name for the success message */
        $tn = $db->query("SELECT name FROM trainers WHERE id=$trainer_id")->fetch_assoc();
        $tName = $tn ? $tn['name'] : "Trainer #$trainer_id";

        $msg = 'Payment of ' . fmtMoney($amount, $cur) . ' recorded for ' . $tName . '.';
        header('Location: index.php?ok=' . urlencode($msg));
    } else {
        $err = $stmt->error;
        $stmt->close();
        header('Location: pay.php?err=' . urlencode('Payment failed: ' . $err));
    }
    exit;
}

/* Trainer list with salary for auto-fill */
$trainers = $db->query("SELECT id, name, specialization, salary FROM trainers ORDER BY name");
$trainerJson = [];
while ($t = $trainers->fetch_assoc()) {
    $trainerJson[$t['id']] = [
        'name'  => $t['name'],
        'salary' => (float)$t['salary'],
        'spec'  => $t['specialization'],
    ];
}
?>
<?= flash() ?>
<div class="page-head">
  <div><h2>Pay Trainer</h2><p>Record a salary or payment to a trainer. A permanent history entry is saved.</p></div>
  <a href="index.php" class="btn btn-ghost">&larr; Back to Payments</a>
</div>

<div class="card"><div class="card-body">
  <form method="post" data-validate>
    <div class="form-grid">
      <div class="form-field">
        <label>Trainer <span class="req">*</span></label>
        <select name="trainer_id" id="trainerSel" required>
          <option value="">— Select trainer —</option>
          <?php foreach ($trainerJson as $tid => $t): ?>
            <option value="<?= $tid ?>" data-salary="<?= $t['salary'] ?>" <?= $preTrainer == $tid ? 'selected' : '' ?>><?= e($t['name']) ?> — <?= e($t['spec']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-field">
        <label>Amount (<?= e($cur) ?>) <span class="req">*</span></label>
        <input type="number" step="0.01" min="0" name="amount" id="amountFld" required placeholder="0.00">
        <span class="hint" id="salaryHint"></span>
      </div>

      <div class="form-field">
        <label>Pay Period</label>
        <select name="pay_period" id="periodSel">
          <option value="">— Select period —</option>
          <?php
          $m  = (int)date('n');
          $yr = date('Y');
          $monthName = date('F Y');
          $lastMonth = date('F Y', strtotime('first day of last month'));
          ?>
          <option value="<?= e($monthName) ?>">Salary — <?= e($monthName) ?></option>
          <option value="<?= e($lastMonth) ?>">Salary — <?= e($lastMonth) ?></option>
          <option value="Bonus">Bonus</option>
          <option value="Advance">Advance</option>
          <option value="Incentive">Incentive</option>
          <option value="Other">Other</option>
        </select>
      </div>

      <div class="form-field">
        <label>Payment Date <span class="req">*</span></label>
        <input type="date" name="payment_date" required value="<?= date('Y-m-d') ?>">
      </div>

      <div class="form-field">
        <label>Payment Mode</label>
        <select name="payment_mode">
          <?php foreach (['Cash','Bank Transfer','UPI','Cheque','Other'] as $opt): ?>
            <option><?= $opt ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-field">
        <label>Reference No.</label>
        <input type="text" name="reference_no" placeholder="Transaction ID / cheque no (optional)">
      </div>

      <div class="form-field full">
        <label>Notes</label>
        <input type="text" name="notes" placeholder="Optional notes about this payment">
      </div>
    </div>

    <div class="form-actions">
      <button class="btn btn-primary">&#10003; Record Payment</button>
      <a href="index.php" class="btn btn-ghost">Cancel</a>
    </div>
  </form>
</div></div>

<script>
  /* Auto-fill amount from the selected trainer's salary */
  (function(){
    var sel    = document.getElementById('trainerSel');
    var amt    = document.getElementById('amountFld');
    var hint   = document.getElementById('salaryHint');

    function fillSalary() {
      var opt    = sel.options[sel.selectedIndex];
      var salary = opt ? opt.getAttribute('data-salary') : null;
      if (salary && parseFloat(salary) > 0) {
        if (!amt.value) amt.value = parseFloat(salary).toFixed(2);
        hint.textContent = 'Monthly salary: ₹' + parseFloat(salary).toLocaleString('en-IN',{minimumFractionDigits:2});
      } else {
        hint.textContent = '';
      }
    }
    sel.addEventListener('change', fillSalary);
    fillSalary(); /* run once in case a trainer was pre-selected */
  })();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
