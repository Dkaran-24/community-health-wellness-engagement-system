<?php
/**
 * community/surveys.php — Surveys & Polls (participate + live results).
 *
 * One response per user per question (UNIQUE constraint backstop).
 * Shows both open surveys/polls (with forms) and closed ones with
 * final results rendered as horizontal bar charts.
 */
$PAGE_TITLE = 'Surveys & Polls';
$PAGE_KEY   = 'surveys';
require_once __DIR__ . '/../includes/community_auth.php';
require_community_login();
require_once __DIR__ . '/../db_connect.php';   /* DB needed by the POST handler, which runs before the layout */

$db  = db();
$uid = (int)$_SESSION['community_user_id'];

/* ---------- POST: submit answers ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $sid = (int)($_POST['survey_id'] ?? 0);

    $stmt = $db->prepare("SELECT id, status FROM community_surveys WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $sid); $stmt->execute();
    $srv = $stmt->get_result()->fetch_assoc(); $stmt->close();

    if (!$srv) { header('Location: surveys.php?err=' . urlencode('Survey not found.')); exit; }
    if ($srv['status'] !== 'Open') { header('Location: surveys.php?warn=' . urlencode('This ' . strtolower($srv['type'] ?? 'survey') . ' is now closed.')); exit; }

    /* process each question: q_<questionId> holds the answer */
    $okCount = 0; $errors2 = [];
    $stmt = $db->prepare("SELECT id, question_text, question_type, options FROM survey_questions WHERE survey_id = ? ORDER BY id");
    $stmt->bind_param('i', $sid); $stmt->execute();
    $qs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

    $alreadyAny = false;
    foreach ($qs as $q) {
        $ans = $_POST['q_' . $q['id']] ?? null;
        if ($ans === null || $ans === '') continue;
        if ($q['question_type'] === 'text') {
            $txt = trim((string)$ans);
            if (mb_strlen($txt) > 500) { $errors2[] = 'Text answers are limited to 500 characters.'; continue; }
            $opt = null;
        } else {
            $opts = json_decode($q['options'] ?: '[]', true) ?: [];
            if (!in_array($ans, $opts, true)) { $errors2[] = 'Invalid option submitted.'; continue; }
            $txt = null;
            $opt = $ans;   /* store the selected option label */
        }
        $stmt = $db->prepare("INSERT INTO survey_responses (survey_id, question_id, community_user_id, option_text, response_text)
                VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('iiiss', $sid, $q['id'], $uid, $opt, $txt);
        try { $stmt->execute(); $okCount++; }
        catch (mysqli_sql_exception $ex) {
            if (strpos($ex->getMessage(), 'uniq_sr_user_question') === false) throw $ex;
            $alreadyAny = true;
        }
        $stmt->close();
    }
    $msg = $okCount ? 'Thank you! Your responses were recorded.' : 'No answers were submitted.';
    if ($alreadyAny) $msg .= ' (You had already answered some questions — duplicates were skipped.)';
    header('Location: surveys.php?' . ($okCount ? 'ok' : 'warn') . '=' . urlencode($msg) . '#survey-' . $sid);
    exit;
}

/* ---------- layout header (renders after the POST handler; unreachable when the POST handler redirects) ---------- */
require_once __DIR__ . '/_header.php';

/* ---------- list surveys ---------- */
$surveys = $db->query(
    "SELECT s.*, (SELECT COUNT(*) FROM survey_responses r WHERE r.survey_id = s.id) AS resp_count
     FROM community_surveys s
     ORDER BY FIELD(s.status,'Open','Closed'), s.created_at DESC"
);

