<?php
/**
 * admin/members/index.php — searchable, sortable member list.
 */
$PAGE_TITLE = 'Members';
$PAGE_KEY   = 'members';
require_once __DIR__ . '/../../includes/header.php';

$db = db();
$cur = cur();
/* Auto-migrate: make sure the username/password columns exist before we
   reference them in the listing query. */
require_once __DIR__ . '/../../includes/member_auth.php';
ensure_member_login_columns();

$members = $db->query(
  "SELECT m.*, p.plan_name, p.duration_months, t.name AS trainer_name,
          DATE_ADD(m.join_date, INTERVAL p.duration_months MONTH) AS expires_on
   FROM members m
   LEFT JOIN membership_plans p ON m.plan_id = p.id
   LEFT JOIN trainers t ON m.trainer_id = t.id
   ORDER BY m.created_at DESC"
);
?>
<?= flash() ?>
<div class="page-head">
  <div><h2>Member Management</h2><p>All registered members of New Life Fitness Club.</p></div>
  <a href="add.php" class="btn btn-primary">&#43; Add Member</a>
</div>

<div class="toolbar">
  <div class="search">
    <span class="ico">&#128269;</span>
    <input type="text" id="searchInput" placeholder="Search members by name, contact, email, username, plan…">
  </div>
</div>

<div class="card">
  <div class="card-body" style="padding:0">
    <div class="table-wrap">
      <table class="data" id="membersTable" data-sortable>
        <thead>
          <tr>
            <th>ID</th><th>Name</th><th>Username</th><th>Contact</th><th>Plan</th><th>Trainer</th><th>Joined</th><th>Expiry</th><th>Status</th><th class="no-sort">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php while ($m = $members->fetch_assoc()): ?>
          <tr>
            <td>#<?= $m['id'] ?></td>
            <td style="display:flex;align-items:center;gap:10px"><?= photoImg($m['photo'], $m['name'], 'member-thumb') ?><b><?= e($m['name']) ?></b></td>
            <td>
              <?php if (!empty($m['username'])): ?>
                <?= e($m['username']) ?>
                <?php if (!empty($m['password'])): ?>
                  <span class="badge green" title="Portal login enabled">Login</span>
                <?php else: ?>
                  <span class="badge gold" title="Username set but no password">No password</span>
                <?php endif; ?>
              <?php else: ?>
                <span class="badge gray" title="No portal credentials">No login</span>
              <?php endif; ?>
            </td>
            <td><?= e($m['contact'] ?: '—') ?></td>
            <td><?= e($m['plan_name'] ?: '—') ?></td>
            <td><?= e($m['trainer_name'] ?: '—') ?></td>
            <td><?= fmtDate($m['join_date']) ?></td>
            <td><?= expiryBadge($m['expires_on']) ?></td>
            <td><?= $m['status']==='Active' ? '<span class="badge green">Active</span>' : '<span class="badge gray">Inactive</span>' ?></td>
            <td class="row-actions">
              <a href="profile.php?id=<?= $m['id'] ?>" class="btn btn-ghost btn-sm">View</a>
              <a href="edit.php?id=<?= $m['id'] ?>" class="btn btn-navy btn-sm">Edit</a>
              <a href="delete.php?id=<?= $m['id'] ?>" class="btn btn-danger btn-sm" data-confirm="Delete member <?= e($m['name']) ?>? This also removes their attendance & fee records.">Delete</a>
            </td>
          </tr>
        <?php endwhile; ?>
        <?php if ($members->num_rows === 0): ?>
          <tr><td colspan="10" class="empty"><span class="ico">&#9635;</span>No members yet. Click "Add Member" to get started.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    if (typeof initTableSearch === 'function') initTableSearch('searchInput','membersTable');
  });
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
