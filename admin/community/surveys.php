<?php
/**
 * admin/community/surveys.php — survey & poll management.
 *
 * Create surveys with single-choice / multi-choice / text questions,
 * open / close them, and view aggregated results with charts.
 *
 * GET:  ?survey=<id> — focus one survey (form + results)
 * POST: action = create | add_question | delete_question | open | close | delete
 */
$PAGE_TITLE = 'Surveys & Polls';
$PAGE_KEY   = 'community-surveys';
require_once __DIR__ . '/../../includes/header.php';

$db = db();
$adminId = (int)($_SESSION['admin_id'] ?? 0);

/* ------------------------------------------------------------------ */
/* POST actions                                                        */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = $_POST['action'] ?? '';
    $bail = function (string $m, $survey = 0) {
        header('Location: surveys.php' . ($survey ? "?survey=$survey&" : '?') . 'err=' . urlencode($m));
        exit;
    };

    /* ---------- create ---------- */
    if ($action === 'create') {
        $title = mb_substr(trim($_POST['title'] ?? ''), 0, 160);
        $desc  = mb_substr(trim($_POST['description'] ?? ''), 0, 1000);
        $type  = ($_POST['type'] ?? 'Poll') === 'Survey' ? 'Survey' : 'Poll';
        if ($title === '') $bail('Title is required.');
        $stmt = $db->prepare("INSERT INTO community_surveys (title, description, type, status, created_by) VALUES (?,?,?,'Open',?)");
        $stmt->bind_param('sssi', $title, $desc, $type, $adminId);
        $stmt->execute();
        $sid = $stmt->insert_id;
        $stmt->close();
        header('Location: surveys.php?survey=' . $sid . '&ok=' . urlencode("Survey \"$title\" created and opened. Add questions below.")); exit;
    }

    /* ---------- add_question ---------- */
    if ($action === 'add_question') {
        $sid  = (int)($_POST['survey_id'] ?? 0);
        $text = mb_substr(trim($_POST['question_text'] ?? ''), 0, 255);
        $qtype = in_array($_POST['question_type'] ?? '', ['single', 'multi', 'text'], true) ? $_POST['question_type'] : 'single';
        $opts = array_values(array_filter(array_map('trim', explode("\n", $_POST['options'] ?? '')), fn($o) => $o !== ''));
        $opts = array_slice(array_map(fn($o) => mb_substr($o, 0, 160), $opts), 0, 10);

        if (!$sid) $bail('Invalid survey id.');
        if ($text === '') $bail('Question text is required.', $sid);
        if ($qtype !== 'text' && count($opts) < 2) $bail('Choice questions need at least 2 options (one per line).', $sid);
        if ($qtype === 'text') $opts = ['__text__'];

        $optsJson = json_encode($opts, JSON_UNESCAPED_UNICODE);
        $stmt = $db->prepare("INSERT INTO survey_questions (survey_id, question_text, question_type, options) VALUES (?,?,?,?)");
        $stmt->bind_param('isss', $sid, $text, $qtype, $optsJson);
        $stmt->execute();
        $stmt->close();
        header('Location: surveys.php?survey=' . $sid . '&ok=' . urlencode('Question added.')); exit;
    }

    /* ---------- delete_question ---------- */
    if ($action === 'delete_question') {
        $qid = (int)($_POST['question_id'] ?? 0);
        $sid = (int)($_POST['survey_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM survey_questions WHERE id = ? AND survey_id = ?");
        $stmt->bind_param('ii', $qid, $sid);
        $stmt->execute();
        $stmt->close();
        header('Location: surveys.php?survey=' . $sid . '&ok=' . urlencode('Question removed (its responses were also removed).')); exit;
    }

    /* ---------- open / close ---------- */
    if ($action === 'open' || $action === 'close') {
        $sid = (int)($_POST['survey_id'] ?? 0);
        $newStatus = $action === 'open' ? 'Open' : 'Closed';
        $stmt = $db->prepare("UPDATE community_surveys SET status = ?, closed_at = " . ($action === 'close' ? 'NOW()' : 'NULL') . " WHERE id = ?");
        $stmt->bind_param('si', $newStatus, $sid);
        $stmt->execute();
        $stmt->close();
        header('Location: surveys.php?survey=' . $sid . '&ok=' . urlencode("Survey #$sid is now $newStatus.")); exit;
    }

    /* ---------- delete ---------- */
    if ($action === 'delete') {
        $sid = (int)($_POST['survey_id'] ?? 0);
        try {
            $stmt = $db->prepare("DELETE FROM community_surveys WHERE id = ?");
            $stmt->bind_param('i', $sid);
            $stmt->execute();
            $stmt->close();
            header('Location: surveys.php?ok=' . urlencode("Survey #$sid deleted (questions and responses removed).")); exit;
        } catch (mysqli_sql_exception $ex) {
            $bail('Could not delete survey (database constraint).', $sid);
        }
    }

    $bail('Unknown action.');
}

/* ------------------------------------------------------------------ */
/* GET page                                                            */
/* ------------------------------------------------------------------ */
$surveys = $db->query(
    "SELECT s.*,
            (SELECT COUNT(*) FROM survey_questions q WHERE q.survey_id = s.id) AS questions,
            (SELECT COUNT(DISTINCT r.community_user_id) FROM survey_responses r WHERE r.survey_id = s.id) AS respondents
     FROM community_surveys s
     ORDER BY FIELD(s.status,'Open','Closed'), s.created_at DESC"
);

$sel = (int)($_GET['survey'] ?? 0);
$selSurvey = null; $questions = null;
if ($sel) {
    $stmt = $db->prepare("SELECT * FROM community_surveys WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $sel);
    $stmt->execute();
    $selSurvey = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($selSurvey) {
        $stmt = $db->prepare("SELECT * FROM survey_questions WHERE survey_id = ? ORDER BY id");
        $stmt->bind_param('i', $sel);
        $stmt->execute();
        $questions = $stmt->get_result();
        $stmt->close();
    }
}

/** Bar chart for one question's aggregated results. */
function question_results(mysqli $db, int $qid): array {
    $stmt = $db->prepare("SELECT option_text, COUNT(*) c FROM survey_responses WHERE question_id = ? AND option_text IS NOT NULL GROUP BY option_text ORDER BY c DESC");
    $stmt->bind_param('i', $qid);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) $out[$r['option_text']] = (int)$r['c'];
    $stmt->close();
    return $out;
}
function text_answers(mysqli $db, int $qid) {
    $stmt = $db->prepare("SELECT response_text, submitted_at FROM survey_responses WHERE question_id = ? AND response_text IS NOT NULL AND response_text <> '' ORDER BY submitted_at DESC LIMIT 50");
    $stmt->bind_param('i', $qid);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();
    return $res;
}
?>
<?= flash() ?>
<div class="page-head">
  <div>
    <h2>Surveys &amp; Polls</h2>
    <p>Create quick polls and detailed surveys to gather structured community input.</p>
  </div>
</div>
<?php include __DIR__ . '/_nav.php'; ?>

<?php if ($selSurvey): ?>
  <!-- ============ focused survey view ============ -->
  <a href="surveys.php" class="btn btn-ghost btn-sm" style="margin-bottom:14px">&larr; All surveys</a>

  <div class="grid cols-2">
    <div class="card">
      <div class="card-head">
        <h3><?= e($selSurvey['title']) ?></h3>
        <div style="display:flex;gap:8px;align-items:center">
          <span class="badge <?= $selSurvey['status'] === 'Open' ? 'green' : 'gray' ?>"><?= e($selSurvey['status']) ?></span>
          <span class="badge steel"><?= e($selSurvey['type']) ?></span>
        </div>
      </div>
      <div class="card-body">
        <?php if ($selSurvey['description']): ?><p style="margin-top:0"><?= e($selSurvey['description']) ?></p><?php endif; ?>
        <p class="muted" style="font-size:13px">
          <?= (int)$questions->num_rows ?> question(s) &middot; <?= (int)($selSurvey['respondents'] ?? 0) ?> respondent(s) &middot;
          created <?= fmtDate(substr($selSurvey['created_at'], 0, 10)) ?>
          <?php if ($selSurvey['closed_at']): ?> &middot; closed <?= fmtDate(substr($selSurvey['closed_at'], 0, 10)) ?><?php endif; ?>
        </p>
        <div class="form-actions">
          <?php if ($selSurvey['status'] === 'Open'): ?>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="close">
              <input type="hidden" name="survey_id" value="<?= $selSurvey['id'] ?>">
              <button class="btn btn-warning btn-sm" type="submit">Close Survey</button>
            </form>
          <?php else: ?>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="open">
              <input type="hidden" name="survey_id" value="<?= $selSurvey['id'] ?>">
              <button class="btn btn-primary btn-sm" type="submit">Re-open Survey</button>
            </form>
          <?php endif; ?>
          <form method="post" data-confirm="Delete this survey, its questions and all responses?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="survey_id" value="<?= $selSurvey['id'] ?>">
            <button class="btn btn-danger btn-sm" type="submit">Delete</button>
          </form>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h3>Add a Question</h3></div>
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_question">
          <input type="hidden" name="survey_id" value="<?= $selSurvey['id'] ?>">
          <div class="form-field" style="margin-bottom:12px">
            <label>Question text <span class="req">*</span></label>
            <input type="text" name="question_text" maxlength="255" required placeholder="e.g. Which morning time suits you best?">
          </div>
          <div class="form-field" style="margin-bottom:12px">
            <label>Answer type</label>
            <select name="question_type" id="qtype" onchange="document.getElementById('optwrap').style.display = this.value === 'text' ? 'none' : 'block'">
              <option value="single">Single choice (radio — poll style)</option>
              <option value="multi">Multi choice (checkboxes)</option>
              <option value="text">Open text</option>
            </select>
          </div>
          <div class="form-field" id="optwrap">
            <label>Options — one per line <span class="req">*</span></label>
            <textarea name="options" rows="4" placeholder="6:00 AM&#10;7:00 AM&#10;8:00 AM"></textarea>
          </div>
          <div class="form-actions"><button class="btn btn-primary btn-sm" type="submit">Add Question</button></div>
        </form>
      </div>
    </div>
  </div>

  <?php if ($questions && $questions->num_rows): ?>
  <h3 style="margin:24px 0 10px">Questions &amp; Live Results</h3>
  <?php $questions->data_seek(0); while ($q = $questions->fetch_assoc()): $opts = json_decode($q['options'], true) ?: []; ?>
    <div class="card" style="margin-bottom:14px">
      <div class="card-head">
        <h3>Q<?= (int)$q['id'] ?> &middot; <?= e($q['question_text']) ?></h3>
        <form method="post" data-confirm="Remove this question and its responses?">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete_question">
          <input type="hidden" name="survey_id" value="<?= $selSurvey['id'] ?>">
          <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
          <button class="btn btn-danger btn-sm" type="submit">Remove</button>
        </form>
      </div>
      <div class="card-body">
        <?php if ($q['question_type'] === 'text'): $answers = text_answers($db, (int)$q['id']); ?>
          <?php if ($answers && $answers->num_rows): ?>
            <ul style="margin:0;padding-left:18px;font-size:13.5px">
              <?php while ($a = $answers->fetch_assoc()): ?>
                <li style="margin-bottom:4px">&ldquo;<?= e($a['response_text']) ?>&rdquo; <small class="muted">(<?= fmtDate(substr($a['submitted_at'], 0, 10)) ?>)</small></li>
              <?php endwhile; ?>
            </ul>
          <?php else: ?><p class="muted" style="margin:0">No text answers yet.</p><?php endif; ?>
        <?php else: $results = question_results($db, (int)$q['id']); $total = array_sum($results); ?>
          <?php if ($total): ?>
            <div class="comm-bars">
              <?php foreach ($opts as $o): $n = (int)($results[$o] ?? 0); $pct = $total ? round($n / $total * 100) : 0; ?>
                <div class="bar-row">
                  <div class="lbl"><?= e($o) ?></div>
                  <div class="track"><div class="fill" style="width: <?= $pct ?>%"></div></div>
                  <div class="val"><?= $n ?> (<?= $pct ?>%)</div>
                </div>
              <?php endforeach; ?>
            </div>
            <p class="muted" style="font-size:12.5px;margin:10px 0 0"><?= $total ?> response(s)</p>
          <?php else: ?>
            <p class="muted" style="margin:0">No responses yet.</p>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  <?php endwhile; ?>
  <?php endif; ?>

<?php else: ?>
  <!-- ============ all surveys list ============ -->
  <?php if ($surveys && $surveys->num_rows): ?>
  <div class="card" style="margin-bottom:18px">
    <div class="card-head"><h3>All Surveys &amp; Polls</h3></div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>ID</th><th>Title</th><th>Type</th><th>Questions</th><th>Respondents</th><th>Status</th><th>Created</th><th class="no-sort">Actions</th></tr></thead>
          <tbody>
          <?php while ($s = $surveys->fetch_assoc()): ?>
            <tr>
              <td>#<?= $s['id'] ?></td>
              <td><b><?= e($s['title']) ?></b></td>
              <td><span class="badge steel"><?= e($s['type']) ?></span></td>
              <td><?= (int)$s['questions'] ?></td>
              <td><?= (int)$s['respondents'] ?></td>
              <td><span class="badge <?= $s['status'] === 'Open' ? 'green' : 'gray' ?>"><?= e($s['status']) ?></span></td>
              <td><?= fmtDate(substr($s['created_at'], 0, 10)) ?></td>
              <td class="row-actions">
                <a href="surveys.php?survey=<?= $s['id'] ?>" class="btn btn-navy btn-sm">Manage &amp; Results</a>
              </td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php else: ?>
    <div class="card"><div class="card-body"><p class="muted" style="margin:0">No surveys yet — create the first one below.</p></div></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-head"><h3>Create New Survey / Poll</h3></div>
    <div class="card-body">
      <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div class="form-field">
          <label>Title <span class="req">*</span></label>
          <input type="text" name="title" maxlength="160" required placeholder="e.g. Preferred morning timings for yoga">
        </div>
        <div class="form-field">
          <label>Type</label>
          <select name="type">
            <option value="Poll">Poll (quick single questions)</option>
            <option value="Survey">Survey (detailed feedback)</option>
          </select>
        </div>
        <div class="form-field full">
          <label>Description</label>
          <textarea name="description" rows="3" maxlength="1000" placeholder="What is this survey for?"></textarea>
        </div>
        <div class="form-actions"><button class="btn btn-primary" type="submit">Create &amp; Open</button></div>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
