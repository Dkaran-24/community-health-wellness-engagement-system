<?php
/**
 * admin/community/challenges.php — fitness challenges CRUD.
 *
 * Challenges are community-wide goal programmes (e.g. "30-Day Steps Challenge").
 * Community members join from their dashboard and log progress.
 *
 * GET:  ?edit=<id>
 * POST: action = create | update | delete | set_status (CSRF protected)
 */
$PAGE_TITLE = 'Fitness Challenges';
$PAGE_KEY   = 'community-challenges';
require_once __DIR__ . '/../../includes/header.php';

$db = db();

$CATS   = ['Walking','Running','Yoga','General Fitness','Steps','Other'];
$UNITS  = ['days','steps','sessions','km'];
$STATES = ['Upcoming','Active','Completed'];

/* ------------------------------------------------------------------ */
/* POST actions                                                        */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = $_POST['action'] ?? '';
    $bail = function (string $m) { header('Location: challenges.php?err=' . urlencode($m)); exit; };

    if ($action === 'set_status') {
        $id = (int)($_POST['id'] ?? 0);
        $st = in_array($_POST['status'] ?? '', $STATES, true) ? $_POST['status'] : '';
        if (!$id || !$st) $bail('Invalid challenge id or status.');
        $stmt = $db->prepare("UPDATE fitness_challenges SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $st, $id);
        $stmt->execute();
        $stmt->close();
        header('Location: challenges.php?ok=' . urlencode("Challenge #$id is now $st.")); exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) $bail('Invalid challenge id.');
        $stmt = $db->prepare("DELETE FROM fitness_challenges WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        header('Location: challenges.php?ok=' . urlencode("Challenge #$id deleted (participants and progress removed).")); exit;
    }

    $id      = (int)($_POST['id'] ?? 0);
    $name    = mb_substr(trim($_POST['challenge_name'] ?? ''), 0, 160);
    $desc    = trim($_POST['description'] ?? '');
    $cat     = in_array($_POST['category'] ?? '', $CATS, true) ? $_POST['category'] : 'General Fitness';
    $unit    = in_array($_POST['target_unit'] ?? '', $UNITS, true) ? $_POST['target_unit'] : 'days';
    $target  = (int)($_POST['target_value'] ?? 0);
    $start   = trim($_POST['start_date'] ?? '');
    $end     = trim($_POST['end_date'] ?? '');
    $status  = in_array($_POST['status'] ?? '', $STATES, true) ? $_POST['status'] : 'Active';

    if ($name === '')            $bail('Challenge name is required.');
    if ($target < 1 || $target > 1000000) $bail('Target value must be between 1 and 1,000,000.');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) $bail('Start and end dates are required (YYYY-MM-DD).');
    if ($end < $start)           $bail('End date must be on or after the start date.');

    if ($action === 'create') {
        $stmt = $db->prepare("INSERT INTO fitness_challenges (challenge_name, description, category, target_value, target_unit, start_date, end_date, status) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param('sssissss', $name, $desc, $cat, $target, $unit, $start, $end, $status);
        $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();
        header('Location: challenges.php?ok=' . urlencode("Challenge \"$name\" created (#$newId).")); exit;
    }
    if ($action === 'update') {
        if (!$id) $bail('Invalid challenge id.');
        $stmt = $db->prepare("UPDATE fitness_challenges SET challenge_name = ?, description = ?, category = ?, target_value = ?, target_unit = ?, start_date = ?, end_date = ?, status = ? WHERE id = ?");
        $stmt->bind_param('sssissssi', $name, $desc, $cat, $target, $unit, $start, $end, $status, $id);
        $stmt->execute();
        $stmt->close();
        header('Location: challenges.php?ok=' . urlencode("Challenge \"$name\" updated.")); exit;
    }
    $bail('Unknown action.');
}

