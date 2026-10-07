<?php
/**
 * admin/plans/index.php — membership plan management.
 */
$PAGE_TITLE = 'Membership Plans';
$PAGE_KEY   = 'plans';
require_once __DIR__ . '/../../includes/header.php';
$db = db();
$cur = cur();
$plans = $db->query("SELECT p.*, (SELECT COUNT(*) FROM members m WHERE m.plan_id=p.id) AS member_count FROM membership_plans p ORDER BY p.duration_months");
?>
<?= flash() ?>
<div class="page-head">
  <div><h2>Membership Plan Management</h2><p>Create and manage the plans offered at New Life Fitness Club.</p></div>
  <a href="add.php" class="btn btn-primary">&#43; Add Plan</a>
</div>

<div class="card">
  <div class="card-body" style="padding:0">
    <div class="table-wrap">
      <table class="data" id="plansTable" data-sortable>
        <thead><tr><th>ID</th><th>Plan Name</th><th>Duration</th><th>Price</th><th>Members</th><th>Description</th><th class="no-sort">Actions</th></tr></thead>
        <tbody>
        <?php while ($p = $plans->fetch_assoc()): ?>
          <tr>
            <td>#<?= $p['id'] ?></td>
            <td><b><?= e($p['plan_name']) ?></b></td>
            <td><?= $p['duration_months'] ?> month<?= $p['duration_months']>1?'s':'' ?></td>
            <td><?= fmtMoney($p['price'], $cur) ?></td>
            <td><span class="badge steel"><?= $p['member_count'] ?></span></td>
            <td class="muted"><?= e($p['description'] ?: '—') ?></td>
            <td class="row-actions">
              <a href="edit.php?id=<?= $p['id'] ?>" class="btn btn-navy btn-sm">Edit</a>
              <a href="delete.php?id=<?= $p['id'] ?>" class="btn btn-danger btn-sm" data-confirm="Delete plan <?= e($p['plan_name']) ?>? Members on this plan will keep their membership but become unassigned.">Delete</a>
            </td>
          </tr>
        <?php endwhile; ?>
        <?php if ($plans->num_rows===0): ?><tr><td colspan="7" class="empty"><span class="ico">&#9733;</span>No plans yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    if (typeof initTableSearch === 'function') initTableSearch('searchInput','plansTable');
  });
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
