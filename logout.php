<?php
/**
 * logout.php — destroy session and return to login.
 */
session_start();
$_SESSION = [];
session_destroy();
header('Location: ' . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/login.php');
exit;
