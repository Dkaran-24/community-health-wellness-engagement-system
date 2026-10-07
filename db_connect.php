<?php
/**
 * db_connect.php — MySQL connection for New Life Fitness Club
 *
 * Database credentials are read from environment variables (getenv) first,
 * then fall back to a local config file (config.local.php), and finally to
 * XAMPP defaults. This lets the SAME code run on:
 *   - XAMPP (local)            → uses the defaults below
 *   - InfinityFree / 000webhost → set credentials via a config.local.php file
 *   - Any VPS / shared host     → set real env vars or config.local.php
 *
 * Priority order:
 *   1. getenv('NLF_DB_HOST') etc.   (real environment variables / .htaccess SetEnv)
 *   2. config.local.php overrides   (a file you create, NOT shipped in the zip)
 *   3. XAMPP defaults               (localhost / root / empty / newlife_fitness)
 */

/* ---- 1. Try environment variables (highest priority) ---- */
$dbHost = getenv('NLF_DB_HOST');
$dbUser = getenv('NLF_DB_USER');
$dbPass = getenv('NLF_DB_PASS');
$dbName = getenv('NLF_DB_NAME');
if ($dbHost === false) $dbHost = '';
if ($dbUser === false) $dbUser = '';
if ($dbPass === false) $dbPass = '';
if ($dbName === false) $dbName = '';

/* ---- 2. Try a local config override file ---- */
// Create config.local.php next to this file with:
//   <?php $DB_HOST='...'; $DB_USER='...'; $DB_PASS='...'; $DB_NAME='...';
$configFile = __DIR__ . '/config.local.php';
if (file_exists($configFile)) {
    require $configFile;
    if (isset($DB_HOST)) $dbHost = $DB_HOST;
    if (isset($DB_USER)) $dbUser = $DB_USER;
    if (isset($DB_PASS)) $dbPass = $DB_PASS;
    if (isset($DB_NAME)) $dbName = $DB_NAME;
}

/* ---- 3. Fall back to XAMPP defaults ---- */
if ($dbHost === '') $dbHost = 'localhost';
if ($dbUser === '') $dbUser = 'root';        // default XAMPP user
// $dbPass stays '' (empty) by default for XAMPP
if ($dbName === '') $dbName = 'newlife_fitness';

define('DB_HOST', $dbHost);
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);
define('DB_NAME', $dbName);

/**
 * Guarded mysqli connection (New Life Fitness).
 *
 * WHY THIS EXISTS
 * mysqli_report is OFF (PHP-7-style "return false on error"), so a failed
 * query()/prepare() returns false instead of throwing. The app has ~200
 * call sites that trust the database to match the code, so when the schema
 * is incomplete — e.g. the CEP community tables were never imported, or the
 * database was re-imported with only database.sql — every page fatals with
 * a cryptic message like:
 *
 *   Fatal error: Call to a member function bind_param() on bool
 *   in ...\includes\community_auth.php on line 104
 *
 * This subclass keeps the historical false-on-error behaviour for code that
 * INTENTIONALLY tolerates failures (the @-suppressed best-effort calls,
 * e.g. the ALTER TABLE auto-migrations and the email open-tracking pixel)
 * but converts UNEXPECTED failures into a clear, actionable error page that
 * names the missing table/column and how to fix it.
 *
 * The @-suppression is detected via the error_reporting() idiom: while an
 * expression is "@-silenced", PHP reports only fatal-level errors, so the
 * reported level drops to the fatal mask. (Verified on PHP 8.x.)
 */
class NLF_MySQLi extends mysqli
{
    /** True while the current call is @-suppressed (best-effort call). */
    private static function callIsSuppressed(): bool
    {
        $level    = error_reporting();
        $fatalMask = E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR
                   | E_USER_ERROR | E_RECOVERABLE_ERROR | E_PARSE;
        return $level !== 0 && ($level & ~$fatalMask) === 0;
    }

