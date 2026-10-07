<?php
/**
 * community/logout.php — secure logout for community users.
 * Destroys the session completely and clears the cookie.
 */
session_start();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
$base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
/* base points at .../community — strip one level. */
$root = rtrim(substr($base, 0, strrpos($base, '/')), '/');
if ($root === '' || $root === '.') $root = '';
header('Location: ' . ($root !== '' ? $root : '') . '/index.php');
exit;
