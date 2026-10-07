<?php
/**
 * community/challenges.php — Community Fitness Challenges (CEP).
 *
 * Join a challenge, log daily progress, watch the progress bar fill,
 * and earn completion when the target is reached.
 * One join per user per challenge (UNIQUE backstop).
 */
$PAGE_TITLE = 'Fitness Challenges';
$PAGE_KEY   = 'challenges';
require_once __DIR__ . '/../includes/community_auth.php';
require_community_login();
require_once __DIR__ . '/_header.php';

$db    = db();
$uid   = (int)$_SESSION['community_user_id'];
$today = date('Y-m-d');

/* ================= POST handlers ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $act  = $_POST['action'] ?? '';
    $cid  = (int)($_POST['challenge_id'] ?? 0);

    /* ---- JOIN ---- */
    if ($act === 'join') {
        $stmt = $db->prepare("SELECT * FROM fitness_challenges WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $cid); $stmt->execute();
        $ch = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$ch || !in_array($ch['status'], ['Active','Upcoming'], true)) {
            header('Location: challenges.php?err=' . urlencode('This challenge is not open for joining.')); exit;
        }
        if ($ch['end_date'] < $today) {
            header('Location: challenges.php?err=' . urlencode('This challenge has already ended.')); exit;
        }
        $stmt = $db->prepare("SELECT id FROM challenge_participants WHERE challenge_id = ? AND community_user_id = ? LIMIT 1");
        $stmt->bind_param('ii', $cid, $uid); $stmt->execute();
        $dup = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($dup) {
            header('Location: challenges.php?warn=' . urlencode('You have already joined this challenge.')); exit;
        }
        $stmt = $db->prepare("INSERT INTO challenge_participants (challenge_id, community_user_id) VALUES (?, ?)");
        $stmt->bind_param('ii', $cid, $uid);
        try { $stmt->execute(); }
        catch (mysqli_sql_exception $ex) {
            if (strpos($ex->getMessage(), 'uniq_cp_user_chal') === false) throw $ex;
        }
        $stmt->close();
        header('Location: challenges.php?ok=' . urlencode('You joined "' . $ch['challenge_name'] . '"! Start logging your progress today.'));
        exit;
    }

    /* ---- LOG PROGRESS ---- */
    if ($act === 'log') {
        $val  = (int)($_POST['value_logged'] ?? 1);
        $note = trim($_POST['notes'] ?? '');
        $d    = $_POST['progress_date'] ?? $today;

        $stmt = $db->prepare(
            "SELECT cp.id AS pid, cp.current_value, cp.completed, c.*
             FROM challenge_participants cp JOIN fitness_challenges c ON c.id = cp.challenge_id
             WHERE cp.challenge_id = ? AND cp.community_user_id = ? LIMIT 1");
        $stmt->bind_param('ii', $cid, $uid); $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc(); $stmt->close();

        $err = '';
        if (!$row) $err = 'Join the challenge before logging progress.';
        elseif ($row['status'] !== 'Active') $err = 'Progress can only be logged during an active challenge.';
        elseif ($d < $row['start_date'] || $d > $row['end_date'] || $d > $today) $err = 'The date must be within the challenge window (and not in the future).';
        elseif ($val < 1 || $val > 100000) $err = 'Value must be between 1 and 100000.';
        elseif (mb_strlen($note) > 255) $err = 'Notes are limited to 255 characters.';
        if ($err) { header('Location: challenges.php?err=' . urlencode($err)); exit; }

        /* one entry per day (UNIQUE backstop); update = replace day's value */
        $stmt = $db->prepare("SELECT id, value_logged FROM challenge_progress WHERE participant_id = ? AND progress_date = ? LIMIT 1");
        $stmt->bind_param('is', $row['pid'], $d); $stmt->execute();
        $prev = $stmt->get_result()->fetch_assoc(); $stmt->close();

        $db->begin_transaction();
        try {
            if ($prev) {
                $stmt = $db->prepare("UPDATE challenge_progress SET value_logged = ?, notes = ? WHERE id = ?");
                $stmt->bind_param('isi', $val, $note, $prev['id']); $stmt->execute(); $stmt->close();
                $delta = $val - (int)$prev['value_logged'];
            } else {
                $stmt = $db->prepare("INSERT INTO challenge_progress (participant_id, progress_date, value_logged, notes) VALUES (?, ?, ?, ?)");
                $stmt->bind_param('isis', $row['pid'], $d, $val, $note); $stmt->execute(); $stmt->close();
                $delta = $val;
            }
            $newVal = (int)$row['current_value'] + $delta;
            $done   = $newVal >= (int)$row['target_value'];
            $stmt = $db->prepare("UPDATE challenge_participants SET current_value = ?, completed = ?, completed_at = ? WHERE id = ?");
            $z = $done ? date('Y-m-d H:i:s') : null;
            $stmt->bind_param('iisi', $newVal, $done, $z, $row['pid']);
            $stmt->execute(); $stmt->close();
            $db->commit();
        } catch (Throwable $t) {
            $db->rollback();
            throw $t;
        }
        $msg = 'Progress logged: +' . $delta . ' ' . $row['target_unit'] . ' (total ' . $newVal . '/' . (int)$row['target_value'] . ').';
        if ($done && !$row['completed']) $msg .= ' *** CHALLENGE COMPLETED — well done! ***';
        header('Location: challenges.php?ok=' . urlencode($msg) . '#chal-' . $cid);
        exit;
    }
}

