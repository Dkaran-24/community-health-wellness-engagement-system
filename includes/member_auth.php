<?php
/**
 * includes/member_auth.php — session-based authentication guard for MEMBERS.
 *
 * This is separate from includes/auth.php (which guards the ADMIN panel).
 * Member sessions use $_SESSION['member_id'] instead of $_SESSION['admin_id'].
 *
 * Usage at the top of every member-facing page:
 *   require_once __DIR__ . '/../includes/member_auth.php';
 *   require_member_login();
 */

require_once __DIR__ . '/output_guard.php';   /* buffer output so header() redirects always work */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Redirect to the member login page if no member session exists.
 */
function require_member_login() {
    if (!isset($_SESSION['member_id'])) {
        header('Location: ' . member_login_url());
        exit;
    }
}

/**
 * Is the current visitor a logged-in member?
 */
function is_member_logged_in() {
    return isset($_SESSION['member_id']);
}

/**
 * Compute the base URL for the project.
 * Works from /new-life-fitness/member/any.php or /new-life-fitness/any.php
 */
function member_base_url() {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    // from /new-life-fitness/member/login.php  ->  /new-life-fitness
    if (preg_match('#(.*?)/member/#', $script, $m)) {
        return rtrim($m[1], '/');
    }
    if (preg_match('#(.*?)/admin/#', $script, $m)) {
        return rtrim($m[1], '/');
    }
    return rtrim(dirname($script), '/');
}

/**
 * URL to the member login page.
 */
function member_login_url() {
    return member_base_url() . '/member/login.php';
}

/**
 * Auto-migrate: ensure the member login columns exist.
 *
 * Adds `username` (with a UNIQUE index) and `password` / `password_set_at`
 * columns to the members table if they are missing. This is fully idempotent
 * and safe to run on every request — existing members keep NULL username/
 * password (so they are not broken) until an admin sets their credentials.
 *
 * Because MySQL/MariaDB versions differ in their support for
 * "ADD COLUMN IF NOT EXISTS" / "ADD INDEX IF NOT EXISTS", we first check
 * information_schema and only run the ALTER when the column/index is absent.
 */
function ensure_member_login_columns() {
    $db = db();

    // --- username column ---
    $check = @$db->query("SHOW COLUMNS FROM members LIKE 'username'");
    if ($check && $check->num_rows === 0) {
        @$db->query("ALTER TABLE members ADD COLUMN username VARCHAR(60) DEFAULT NULL AFTER email");
    }
    // unique index on username (only non-NULL values must be unique)
    $idx = @$db->query("SHOW INDEX FROM members WHERE Key_name = 'uniq_members_username'");
    if ($idx && $idx->num_rows === 0) {
        // If duplicate non-NULL usernames already exist this will fail — that's
        // fine, we suppress the error; the admin must resolve duplicates via
        // Edit Member before unique enforcement can be applied.
        @$db->query("ALTER TABLE members ADD UNIQUE KEY uniq_members_username (username)");
    }

    // --- password column ---
    $check = @$db->query("SHOW COLUMNS FROM members LIKE 'password'");
    if ($check && $check->num_rows === 0) {
        @$db->query("ALTER TABLE members ADD COLUMN password VARCHAR(255) DEFAULT NULL AFTER username");
    }
    // --- password_set_at column ---
    $check2 = @$db->query("SHOW COLUMNS FROM members LIKE 'password_set_at'");
    if ($check2 && $check2->num_rows === 0) {
        @$db->query("ALTER TABLE members ADD COLUMN password_set_at TIMESTAMP NULL DEFAULT NULL AFTER password");
    }
}

/**
 * Backwards-compatible alias for the old function name used by some pages.
 */
function ensure_member_password_column() {
    ensure_member_login_columns();
}

/**
 * Fetch the logged-in member's full record (with plan + trainer joins).
 * Returns assoc array or null.
 */
function current_member() {
    if (!isset($_SESSION['member_id'])) return null;
    $id = (int)$_SESSION['member_id'];
    $stmt = db()->prepare(
      "SELECT m.*, p.plan_name, p.duration_months, p.price, p.description AS plan_desc,
              t.name AS trainer_name,
              DATE_ADD(m.join_date, INTERVAL p.duration_months MONTH) AS expires_on
       FROM members m
       LEFT JOIN membership_plans p ON m.plan_id = p.id
       LEFT JOIN trainers t ON m.trainer_id = t.id
       WHERE m.id = ?"
    );
    if (!$stmt) return null;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    /* Ghost-session guard: if the member row no longer exists (database
       re-imported / member deleted), the session refers to nobody. Destroy
       it and send the visitor to the login page with a clear message
       instead of rendering a broken page off a null member. */
    if ($row === null) {
        $_SESSION = [];
        session_destroy();
        header('Location: ' . member_login_url() . '?expired=1');
        exit;
    }
    return $row;
}

/**
 * Validate a member username for length, allowed characters and uniqueness.
 *
 * Rules:
 *   - 3–60 characters
 *   - letters, numbers, dot, underscore, hyphen only
 *   - must be unique across members (excluding a given member id when editing)
 *
 * @param string  $username      The username to validate (already trimmed).
 * @param mysqli  $db            Database connection.
 * @param int     $excludeId     Member id to exclude from the uniqueness check
 *                               (use 0 when creating a new member).
 * @return string|null  Error message string on failure, or null if valid.
 */
function validate_member_username($username, $db, $excludeId = 0) {
    $username = trim((string)$username);
    if ($username === '') {
        return 'Username is required.';
    }
    if (strlen($username) < 3 || strlen($username) > 60) {
        return 'Username must be between 3 and 60 characters.';
    }
    if (!preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
        return 'Username may only contain letters, numbers, dots, underscores and hyphens.';
    }
    // Uniqueness check (case-insensitive on MySQL default collation, but we
    // check the exact value to be safe across collations).
    $excludeId = (int)$excludeId;
    $stmt = $db->prepare(
      "SELECT id FROM members WHERE username = ? AND id <> ? LIMIT 1"
    );
    if ($stmt) {
        $stmt->bind_param('si', $username, $excludeId);
        $stmt->execute();
        $res = $stmt->get_result();
        $dup = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if ($dup) {
            return 'That username is already taken. Please choose a different one.';
        }
    }
    return null;
}
