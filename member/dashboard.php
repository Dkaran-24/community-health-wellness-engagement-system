<?php
/**
 * PAGE: MEMBER DASHBOARD
 * FILE: member/dashboard.php
 * PURPOSE: Member-facing dashboard containing membership overview,
 * attendance summary, recent payments, and Fitness Progress Analysis.
 * AND a modern Fitness Progress Analysis section (weight, BMI, goals,
 * workouts, measurements, milestones, personalized insights).
 *
 * All progress data is scoped to the logged-in member's session ID
 * ($_SESSION['member_id']). No data is invented — friendly empty states
 * are shown when information isn't available yet.
 */
$PAGE_TITLE = 'My Dashboard';
$PAGE_KEY   = 'dashboard';
require_once __DIR__ . '/_header.php';
require_once __DIR__ . '/../includes/progress.php';

$db  = db();
$cur = cur();
$m   = current_member();
if (!$m) { session_destroy(); header('Location: ' . member_base_url() . '/member/login.php'); exit; }
$id  = (int)$m['id'];

/* Ensure the fitness progress tables exist (idempotent auto-migration). */
ensure_progress_tables();

/* =====================================================================
 * EXISTING DATA (membership / attendance / payments) — unchanged
 * ===================================================================== */
$present = (int)$db->query("SELECT COUNT(*) c FROM attendance WHERE member_id=$id AND status='Present'")->fetch_assoc()['c'];
$total   = (int)$db->query("SELECT COUNT(*) c FROM attendance WHERE member_id=$id")->fetch_assoc()['c'];
$attPct  = $total ? round($present / $total * 100) : 0;
$att = $db->query("SELECT attend_date, status FROM attendance WHERE member_id=$id ORDER BY attend_date DESC LIMIT 10");
$paid = (float)$db->query("SELECT COALESCE(SUM(amount),0) s FROM fees WHERE member_id=$id AND status='Paid'")->fetch_assoc()['s'];
$pending = (float)$db->query("SELECT COALESCE(SUM(amount),0) s FROM fees WHERE member_id=$id AND status IN ('Pending','Overdue')")->fetch_assoc()['s'];
$fees = $db->query("SELECT * FROM fees WHERE member_id=$id ORDER BY payment_date DESC LIMIT 5");
$expiresOn = $m['expires_on'];
$expiryBadge = expiryBadge($expiresOn);
$daysLeft = $expiresOn ? (int)((strtotime($expiresOn) - strtotime(date('Y-m-d'))) / 86400) : null;

/* =====================================================================
 * FITNESS PROGRESS DATA (all scoped by $id = session member id)
 * ===================================================================== */
$weightLog    = get_weight_log($id);
$weightSum    = get_weight_summary($id);
$measLog      = get_measurement_log($id);
$latestMeas   = get_latest_measurement($id);
$goals        = get_active_goals($id);
$attSummary   = get_attendance_summary($id);
$attTrend     = get_attendance_monthly_trend($id, 6);
$workoutSum   = get_workout_summary($id);
$recentWorkouts = get_recent_workouts($id, 6);
$milestones   = get_milestones($id, 8);
$weightChart  = build_weight_chart($weightLog);

/* BMI calculation (from current weight + latest height) */
$currentBMI = ['bmi' => null, 'category' => '—', 'color' => 'gray'];
$bmiHeightDisplay = '';
$bmiWeightDisplay = '';
if ($weightSum['current'] && $latestMeas && $latestMeas['height'] !== null) {
    $hUnit  = $latestMeas['height_unit'] ?? ($latestMeas['unit'] ?? 'cm');
    $hCm    = ($hUnit === 'in') ? (float)$latestMeas['height'] * 2.54 : (float)$latestMeas['height'];
    $wUnit  = $weightSum['current']['unit'];
    $wKg    = ($wUnit === 'lb') ? (float)$weightSum['current']['weight'] * 0.4536 : (float)$weightSum['current']['weight'];
    $currentBMI = calculate_bmi($wKg, $hCm);
    $bmiHeightDisplay = e($latestMeas['height']) . ' ' . e($hUnit);
    $bmiWeightDisplay = e($weightSum['current']['weight']) . ' ' . e($wUnit);
}

/* Personalized insights (built only from real data) */
$insightCtx = [
  'member'       => $m,
  'weight'       => $weightSum,
  'measurements' => $latestMeas,
  'goals'        => $goals,
  'attendance'   => $attSummary,
  'workouts'     => $workoutSum,
  'milestones'   => $milestones,
];
$insights = build_personalized_insights($insightCtx);

