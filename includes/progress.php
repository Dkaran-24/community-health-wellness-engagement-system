<?php
/**
 * includes/progress.php — Fitness Progress Analysis helpers for the member portal.
 *
 * Every function here is scoped by the logged-in member's session ID
 * ($_SESSION['member_id']) so a member can ONLY see their own data.
 *
 * Design rules:
 *  - Never invent data. If a table is empty / a row is missing, return null
 *    or an empty array so the dashboard can show a friendly empty state.
 *  - All queries use prepared statements with the member id bound.
 *  - Tables are auto-created on first use via ensure_progress_tables().
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Auto-create the fitness progress tables if they don't exist yet.
 * Idempotent — safe to call on every request. Mirrors the pattern used by
 * ensure_member_login_columns() in member_auth.php.
 */
function ensure_progress_tables() {
    $db = db();

    $tables = [

      "member_weight_log" => "CREATE TABLE IF NOT EXISTS member_weight_log (
          id          INT AUTO_INCREMENT PRIMARY KEY,
          member_id   INT NOT NULL,
          weight      DECIMAL(6,2) NOT NULL,
          unit        ENUM('kg','lb') NOT NULL DEFAULT 'kg',
          logged_date DATE NOT NULL,
          notes       VARCHAR(255) DEFAULT NULL,
          created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT fk_wlog_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
          INDEX idx_wlog_member_date (member_id, logged_date)
        ) ENGINE=InnoDB",

      "member_measurements" => "CREATE TABLE IF NOT EXISTS member_measurements (
          id          INT AUTO_INCREMENT PRIMARY KEY,
          member_id   INT NOT NULL,
          chest       DECIMAL(6,2) DEFAULT NULL,
          waist       DECIMAL(6,2) DEFAULT NULL,
          hips        DECIMAL(6,2) DEFAULT NULL,
          arm         DECIMAL(6,2) DEFAULT NULL,
          thigh       DECIMAL(6,2) DEFAULT NULL,
          shoulder    DECIMAL(6,2) DEFAULT NULL,
          height      DECIMAL(6,2) DEFAULT NULL,
          height_unit ENUM('cm','in') NOT NULL DEFAULT 'cm',
          unit        ENUM('cm','in') NOT NULL DEFAULT 'cm',
          logged_date DATE NOT NULL,
          notes       VARCHAR(255) DEFAULT NULL,
          created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT fk_meas_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
          INDEX idx_meas_member_date (member_id, logged_date)
        ) ENGINE=InnoDB",

      "member_goals" => "CREATE TABLE IF NOT EXISTS member_goals (
          id          INT AUTO_INCREMENT PRIMARY KEY,
          member_id   INT NOT NULL,
          goal_type   ENUM('weight_loss','weight_gain','bmi_target','attendance','workout_days','custom') NOT NULL DEFAULT 'custom',
          title       VARCHAR(120) NOT NULL,
          target_value  DECIMAL(8,2) DEFAULT NULL,
          start_value   DECIMAL(8,2) DEFAULT NULL,
          current_value DECIMAL(8,2) DEFAULT NULL,
          unit        VARCHAR(20) DEFAULT NULL,
          target_date DATE DEFAULT NULL,
          status      ENUM('active','achieved','abandoned') NOT NULL DEFAULT 'active',
          created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          CONSTRAINT fk_goal_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
          INDEX idx_goal_member (member_id, status)
        ) ENGINE=InnoDB",

      "member_workouts" => "CREATE TABLE IF NOT EXISTS member_workouts (
          id            INT AUTO_INCREMENT PRIMARY KEY,
          member_id     INT NOT NULL,
          workout_date  DATE NOT NULL,
          workout_type  VARCHAR(60) DEFAULT NULL,
          duration_min  INT DEFAULT NULL,
          calories_burn INT DEFAULT NULL,
          sets          INT DEFAULT NULL,
          reps          INT DEFAULT NULL,
          weight_lifted DECIMAL(8,2) DEFAULT NULL,
          intensity     ENUM('Low','Medium','High') DEFAULT 'Medium',
          notes         VARCHAR(255) DEFAULT NULL,
          created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT fk_wo_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
          INDEX idx_wo_member_date (member_id, workout_date)
        ) ENGINE=InnoDB",

      "member_milestones" => "CREATE TABLE IF NOT EXISTS member_milestones (
          id          INT AUTO_INCREMENT PRIMARY KEY,
          member_id   INT NOT NULL,
          title       VARCHAR(120) NOT NULL,
          description VARCHAR(255) DEFAULT NULL,
          category    ENUM('weight','strength','endurance','attendance','nutrition','other') NOT NULL DEFAULT 'other',
          achieved_date DATE NOT NULL,
          icon        VARCHAR(40) DEFAULT NULL,
          created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT fk_ms_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
          INDEX idx_ms_member_date (member_id, achieved_date)
        ) ENGINE=InnoDB",
    ];

    foreach ($tables as $name => $ddl) {
        // Check existence first (cheap), then create if missing.
        $check = @$db->query("SHOW TABLES LIKE '" . $db->real_escape_string($name) . "'");
        if (!$check || $check->num_rows === 0) {
            @$db->query($ddl);
        }
    }

    /* Auto-migration: add height_unit column to member_measurements if missing
       (so height can have its own unit independent of body-measurement unit). */
    $colCheck = @$db->query("SHOW COLUMNS FROM member_measurements LIKE 'height_unit'");
    if ($colCheck && $colCheck->num_rows === 0) {
        @$db->query("ALTER TABLE member_measurements ADD COLUMN height_unit ENUM('cm','in') NOT NULL DEFAULT 'cm' AFTER height");
    }
}