/* ------------------------------------------------------------------ */
/* GET page                                                            */
/* ------------------------------------------------------------------ */
$challenges = $db->query(
    "SELECT c.*,
            COUNT(cp.id)          AS participants,
            SUM(cp.completed)     AS completions
       FROM fitness_challenges c
       LEFT JOIN challenge_participants cp ON cp.challenge_id = c.id
      GROUP BY c.id
      ORDER BY FIELD(c.status,'Active','Upcoming','Completed'), c.start_date ASC"
);

$editChal = null;
if (isset($_GET['edit'])) {
    $cid = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM fitness_challenges WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $cid);
    $stmt->execute();
    $editChal = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
$v = function (string $key, $fallback = '') use ($editChal) {
    return $editChal[$key] ?? $fallback;
};
?>
<?= flash() ?>
<div class="page-head">
  <div>
    <h2>Fitness Challenges</h2>
    <p>Community-wide goal programmes residents can join from their dashboard (steps, days, sessions or km).</p>
  </div>
  <div class="toolbar">
    <span class="badge green"><?= (int)($challenges->num_rows) ?> challenges</span>
  </div>
</div>

<?php include __DIR__ . '/_nav.php'; ?>

<?php if ($editChal): ?>
<div class="card" style="margin-bottom:18px">
  <div class="card-head"><h3>Edit Challenge #<?= (int)$editChal['id'] ?></h3><a href="challenges.php" class="btn btn-ghost btn-sm">Cancel edit</a></div>
  <div class="card-body">
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" value="<?= (int)$editChal['id'] ?>">
      <?php include __DIR__ . '/_challenge_form_fields.php'; ?>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:18px">
  <div class="card-head"><h3><?= $editChal ? 'Create Another Challenge' : 'Create New Challenge' ?></h3></div>
  <div class="card-body">
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <?php include __DIR__ . '/_challenge_form_fields.php'; ?>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-head"><h3>All Challenges (<?= (int)$challenges->num_rows ?>)</h3></div>
  <div class="card-body" style="padding:0">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>ID</th><th>Challenge</th><th>Category</th><th>Target</th><th>Dates</th><th>Status</th><th>Participants</th><th>Completed</th><th class="no-sort">Actions</th></tr></thead>
        <tbody>
        <?php if ($challenges && $challenges->num_rows): while ($c = $challenges->fetch_assoc()): ?>
          <tr>
            <td>#<?= (int)$c['id'] ?></td>
            <td>
              <strong><?= e($c['challenge_name']) ?></strong>
              <?php if ($c['description']): ?><div class="muted" style="font-size:.85em"><?= e(mb_substr($c['description'], 0, 90)) ?><?= mb_strlen($c['description']) > 90 ? '&hellip;' : '' ?></div><?php endif; ?>
            </td>
            <td><?= e($c['category']) ?></td>
            <td><strong><?= (int)$c['target_value'] ?></strong> <?= e($c['target_unit']) ?></td>
            <td><?= e(fmtDate($c['start_date'])) ?> &rarr; <?= e(fmtDate($c['end_date'])) ?></td>
            <td>
              <form method="post" style="display:inline-flex;gap:6px;align-items:center">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="set_status">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <select name="status" onchange="this.form.submit()">
                  <?php foreach ($STATES as $s): ?>
                    <option value="<?= $s ?>" <?= $c['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            </td>
            <td><span class="badge steel"><?= (int)$c['participants'] ?> joined</span></td>
            <td><?= (int)$c['completions'] ?></td>
            <td class="no-sort">
              <a class="btn btn-sm btn-ghost" href="challenges.php?edit=<?= (int)$c['id'] ?>">Edit</a>
              <form method="post" style="display:inline" data-confirm="Delete this challenge and all participant records?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
              </form>
            </td>
          </tr>
        <?php endwhile; else: ?>
          <tr><td colspan="9" class="muted" style="text-align:center;padding:24px">No challenges yet &mdash; create the first one above.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
