<?php
/**
 * includes/csrf.php — CSRF token helpers for the CEP.
 *
 * Generates a per-session token that must accompany every state-changing
 * form POST (register, login, event registration, feedback, surveys ...).
 * Usage:
 *   csrf_token()       — get (or create) the token for this session
 *   csrf_field()       — ready-to-print <input type="hidden"> field
 *   csrf_verify()      — true if the posted token matches the session token
 *   csrf_require()     — verify or die(400) with a safe message
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Get or create the CSRF token for this session. */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Ready-to-print hidden input field. */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/** Verify a submitted token against the session token. */
function csrf_verify() {
    $sent = $_POST['csrf_token'] ?? '';
    return is_string($sent)
        && $sent !== ''
        && hash_equals($_SESSION['csrf_token'] ?? '', $sent);
}

/** Verify or stop the request with a 400 response. */
function csrf_require() {
    if (!csrf_verify()) {
        http_response_code(400);
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Security Check Failed</title>';
        echo '<style>body{font-family:Segoe UI,Arial,sans-serif;background:#f4f6f9;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;}';
        echo '.box{background:#fff;padding:32px 40px;border-radius:14px;box-shadow:0 6px 22px rgba(22,50,74,.1);max-width:460px;border-top:5px solid #d64545;}';
        echo 'h1{color:#d64545;font-size:20px;margin:0 0 10px;}p{color:#61707d;line-height:1.6;margin:0 0 16px;}a{color:#1B3A5C;font-weight:600;}</style></head><body><div class="box">';
        echo '<h1>&#9888; Security Check Failed</h1>';
        echo '<p>Your request could not be verified (missing or invalid security token). This protects you against cross-site request forgery.</p>';
        echo '<p>Please go back, refresh the page and try again.</p>';
        echo '<p><a href="javascript:history.back()">&larr; Go back</a></p></div></body></html>';
        exit;
    }
}