/* helper: build results chart for one question */
function question_chart(mysqli $db, $q) {
    if ($q['question_type'] === 'text') {
        $stmt = $db->prepare("SELECT response_text, full_name FROM survey_responses r
                JOIN community_users u ON u.id = r.community_user_id
                WHERE r.question_id = ? AND r.response_text IS NOT NULL AND r.response_text <> ''
                ORDER BY r.submitted_at DESC LIMIT 20");
        $stmt->bind_param('i', $q['id']); $stmt->execute();
        $res = $stmt->get_result(); $stmt->close();
        $out = '';
        while ($r = $res->fetch_assoc()) {
            $out .= '<div style="padding:8px 12px;border-left:3px solid var(--steel);background:#f7fafd;'
                  . 'border-radius:0 8px 8px 0;margin-bottom:8px;font-size:13px;">'
                  . '<b>' . e($r['full_name']) . ':</b> ' . e($r['response_text']) . '</div>';
        }
        return $out ?: '<p class="c-muted" style="font-size:12.5px;">No written answers yet.</p>';
    }
    $opts = json_decode($q['options'] ?: '[]', true) ?: [];
    $stmt = $db->prepare("SELECT option_text, COUNT(*) c FROM survey_responses WHERE question_id = ? GROUP BY option_text");
    $stmt->bind_param('i', $q['id']); $stmt->execute();
    $res = $stmt->get_result();
    $counts = [];
    while ($r = $res->fetch_assoc()) $counts[$r['option_text']] = (int)$r['c'];
    $stmt->close();
    $total = array_sum($counts) ?: 1;
    $out = '<div class="c-chart">';
    foreach ($opts as $o) {
        $c = $counts[$o] ?? 0; $pct = round($c * 100 / $total, 1);
        $out .= '<div class="row"><span>' . e($o) . '</span>'
              . '<div class="bar' . ($c ? '' : ' empty') . '"><span style="width:' . $pct . '%"></span></div>'
              . '<span class="val">' . $c . '</span></div>';
    }
    $out .= '</div><div class="c-muted" style="font-size:12px;">Total votes: ' . array_sum($counts) . '</div>';
    return $out;
}
?>
<div class="c-hero small">
  <span class="c-tag">SURVEYS &amp; POLLS</span>
  <h1>Surveys &amp; Polls</h1>
  <p>Help shape the community program — vote in polls and answer short wellness surveys.
     Results update live as neighbours respond.</p>
</div>

<?php if ($surveys && $surveys->num_rows): while ($s = $surveys->fetch_assoc()):
    $sid = (int)$s['id'];
    $stmt = $db->prepare("SELECT id, question_text, question_type, options FROM survey_questions WHERE survey_id = ? ORDER BY id");
    $stmt->bind_param('i', $sid); $stmt->execute();
    $questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

    /* which questions has THIS user already answered? */
    $answered = [];
    $stmt = $db->prepare("SELECT question_id FROM survey_responses WHERE survey_id = ? AND community_user_id = ?");
    $stmt->bind_param('ii', $sid, $uid); $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $answered[(int)$r['question_id']] = true;
    $stmt->close();
?>
<div class="c-card" id="survey-<?= $sid ?>" style="margin-bottom:20px;">
  <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px;align-items:flex-start;">
    <div>
      <h3 style="margin:0 0 4px;"><?= e($s['title']) ?></h3>
      <span class="c-badge <?= $s['type'] === 'Poll' ? 'gold' : 'steel' ?>"><?= e($s['type']) ?></span>
      <?php if ($s['status'] === 'Open'): ?><span class="c-badge green">OPEN</span>
      <?php else: ?><span class="c-badge gray">CLOSED</span><?php endif; ?>
      <span class="c-badge blue"><?= (int)$s['resp_count'] ?> responses</span>
      <?php if ($s['closed_at']): ?><span class="c-muted" style="font-size:12px;">closed <?= date('M j, Y', strtotime($s['closed_at'])) ?></span><?php endif; ?>
    </div>
    <?php if ($s['status'] === 'Open'): ?>
      <?php if ($answered): ?><span class="c-badge green">&#10003; YOU RESPONDED</span><?php endif; ?>
    <?php endif; ?>
  </div>
  <p class="c-muted" style="font-size:13px;margin:10px 0 4px;"><?= e($s['description'] ?? '') ?></p>

  <?php if ($s['status'] === 'Open' && $questions): ?>
  <form method="post" action="surveys.php#survey-<?= $sid ?>" style="margin-top:10px;">
    <?= csrf_field() ?>
    <input type="hidden" name="survey_id" value="<?= $sid ?>">
    <?php $anyUnanswered = false; foreach ($questions as $q):
        $done = isset($answered[(int)$q['id']]);
        if (!$done) $anyUnanswered = true;
        $opts = json_decode($q['options'] ?: '[]', true) ?: [];
    ?>
    <div class="c-field" style="padding:10px 0;border-bottom:1px dashed var(--line);">
      <label style="font-weight:600;color:var(--navy-800);"><?= e($q['question_text']) ?></label>
      <?php if ($done): ?>
        <div class="c-muted" style="font-size:12.5px;margin-top:4px;">&#10003; You already answered this question.</div>
      <?php elseif ($q['question_type'] === 'text'): ?>
        <textarea name="q_<?= (int)$q['id'] ?>" rows="2" maxlength="500" placeholder="Share your thoughts (optional)"></textarea>
      <?php else: ?>
        <div class="rating-row">
        <?php foreach ($opts as $o): ?>
          <label class="rate-opt"><input type="radio" name="q_<?= (int)$q['id'] ?>" value="<?= e($o) ?>"> <?= e($o) ?></label>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if ($anyUnanswered): ?>
      <button class="c-btn gold sm" style="margin-top:12px;">Submit My Responses</button>
    <?php else: ?>
      <p class="c-muted" style="font-size:12.5px;margin-top:10px;">&#10003; You have answered all questions in this <?= e(strtolower($s['type'])) ?>.</p>
    <?php endif; ?>
  </form>
  <?php endif; ?>

  <?php if ($questions): ?>
  <div style="margin-top:14px;">
    <div style="font-weight:700;font-size:13.5px;color:var(--navy-800);margin-bottom:8px;">
      <?= $s['status'] === 'Open' ? 'Live Results' : 'Final Results' ?> <span class="c-badge steel">UPDATES AUTOMATICALLY</span>
    </div>
    <?php foreach ($questions as $q): ?>
      <div class="c-chart-block">
        <h4><?= e($q['question_text']) ?></h4>
        <?= question_chart($db, $q) ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php endwhile; else: ?>
<div class="c-card"><p class="c-muted">No surveys or polls are available right now. Check back soon!</p></div>
<?php endif; ?>

<?php require_once __DIR__ . '/_footer.php'; ?>
