<?php
/**
 * member/progress.php — Member-facing Fitness Progress logging page.
 *
 * Lets the logged-in member log their own fitness data:
 *   - Weight entries
 *   - Body measurements (incl. height for BMI)
 *   - Fitness goals
 *   - Workouts
 *   - Milestones
 *
 * All data is scoped to the current member's session ID.
 */
$PAGE_TITLE = 'My Progress';
$PAGE_KEY   = 'progress';
require_once __DIR__ . '/_header.php';
require_once __DIR__ . '/../includes/progress.php';

ensure_progress_tables();

$db  = db();
$id  = (int)$m['id'];   // current member ID from session (set by _header.php)
$mName = $m['name'];

/* ---------- Handle form submissions ---------- */
$ok  = '';
$err = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    try {

    /* ---- WEIGHT LOG ---- */
    if ($action === 'add_weight') {
        $weight = (float)($_POST['weight'] ?? 0);
        $unit   = $_POST['unit'] ?? 'kg';
        $date   = trim($_POST['logged_date'] ?? date('Y-m-d'));
        $notes  = trim($_POST['notes'] ?? '');
        if ($weight <= 0) throw new Exception('Weight must be greater than zero.');
        $stmt = $db->prepare("INSERT INTO member_weight_log (member_id, weight, unit, logged_date, notes) VALUES (?,?,?,?,?)");
        $stmt->bind_param('idsss', $id, $weight, $unit, $date, $notes);
        $stmt->execute(); $stmt->close();
        $ok = "Weight entry ($weight $unit) added successfully!";
    }

    /* ---- MEASUREMENTS ---- */
    if ($action === 'add_measurement') {
        $date       = trim($_POST['logged_date'] ?? date('Y-m-d'));
        $unit       = $_POST['unit'] ?? 'cm';
        $heightUnit = $_POST['height_unit'] ?? 'cm';
        $height     = $_POST['height'] !== '' ? (float)$_POST['height'] : null;
        $chest      = $_POST['chest']    !== '' ? (float)$_POST['chest']    : null;
        $waist      = $_POST['waist']    !== '' ? (float)$_POST['waist']    : null;
        $hips       = $_POST['hips']     !== '' ? (float)$_POST['hips']     : null;
        $arm        = $_POST['arm']      !== '' ? (float)$_POST['arm']      : null;
        $thigh      = $_POST['thigh']    !== '' ? (float)$_POST['thigh']    : null;
        $should     = $_POST['shoulder'] !== '' ? (float)$_POST['shoulder'] : null;
        $notes      = trim($_POST['notes'] ?? '');
        if (($height === null || $height <= 0) && $chest === null && $waist === null && $hips === null) {
            throw new Exception('Please enter at least your height or some body measurements.');
        }
        if ($height === null) $height = 0;
        $stmt = $db->prepare("INSERT INTO member_measurements (member_id, chest, waist, hips, arm, thigh, shoulder, height, height_unit, unit, logged_date, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('idddddddssss', $id, $chest, $waist, $hips, $arm, $thigh, $should, $height, $heightUnit, $unit, $date, $notes);
        $stmt->execute(); $stmt->close();
        $ok = "Measurements saved successfully!";
    }

    /* ---- GOALS ---- */
    if ($action === 'add_goal') {
        $goalType  = $_POST['goal_type'] ?? 'weight_loss';
        $title     = trim($_POST['title'] ?? '');
        $targetVal = (float)($_POST['target_value'] ?? 0);
        $startVal  = $_POST['start_value'] !== '' ? (float)$_POST['start_value'] : null;
        $currVal   = $_POST['current_value'] !== '' ? (float)$_POST['current_value'] : null;
        $unit      = trim($_POST['unit'] ?? '');
        $targetDt  = trim($_POST['target_date'] ?? '');
        if ($targetDt === '') $targetDt = null;
        if ($title === '') throw new Exception('Goal title is required.');
        if ($targetVal <= 0) throw new Exception('Target value is required and must be greater than 0.');
        // Smart defaults: if start not given, use current; if current not given, use start;
        // if neither given, both = target (goal starts at 0% progress, user updates later)
        if ($startVal === null && $currVal !== null) $startVal = $currVal;
        if ($currVal === null && $startVal !== null) $currVal = $startVal;
        if ($startVal === null && $currVal === null) { $startVal = $targetVal; $currVal = $targetVal; }
        $stmt = $db->prepare("INSERT INTO member_goals (member_id, goal_type, title, target_value, start_value, current_value, unit, target_date, status) VALUES (?,?,?,?,?,?,?,?,'active')");
        $stmt->bind_param('issdddss', $id, $goalType, $title, $targetVal, $startVal, $currVal, $unit, $targetDt);
        $stmt->execute(); $stmt->close();
        $ok = "Goal '$title' added successfully! Update your current value anytime to track progress.";
    }

    /* ---- UPDATE GOAL ---- */
    if ($action === 'update_goal') {
        $goalId  = (int)($_POST['goal_id'] ?? 0);
        $currVal = (float)($_POST['current_value'] ?? 0);
        $status  = $_POST['status'] ?? 'active';
        $stmt = $db->prepare("UPDATE member_goals SET current_value=?, status=?, updated_at=NOW() WHERE id=? AND member_id=?");
        $stmt->bind_param('dsii', $currVal, $status, $goalId, $id);
        $stmt->execute(); $stmt->close();
        $ok = "Goal updated!";
    }

    /* ---- DELETE GOAL ---- */
    if ($action === 'delete_goal') {
        $goalId = (int)($_POST['goal_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM member_goals WHERE id=? AND member_id=?");
        $stmt->bind_param('ii', $goalId, $id);
        $stmt->execute(); $stmt->close();
        $ok = "Goal deleted.";
    }

    /* ---- WORKOUT ---- */
    if ($action === 'add_workout') {
        $date    = trim($_POST['workout_date'] ?? date('Y-m-d'));
        $type    = trim($_POST['workout_type'] ?? '');
        $dur     = (int)($_POST['duration_min'] ?? 0);
        $cal     = $_POST['calories_burn'] !== '' ? (int)$_POST['calories_burn'] : null;
        $sets    = $_POST['sets'] !== '' ? (int)$_POST['sets'] : null;
        $reps    = $_POST['reps'] !== '' ? (int)$_POST['reps'] : null;
        $wlift   = $_POST['weight_lifted'] !== '' ? (float)$_POST['weight_lifted'] : null;
        $intens  = $_POST['intensity'] ?? 'Medium';
        $notes   = trim($_POST['notes'] ?? '');
        if ($type === '') throw new Exception('Workout type is required.');
        $stmt = $db->prepare("INSERT INTO member_workouts (member_id, workout_date, workout_type, duration_min, calories_burn, sets, reps, weight_lifted, intensity, notes) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('issiiiidds', $id, $date, $type, $dur, $cal, $sets, $reps, $wlift, $intens, $notes);
        $stmt->execute(); $stmt->close();
        $ok = "Workout '$type' logged successfully!";
    }

    /* ---- DELETE WORKOUT ---- */
    if ($action === 'delete_workout') {
        $wid = (int)($_POST['workout_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM member_workouts WHERE id=? AND member_id=?");
        $stmt->bind_param('ii', $wid, $id);
        $stmt->execute(); $stmt->close();
        $ok = "Workout deleted.";
    }

    /* ---- MILESTONE ---- */
    if ($action === 'add_milestone') {
        $title  = trim($_POST['title'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $cat    = $_POST['category'] ?? 'other';
        $date   = trim($_POST['achieved_date'] ?? date('Y-m-d'));
        $icon   = trim($_POST['icon'] ?? '');
        if ($title === '') throw new Exception('Milestone title is required.');
        $stmt = $db->prepare("INSERT INTO member_milestones (member_id, title, description, category, achieved_date, icon) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param('isssss', $id, $title, $desc, $cat, $date, $icon);
        $stmt->execute(); $stmt->close();
        $ok = "Milestone '$title' added!";
    }

    /* ---- DELETE MILESTONE ---- */
    if ($action === 'delete_milestone') {
        $mid = (int)($_POST['milestone_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM member_milestones WHERE id=? AND member_id=?");
        $stmt->bind_param('ii', $mid, $id);
        $stmt->execute(); $stmt->close();
        $ok = "Milestone deleted.";
    }

    /* ---- DELETE WEIGHT ---- */
    if ($action === 'delete_weight') {
        $wid = (int)($_POST['weight_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM member_weight_log WHERE id=? AND member_id=?");
        $stmt->bind_param('ii', $wid, $id);
        $stmt->execute(); $stmt->close();
        $ok = "Weight entry deleted.";
    }

    /* ---- DELETE MEASUREMENT ---- */
    if ($action === 'delete_measurement') {
        $mid = (int)($_POST['measurement_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM member_measurements WHERE id=? AND member_id=?");
        $stmt->bind_param('ii', $mid, $id);
        $stmt->execute(); $stmt->close();
        $ok = "Measurement deleted.";
    }

    } catch (Exception $ex) {
        $err = $ex->getMessage();
    }
}

/* ---------- Load member's data ---------- */
$tab        = $_GET['tab'] ?? 'weight';
$weightLog  = get_weight_log($id);
$measLog    = get_measurement_log($id);
$latestMeas = get_latest_measurement($id);
$goals      = get_active_goals($id);
$workouts   = get_recent_workouts($id, 20);
$milestones = get_milestones($id, 20);

$tabs = [
    'weight'       => '&#128202; Weight',
    'measurements' => '&#128207; Measurements',
    'goals'        => '&#127919; Goals',
    'workouts'     => '&#127947; Workouts',
    'milestones'   => '&#127942; Milestones',
];
?>

<div class="member-content">
  <div class="page-head" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:20px">
    <div>
      <h1 style="margin:0;font-size:24px;color:var(--navy-900)">&#128202; My Fitness Progress</h1>
      <p style="margin:4px 0 0;color:var(--steel)">Log your weight, measurements, goals, workouts &amp; milestones. Your dashboard updates automatically.</p>
    </div>
    <a href="dashboard.php" class="btn btn-ghost">&#8592; Back to Dashboard</a>
  </div>

  <?php if ($err): ?>
    <div class="progress-alert progress-alert-err">&#9888; <?= e($err) ?></div>
  <?php endif; ?>
  <?php if ($ok): ?>
    <div class="progress-alert progress-alert-ok">&#10003; <?= e($ok) ?></div>
  <?php endif; ?>

  <!-- ===== Tabs ===== -->
  <div class="progress-tabs">
    <?php foreach ($tabs as $k => $label):
      $active = ($tab === $k) ? ' active' : '';
    ?>
      <a href="progress.php?tab=<?= $k ?>" class="progress-tab<?= $active ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>

  <!-- =========================== WEIGHT TAB =========================== -->
  <?php if ($tab === 'weight'): ?>
  <div class="grid cols-2">
    <div class="card">
      <div class="card-head"><h3>Add Weight Entry</h3></div>
      <div class="card-body">
        <form method="post" data-validate>
          <input type="hidden" name="action" value="add_weight">
          <div class="form-grid">
            <div class="form-field">
              <label>Weight <span class="req">*</span></label>
              <input type="number" name="weight" step="0.01" min="1" max="500" required placeholder="e.g. 78.5">
            </div>
            <div class="form-field">
              <label>Unit</label>
              <select name="unit"><option value="kg">kg</option><option value="lb">lb</option></select>
            </div>
            <div class="form-field">
              <label>Date <span class="req">*</span></label>
              <input type="date" name="logged_date" required value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-field full">
              <label>Notes (optional)</label>
              <input type="text" name="notes" placeholder="e.g. Morning weight, before breakfast">
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn btn-primary">&#10003; Add Weight Entry</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h3>Weight History (<?= count($weightLog) ?>)</h3></div>
      <div class="card-body">
        <?php if (empty($weightLog)): ?>
          <div class="progress-empty-inline">
            <div style="font-size:32px">&#128202;</div>
            <p>No weight entries yet. Add your first entry using the form on the left!</p>
          </div>
        <?php else: ?>
          <table class="data" style="width:100%">
            <thead><tr><th>Date</th><th>Weight</th><th>Notes</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($weightLog as $w): ?>
              <tr>
                <td><?= fmtDate($w['logged_date']) ?></td>
                <td><b><?= e($w['weight']) ?> <?= e($w['unit']) ?></b></td>
                <td><?= e($w['notes'] ?: '') ?></td>
                <td>
                  <form method="post" style="display:inline" data-confirm="Delete this weight entry?">
                    <input type="hidden" name="action" value="delete_weight">
                    <input type="hidden" name="weight_id" value="<?= $w['id'] ?>">
                    <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--err)">&#10005;</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- =========================== MEASUREMENTS TAB =========================== -->
  <?php elseif ($tab === 'measurements'): ?>
  <div class="grid cols-2">
    <div class="card">
      <div class="card-head"><h3>Add Body Measurements</h3></div>
      <div class="card-body">
        <form method="post" data-validate>
          <input type="hidden" name="action" value="add_measurement">
          <div class="form-grid">
            <div class="form-field">
              <label>Date <span class="req">*</span></label>
              <input type="date" name="logged_date" required value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-field">
              <label>Body Unit <span class="hint">(for chest, waist, etc.)</span></label>
              <select name="unit"><option value="cm">cm</option><option value="in">inches</option></select>
            </div>
            <div class="form-field">
              <label>Height <span class="req">*</span> <span class="hint">(needed for BMI)</span></label>
              <input type="number" name="height" step="0.01" min="0" placeholder="e.g. 175 cm or 68 in" value="<?= $latestMeas ? e($latestMeas['height']) : '' ?>">
            </div>
            <div class="form-field">
              <label>Height Unit</label>
              <select name="height_unit">
                <option value="cm" <?= ($latestMeas && ($latestMeas['height_unit'] ?? 'cm') === 'cm') ? 'selected' : '' ?>>cm (centimeters)</option>
                <option value="in" <?= ($latestMeas && ($latestMeas['height_unit'] ?? '') === 'in') ? 'selected' : '' ?>>in (inches)</option>
              </select>
            </div>
            <div class="form-field">
              <label>Chest</label>
              <input type="number" name="chest" step="0.01" min="0" placeholder="e.g. 97">
            </div>
            <div class="form-field">
              <label>Waist</label>
              <input type="number" name="waist" step="0.01" min="0" placeholder="e.g. 82">
            </div>
            <div class="form-field">
              <label>Hips</label>
              <input type="number" name="hips" step="0.01" min="0" placeholder="e.g. 96">
            </div>
            <div class="form-field">
              <label>Arm</label>
              <input type="number" name="arm" step="0.01" min="0" placeholder="e.g. 36.5">
            </div>
            <div class="form-field">
              <label>Thigh</label>
              <input type="number" name="thigh" step="0.01" min="0" placeholder="e.g. 55.5">
            </div>
            <div class="form-field">
              <label>Shoulder</label>
              <input type="number" name="shoulder" step="0.01" min="0" placeholder="e.g. 45">
            </div>
            <div class="form-field full">
              <label>Notes (optional)</label>
              <input type="text" name="notes" placeholder="e.g. Monthly check-in">
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn btn-primary">&#10003; Save Measurements</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h3>Measurement History (<?= count($measLog) ?>)</h3></div>
      <div class="card-body">
        <?php if (empty($measLog)): ?>
          <div class="progress-empty-inline">
            <div style="font-size:32px">&#128207;</div>
            <p>No measurements recorded yet. Add your first set of measurements using the form on the left!</p>
          </div>
        <?php else: ?>
          <div style="overflow-x:auto">
          <table class="data" style="width:100%">
            <thead><tr><th>Date</th><th>Height</th><th>Chest</th><th>Waist</th><th>Hips</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($measLog as $row): ?>
              <tr>
                <td><?= fmtDate($row['logged_date']) ?></td>
                <td><?= e($row['height'] ?: '—') ?></td>
                <td><?= e($row['chest'] ?: '—') ?></td>
                <td><?= e($row['waist'] ?: '—') ?></td>
                <td><?= e($row['hips'] ?: '—') ?></td>
                <td>
                  <form method="post" style="display:inline" data-confirm="Delete this measurement?">
                    <input type="hidden" name="action" value="delete_measurement">
                    <input type="hidden" name="measurement_id" value="<?= $row['id'] ?>">
                    <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--err)">&#10005;</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- =========================== GOALS TAB =========================== -->
  <?php elseif ($tab === 'goals'): ?>
  <div class="grid cols-2">
    <div class="card">
      <div class="card-head"><h3>Set a New Goal</h3></div>
      <div class="card-body">
        <form method="post" data-validate>
          <input type="hidden" name="action" value="add_goal">
          <div class="form-grid">
            <div class="form-field full">
              <label>Goal Title <span class="req">*</span></label>
              <input type="text" name="title" required placeholder="e.g. Lose 10 kg, Bench press 100kg, Attend 20 sessions">
              <div class="hint">A short name for your goal</div>
            </div>
            <div class="form-field">
              <label>Goal Type</label>
              <select name="goal_type" id="goal_type_select">
                <option value="weight_loss">⚖️ Weight Loss</option>
                <option value="weight_gain">💪 Weight Gain</option>
                <option value="bmi_target">📏 BMI Target</option>
                <option value="attendance">📅 Attendance %</option>
                <option value="workout_days">🏋️ Workout Days</option>
                <option value="custom">🎯 Custom</option>
              </select>
            </div>
            <div class="form-field">
              <label>Unit</label>
              <input type="text" name="unit" placeholder="kg, %, days, reps...">
              <div class="hint">What are we measuring?</div>
            </div>
            <div class="form-field">
              <label>Starting Value</label>
              <input type="number" name="start_value" step="0.01" placeholder="e.g. 85">
              <div class="hint">Where you are now (optional — defaults to current)</div>
            </div>
            <div class="form-field">
              <label>Current Value</label>
              <input type="number" name="current_value" step="0.01" placeholder="e.g. 82">
              <div class="hint">Your latest measurement (optional — defaults to starting)</div>
            </div>
            <div class="form-field">
              <label>Target Value <span class="req">*</span></label>
              <input type="number" name="target_value" step="0.01" required placeholder="e.g. 75">
              <div class="hint">The value you want to reach</div>
            </div>
            <div class="form-field">
              <label>Target Date</label>
              <input type="date" name="target_date">
              <div class="hint">When do you want to achieve it? (optional)</div>
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn btn-primary">&#10003; Add Goal</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h3>My Goals (<?= count($goals) ?>)</h3></div>
      <div class="card-body">
        <?php if (empty($goals)): ?>
          <div class="progress-empty-inline">
            <div style="font-size:32px">&#127919;</div>
            <p>No goals set yet. Create your first fitness goal using the form on the left!</p>
          </div>
        <?php else: ?>
          <?php foreach ($goals as $g):
            $pct = goal_progress_pct($g);
          ?>
            <div class="goal-manage-item">
              <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px">
                <div>
                  <b><?= e($g['title']) ?></b>
                  <div class="muted" style="font-size:13px">
                    <?php
                      $sv = $g['start_value'] !== null ? e($g['start_value']) : '?';
                      $cv = $g['current_value'] !== null ? e($g['current_value']) : '?';
                      $tv = $g['target_value'] !== null ? e($g['target_value']) : '?';
                      $un = $g['unit'] ? ' ' . e($g['unit']) : '';
                    ?>
                    Started: <?= $sv ?><?= $un ?> &middot; Now: <?= $cv ?><?= $un ?> &middot; Target: <?= $tv ?><?= $un ?>
                    <?php if ($g['target_date']): ?> &middot; due <?= fmtDate($g['target_date']) ?><?php endif; ?>
                  </div>
                </div>
                <span class="badge <?= $g['status'] === 'achieved' ? 'green' : 'gold' ?>"><?= e($g['status']) ?></span>
              </div>
              <div class="goal-progress-bar" style="margin-bottom:10px">
                <div class="goal-progress-fill <?= $pct >= 75 ? 'good' : ($pct >= 40 ? 'mid' : 'low') ?>" style="width:<?= $pct ?>%"></div>
              </div>
              <form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <input type="hidden" name="action" value="update_goal">
                <input type="hidden" name="goal_id" value="<?= $g['id'] ?>">
                <input type="number" name="current_value" step="0.01" value="<?= e($g['current_value']) ?>" style="width:90px" placeholder="Current">
                <select name="status" style="width:auto">
                  <option value="active" <?= $g['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                  <option value="achieved" <?= $g['status'] === 'achieved' ? 'selected' : '' ?>>Achieved!</option>
                  <option value="abandoned" <?= $g['status'] === 'abandoned' ? 'selected' : '' ?>>Abandoned</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">Update</button>
              </form>
              <form method="post" style="display:inline;margin-top:6px" data-confirm="Delete this goal?">
                <input type="hidden" name="action" value="delete_goal">
                <input type="hidden" name="goal_id" value="<?= $g['id'] ?>">
                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--err)">&#10005; Delete</button>
              </form>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- =========================== WORKOUTS TAB =========================== -->
  <?php elseif ($tab === 'workouts'): ?>
  <div class="grid cols-2">
    <div class="card">
      <div class="card-head"><h3>Log a Workout</h3></div>
      <div class="card-body">
        <form method="post" data-validate>
          <input type="hidden" name="action" value="add_workout">
          <div class="form-grid">
            <div class="form-field">
              <label>Date <span class="req">*</span></label>
              <input type="date" name="workout_date" required value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-field">
              <label>Workout Type <span class="req">*</span></label>
              <input type="text" name="workout_type" required placeholder="e.g. Strength Training" list="wtypes">
              <datalist id="wtypes">
                <option>Strength Training</option>
                <option>Cardio</option>
                <option>HIIT</option>
                <option>Yoga &amp; Flexibility</option>
                <option>CrossFit</option>
                <option>Leg Day</option>
                <option>Upper Body</option>
                <option>Full Body</option>
              </datalist>
            </div>
            <div class="form-field">
              <label>Duration (min)</label>
              <input type="number" name="duration_min" min="0" placeholder="e.g. 45">
            </div>
            <div class="form-field">
              <label>Calories Burned</label>
              <input type="number" name="calories_burn" min="0" placeholder="e.g. 380">
            </div>
            <div class="form-field">
              <label>Sets</label>
              <input type="number" name="sets" min="0" placeholder="e.g. 4">
            </div>
            <div class="form-field">
              <label>Reps</label>
              <input type="number" name="reps" min="0" placeholder="e.g. 12">
            </div>
            <div class="form-field">
              <label>Weight Lifted (kg)</label>
              <input type="number" name="weight_lifted" step="0.5" min="0" placeholder="e.g. 60">
            </div>
            <div class="form-field">
              <label>Intensity</label>
              <select name="intensity">
                <option>Low</option><option selected>Medium</option><option>High</option>
              </select>
            </div>
            <div class="form-field full">
              <label>Notes (optional)</label>
              <input type="text" name="notes" placeholder="e.g. Focused on bench press and squats">
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn btn-primary">&#10003; Log Workout</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h3>Recent Workouts (<?= count($workouts) ?>)</h3></div>
      <div class="card-body">
        <?php if (empty($workouts)): ?>
          <div class="progress-empty-inline">
            <div style="font-size:32px">&#127947;</div>
            <p>No workouts logged yet. Log your first workout using the form on the left!</p>
          </div>
        <?php else: ?>
          <div style="overflow-x:auto">
          <table class="data" style="width:100%">
            <thead><tr><th>Date</th><th>Type</th><th>Dur</th><th>Cal</th><th>Intensity</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($workouts as $w): ?>
              <tr>
                <td><?= fmtDate($w['workout_date']) ?></td>
                <td><?= e($w['workout_type']) ?></td>
                <td><?= e($w['duration_min'] ?: '—') ?> min</td>
                <td><?= e($w['calories_burn'] ?: '—') ?></td>
                <td>
                  <?php
                    $ic = $w['intensity'] === 'High' ? 'red' : ($w['intensity'] === 'Medium' ? 'gold' : 'green');
                  ?>
                  <span class="badge <?= $ic ?>"><?= e($w['intensity']) ?></span>
                </td>
                <td>
                  <form method="post" style="display:inline" data-confirm="Delete this workout?">
                    <input type="hidden" name="action" value="delete_workout">
                    <input type="hidden" name="workout_id" value="<?= $w['id'] ?>">
                    <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--err)">&#10005;</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- =========================== MILESTONES TAB =========================== -->
  <?php elseif ($tab === 'milestones'): ?>
  <div class="grid cols-2">
    <div class="card">
      <div class="card-head"><h3>Add a Milestone</h3></div>
      <div class="card-body">
        <form method="post" data-validate>
          <input type="hidden" name="action" value="add_milestone">
          <div class="form-grid">
            <div class="form-field full">
              <label>Title <span class="req">*</span></label>
              <input type="text" name="title" required placeholder="e.g. Lost First 5 kg">
            </div>
            <div class="form-field full">
              <label>Description</label>
              <textarea name="description" placeholder="e.g. Reached the 5kg weight loss milestone in 3 months"></textarea>
            </div>
            <div class="form-field">
              <label>Category</label>
              <select name="category">
                <option value="weight">Weight</option>
                <option value="strength">Strength</option>
                <option value="endurance">Endurance</option>
                <option value="attendance">Attendance</option>
                <option value="nutrition">Nutrition</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="form-field">
              <label>Date Achieved</label>
              <input type="date" name="achieved_date" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-field full">
              <label>Icon (optional)</label>
              <input type="text" name="icon" placeholder="e.g. 🏆 or leave blank for auto">
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn btn-primary">&#10003; Add Milestone</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h3>My Milestones (<?= count($milestones) ?>)</h3></div>
      <div class="card-body">
        <?php if (empty($milestones)): ?>
          <div class="progress-empty-inline">
            <div style="font-size:32px">&#127942;</div>
            <p>No milestones yet. Record your first achievement using the form on the left!</p>
          </div>
        <?php else: ?>
          <?php foreach ($milestones as $ms):
            $icon = milestone_icon($ms['category'], $ms['icon']);
          ?>
            <div class="milestone-manage-item">
              <div style="font-size:28px;line-height:1"><?= $icon ?></div>
              <div style="flex:1">
                <b><?= e($ms['title']) ?></b>
                <div class="muted" style="font-size:13px;margin:2px 0"><?= e($ms['description'] ?: '') ?></div>
                <span class="badge gold"><?= e($ms['category']) ?></span>
                <span class="muted" style="font-size:12px;margin-left:6px"><?= fmtDate($ms['achieved_date']) ?></span>
              </div>
              <form method="post" data-confirm="Delete this milestone?">
                <input type="hidden" name="action" value="delete_milestone">
                <input type="hidden" name="milestone_id" value="<?= $ms['id'] ?>">
                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--err)">&#10005;</button>
              </form>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php endif; // end tab switch ?>

  <div style="margin-top:20px;padding:16px;background:#f7f9fc;border-radius:10px;border:1px solid var(--line)">
    <p style="margin:0;color:var(--navy-700);font-size:14px">
      &#128161; <b>Tip:</b> After logging data here, visit your <a href="dashboard.php" style="color:var(--gold-dk);font-weight:600">Dashboard</a> to see your fitness progress charts, BMI, goal progress, and personalized insights — all updated automatically!
    </p>
  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
