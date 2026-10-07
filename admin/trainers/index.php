<?php
/**
 * admin/trainers/index.php — trainer list.
 */
$PAGE_TITLE = 'Trainers';
$PAGE_KEY   = 'trainers';
require_once __DIR__ . '/../../includes/header.php';

$db = db();
$cur = cur();
$trainers = $db->query("SELECT t.*, (SELECT COUNT(*) FROM members m WHERE m.trainer_id=t.id) AS member_count FROM trainers t ORDER BY t.name");
?>
<?= flash() ?>
<div class="page-head">
  <div><h2>Trainer Management</h2><p>Coach roster for New Life Fitness Club.</p></div>
  <a href="add.php" class="btn btn-primary">&#43; Add Trainer</a>
</div>

<div class="toolbar">
  <div class="search">
    <span class="ico">&#128269;</span>
    <input type="text" id="searchInput" placeholder="Search trainers by name, specialization, contact…">
  </div>
</div>

<div class="card">
  <div class="card-body" style="padding:0">
    <div class="table-wrap">
      <table class="data" id="trainersTable" data-sortable>
        <thead><tr><th>ID</th><th>Name</th><th>Specialization</th><th>Contact</th><th>Schedule</th><th>Salary</th><th>Members</th><th class="no-sort">Actions</th></tr></thead>
        <tbody>
        <?php while ($t = $trainers->fetch_assoc()): ?>
          <tr>
            <td>#<?= $t['id'] ?></td>
            <td><b><?= e($t['name']) ?></b></td>
            <td><?= e($t['specialization']) ?></td>
            <td><?= e($t['contact'] ?: '—') ?></td>
            <td><?= e($t['schedule'] ?: '—') ?></td>
            <td><?= fmtMoney($t['salary'], $cur) ?></td>
            <td><span class="badge steel"><?= $t['member_count'] ?></span></td>
            <td class="row-actions">
              <a href="../trainer-payments/pay.php?trainer=<?= $t['id'] ?>" class="btn btn-primary btn-sm">&#8377; Pay</a>
              <a href="edit.php?id=<?= $t['id'] ?>" class="btn btn-navy btn-sm">Edit</a>
              <a href="delete.php?id=<?= $t['id'] ?>" class="btn btn-danger btn-sm" data-confirm="Delete trainer <?= e($t['name']) ?>? Assigned members will be unassigned.">Delete</a>
            </td>
          </tr>
        <?php endwhile; ?>
        <?php if ($trainers->num_rows===0): ?><tr><td colspan="8" class="empty"><span class="ico">&#9679;</span>No trainers yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    if (typeof initTableSearch === 'function') initTableSearch('searchInput','trainersTable');
  });
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