/* Weight change text */
$weightChange = null;
if ($weightSum['start'] && $weightSum['current']) {
    $startW = (float)$weightSum['start']['weight'];
    $curW   = (float)$weightSum['current']['weight'];
    $unit   = $weightSum['current']['unit'] ?: 'kg';
    $diff   = round($curW - $startW, 1);
    $weightChange = ['diff' => $diff, 'unit' => $unit, 'start' => $startW, 'current' => $curW];
}

/* Count how many progress sections have data (for empty-state messaging) */
$progressDataCount = (int)(count($weightLog) > 0) + (int)($latestMeas !== null)
                   + (int)(count($goals) > 0) + (int)($workoutSum['total'] > 0)
                   + (int)(count($milestones) > 0) + (int)($attSummary['total'] > 0);
?>

<?= flash() ?>

<!-- =================================================================
     PAGE: MEMBER DASHBOARD
     SECTION: WELCOME / MEMBERSHIP STATUS BANNER
     UI: Top dashboard hero showing member identity, plan and expiry.
     ================================================================= -->
<div class="member-welcome">
  <div style="display:flex;align-items:center;gap:16px;">
    <div class="welcome-photo"><?= photoImg($m['photo'], $m['name'], 'member-photo-lg') ?></div>
    <div>
      <h2>Welcome back, <?= e($m['name']) ?>!</h2>
      <p><?= $today_date ?></p>
      <p>Member #<?= $m['id'] ?> &middot; <?= e($m['plan_name'] ?: 'No active plan') ?></p>
    </div>
  </div>
  <div style="text-align:right; position:relative; z-index:1;">
    <div style="font-size:13px; color:var(--muted-gold); text-transform:uppercase; letter-spacing:.1em;">Membership</div>
    <div style="font-size:20px; font-weight:700;"><?= $expiryBadge ?></div>
    <?php if ($daysLeft !== null && $daysLeft >= 0): ?>
      <div style="font-size:13px; color:#C9E2D6;"><?= $daysLeft ?> day<?= $daysLeft==1?'':'s' ?> remaining</div>
    <?php elseif ($daysLeft !== null && $daysLeft < 0): ?>
      <div style="font-size:13px; color:#ffb4b4;">Expired <?= abs($daysLeft) ?> day<?= abs($daysLeft)==1?'':'s' ?> ago</div>
    <?php endif; ?>
  </div>
</div>

<!-- DASHBOARD COMPONENT: MEMBERSHIP STATUS / EXPIRY CALLOUT
     UI: Warning/error message shown directly below the welcome banner. -->
<?php if ($m['status'] !== 'Active'): ?>
  <div class="member-callout">
    &#9888; Your membership is currently marked <b>Inactive</b>. Please contact the gym front desk to reactivate your account.
  </div>
<?php elseif ($daysLeft !== null && $daysLeft < 0): ?>
  <div class="member-callout">
    &#9888; Your membership expired on <b><?= fmtDate($expiresOn) ?></b>. Please visit the front desk to renew your plan.
  </div>
<?php elseif ($daysLeft !== null && $daysLeft <= 30 && $daysLeft >= 0): ?>
  <div class="member-callout warn">
    &#9888; Your membership expires in <b><?= $daysLeft ?> day<?= $daysLeft==1?'':'s' ?></b> (on <?= fmtDate($expiresOn) ?>). Renew soon to avoid interruption.
  </div>
<?php endif; ?>

<!-- DASHBOARD SECTION: QUICK STATS
     CARDS: Membership Plan | Attendance | Total Paid
     LOCATION: Directly below membership status callouts. -->
<div class="grid cols-3" style="margin-bottom:22px">
  <div class="stat">
    <div class="stat-ico gold">&#9733;</div>
    <div><div class="stat-val"><?= e($m['plan_name'] ?: 'None') ?></div><div class="stat-lbl">Membership Plan</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico <?= $attPct >= 75 ? 'green' : ($attPct >= 50 ? 'gold' : 'red') ?>">&#10003;</div>
    <div><div class="stat-val"><?= $total ? $attPct.'%' : '—' ?></div><div class="stat-lbl">Attendance (<?= $present ?>/<?= $total ?>)</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico steel">&#8377;</div>
    <div><div class="stat-val"><?= fmtMoney($paid, $cur) ?></div><div class="stat-lbl">Total Paid</div></div>
  </div>
