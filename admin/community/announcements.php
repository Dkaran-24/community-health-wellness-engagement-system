<?php
/**
 * admin/community/announcements.php — community announcements CRUD.
 *
 * Announcements are shown on the community dashboard (and public homepage).
 *
 * GET:  ?edit=<id> — edit form
 * POST: action = create | update | delete | archive | unarchive (CSRF protected)
 */
$PAGE_TITLE = 'Announcements';
$PAGE_KEY   = 'community-announcements';
require_once __DIR__ . '/../../includes/header.php';

$db = db();
$adminId = (int)($_SESSION['admin_id'] ?? 0);
$TYPES = ['Event','Health Camp','Challenge','Workshop','Volunteer Opportunity','Notice','Other'];

/* ------------------------------------------------------------------ */
/* POST actions                                                        */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = $_POST['action'] ?? '';
    $bail = function (string $m) { header('Location: announcements.php?err=' . urlencode($m)); exit; };

    if ($action === 'archive' || $action === 'unarchive') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) $bail('Invalid announcement id.');
        $newStatus = $action === 'archive' ? 'Archived' : 'Active';
        $stmt = $db->prepare("UPDATE community_announcements SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $newStatus, $id);
        $stmt->execute();
        $stmt->close();
        header('Location: announcements.php?ok=' . urlencode("Announcement #$id is now $newStatus.")); exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) $bail('Invalid announcement id.');
        $stmt = $db->prepare("DELETE FROM community_announcements WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        header('Location: announcements.php?ok=' . urlencode("Announcement #$id deleted.")); exit;
    }

    $id    = (int)($_POST['id'] ?? 0);
    $title = mb_substr(trim($_POST['title'] ?? ''), 0, 160);
    $msg   = trim($_POST['message'] ?? '');
    $type  = in_array($_POST['type'] ?? '', $TYPES, true) ? $_POST['type'] : 'Notice';
    $status = ($_POST['status'] ?? 'Active') === 'Archived' ? 'Archived' : 'Active';

    if ($title === '') $bail('Title is required.');
    if ($msg === '')   $bail('Message is required.');
    if (mb_strlen($msg) > 2000) $bail('Message must be 2,000 characters or fewer.');

    if ($action === 'create') {
        $stmt = $db->prepare("INSERT INTO community_announcements (title, message, type, status, created_by) VALUES (?,?,?,?,?)");
        $stmt->bind_param('ssssi', $title, $msg, $type, $status, $adminId);
        $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();
        header('Location: announcements.php?ok=' . urlencode("Announcement \"$title\" posted (#$newId).")); exit;
    }
    if ($action === 'update') {
        if (!$id) $bail('Invalid announcement id.');
        $stmt = $db->prepare("UPDATE community_announcements SET title = ?, message = ?, type = ?, status = ? WHERE id = ?");
        $stmt->bind_param('ssssi', $title, $msg, $type, $status, $id);
        $stmt->execute();
        $stmt->close();
        header('Location: announcements.php?ok=' . urlencode("Announcement \"$title\" updated.")); exit;
    }
    $bail('Unknown action.');
}

/* ------------------------------------------------------------------ */
/* GET page                                                            */
/* ------------------------------------------------------------------ */
$announcements = $db->query(
    "SELECT a.*, u2.full_name AS author FROM community_announcements a
     LEFT JOIN admins u2 ON u2.id = a.created_by
     ORDER BY a.status = 'Active' DESC, a.created_at DESC"
);

$editAnn = null;
if (isset($_GET['edit'])) {
    $aid = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM community_announcements WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $aid);
    $stmt->execute();
    $editAnn = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>
<?= flash() ?>
<div class="page-head">
  <div>
    <h2>Community Announcements</h2>
    <p>Short notices shown on the community dashboard and public homepage.</p>
  </div>
</div>
<?php include __DIR__ . '/_nav.php'; ?>

<?php if ($editAnn): ?>
<div class="card" style="margin-bottom:18px">
  <div class="card-head"><h3>Edit Announcement #<?= (int)$editAnn['id'] ?></h3><a href="announcements.php" class="btn btn-ghost btn-sm">Cancel edit</a></div>
  <div class="card-body">
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" value="<?= (int)$editAnn['id'] ?>">
      <?php include __DIR__ . '/_announcement_form_fields.php'; ?>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:18px">
  <div class="card-head"><h3><?= $editAnn ? 'Post Another Announcement' : 'Post New Announcement' ?></h3></div>
  <div class="card-body">
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <?php include __DIR__ . '/_announcement_form_fields.php'; ?>
    </form>
  </div>
</div>

<?php if ($announcements && $announcements->num_rows): ?>
<div class="card">
  <div class="card-head"><h3>All Announcements (<?= (int)$announcements->num_rows ?>)</h3></div>
  <div class="card-body" style="padding:0">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>ID</th><th>Title</th><th>Type</th><th>Message</th><th>Posted By</th><th>Status</th><th class="no-sort">Actions</th></tr></thead>
        <tbody>
        <?php while ($a = $announcements->fetch_assoc()): ?>
          <tr>
            <td>#<?= $a['id'] ?></td>
            <td><b><?= e($a['title']) ?></b></td>
            <td><span class="badge steel"><?= e($a['type']) ?></span></td>
            <td style="max-width:280px"><?= e(mb_substr($a['message'], 0, 100)) ?><?= mb_strlen($a['message']) > 100 ? '&hellip;' : '' ?></td>
            <td><?= e($a['author'] ?: 'Admin') ?></td>
            <td><?= $a['status'] === 'Active' ? '<span class="badge green">Active</span>' : '<span class="badge gray">Archived</span>' ?></td>
            <td class="row-actions">
              <a href="announcements.php?edit=<?= $a['id'] ?>" class="btn btn-navy btn-sm">Edit</a>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $a['status'] === 'Active' ? 'archive' : 'unarchive' ?>">
                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                <button class="btn btn-warning btn-sm" type="submit"><?= $a['status'] === 'Active' ? 'Archive' : 'Restore' ?></button>
              </form>
              <form method="post" style="display:inline" data-confirm="Delete this announcement?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                <button class="btn btn-danger btn-sm" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php else: ?>
  <div class="card"><div class="card-body"><p class="muted" style="margin:0">No announcements yet — post the first one above.</p></div></div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
