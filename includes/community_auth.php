<?php
/**
 * includes/community_auth.php — session-based authentication guard for
 * COMMUNITY USERS (New Life Fitness CEP).
 *
 * A community user is a local resident who created a free community account.
 * They are NOT gym members and have completely separate sessions.
 *
 * Session keys used by this module:
 *   $_SESSION['community_user_id']
 *   $_SESSION['community_user_name']
 *   $_SESSION['community_ip']   (session binding, best-effort)
 *   $_SESSION['community_role'] = 'community'  (and 'volunteer' after approval)
 *
 * SECURITY MODEL
 *   - Admin  sessions use  $_SESSION['admin_id']
 *   - Member sessions use  $_SESSION['member_id']
 *   - Community sessions use $_SESSION['community_user_id']
 *   A community session therefore can NEVER be mistaken for an admin or
 *   member session — the guards check different keys.
 */

require_once __DIR__ . '/output_guard.php';   /* buffer output so header() redirects always work */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/csrf.php';

/**
 * Compute the base URL of the project from the current script.
 * Works from /community/any.php, /admin/any.php or project root.
 */
function community_base_url() {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    foreach (['/community/', '/admin/', '/volunteer/', '/member/'] as $frag) {
        if (preg_match('#(.*?)' . $frag . '#', $script, $m)) {
            return rtrim($m[1], '/');
        }
    }
    return rtrim(dirname($script), '/');
}

/** URL of the community login page. */
function community_login_url() {
    return community_base_url() . '/community/login.php';
}

/** URL of the community register page. */
function community_register_url() {
    return community_base_url() . '/community/register.php';
}

/** Require a logged-in community user; otherwise redirect to login. */
function require_community_login() {
    if (!isset($_SESSION['community_user_id'])) {
        $next = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header('Location: ' . community_login_url() . ($next ? '?next=' . $next : ''));
        exit;
    }
    /* Defence in depth: if the session was bound to another client IP at
       login, treat it as tampered and force a re-login. */
    if (isset($_SESSION['community_ip']) && $_SESSION['community_ip'] !== ''
        && ($_SERVER['REMOTE_ADDR'] ?? '') !== ''
        && $_SESSION['community_ip'] !== $_SERVER['REMOTE_ADDR']) {
        $_SESSION = [];
        session_destroy();
        header('Location: ' . community_login_url());
        exit;
    }
}

/** Is the current visitor a logged-in community user? */
function is_community_logged_in() {
    return isset($_SESSION['community_user_id']);
}

/**
 * Require that the current community user is ALSO an approved volunteer.
 * Community users who are not volunteers are redirected to the volunteer
 * apply page with an explanatory message.
 */
function require_volunteer() {
    require_community_login();
    if (!isset($_SESSION['community_role']) || $_SESSION['community_role'] !== 'volunteer') {
        header('Location: ' . community_base_url() . '/community/volunteer.php?need=1');
        exit;
    }
}

/** Is the current community user an approved volunteer? */
function is_volunteer_user() {
    return is_community_logged_in()
        && ($_SESSION['community_role'] ?? '') === 'volunteer';
}

/** Fetch the full row of the current community user (fresh from DB). */
function current_community_user() {
    if (!is_community_logged_in()) return null;
    static $cached = null;
    if ($cached === null) {
        $stmt = db()->prepare("SELECT * FROM community_users WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $_SESSION['community_user_id']);
        $stmt->execute();
        $res = $stmt->get_result();
        $cached = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        /* Ghost-session guard: if the account row no longer exists (database
           re-imported / user deleted), the session refers to nobody. Destroy
           it and send the visitor to the login page with a clear message
           instead of rendering a broken page off a null user. */
        if ($cached === null) {
            $_SESSION = [];
            session_destroy();
            header('Location: ' . community_login_url() . '?expired=1');
            exit;
        }

        /* Keep the volunteer flag fresh on every page load. */
        if ($cached) {
            $_SESSION['community_role'] = community_is_volunteer($cached['id']) ? 'volunteer' : 'community';
        }
    }
    return $cached;
}

/** Does this community user have an approved volunteer record? */
function community_is_volunteer($userId) {
    $stmt = db()->prepare("SELECT id FROM volunteers WHERE community_user_id = ? AND status = 'Active' LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $vol = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $vol ? (int)$vol['id'] : 0;
}

/** Voluntary id of the current user (0 = not a volunteer). */
function current_volunteer_id() {
    static $vid = null;
    if ($vid === null) {
        $vid = is_community_logged_in() ? community_is_volunteer($_SESSION['community_user_id']) : 0;
    }
    return $vid;
}

/** Friendly greeting name for the current user. */
function community_user_name() {
    return $_SESSION['community_user_name'] ?? 'Community Member';
}

/**
 * Fresh volunteer data for the current user (or null).
 */
function current_volunteer_row() {
    $vid = current_volunteer_id();
    if (!$vid) return null;
    $stmt = db()->prepare("SELECT v.*, u.full_name FROM volunteers v JOIN community_users u ON u.id = v.community_user_id WHERE v.id = ? LIMIT 1");
    $stmt->bind_param('i', $vid);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row;
}