</div>

<!-- =================================================================
     PAGE: MEMBER DASHBOARD
     SECTION: FITNESS PROGRESS ANALYSIS
     PURPOSE: Main fitness analytics area containing all progress cards.
     ================================================================= -->
<div class="progress-section-header">
  <div>
    <h2>🏋 Fitness Progress Analysis</h2>
    <p>Track your journey — weight, BMI, goals, workouts, and milestones, all in one place.</p>
  </div>
  <?php if ($progressDataCount > 0): ?>
    <div class="progress-badge-summary"><?= $progressDataCount ?> activ<?= $progressDataCount==1?'e':'e' ?> metric<?= $progressDataCount==1?'':'s' ?></div>
  <?php endif; ?>
</div>

<?php if ($progressDataCount === 0): ?>
  <!-- DASHBOARD CARD/STATE: FITNESS PROGRESS — GRAND EMPTY STATE
     SHOWN WHEN: No progress data exists for the member. -->
  <div class="card progress-empty-hero">
    <div class="progress-empty-icon">📊</div>
    <h3>Your Fitness Progress Dashboard Awaits</h3>
    <p>This is where your personal fitness journey comes to life. Once your trainer or you start logging data, you'll see:</p>
    <div class="progress-empty-grid">
      <div class="progress-empty-item"><span class="ico">⚖</span> Weight tracking &amp; trend chart</div>
      <div class="progress-empty-item"><span class="ico">📏</span> BMI calculation &amp; body measurements</div>
      <div class="progress-empty-item"><span class="ico">🎯</span> Fitness goals &amp; progress bars</div>
      <div class="progress-empty-item"><span class="ico">🏋</span> Workout &amp; training log</div>
      <div class="progress-empty-item"><span class="ico">🏆</span> Key fitness milestones</div>
      <div class="progress-empty-item"><span class="ico">💡</span> Personalized insights</div>
    </div>
    <p class="progress-empty-note">Ask your trainer or the front desk to start logging your progress, or check back after your next assessment.</p>
  </div>
<?php else: ?>

<!-- DASHBOARD ROW 1: WEIGHT + BMI
     LEFT CARD: Weight Tracking
     RIGHT CARD: BMI & Body Composition
     ================================================================= -->