/**
 * Safely get the current member id from the session (or 0).
 */
function progress_member_id() {
    return isset($_SESSION['member_id']) ? (int)$_SESSION['member_id'] : 0;
}

/* =====================================================================
 * WEIGHT TRACKING
 * ===================================================================== */

/**
 * Get the chronological weight log for a member (oldest → newest).
 * Returns array of rows or empty array.
 */
function get_weight_log($memberId) {
    $memberId = (int)$memberId;
    if (!$memberId) return [];
    $db = db();
    $stmt = $db->prepare(
      "SELECT id, weight, unit, logged_date, notes
       FROM member_weight_log
       WHERE member_id = ?
       ORDER BY logged_date ASC, id ASC"
    );
    if (!$stmt) return [];
    $stmt->bind_param('i', $memberId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
    return $rows;
}

/**
 * Get the starting (first) and current (last) weight entries.
 * Returns ['start' => row|null, 'current' => row|null].
 */
function get_weight_summary($memberId) {
    $log = get_weight_log($memberId);
    if (!$log) return ['start' => null, 'current' => null];
    return ['start' => $log[0], 'current' => $log[count($log) - 1]];
}

/* =====================================================================
 * BODY MEASUREMENTS (incl. height for BMI)
 * ===================================================================== */

/**
 * Get all measurement rows for a member (oldest → newest).
 */
function get_measurement_log($memberId) {
    $memberId = (int)$memberId;
    if (!$memberId) return [];
    $db = db();
    $stmt = $db->prepare(
      "SELECT id, chest, waist, hips, arm, thigh, shoulder, height, height_unit, unit, logged_date, notes
       FROM member_measurements
       WHERE member_id = ?
       ORDER BY logged_date ASC, id ASC"
    );
    if (!$stmt) return [];
    $stmt->bind_param('i', $memberId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
    return $rows;
}

/**
 * Get the latest measurement row (for current BMI / measurements display).
 */
function get_latest_measurement($memberId) {
    $rows = get_measurement_log($memberId);
    return $rows ? $rows[count($rows) - 1] : null;
}

/**
 * Calculate BMI from weight (kg) and height (cm).
 * If height is in inches or weight in lb, converts first.
 *
 * @param float|null $weightKg
 * @param float|null $heightCm
 * @return array ['bmi'=>float|null, 'category'=>string, 'color'=>string]
 */
function calculate_bmi($weightKg, $heightCm) {
    $weightKg = $weightKg !== null ? (float)$weightKg : null;
    $heightCm = $heightCm !== null ? (float)$heightCm : null;
    if ($weightKg === null || $heightCm === null || $heightCm <= 0) {
        return ['bmi' => null, 'category' => '—', 'color' => 'gray'];
    }
    $heightM = $heightCm / 100.0;
    $bmi = $weightKg / ($heightM * $heightM);

    if ($bmi < 18.5)       return ['bmi' => round($bmi, 1), 'category' => 'Underweight', 'color' => 'gold'];
    if ($bmi < 25)         return ['bmi' => round($bmi, 1), 'category' => 'Healthy',     'color' => 'green'];
    if ($bmi < 30)         return ['bmi' => round($bmi, 1), 'category' => 'Overweight',  'color' => 'gold'];
    return ['bmi' => round($bmi, 1), 'category' => 'Obese', 'color' => 'red'];
}

/**
 * Get a BMI progression: pairs of (date, bmi) using weight log + height.
 * Uses the latest known height for all calculations (height is relatively
 * stable for adults). Returns array of ['date','bmi'] or empty array.
 */
function get_bmi_progress($memberId) {
    $memberId = (int)$memberId;
    $meas = get_measurement_log($memberId);
    // Find the latest non-null height (in cm)
    $heightCm = null;
    for ($i = count($meas) - 1; $i >= 0; $i--) {
        if ($meas[$i]['height'] !== null) {
            $h = (float)$meas[$i]['height'];
            $hUnit = $meas[$i]['height_unit'] ?? ($meas[$i]['unit'] ?? 'cm');
            $heightCm = ($hUnit === 'in') ? $h * 2.54 : $h;
            break;
        }
    }
    if ($heightCm === null) return [];

    $weights = get_weight_log($memberId);
    $out = [];
    foreach ($weights as $w) {
        $weightKg = ($w['unit'] === 'lb') ? (float)$w['weight'] * 0.4536 : (float)$w['weight'];
        $bmi = calculate_bmi($weightKg, $heightCm);
        if ($bmi['bmi'] !== null) {
            $out[] = ['date' => $w['logged_date'], 'bmi' => $bmi['bmi']];
        }
    }
    return $out;
}

/* =====================================================================
 * FITNESS GOALS
 * ===================================================================== */

/**
 * Get active goals for a member.
 */
function get_active_goals($memberId) {
    $memberId = (int)$memberId;
    if (!$memberId) return [];
    $db = db();
    $stmt = $db->prepare(
      "SELECT * FROM member_goals
       WHERE member_id = ? AND status = 'active'
       ORDER BY created_at DESC"
    );
    if (!$stmt) return [];
    $stmt->bind_param('i', $memberId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
    return $rows;
}

/**
 * Compute the progress percentage for a goal.
 * Handles both "increase" goals (start → target, higher is better, e.g. workout days)
 * and "decrease" goals (start → target, lower is better, e.g. weight loss).
 *
 * @param array $goal
 * @return int  0–100
 */
function goal_progress_pct($goal) {
    $start   = $goal['start_value'] !== null ? (float)$goal['start_value'] : null;
    $target  = $goal['target_value'] !== null ? (float)$goal['target_value'] : null;
    $current = $goal['current_value'] !== null ? (float)$goal['current_value'] : null;

    if ($target === null || $current === null) return 0;

    // If start is null OR start is 0 but target is large (wasn't properly set),
    // default start = current so progress shows 0% until the user updates
    if ($start === null || ($start == 0 && abs($target) > 0 && $current > 0)) {
        $start = $current;
    }

    $totalChange = $target - $start;
    // If start == target (e.g. both defaulted to target because user only
    // entered target), there's no measurable progress yet → 0%
    if ($totalChange == 0) {
        // Only show 100% if the user explicitly set start != target and reached it
        return ($current == $target && $start != $target) ? 100 : 0;
    }

    $actualChange = $current - $start;
    $pct = ($actualChange / $totalChange) * 100;

    // Clamp 0–100
    if ($pct < 0) $pct = 0;
    if ($pct > 100) $pct = 100;
    return (int)round($pct);
}

/* =====================================================================
 * ATTENDANCE (uses the existing attendance table, scoped by member)
 * ===================================================================== */

/**
 * Attendance totals for a member: present count, total count, percentage.
 */
function get_attendance_summary($memberId) {
    $memberId = (int)$memberId;
    if (!$memberId) return ['present' => 0, 'total' => 0, 'pct' => 0];
    $db = db();
    $stmt = $db->prepare(
      "SELECT
         SUM(status = 'Present') AS present,
         COUNT(*) AS total
       FROM attendance WHERE member_id = ?"
    );
    if (!$stmt) return ['present' => 0, 'total' => 0, 'pct' => 0];
    $stmt->bind_param('i', $memberId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    $present = (int)($row['present'] ?? 0);
    $total   = (int)($row['total'] ?? 0);
    $pct     = $total ? (int)round($present / $total * 100) : 0;
    return ['present' => $present, 'total' => $total, 'pct' => $pct];
}

/**
 * Monthly attendance trend: present count per month for the last N months.
 * Returns array of ['month'=>'YYYY-MM', 'label'=>'Mon YYYY', 'present'=>int, 'total'=>int, 'pct'=>int]
 */
function get_attendance_monthly_trend($memberId, $months = 6) {
    $memberId = (int)$memberId;
    $months = max(1, (int)$months);
    if (!$memberId) return [];

    $db = db();
    $out = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $dateObj = strtotime("first day of this month -$i months");
        $monthStart = date('Y-m-01', $dateObj);
        $monthEnd   = date('Y-m-t', $dateObj);
        $label      = date('M Y', $dateObj);
        $monthKey   = date('Y-m', $dateObj);

        $stmt = $db->prepare(
          "SELECT
             SUM(status = 'Present') AS present,
             COUNT(*) AS total
           FROM attendance
           WHERE member_id = ? AND attend_date BETWEEN ? AND ?"
        );
        if (!$stmt) { $out[] = ['month'=>$monthKey,'label'=>$label,'present'=>0,'total'=>0,'pct'=>0]; continue; }
        $stmt->bind_param('iss', $memberId, $monthStart, $monthEnd);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        $present = (int)($row['present'] ?? 0);
        $total   = (int)($row['total'] ?? 0);
        $pct     = $total ? (int)round($present / $total * 100) : 0;
        $out[] = ['month' => $monthKey, 'label' => $label, 'present' => $present, 'total' => $total, 'pct' => $pct];
    }
    return $out;
}

/* =====================================================================
 * WORKOUT / TRAINING LOG
 * ===================================================================== */

/**
 * Workout summary: total workouts, total minutes, total calories, this month count.
 */
function get_workout_summary($memberId) {
    $memberId = (int)$memberId;
    if (!$memberId) return ['total' => 0, 'minutes' => 0, 'calories' => 0, 'this_month' => 0];
    $db = db();
    $stmt = $db->prepare(
      "SELECT
         COUNT(*) AS total,
         COALESCE(SUM(duration_min),0) AS minutes,
         COALESCE(SUM(calories_burn),0) AS calories
       FROM member_workouts WHERE member_id = ?"
    );
    if (!$stmt) return ['total' => 0, 'minutes' => 0, 'calories' => 0, 'this_month' => 0];
    $stmt->bind_param('i', $memberId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    $total    = (int)($row['total'] ?? 0);
    $minutes  = (int)($row['minutes'] ?? 0);
    $calories = (int)($row['calories'] ?? 0);

    // This month count
    $monthStart = date('Y-m-01');
    $monthEnd   = date('Y-m-t');
    $stmt2 = $db->prepare(
      "SELECT COUNT(*) AS c FROM member_workouts
       WHERE member_id = ? AND workout_date BETWEEN ? AND ?"
    );
    $thisMonth = 0;
    if ($stmt2) {
        $stmt2->bind_param('iss', $memberId, $monthStart, $monthEnd);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        $thisMonth = (int)($res2 ? $res2->fetch_assoc()['c'] ?? 0 : 0);
        $stmt2->close();
    }

    return ['total' => $total, 'minutes' => $minutes, 'calories' => $calories, 'this_month' => $thisMonth];
}

/**
 * Recent workouts (for the progress history list).
 */
function get_recent_workouts($memberId, $limit = 10) {
    $memberId = (int)$memberId;
    $limit = max(1, (int)$limit);
    if (!$memberId) return [];
    $db = db();
    $stmt = $db->prepare(
      "SELECT * FROM member_workouts
       WHERE member_id = ?
       ORDER BY workout_date DESC, id DESC
       LIMIT ?"
    );
    if (!$stmt) return [];
    $stmt->bind_param('ii', $memberId, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
    return $rows;
}

/* =====================================================================
 * MILESTONES
 * ===================================================================== */

/**
 * Get milestones for a member (most recent first).
 */
function get_milestones($memberId, $limit = 12) {
    $memberId = (int)$memberId;
    $limit = max(1, (int)$limit);
    if (!$memberId) return [];
    $db = db();
    $stmt = $db->prepare(
      "SELECT * FROM member_milestones
       WHERE member_id = ?
       ORDER BY achieved_date DESC, id DESC
       LIMIT ?"
    );
    if (!$stmt) return [];
    $stmt->bind_param('ii', $memberId, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
    return $rows;
}

/**
 * Map a milestone category to an emoji icon (fallback if no icon set).
 */
function milestone_icon($category, $icon = null) {
    if ($icon) return $icon;
    $map = [
      'weight'     => '⚖',
      'strength'   => '💪',
      'endurance'  => '🏃',
      'attendance' => '📅',
      'nutrition'  => '🥗',
      'other'      => '🏆',
    ];
    return $map[$category] ?? '🏆';
}

/* =====================================================================
 * PERSONALIZED INSIGHTS
 * (Generated ONLY from the member's actual data — no invented stats.)
 * ===================================================================== */

/**
 * Build an array of insight strings based on the member's real data.
 * Each insight: ['icon'=>str, 'text'=>str, 'tone'=>'good'|'warn'|'info']
 *
 * @param array $ctx  context array with keys: member, weight, measurements,
 *                    goals, attendance, workouts, milestones
 * @return array
 */
function build_personalized_insights($ctx) {
    $insights = [];
    $m        = $ctx['member'] ?? null;
    $weight   = $ctx['weight'] ?? null;       // ['start','current']
    $meas     = $ctx['measurements'] ?? null; // latest measurement row
    $goals    = $ctx['goals'] ?? [];
    $att      = $ctx['attendance'] ?? ['present'=>0,'total'=>0,'pct'=>0];
    $workouts = $ctx['workouts'] ?? ['total'=>0,'minutes'=>0,'calories'=>0,'this_month'=>0];
    $ms       = $ctx['milestones'] ?? [];

    /* --- Weight insights --- */
    if ($weight && $weight['start'] && $weight['current']) {
        $startW = (float)$weight['start']['weight'];
        $curW   = (float)$weight['current']['weight'];
        $unit   = $weight['current']['unit'] ?: 'kg';
        $diff   = round($curW - $startW, 1);
        if (abs($diff) >= 0.1) {
            $dir = $diff < 0 ? 'lost' : 'gained';
            $insights[] = [
              'icon' => '⚖',
              'tone' => $diff < 0 ? 'good' : 'info',
              'text' => "You've $dir " . abs($diff) . " $unit since you started tracking (" . fmtDate($weight['start']['logged_date']) . ")."
            ];
        } else {
            $insights[] = [
              'icon' => '⚖',
              'tone' => 'info',
              'text' => "Your weight has been stable at {$curW} {$unit}."
            ];
        }
    }

    /* --- BMI insights --- */
    if ($meas && $meas['height'] !== null && $weight && $weight['current']) {
        $heightCm = ($meas['unit'] === 'in') ? (float)$meas['height'] * 2.54 : (float)$meas['height'];
        $weightKg = ($weight['current']['unit'] === 'lb') ? (float)$weight['current']['weight'] * 0.4536 : (float)$weight['current']['weight'];
        $bmi = calculate_bmi($weightKg, $heightCm);
        if ($bmi['bmi'] !== null) {
            if ($bmi['category'] === 'Healthy') {
                $insights[] = ['icon' => '✅', 'tone' => 'good', 'text' => "Your BMI of {$bmi['bmi']} is in the Healthy range. Great work!"];
            } elseif ($bmi['category'] === 'Overweight' || $bmi['category'] === 'Obese') {
                $insights[] = ['icon' => '💡', 'tone' => 'warn', 'text' => "Your BMI is {$bmi['bmi']} ({$bmi['category']}). Regular workouts and balanced nutrition can help bring it down."];
            } else {
                $insights[] = ['icon' => '💡', 'tone' => 'warn', 'text' => "Your BMI is {$bmi['bmi']} ({$bmi['category']}). Consider a nutrition plan to reach a healthy weight."];
            }
        }
    }

    /* --- Attendance insights --- */
    if ($att['total'] > 0) {
        if ($att['pct'] >= 80) {
            $insights[] = ['icon' => '🔥', 'tone' => 'good', 'text' => "Excellent consistency! You've attended {$att['pct']}% of your sessions ({$att['present']}/{$att['total']})."];
        } elseif ($att['pct'] >= 60) {
            $insights[] = ['icon' => '📈', 'tone' => 'info', 'text' => "You're attending {$att['pct']}% of sessions. Push for 80% to see faster results."];
        } else {
            $insights[] = ['icon' => '⚠', 'tone' => 'warn', 'text' => "Your attendance is at {$att['pct']}%. Try to visit the gym more regularly for better progress."];
        }
    }

    /* --- Workout insights --- */
    if ($workouts['total'] > 0) {
        $insights[] = [
          'icon' => '🏋',
          'tone' => 'good',
          'text' => "You've logged {$workouts['total']} workout" . ($workouts['total'] == 1 ? '' : 's') .
                    ($workouts['minutes'] > 0 ? " totalling " . $workouts['minutes'] . " minutes" : '') . "."
        ];
        if ($workouts['this_month'] > 0) {
            $insights[] = ['icon' => '📅', 'tone' => 'info', 'text' => "{$workouts['this_month']} workout" . ($workouts['this_month'] == 1 ? '' : 's') . " logged this month."];
        }
    }

    /* --- Goal insights --- */
    $activeGoals = count($goals);
    if ($activeGoals > 0) {
        $nearComplete = 0;
        foreach ($goals as $g) {
            if (goal_progress_pct($g) >= 75) $nearComplete++;
        }
        if ($nearComplete > 0) {
            $insights[] = ['icon' => '🎯', 'tone' => 'good', 'text' => "You're 75%+ of the way on $nearComplete goal" . ($nearComplete == 1 ? '' : 's') . " — almost there!"];
        } else {
            $insights[] = ['icon' => '🎯', 'tone' => 'info', 'text' => "You have $activeGoals active goal" . ($activeGoals == 1 ? '' : 's') . ". Keep logging progress to stay on track."];
        }
    }

    /* --- Milestone insights --- */
    if (count($ms) > 0) {
        $insights[] = ['icon' => '🏆', 'tone' => 'good', 'text' => "You've achieved " . count($ms) . " milestone" . (count($ms) == 1 ? '' : 's') . " so far. Celebrate your wins!"];
    }

    /* --- Membership status insight --- */
    if ($m) {
        $expiresOn = $m['expires_on'] ?? null;
        if ($expiresOn) {
            $days = (int)((strtotime($expiresOn) - strtotime(date('Y-m-d'))) / 86400);
            if ($days < 0) {
                $insights[] = ['icon' => '⏰', 'tone' => 'warn', 'text' => "Your membership expired " . abs($days) . " day" . (abs($days) == 1 ? '' : 's') . " ago. Renew to continue your fitness journey."];
            } elseif ($days <= 30) {
                $insights[] = ['icon' => '⏰', 'tone' => 'warn', 'text' => "Your membership expires in $days day" . ($days == 1 ? '' : 's') . ". Plan your renewal soon."];
            }
        }
    }

    return $insights;
}

/* =====================================================================
 * CHART DATA BUILDERS (return simple arrays consumed by inline SVG)
 * ===================================================================== */

/**
 * Build weight chart points (normalized 0–100 for SVG plotting).
 * Returns ['points'=>[[x,y,raw,rawDate]], 'min'=>float, 'max'=>float, 'count'=>int]
 * or null if fewer than 2 entries.
 */
function build_weight_chart($weightLog) {
    $n = count($weightLog);
    if ($n < 2) return null;
    $values = array_map(function($r){ return (float)$r['weight']; }, $weightLog);
    $min = min($values);
    $max = max($values);
    $range = $max - $min;
    if ($range == 0) $range = 1; // avoid divide-by-zero (flat line)
    $points = [];
    for ($i = 0; $i < $n; $i++) {
        $x = $n == 1 ? 50 : ($i / ($n - 1)) * 100;
        $y = 100 - ((($values[$i] - $min) / $range) * 100);
        $points[] = ['x' => $x, 'y' => $y, 'raw' => $values[$i], 'date' => $weightLog[$i]['logged_date']];
    }
    return ['points' => $points, 'min' => $min, 'max' => $max, 'count' => $n];
}

/**
 * Build attendance monthly trend bar data.
 * Returns array of ['label','pct','present','total'] (already from get_attendance_monthly_trend).
 */
function build_attendance_chart($trend) {
    return $trend; // already structured; the template renders bars
}