    public function prepare(string $query): mysqli_stmt|false
    {
        $stmt = parent::prepare($query);
        if ($stmt === false && !self::callIsSuppressed() && PHP_SAPI !== 'cli') {
            nlf_query_failure_page('prepare()', $query, $this->error, $this->errno);
        }
        return $stmt;
    }

    public function query(string $query, int $result_mode = MYSQLI_STORE_RESULT): mysqli_result|bool
    {
        $res = parent::query($query, $result_mode);
        if ($res === false && !self::callIsSuppressed() && PHP_SAPI !== 'cli') {
            nlf_query_failure_page('query()', $query, $this->error, $this->errno);
        }
        return $res;
    }
}

/**
 * Friendly, actionable page for an unexpected database query failure.
 * Styled like the connection-error page above. Never returns.
 */
function nlf_query_failure_page(string $where, string $sql, string $error, int $errno): void
{
    http_response_code(500);

    /* Special-case the two most common schema problems so the page can
       tell the user EXACTLY what to import. */
    $hint = '';
    if ($errno === 1146 || $errno === 1049) {          /* table / database missing */
        if (preg_match("/(?:table|database)\s+'?([^'\s]+?)'?(?:\.\s*'?([^'\s]+?)'?)?\s+doesn'?t exist/i",
                       $error, $m)) {
            $missing = trim($m[2] ?? $m[1]);
            $isCep   = (bool)preg_match('/^(community_|volunteer|event_|survey_|challenge_|wellness_|ai_|fitness_|member_weight|member_measurements|member_goals|member_workouts|member_milestones|trainer_payments)/', $missing);
            $hint = $isCep
                ? "The <code>{$missing}</code> table is part of the community (CEP) schema and is missing from this database. Import <code>cep_install.sql</code> (or <code>cep_schema.sql</code> + <code>cep_seed.sql</code>) via phpMyAdmin — it creates all 20+ community tables without touching the gym tables. If you had a community page open from before, log in again afterwards."
                : "The <code>{$missing}</code> table is missing from this database. Import <code>database.sql</code> via phpMyAdmin, then reload this page.";
        }
    } elseif ($errno === 1054) {                        /* unknown column */
        if (preg_match("/unknown column '([^']+)'/i", $error, $m)) {
            $hint = "The column <code>" . htmlspecialchars($m[1]) . "</code> does not exist. The code is newer than this database — re-import the matching schema SQL (the admin pages also auto-add many columns on first visit).";
        }
    }

    $err = htmlspecialchars($error ?: 'Unknown database error');
    $sqlShort = htmlspecialchars(mb_strlen($sql) > 300 ? mb_substr($sql, 0, 300) . '…' : $sql);

    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Database Setup Error</title>";
    echo "<style>body{font-family:Arial,Helvetica,sans-serif;background:#f5f5f5;padding:40px;color:#333;}";
    echo ".box{max-width:640px;margin:0 auto;background:#fff;padding:30px;border-radius:8px;";
    echo "box-shadow:0 2px 10px rgba(0,0,0,.1);border-left:5px solid #e67e22;}";
    echo "h1{color:#b9770e;margin-top:0;font-size:22px;} code{background:#f0f0f0;padding:2px 6px;border-radius:3px;font-size:13px;}";
    echo ".err{background:#fdf3e7;border:1px solid #f5d9b0;padding:10px 14px;border-radius:6px;font-size:13px;word-break:break-word;}";
    echo "ol{padding-left:22px;} li{margin:6px 0;}";
    echo "</style></head><body><div class='box'>";
    echo "<h1>&#9888;&#65039; Database Query Failed</h1>";
    echo "<p>A database query could not run, so this page cannot be displayed safely.</p>";
    if ($hint !== '') echo "<p>{$hint}</p>";
    echo "<div class='err'><strong>MySQL error #{$errno}:</strong> {$err}<br>";
    echo "<strong>Query ({$where}):</strong> <code>{$sqlShort}</code></div>";
    echo "<hr><p><strong>How to fix:</strong></p><ol>";
    echo "<li>Open <b>phpMyAdmin</b> and select this project's database.</li>";
    echo "<li>Import the SQL files from the project folder (in order): <code>database.sql</code>, then <code>cep_schema.sql</code>, then <code>cep_seed.sql</code> — or just <code>cep_install.sql</code>, which contains everything.</li>";
    echo "<li>Visit <code>diag.php</code> in your browser for a live diagnosis (delete it once everything works).</li>";
    echo "</ol>";
    echo "<p style='font-size:12px;color:#888;'>This is a <em>setup</em> problem (database does not match the code), not a bug in the page you were viewing. Fixing the database resolves it permanently.</p>";
    echo "</div></body></html>";
    exit;
}

