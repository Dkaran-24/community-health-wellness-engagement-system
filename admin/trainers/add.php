<?php
/**
 * admin/trainers/add.php — add a trainer.
 */
$PAGE_TITLE = 'Add Trainer';
$PAGE_KEY   = 'trainers';
require_once __DIR__ . '/../../includes/header.php';
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $spec = trim($_POST['specialization'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $schedule = trim($_POST['schedule'] ?? '');
    $salary = (float)($_POST['salary'] ?? 0);
    if ($name==='' || $spec==='') { header('Location: add.php?err='.urlencode('Name and specialization are required.')); exit; }
    $stmt = $db->prepare("INSERT INTO trainers (name,specialization,contact,email,schedule,salary) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param('sssssd', $name,$spec,$contact,$email,$schedule,$salary);
    if ($stmt->execute()) header('Location: index.php?ok='.urlencode('Trainer "'.$name.'" added.'));
    else header('Location: add.php?err='.urlencode('Add failed: '.$stmt->error));
    $stmt->close(); exit;
}
?>
<?= flash() ?>
<div class="page-head">
  <div><h2>Add New Trainer</h2><p>Add a coach to the New Life Fitness Club team.</p></div>
  <a href="index.php" class="btn btn-ghost">&larr; Back to Trainers</a>
</div>
<div class="card"><div class="card-body">
  <form method="post" data-validate>
    <div class="form-grid">
      <div class="form-field"><label>Full Name <span class="req">*</span></label><input type="text" name="name" required></div>
      <div class="form-field"><label>Specialization <span class="req">*</span></label><input type="text" name="specialization" required placeholder="e.g. Strength & Conditioning"></div>
      <div class="form-field"><label>Contact Number</label><input type="text" name="contact"></div>
      <div class="form-field"><label>Email</label><input type="email" name="email"></div>
      <div class="form-field"><label>Schedule</label><input type="text" name="schedule" placeholder="e.g. Mon-Fri 06:00-12:00"></div>
      <div class="form-field"><label>Monthly Salary (<?= e(cur()) ?>)</label><input type="number" step="0.01" min="0" name="salary" value="0"></div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">&#10003; Save Trainer</button>
      <a href="index.php" class="btn btn-ghost">Cancel</a>
    </div>
  </form>
</div></div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
