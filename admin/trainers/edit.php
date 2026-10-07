<?php
/**
 * admin/trainers/edit.php — edit a trainer.
 */
$PAGE_TITLE = 'Edit Trainer';
$PAGE_KEY   = 'trainers';
require_once __DIR__ . '/../../includes/header.php';
$db = db();
$id = (int)($_GET['id'] ?? 0);
$t = $db->query("SELECT * FROM trainers WHERE id=$id")->fetch_assoc();
if (!$t) { header('Location: index.php?err=Trainer not found.'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $spec = trim($_POST['specialization'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $schedule = trim($_POST['schedule'] ?? '');
    $salary = (float)($_POST['salary'] ?? 0);
    $stmt = $db->prepare("UPDATE trainers SET name=?,specialization=?,contact=?,email=?,schedule=?,salary=? WHERE id=$id");
    $stmt->bind_param('sssssd', $name,$spec,$contact,$email,$schedule,$salary);
    if ($stmt->execute()) header('Location: index.php?ok='.urlencode('Trainer "'.$name.'" updated.'));
    else header('Location: edit.php?id='.$id.'&err='.urlencode('Update failed: '.$stmt->error));
    $stmt->close(); exit;
}
?>
<?= flash() ?>
<div class="page-head">
  <div><h2>Edit Trainer</h2><p>Update <b><?= e($t['name']) ?></b>.</p></div>
  <a href="index.php" class="btn btn-ghost">&larr; Back to Trainers</a>
</div>
<div class="card"><div class="card-body">
  <form method="post" data-validate>
    <div class="form-grid">
      <div class="form-field"><label>Full Name <span class="req">*</span></label><input type="text" name="name" required value="<?= e($t['name']) ?>"></div>
      <div class="form-field"><label>Specialization <span class="req">*</span></label><input type="text" name="specialization" required value="<?= e($t['specialization']) ?>"></div>
      <div class="form-field"><label>Contact Number</label><input type="text" name="contact" value="<?= e($t['contact']) ?>"></div>
      <div class="form-field"><label>Email</label><input type="email" name="email" value="<?= e($t['email']) ?>"></div>
      <div class="form-field"><label>Schedule</label><input type="text" name="schedule" value="<?= e($t['schedule']) ?>"></div>
      <div class="form-field"><label>Monthly Salary (<?= e(cur()) ?>)</label><input type="number" step="0.01" min="0" name="salary" value="<?= e($t['salary']) ?>"></div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">&#10003; Update Trainer</button>
      <a href="index.php" class="btn btn-ghost">Cancel</a>
    </div>
  </form>
</div></div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