/**
 * Returns a shared mysqli connection (creates it on first call).
 */
function db() {
    static $conn = null;
    if ($conn === null) {
        // PHP 8.1+ throws mysqli_sql_exception on connect failure instead of
        // setting connect_error. Wrap in try/catch so we always show the
        // friendly error page instead of a raw fatal error / blank screen.
        try {
            mysqli_report(MYSQLI_REPORT_OFF); // turn off exception-throwing for the connect call
            $conn = @new NLF_MySQLi(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            // Keep MYSQLI_REPORT_OFF for the rest of the app so that mysqli
            // errors set ->error / return false (like PHP 7.x behaviour) instead
            // of throwing exceptions. The original app code was written for this
            // style (manual ->error checks), and turning on STRICT mode would
            // cause uncaught-exception blank pages on every page that doesn't
            // have try/catch around its queries.
        } catch (\Throwable $e) {
            $conn = null;
        }
        if ($conn === null || $conn->connect_error) {
            http_response_code(500);
            $err = htmlspecialchars($conn->connect_error ?? ($e->getMessage() ?? 'Unknown connection error'));
            echo "<!DOCTYPE html><html><head><title>Database Error</title>";
            echo "<style>body{font-family:Arial,sans-serif;background:#f5f5f5;padding:40px;}";
            echo ".box{max-width:600px;margin:0 auto;background:#fff;padding:30px;border-radius:8px;";
            echo "box-shadow:0 2px 10px rgba(0,0,0,0.1);border-left:5px solid #e74c3c;}";
            echo "h1{color:#e74c3c;margin-top:0;} code{background:#f0f0f0;padding:2px 6px;border-radius:3px;}";
            echo "</style></head><body><div class='box'>";
            echo "<h1>Database Connection Error</h1>";
            echo "<p>The application could not connect to the database.</p>";
            echo "<p><strong>Error:</strong> <code>$err</code></p>";
            echo "<hr><p><strong>How to fix:</strong></p>";
            echo "<ol>";
            echo "<li>Make sure <code>config.local.php</code> exists and has your correct database credentials.</li>";
            echo "<li>Make sure the database exists and <code>database.sql</code> has been imported.</li>";
            echo "<li>Visit <code>diag.php</code> for a detailed diagnosis.</li>";
            echo "</ol>";
            echo "</div></body></html>";
            exit;
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

/**
 * Fetch the single settings row (gym name, address, contact, logo, currency).
 * Cached for the request.
 */
function get_settings($forceRefresh = false) {
    static $settings = null;
    if ($settings === null || $forceRefresh) {
        $res = db()->query("SELECT * FROM settings LIMIT 1");
        $settings = $res ? $res->fetch_assoc() : [
            'gym_name'  => 'New Life Fitness Club',
            'address'   => '',
            'contact'   => '',
            'email'     => '',
            'logo_path' => 'assets/images/logo.jpg',
            'currency'  => '₹'
        ];
    }
    return $settings;
}

/**
 * Convenience: currency symbol.
 */
function cur() {
    // Always return ₹ (Indian Rupee). The database settings table may have
    // encoding issues with the ₹ symbol, so we hardcode it here to guarantee
    // correct display everywhere.
    return '₹';
}
