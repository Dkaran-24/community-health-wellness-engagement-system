
/**
 * config.local.php — EXAMPLE / TEMPLATE
 *
 * This file lets you set database credentials for your hosting provider
 * WITHOUT editing db_connect.php. It is NOT loaded automatically until
 * you rename it and fill in your real values.
 *
 * HOW TO USE (on InfinityFree, 000webhost, or any shared host):
 *   1. Copy this file to config.local.php  (same folder as db_connect.php)
 *   2. Replace the placeholder values below with the ones your host gave you
 *   3. Upload it to your server
 *   4. The .htaccess rules already block web access to this file, so your
 *      password stays safe even if someone guesses the URL.
 *
 * On XAMPP (local) you do NOT need this file — db_connect.php falls back
 * to localhost / root / empty password automatically.
 */

<?php
/**
 * Local database configuration for XAMPP
 */

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'cep';
