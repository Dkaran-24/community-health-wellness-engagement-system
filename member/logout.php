<?php
/**
 * member/logout.php — destroy the member session and redirect to login.
 */
session_start();
$_SESSION = [];
session_destroy();
header('Location: ' . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/login.php');
exit;