<div class="grid cols-2">
  <!-- DASHBOARD CARD: WEIGHT TRACKING
     INCLUDES: Starting/current/change summary + weight trend chart. -->
  <div class="card progress-card">
    <div class="card-head"><h3>📊 Weight Tracking</h3><?php if (count($weightLog) > 0): ?><span class="progress-count"><?= count($weightLog) ?> entr<?= count($weightLog)==1?'y':'ies' ?></span><?php endif; ?></div>
    <div class="card-body">
      <?php if (count($weightLog) === 0): ?>
        <div class="progress-empty-inline">
          <span class="ico">⚖</span>
          <p>No weight entries yet. Your starting weight and progress chart will appear here once data is logged.</p>
        </div>
      <?php else: ?>
        <div class="weight-summary-row">
          <div class="weight-stat">
            <div class="weight-stat-lbl">Starting</div>
            <div class="weight-stat-val"><?= e($weightSum['start']['weight']) ?> <small><?= e($weightSum['start']['unit']) ?></small></div>
            <div class="weight-stat-date"><?= fmtDate($weightSum['start']['logged_date']) ?></div>
          </div>
          <div class="weight-arrow">→</div>
          <div class="weight-stat current">
            <div class="weight-stat-lbl">Current</div>
            <div class="weight-stat-val"><?= e($weightSum['current']['weight']) ?> <small><?= e($weightSum['current']['unit']) ?></small></div>
            <div class="weight-stat-date"><?= fmtDate($weightSum['current']['logged_date']) ?></div>
          </div>
          <div class="weight-change <?= $weightChange && $weightChange['diff'] < 0 ? 'neg' : ($weightChange && $weightChange['diff'] > 0 ? 'pos' : '') ?>">
            <div class="weight-stat-lbl">Change</div>
            <div class="weight-stat-val"><?= $weightChange ? ($weightChange['diff'] > 0 ? '+' : '') . $weightChange['diff'] . ' ' . e($weightChange['unit']) : '—' ?></div>
            <div class="weight-stat-date"><?= $weightChange && $weightChange['diff'] < 0 ? 'lost' : ($weightChange && $weightChange['diff'] > 0 ? 'gained' : 'no change') ?></div>
          </div>
        </div>

        <?php if ($weightChart): ?>
          <!-- DASHBOARD CARD COMPONENT: WEIGHT TREND CHART
     TYPE: Inline SVG; no external JavaScript dependency. -->
          <div class="chart-wrap">
            <svg class="progress-chart" viewBox="0 0 100 100" preserveAspectRatio="none" aria-label="Weight trend chart">
              <!-- Grid lines -->
              <line x1="0" y1="0"   x2="100" y2="0"   class="chart-grid" />
              <line x1="0" y1="25"  x2="100" y2="25"  class="chart-grid" />
              <line x1="0" y1="50"  x2="100" y2="50"  class="chart-grid" />
              <line x1="0" y1="75"  x2="100" y2="75"  class="chart-grid" />
              <line x1="0" y1="100" x2="100" y2="100" class="chart-axis" />
              <!-- Area fill -->
              <polygon class="chart-area"
                points="<?= implode(' ', array_map(function($p){ return $p['x'].','.$p['y']; }, $weightChart['points'])) ?> 100,100 0,100" />
              <!-- Line -->
              <polyline class="chart-line"
                points="<?= implode(' ', array_map(function($p){ return $p['x'].','.$p['y']; }, $weightChart['points'])) ?>" />
              <!-- Points -->
              <?php foreach ($weightChart['points'] as $p): ?>
                <circle cx="<?= $p['x'] ?>" cy="<?= $p['y'] ?>" r="1.4" class="chart-point">
                  <title><?= e($p['raw']) . ' ' . e($weightSum['current']['unit']) . ' — ' . fmtDate($p['date']) ?></title>
                </circle>
              <?php endforeach; ?>
            </svg>
            <div class="chart-x-labels">
              <span><?= fmtDate($weightLog[0]['logged_date']) ?></span>
              <span><?= fmtDate($weightLog[count($weightLog)-1]['logged_date']) ?></span>
            </div>
            <div class="chart-y-labels">
              <span><?= e($weightChart['max']) ?></span>
              <span><?= e($weightChart['min']) ?></span>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- DASHBOARD CARD: BMI & BODY COMPOSITION
     INCLUDES: BMI score/category + scale + latest body measurements. -->
  <div class="card progress-card">
    <div class="card-head"><h3>⚖ BMI &amp; Body Composition</h3></div>
    <div class="card-body">
      <?php if ($currentBMI['bmi'] === null): ?>
        <div class="progress-empty-inline">
          <span class="ico">📏</span>
          <p>BMI can't be calculated yet. It needs your <b>height</b> (from body measurements) and your <b>current weight</b>. Ask your trainer to log these.</p>
        </div>
      <?php else: ?>
        <div class="bmi-display">
          <div class="bmi-circle bmi-<?= $currentBMI['color'] ?>">
            <div class="bmi-num"><?= e($currentBMI['bmi']) ?></div>
            <div class="bmi-cat"><?= e($currentBMI['category']) ?></div>
          </div>
          <div class="bmi-scale">
            <?php if ($bmiHeightDisplay && $bmiWeightDisplay): ?>
              <div class="bmi-scale-info">
                Calculated from: <b><?= $bmiWeightDisplay ?></b> weight &amp; <b><?= $bmiHeightDisplay ?></b> height
              </div>
            <?php endif; ?>
            <div class="bmi-scale-bar">
              <div class="seg under">Under</div>
              <div class="seg healthy">Healthy</div>
              <div class="seg over">Over</div>
              <div class="seg obese">Obese</div>
            </div>
            <div class="bmi-scale-labels">
              <span>&lt;18.5</span><span>18.5</span><span>25</span><span>30</span><span>40+</span>
            </div>
            <!-- DASHBOARD CARD COMPONENT: BMI SCALE MARKER
     PURPOSE: Positions the current BMI indicator on the scale. -->
            <?php
              $bmiVal = (float)$currentBMI['bmi'];
              $markerPos = (($bmiVal - 15) / (40 - 15)) * 100;
              if ($markerPos < 0) $markerPos = 0;
              if ($markerPos > 100) $markerPos = 100;
            ?>
            <div class="bmi-marker" style="left: <?= $markerPos ?>%;">&#9650;</div>
          </div>
        </div>
        <?php
          /* DASHBOARD CARD COMPONENT: BMI HISTORY NOTE
   SHOWN WHEN: At least two BMI records are available. */
          $bmiProg = get_bmi_progress($id);
        ?>
        <?php if (count($bmiProg) >= 2): ?>
          <div class="bmi-progress-note">
            📈 Your BMI has gone from <b><?= e($bmiProg[0]['bmi']) ?></b> to <b><?= e($bmiProg[count($bmiProg)-1]['bmi']) ?></b> since <?= fmtDate($bmiProg[0]['date']) ?>.
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($latestMeas): ?>
        <div class="measurement-mini-grid">
          <?php foreach (['chest'=>'Chest','waist'=>'Waist','hips'=>'Hips','arm'=>'Arm','thigh'=>'Thigh','shoulder'=>'Shoulder'] as $k=>$label): ?>
            <?php if ($latestMeas[$k] !== null): ?>
              <div class="meas-mini"><span class="lbl"><?= $label ?></span><span class="val"><?= e($latestMeas[$k]) ?> <small><?= e($latestMeas['unit']) ?></small></span></div>
            <?php endif; ?>
          <?php endforeach; ?>
          <?php if ($latestMeas['height'] !== null): ?>
            <div class="meas-mini"><span class="lbl">Height</span><span class="val"><?= e($latestMeas['height']) ?> <small><?= e($latestMeas['height_unit'] ?? $latestMeas['unit']) ?></small></span></div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="spacer"></div>

