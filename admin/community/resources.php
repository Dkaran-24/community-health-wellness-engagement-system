<?php
/**
 * admin/community/resources.php — wellness & fitness resources CRUD.
 *
 * Resources are short educational articles shown in the community portal.
 *
 * GET:  ?edit=<id> — edit form
 * POST: action = create | update | delete | toggle   (CSRF protected)
 */
$PAGE_TITLE = 'Wellness Resources';
$PAGE_KEY   = 'community-resources';
require_once __DIR__ . '/../../includes/header.php';

$db = db();
$adminId = (int)($_SESSION['admin_id'] ?? 0);
$CATS = ['Exercise Guide','Nutrition','Healthy Lifestyle','Fitness Education',
         'Exercise Safety','Wellness Awareness','Beginner Fitness','Other'];

/* ------------------------------------------------------------------ */
/* POST actions                                                        */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = $_POST['action'] ?? '';
    $bail = function (string $m) { header('Location: resources.php?err=' . urlencode($m)); exit; };

    /* ---------- toggle publish ---------- */
    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) $bail('Invalid resource id.');
        $stmt = $db->prepare("UPDATE wellness_resources SET status = IF(status='Published','Draft','Published') WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        header('Location: resources.php?ok=' . urlencode("Resource #$id publish status toggled.")); exit;
    }

    /* ---------- delete ---------- */
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) $bail('Invalid resource id.');
        $stmt = $db->prepare("DELETE FROM wellness_resources WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        header('Location: resources.php?ok=' . urlencode("Resource #$id deleted.")); exit;
    }

    /* ---------- create / update ---------- */
    $id      = (int)($_POST['id'] ?? 0);
    $title   = mb_substr(trim($_POST['title'] ?? ''), 0, 160);
    $cat     = in_array($_POST['category'] ?? '', $CATS, true) ? $_POST['category'] : 'Other';
    $summary = mb_substr(trim($_POST['summary'] ?? ''), 0, 255);
    $content = trim($_POST['content'] ?? '');
    $status  = ($_POST['status'] ?? 'Published') === 'Draft' ? 'Draft' : 'Published';

    if ($title === '')  $bail('Title is required.');
    if ($summary === '') $bail('Summary is required.');
    if (mb_strlen($summary) < 20) $bail('Summary should be at least 20 characters (shown on resource cards).');
    if ($content === '') $bail('Content is required.');

    if ($action === 'create') {
        $stmt = $db->prepare("INSERT INTO wellness_resources (title, category, summary, content, status, created_by) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param('sssssi', $title, $cat, $summary, $content, $status, $adminId);
        $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();
        header('Location: resources.php?ok=' . urlencode("Resource \"$title\" created (#$newId).")); exit;
    }
    if ($action === 'update') {
        if (!$id) $bail('Invalid resource id.');
        $stmt = $db->prepare("UPDATE wellness_resources SET title = ?, category = ?, summary = ?, content = ?, status = ? WHERE id = ?");
        $stmt->bind_param('sssssi', $title, $cat, $summary, $content, $status, $id);
        $stmt->execute();
        $stmt->close();
        header('Location: resources.php?ok=' . urlencode("Resource \"$title\" updated.")); exit;
    }
    $bail('Unknown action.');
}

/* ------------------------------------------------------------------ */
/* GET page                                                            */
/* ------------------------------------------------------------------ */
$resources = $db->query(
    "SELECT * FROM wellness_resources ORDER BY status = 'Published' DESC, created_at DESC"
);

$editRes = null;
if (isset($_GET['edit'])) {
    $rid = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM wellness_resources WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $rid);
    $stmt->execute();
    $editRes = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>
<?= flash() ?>
<div class="page-head">
  <div>
    <h2>Wellness Resources</h2>
    <p>Educational articles on exercise, nutrition and healthy living for the community portal.</p>
  </div>
</div>
<?php include __DIR__ . '/_nav.php'; ?>

<?php if ($editRes): ?>
<div class="card" style="margin-bottom:18px">
  <div class="card-head"><h3>Edit Resource #<?= (int)$editRes['id'] ?></h3><a href="resources.php" class="btn btn-ghost btn-sm">Cancel edit</a></div>
  <div class="card-body">
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" value="<?= (int)$editRes['id'] ?>">
      <?php include __DIR__ . '/_resource_form_fields.php'; ?>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:18px">
  <div class="card-head"><h3><?= $editRes ? 'Create Another Resource' : 'Create New Resource' ?></h3></div>
  <div class="card-body">
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <?php include __DIR__ . '/_resource_form_fields.php'; ?>
    </form>
  </div>
</div>

<?php if ($resources && $resources->num_rows): ?>
<div class="card">
  <div class="card-head"><h3>All Resources (<?= (int)$resources->num_rows ?>)</h3></div>
  <div class="card-body" style="padding:0">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>ID</th><th>Title</th><th>Category</th><th>Views</th><th>Status</th><th class="no-sort">Actions</th></tr></thead>
        <tbody>
        <?php while ($r = $resources->fetch_assoc()): ?>
          <tr>
            <td>#<?= $r['id'] ?></td>
            <td>
              <b><?= e($r['title']) ?></b>
              <br><small class="muted"><?= e(mb_substr($r['summary'], 0, 80)) ?><?= mb_strlen($r['summary']) > 80 ? '&hellip;' : '' ?></small>
            </td>
            <td><span class="badge steel"><?= e($r['category']) ?></span></td>
            <td><?= (int)$r['views'] ?></td>
            <td><?= $r['status'] === 'Published' ? '<span class="badge green">Published</span>' : '<span class="badge gray">Draft</span>' ?></td>
            <td class="row-actions">
              <a href="resources.php?edit=<?= $r['id'] ?>" class="btn btn-navy btn-sm">Edit</a>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                <button class="btn btn-warning btn-sm" type="submit"><?= $r['status'] === 'Published' ? 'Unpublish' : 'Publish' ?></button>
              </form>
              <form method="post" style="display:inline" data-confirm="Delete this resource?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
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
  <div class="card"><div class="card-body"><p class="muted" style="margin:0">No resources yet — create the first one above.</p></div></div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
