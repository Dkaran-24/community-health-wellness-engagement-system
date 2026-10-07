<?php
/**
 * admin/plans/add.php — create a membership plan.
 */
$PAGE_TITLE = 'Add Plan';
$PAGE_KEY   = 'plans';
require_once __DIR__ . '/../../includes/header.php';
$db = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['plan_name'] ?? '');
    $dur  = (int)($_POST['duration_months'] ?? 1);
    $price= (float)($_POST['price'] ?? 0);
    $desc = trim($_POST['description'] ?? '');
    if ($name==='') { header('Location: add.php?err=Plan name is required.'); exit; }
    $stmt = $db->prepare("INSERT INTO membership_plans (plan_name,duration_months,price,description) VALUES (?,?,?,?)");
    $stmt->bind_param('sids', $name,$dur,$price,$desc);
    if ($stmt->execute()) header('Location: index.php?ok='.urlencode('Plan "'.$name.'" created.'));
    else header('Location: add.php?err='.urlencode('Create failed: '.$stmt->error));
    $stmt->close(); exit;
}
?>
<?= flash() ?>
<div class="page-head">
  <div><h2>Add Membership Plan</h2><p>Define a new membership tier.</p></div>
  <a href="index.php" class="btn btn-ghost">&larr; Back to Plans</a>
</div>
<div class="card"><div class="card-body">
  <form method="post" data-validate>
    <div class="form-grid">
      <div class="form-field"><label>Plan Name <span class="req">*</span></label><input type="text" name="plan_name" required placeholder="e.g. Monthly"></div>
      <div class="form-field"><label>Duration (months) <span class="req">*</span></label><input type="number" name="duration_months" min="1" required value="1"></div>
      <div class="form-field"><label>Price (<?= e(cur()) ?>) <span class="req">*</span></label><input type="number" step="0.01" min="0" name="price" required value="0.00"></div>
      <div class="form-field full"><label>Description</label><input type="text" name="description" placeholder="Short description of the plan"></div>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary">&#10003; Save Plan</button>
      <a href="index.php" class="btn btn-ghost">Cancel</a>
    </div>
  </form>
</div></div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
