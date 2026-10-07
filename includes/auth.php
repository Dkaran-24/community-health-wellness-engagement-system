<?php
/**
 * auth.php — session-based authentication guard for New Life Fitness Club.
 * Include at the top of every protected page (after session_start).
 */

require_once __DIR__ . '/output_guard.php';   /* buffer output so header() redirects always work */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Redirect to login if no admin session exists.
 */
function require_login() {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: ' . login_url());
        exit;
    }
}

/**
 * Is the current visitor logged in?
 */
function is_logged_in() {
    return isset($_SESSION['admin_id']);
}

/**
 * Resolve the login URL relative to where this project lives.
 * We compute a base path from the current script so links work whether the
 * project sits at /new-life-fitness or the XAMPP htdocs root.
 */
function base_url() {
    // from /new-life-fitness/admin/members/list.php  ->  /new-life-fitness
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (preg_match('#(.*?)/admin/#', $script, $m)) {
        return rtrim($m[1], '/');
    }
    // login.php lives at project root
    return rtrim(dirname($script), '/');
}

function login_url() {
    return base_url() . '/login.php';
}