<!-- DASHBOARD ROW 2: GOALS + ATTENDANCE
     LEFT CARD: Fitness Goals
     RIGHT CARD: Attendance Trend
     ================================================================= -->
<div class="grid cols-2">
  <!-- DASHBOARD CARD: FITNESS GOALS
     UI: Active goals with current/target values and progress bars. -->
  <div class="card progress-card">
    <div class="card-head"><h3>🎯 Fitness Goals</h3><?php if (count($goals) > 0): ?><span class="progress-count"><?= count($goals) ?> active</span><?php endif; ?></div>
    <div class="card-body">
      <?php if (count($goals) === 0): ?>
        <div class="progress-empty-inline">
          <span class="ico">🎯</span>
          <p>No active goals set yet. Goals help you stay motivated and measure progress — ask your trainer to set targets like weight loss, workout days, or attendance.</p>
        </div>
      <?php else: ?>
        <div class="goals-list">
          <?php foreach ($goals as $g):
            $pct = goal_progress_pct($g);
            $goalIcons = ['weight_loss'=>'⚖','weight_gain'=>'💪','bmi_target'=>'📏','attendance'=>'📅','workout_days'=>'🏋','custom'=>'🎯'];
            $gIcon = $goalIcons[$g['goal_type']] ?? '🎯';
          ?>
            <div class="goal-item">
              <div class="goal-head">
                <span class="goal-icon"><?= $gIcon ?></span>
                <div class="goal-title-wrap">
                  <div class="goal-title"><?= e($g['title']) ?></div>
                  <div class="goal-meta">
                    <?php
                      $sv = $g['start_value'] !== null ? e($g['start_value']) : '?';
                      $cv = $g['current_value'] !== null ? e($g['current_value']) : '?';
                      $tv = $g['target_value'] !== null ? e($g['target_value']) : '?';
                      $un = $g['unit'] ? ' ' . e($g['unit']) : '';
                    ?>
                    <?= $cv ?><?= $un ?> / <?= $tv ?><?= $un ?>
                    <?php if ($g['target_date']): ?> · due <?= fmtDate($g['target_date']) ?><?php endif; ?>
                  </div>
                </div>
                <span class="goal-pct <?= $pct >= 75 ? 'good' : ($pct >= 40 ? 'mid' : 'low') ?>"><?= $pct ?>%</span>
              </div>
              <div class="goal-progress-bar">
                <div class="goal-progress-fill <?= $pct >= 75 ? 'good' : ($pct >= 40 ? 'mid' : 'low') ?>" style="width: <?= $pct ?>%;"></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- DASHBOARD CARD: ATTENDANCE TREND
     UI: Attendance percentage, session breakdown and monthly chart. -->
  <div class="card progress-card">
    <div class="card-head"><h3>📅 Attendance Trend</h3><?php if ($attSummary['total'] > 0): ?><span class="progress-count"><?= $attSummary['present'] ?>/<?= $attSummary['total'] ?> sessions</span><?php endif; ?></div>
    <div class="card-body">
      <?php if ($attSummary['total'] === 0): ?>
        <div class="progress-empty-inline">
          <span class="ico">📅</span>
          <p>No attendance records yet. Your attendance total, percentage, and monthly trend will appear here once sessions are marked.</p>
        </div>
      <?php else: ?>
        <div class="att-summary-row">
          <div class="att-pct-circle <?= $attSummary['pct'] >= 75 ? 'good' : ($attSummary['pct'] >= 50 ? 'mid' : 'low') ?>">
            <div class="att-pct-num"><?= $attSummary['pct'] ?>%</div>
            <div class="att-pct-lbl">Attendance</div>
          </div>
          <div class="att-breakdown">
            <div><span class="att-dot present"></span> Present: <b><?= $attSummary['present'] ?></b></div>
            <div><span class="att-dot absent"></span> Absent: <b><?= $attSummary['total'] - $attSummary['present'] ?></b></div>
            <div><span class="att-dot total"></span> Total Sessions: <b><?= $attSummary['total'] ?></b></div>
          </div>
        </div>

        <!-- DASHBOARD CARD COMPONENT: ATTENDANCE MONTHLY TREND CHART
     TYPE: Inline HTML/CSS bar chart; no JavaScript dependency. -->
        <div class="att-chart">
          <?php foreach ($attTrend as $mt): ?>
            <div class="att-bar-col">
              <div class="att-bar-val"><?= $mt['present'] ?></div>
              <div class="att-bar-track">
                <div class="att-bar-fill <?= $mt['pct'] >= 75 ? 'good' : ($mt['pct'] >= 50 ? 'mid' : ($mt['pct'] > 0 ? 'low' : 'none')) ?>" style="height: <?= max($mt['pct'], $mt['present'] > 0 ? 8 : 0) ?>%;"></div>
              </div>
              <div class="att-bar-label"><?= e($mt['label']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="spacer"></div>

<!-- DASHBOARD ROW 3: WORKOUT & TRAINING PROGRESS
     FULL-WIDTH CARD
     INCLUDES: Four workout stats + recent workout table.
     ================================================================= -->
<div class="card progress-card">
  <div class="card-head"><h3>🏋 Workout &amp; Training Progress</h3><?php if ($workoutSum['total'] > 0): ?><span class="progress-count"><?= $workoutSum['total'] ?> logged</span><?php endif; ?></div>
  <div class="card-body">
    <?php if ($workoutSum['total'] === 0): ?>
      <div class="progress-empty-inline">
        <span class="ico">🏋</span>
        <p>No workouts logged yet. Your training summary, total minutes, calories burned, and recent sessions will show here once workouts are recorded.</p>
      </div>
    <?php else: ?>
      <div class="grid cols-4 workout-stats">
        <div class="workout-stat">
          <div class="workout-stat-ico gold">🏋</div>
          <div class="workout-stat-val"><?= $workoutSum['total'] ?></div>
          <div class="workout-stat-lbl">Total Workouts</div>
        </div>
        <div class="workout-stat">
          <div class="workout-stat-ico steel">⏱</div>
          <div class="workout-stat-val"><?= $workoutSum['minutes'] ?></div>
          <div class="workout-stat-lbl">Minutes Trained</div>
        </div>
        <div class="workout-stat">
          <div class="workout-stat-ico red">🔥</div>
          <div class="workout-stat-val"><?= $workoutSum['calories'] ?></div>
          <div class="workout-stat-lbl">Calories Burned</div>
        </div>
        <div class="workout-stat">
          <div class="workout-stat-ico green">📅</div>
          <div class="workout-stat-val"><?= $workoutSum['this_month'] ?></div>
          <div class="workout-stat-lbl">This Month</div>
        </div>
      </div>

      <?php if (count($recentWorkouts) > 0): ?>
        <div class="table-wrap" style="margin-top:18px;">
          <table class="data workout-table">
            <thead><tr><th>Date</th><th>Type</th><th>Duration</th><th>Calories</th><th>Intensity</th></tr></thead>
            <tbody>
              <?php foreach ($recentWorkouts as $w): ?>
                <tr>
                  <td><?= fmtDate($w['workout_date']) ?></td>
                  <td><?= e($w['workout_type'] ?: '—') ?></td>
                  <td><?= $w['duration_min'] ? e($w['duration_min']) . ' min' : '—' ?></td>
                  <td><?= $w['calories_burn'] ? e($w['calories_burn']) . ' kcal' : '—' ?></td>
                  <td><?php
                    $int = $w['intensity'] ?? 'Medium';
                    $cls = strtolower($int) === 'high' ? 'red' : (strtolower($int) === 'low' ? 'gray' : 'gold');
                    echo '<span class="badge ' . $cls . '">' . e($int) . '</span>';
                  ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<div class="spacer"></div>

<!-- DASHBOARD ROW 4: HISTORY + MILESTONES
     LEFT CARD: Progress History
     RIGHT CARD: Key Milestones
     ================================================================= -->
<div class="grid cols-2">
  <!-- DASHBOARD CARD: PROGRESS HISTORY
     UI: Historical body-measurement table (date + available metrics). -->
  <div class="card progress-card">
    <div class="card-head"><h3>📈 Progress History</h3><?php if (count($measLog) > 0): ?><span class="progress-count"><?= count($measLog) ?> record<?= count($measLog)==1?'':'s' ?></span><?php endif; ?></div>
    <div class="card-body" style="padding:0">
      <?php if (count($measLog) === 0): ?>
        <div class="progress-empty-inline" style="padding:20px;">
          <span class="ico">📈</span>
          <p>No measurement history yet. Body measurements (chest, waist, hips, etc.) logged over time will appear here so you can track your transformation.</p>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="data progress-history-table">
            <thead><tr>
              <th>Date</th>
              <?php
                $cols = [];
                foreach (['chest','waist','hips','arm','thigh','shoulder','height'] as $c) {
                  $has = false;
                  foreach ($measLog as $r) { if ($r[$c] !== null) { $has = true; break; } }
                  if ($has) { $cols[] = $c; echo '<th>' . ucfirst($c) . '</th>'; }
                }
              ?>
            </tr></thead>
            <tbody>
              <?php foreach (array_reverse($measLog) as $r): // newest first ?>
                <tr>
                  <td><?= fmtDate($r['logged_date']) ?></td>
                  <?php foreach ($cols as $c): ?>
                    <td><?= $r[$c] !== null ? e($r[$c]) : '—' ?></td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- DASHBOARD CARD: KEY MILESTONES
     UI: Achievements with icon, title, description, date and category. -->
  <div class="card progress-card">
    <div class="card-head"><h3>🏆 Key Milestones</h3><?php if (count($milestones) > 0): ?><span class="progress-count"><?= count($milestones) ?> achieved</span><?php endif; ?></div>
    <div class="card-body">
      <?php if (count($milestones) === 0): ?>
        <div class="progress-empty-inline">
          <span class="ico">🏆</span>
          <p>No milestones recorded yet. Your fitness achievements — like weight goals hit, workout streaks, or personal records — will be celebrated here.</p>
        </div>
      <?php else: ?>
        <div class="milestone-list">
          <?php foreach ($milestones as $ms): ?>
            <div class="milestone-item">
              <div class="milestone-icon"><?= milestone_icon($ms['category'], $ms['icon']) ?></div>
              <div class="milestone-body">
                <div class="milestone-title"><?= e($ms['title']) ?></div>
                <?php if ($ms['description']): ?>
                  <div class="milestone-desc"><?= e($ms['description']) ?></div>
                <?php endif; ?>
                <div class="milestone-date"><?= fmtDate($ms['achieved_date']) ?></div>
              </div>
              <span class="badge <?= $ms['category'] === 'weight' ? 'gold' : ($ms['category'] === 'strength' ? 'steel' : ($ms['category'] === 'endurance' ? 'green' : 'gray')) ?>"><?= e($ms['category']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="spacer"></div>

<!-- DASHBOARD ROW 5: PERSONALIZED INSIGHTS
     FULL-WIDTH CARD
     UI: Recommendations generated from the member's real activity data.
     ================================================================= -->
<div class="card progress-card insights-card">
  <div class="card-head"><h3>💡 Personalized Insights</h3></div>
  <div class="card-body">
    <?php if (count($insights) === 0): ?>
      <div class="progress-empty-inline">
        <span class="ico">💡</span>
        <p>Insights are generated from your activity data. Once you have attendance, weight, workouts, or goals logged, personalized recommendations will appear here to guide your fitness journey.</p>
      </div>
    <?php else: ?>
      <div class="insights-list">
        <?php foreach ($insights as $ins): ?>
          <div class="insight-item insight-<?= e($ins['tone']) ?>">
            <span class="insight-icon"><?= e($ins['icon']) ?></span>
            <span class="insight-text"><?= e($ins['text']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="spacer"></div>
<?php endif; /* end of progressDataCount > 0 block */ ?>
<!-- =================================================================
     END DASHBOARD SECTION: FITNESS PROGRESS ANALYSIS
     ================================================================= -->

<div class="spacer"></div>

<!-- =================================================================
     DASHBOARD SECTION: MEMBERSHIP DETAILS + RECENT ATTENDANCE
     LEFT CARD: My Membership
     RIGHT CARD: Recent Attendance
     ================================================================= -->
<div class="grid cols-2">
  <div class="card">
    <div class="card-head"><h3>My Membership</h3></div>
    <div class="card-body" style="padding:0">
      <table class="data member-info-table">
        <tr><td class="k">Plan</td><td><?= e($m['plan_name'] ?: '—') ?></td></tr>
        <tr><td class="k">Duration</td><td><?= $m['duration_months'] ? e($m['duration_months']).' month'.($m['duration_months']==1?'':'s') : '—' ?></td></tr>
        <tr><td class="k">Plan Price</td><td><?= fmtMoney($m['price'], $cur) ?></td></tr>
        <tr><td class="k">Join Date</td><td><?= fmtDate($m['join_date']) ?></td></tr>
        <tr><td class="k">Expires On</td><td><?= fmtDate($expiresOn) ?></td></tr>
        <tr><td class="k">Status</td><td><?= $m['status']==='Active' ? '<span class="badge green">Active</span>' : '<span class="badge gray">Inactive</span>' ?></td></tr>
        <tr><td class="k">Assigned Trainer</td><td><?= e($m['trainer_name'] ?: '—') ?></td></tr>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h3>Recent Attendance</h3><a href="attendance.php" class="btn btn-ghost btn-sm">View all</a></div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap"><table class="data">
        <thead><tr><th>Date</th><th class="no-sort">Status</th></tr></thead>
        <tbody>
        <?php while ($a = $att->fetch_assoc()): ?>
          <tr><td><?= fmtDate($a['attend_date']) ?></td>
          <td><?= $a['status']==='Present' ? '<span class="badge green">Present</span>' : '<span class="badge red">Absent</span>' ?></td></tr>
        <?php endwhile; ?>
        <?php if ($att->num_rows === 0): ?>
          <tr><td colspan="2" class="muted center">No attendance records yet.</td></tr>
        <?php endif; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>

<div class="spacer"></div>
<!-- DASHBOARD SECTION: RECENT PAYMENTS
     FULL-WIDTH CARD
     UI: Recent fee/payment records with receipt action. -->
<div class="card">
  <div class="card-head"><h3>Recent Payments</h3><a href="payments.php" class="btn btn-ghost btn-sm">View all</a></div>
  <div class="card-body" style="padding:0">
    <div class="table-wrap"><table class="data">
      <thead><tr><th>Date</th><th>Amount</th><th>Mode</th><th>Status</th><th class="no-sort">Receipt</th></tr></thead>
      <tbody>
      <?php while ($f = $fees->fetch_assoc()): ?>
        <tr>
          <td><?= fmtDate($f['payment_date']) ?></td>
          <td><?= fmtMoney($f['amount'], $cur) ?></td>
          <td><?= e($f['payment_mode']) ?></td>
          <td><?php $s=strtolower($f['status']); echo '<span class="badge '.($s==='paid'?'green':($s==='overdue'?'red':'gold')).'">'.$f['status'].'</span>'; ?></td>
          <td><a href="<?= $base ?>/member/receipt.php?id=<?= $f['id'] ?>" class="btn btn-ghost btn-sm">Print</a></td>
        </tr>
      <?php endwhile; ?>
      <?php if ($fees->num_rows === 0): ?>
        <tr><td colspan="5" class="muted center">No payment records yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>

<?php if ($pending > 0): ?>
<div class="spacer"></div>
<div class="member-callout warn">
  &#9888; You have <b><?= fmtMoney($pending, $cur) ?></b> in pending/overdue payments. Please clear your dues at the front desk.
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/_footer.php'; ?>