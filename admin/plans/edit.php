<?php
/**
 * admin/plans/edit.php — edit a membership plan.
 */
$PAGE_TITLE = 'Edit Plan';
$PAGE_KEY   = 'plans';
require_once __DIR__ . '/../../includes/header.php';
$db = db();
$id = (int)($_GET['id'] ?? 0);
$p = $db->query("SELECT * FROM membership_plans WHERE id=$id")->fetch_assoc();
if (!$p) { header('Location: index.php?err=Plan not found.'); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['plan_name'] ?? '');
    $dur  = (int)($_POST['duration_months'] ?? 1);
    $price= (float)($_POST['price'] ?? 0);
    $desc = trim($_POST['description'] ?? '');
    $stmt = $db->prepare("UPDATE membership_plans SET plan_name=?,duration_months=?,price=?,description=? WHERE id=$id");
    $stmt->bind_param('sids', $name,$dur,$price,$desc);
    if ($stmt->execute()) header('Location: index.php?ok='.urlencode('Plan "'.$name.'" updated.'));
    else header('Location: edit.php?id='.$id.'&err='.urlencode('Update failed: '.$stmt->error));
    $stmt->close(); exit;
}
?>
<?= flash() ?>
<div class="page-head">
  <div><h2>Edit Plan</h2><p>Update <b><?= e($p['plan_name']) ?></b>.</p></div>
  <a href="index.php" class="btn btn-ghost">&larr; Back to Plans</a>
</div>
<div class="card"><div class="card-body">
  <form method="post" data-validate>
    <div class="form-grid">
      <div class="form-field"><label>Plan Name <span class="req">*</span></label><input type="text" name="plan_name" required value="<?= e($p['plan_name']) ?>"></div>
      <div class="form-field"><label>Duration (months) <span class="req">*</span></label><input type="number" name="duration_months" min="1" required value="<?= e($p['duration_months']) ?>"></div>
      <div class="form-field"><label>Price (<?= e(cur()) ?>) <span class="req">*</span></label><input type="number" step="0.01" min="0" name="price" required value="<?= e($p['price']) ?>"></div>
      <div class="form-field full"><label>Description</label><input type="text" name="description" value="<?= e($p['description']) ?>"></div>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary">&#10003; Update Plan</button>
      <a href="index.php" class="btn btn-ghost">Cancel</a>
    </div>
  </form>
</div></div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