/* ================= data ================= */
$challenges = $db->query(
    "SELECT c.*,
            (SELECT COUNT(*) FROM challenge_participants p WHERE p.challenge_id = c.id) AS participants
     FROM fitness_challenges c
     ORDER BY FIELD(c.status,'Active','Upcoming','Completed'), c.start_date ASC"
);
$chalArr = $challenges ? $challenges->fetch_all(MYSQLI_ASSOC) : [];

/* my participation per challenge */
$myPart = [];
$stmt = $db->prepare("SELECT cp.*, c.target_value, c.target_unit FROM challenge_participants cp
                      JOIN fitness_challenges c ON c.id = cp.challenge_id WHERE cp.community_user_id = ?");
$stmt->bind_param('i', $uid); $stmt->execute(); $res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $myPart[(int)$r['challenge_id']] = $r;
$stmt->close();
?>
<div class="c-hero small">
  <span class="c-tag">STAY MOTIVATED</span>
  <h1>Community Fitness Challenges</h1>
  <p>Fun, free challenges for the whole neighbourhood — walk, run, stretch or breathe your way
     to the target, one day at a time. Log your progress and celebrate completion together.</p>
</div>

<?php if (!$chalArr): ?>
<div class="c-card"><p class="c-muted">No challenges are running right now. New ones are announced regularly — check back soon!</p></div>
<?php else: foreach ($chalArr as $c):
    $cid   = (int)$c['id'];
    $part  = $myPart[$cid] ?? null;
    $target = max(1, (int)$c['target_value']);
    $cur    = $part ? (int)$part['current_value'] : 0;
    $pct    = min(100, (int)($cur * 100 / $target));
    $canJoin = in_array($c['status'], ['Active','Upcoming'], true) && $c['end_date'] >= $today && !$part;
    $canLog  = $part && $c['status'] === 'Active';
    $completions = (int)$db->query("SELECT COUNT(*) c2 FROM challenge_participants WHERE challenge_id=$cid AND completed=1")->fetch_assoc()['c2'];

    /* my recent logs for this challenge */
    $logs = null;
    if ($part) {
        $stmt = $db->prepare("SELECT progress_date, value_logged, notes FROM challenge_progress
                              WHERE participant_id = ? ORDER BY progress_date DESC LIMIT 5");
        $stmt->bind_param('i', $part['id']); $stmt->execute(); $logs = $stmt->get_result(); $stmt->close();
    }
?>
<div class="c-card" id="chal-<?= $cid ?>" style="margin-bottom:18px;">
  <div style="display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap;">
    <div style="flex:1;min-width:250px;">
      <h3 style="margin:0 0 4px;"><?= e($c['challenge_name']) ?></h3>
      <span class="c-badge blue"><?= e($c['category']) ?></span>
      <?php if ($c['status'] === 'Active'): ?><span class="c-badge green">ACTIVE</span>
      <?php elseif ($c['status'] === 'Upcoming'): ?><span class="c-badge gold">UPCOMING</span>
      <?php else: ?><span class="c-badge gray">COMPLETED</span><?php endif; ?>
      <?php if ($part && $part['completed']): ?><span class="c-badge gold">&#127942; YOU COMPLETED IT</span><?php endif; ?>
      <p class="c-desc" style="margin:8px 0;color:var(--ink);font-size:13.5px;"><?= e($c['description'] ?? '') ?></p>
      <div class="c-meta" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;font-size:13px;">
        <div><b>&#127919; Target:</b> <?= (int)$c['target_value'] ?> <?= e($c['target_unit']) ?></div>
        <div><b>&#128197; Window:</b> <?= fmtDate($c['start_date']) ?> &rarr; <?= fmtDate($c['end_date']) ?></div>
        <div><b>&#128101; Participants:</b> <?= (int)$c['participants'] ?></div>
        <div><b>&#127942; Completions:</b> <?= $completions ?></div>
      </div>
    </div>

    <div style="width:280px;">
      <?php if ($part): ?>
        <div style="font-size:12.5px;color:var(--muted);margin-bottom:5px;">My progress: <b><?= $cur ?></b> / <?= $target ?> <?= e($c['target_unit']) ?></div>
        <div class="c-capbar"><span style="width:<?= $pct ?>%"></span></div>
        <div style="font-size:11.5px;color:var(--muted);margin:4px 0 10px;"><?= $pct ?>% of target<?= $part['completed'] ? ' — completed ' . date('M j', strtotime($part['completed_at'])) : '' ?></div>
      <?php endif; ?>

      <?php if ($canJoin): ?>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="join">
          <input type="hidden" name="challenge_id" value="<?= $cid ?>">
          <button class="c-btn gold sm" style="width:100%;">Join Challenge &rarr;</button>
        </form>
      <?php elseif ($c['status'] === 'Completed' || $c['end_date'] < $today): ?>
        <button class="c-btn ghost sm" style="width:100%;" disabled>Challenge Ended</button>
      <?php elseif ($part && $c['status'] === 'Upcoming'): ?>
        <button class="c-btn ghost sm" style="width:100%;" disabled>Starts <?= fmtDate($c['start_date']) ?></button>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($canLog): ?>
  <div style="margin-top:14px;padding:14px;border:1px solid var(--line);border-radius:10px;background:#f9fbfd;">
    <b style="font-size:13px;color:var(--navy-800);">&#128221; Log today's progress</b>
    <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-top:8px;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="log">
      <input type="hidden" name="challenge_id" value="<?= $cid ?>">
      <div class="c-field" style="margin:0;">
        <label>Date</label>
        <input type="date" name="progress_date" value="<?= $today ?>" max="<?= $today ?>"
               min="<?= max($c['start_date'], '2000-01-01') ?>">
      </div>
      <div class="c-field" style="margin:0;">
        <label><?= e(ucfirst($c['target_unit'])) ?> done</label>
        <input type="number" name="value_logged" min="1" max="100000" value="1" required style="width:110px;">
      </div>
      <div class="c-field" style="margin:0;flex:1;min-width:160px;">
        <label>Notes (optional)</label>
        <input type="text" name="notes" maxlength="255" placeholder="e.g. morning walk with neighbour">
      </div>
      <button class="c-btn gold sm">Log</button>
    </form>
    <div class="c-muted" style="font-size:11.5px;margin-top:6px;">
      One entry per day — logging again for the same date updates that day's value.
    </div>
  </div>
  <?php endif; ?>

  <?php if ($logs && $logs->num_rows): ?>
  <div style="margin-top:12px;">
    <b style="font-size:12.5px;color:var(--navy-800);">My recent logs:</b>
    <?php while ($lg = $logs->fetch_assoc()): ?>
      <span class="c-badge gray" style="margin:4px 4px 0 0;"><?= date('M j', strtotime($lg['progress_date'])) ?>: +<?= (int)$lg['value_logged'] ?><?= $lg['notes'] ? ' &middot; ' . e($lg['notes']) : '' ?></span>
    <?php endwhile; ?>
  </div>
  <?php endif; ?>
</div>
<?php endforeach; endif; ?>

<?php require_once __DIR__ . '/_footer.php'; ?>
