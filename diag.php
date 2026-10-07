<?php
/**
 * diag.php — Diagnostic tool for New Life Fitness Club
 * Visit this in your browser to see exactly what's wrong.
 * DELETE THIS FILE after your site is working!
 *
 * Usage: https://yourdomain.com/new-life-fitness/diag.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><title>Diag</title>";
echo "<style>body{font-family:monospace;background:#1a1a2e;color:#e0e0e0;padding:20px;}";
echo ".ok{color:#4ecca3;} .bad{color:#ff6b6b;} .info{color:#a8dadc;}";
echo "h2{color:#f4a261;border-bottom:1px solid #444;padding-bottom:5px;}";
echo "pre{background:#16213e;padding:10px;border-radius:5px;overflow-x:auto;}";
echo "</style></head><body>";

echo "<h1>🔧 New Life Fitness — Diagnostic</h1>";

/* 1. PHP version */
echo "<h2>1. PHP Environment</h2>";
echo "<p class='info'>PHP Version: <b>" . phpversion() . "</b></p>";
echo "<p class='info'>Server: " . php_uname() . "</p>";

/* 2. Check config.local.php */
echo "<h2>2. Config File Check</h2>";
$configFile = __DIR__ . '/config.local.php';
if (file_exists($configFile)) {
    echo "<p class='ok'>✅ config.local.php EXISTS</p>";
    require $configFile;
    echo "<p class='info'>\$DB_HOST = " . (isset($DB_HOST) ? "'" . htmlspecialchars($DB_HOST) . "'" : "<span class='bad'>NOT SET</span>") . "</p>";
    echo "<p class='info'>\$DB_USER = " . (isset($DB_USER) ? "'" . htmlspecialchars($DB_USER) . "'" : "<span class='bad'>NOT SET</span>") . "</p>";
    echo "<p class='info'>\$DB_PASS = " . (isset($DB_PASS) ? "'" . str_repeat('*', strlen($DB_PASS)) . "' (length: " . strlen($DB_PASS) . ")" : "<span class='bad'>NOT SET</span>") . "</p>";
    echo "<p class='info'>\$DB_NAME = " . (isset($DB_NAME) ? "'" . htmlspecialchars($DB_NAME) . "'" : "<span class='bad'>NOT SET</span>") . "</p>";
} else {
    echo "<p class='bad'>❌ config.local.php DOES NOT EXIST at: " . htmlspecialchars($configFile) . "</p>";
    echo "<p class='info'>You need to create this file with your database credentials.</p>";
}

/* 3. Try database connection */
echo "<h2>3. Database Connection Test</h2>";
$host = $DB_HOST ?? 'localhost';
$user = $DB_USER ?? 'root';
$pass = $DB_PASS ?? '';
$name = $DB_NAME ?? 'newlife_fitness';

echo "<p class='info'>Attempting to connect to: <b>$user@$host</b> / database <b>$name</b></p>";

try {
    $conn = @new mysqli($host, $user, $pass, $name);
    if ($conn->connect_error) {
        echo "<p class='bad'>❌ Connection FAILED: " . $conn->connect_error . "</p>";
        echo "<p class='info'>Error code: " . $conn->connect_errno . "</p>";
    } else {
        echo "<p class='ok'>✅ Connection SUCCESSFUL!</p>";
        $conn->set_charset('utf8mb4');

        /* 4. Check tables */
        echo "<h2>4. Database Tables</h2>";
        $res = $conn->query("SHOW TABLES");
        if ($res) {
            $tables = [];
            while ($row = $res->fetch_array()) {
                $tables[] = $row[0];
            }
            if (count($tables) > 0) {
                echo "<p class='ok'>✅ Found " . count($tables) . " tables:</p><ul>";
                foreach ($tables as $t) {
                    echo "<li class='info'>$t</li>";
                }
                echo "</ul>";

                /* Check if admins table has data */
                $check = $conn->query("SELECT COUNT(*) as c FROM admins");
                if ($check) {
                    $count = $check->fetch_assoc()['c'];
                    echo "<p class='info'>Admin accounts in database: <b>$count</b></p>";
                    if ($count == 0) {
                        echo "<p class='bad'>❌ No admin accounts! You need to import database.sql</p>";
                    } else {
                        echo "<p class='ok'>✅ Admin accounts exist. You can log in.</p>";
                    }
                }
            } else {
                echo "<p class='bad'>❌ No tables found! You need to import database.sql via phpMyAdmin.</p>";
            }
        } else {
            echo "<p class='bad'>❌ Could not list tables: " . $conn->error . "</p>";
        }
        $conn->close();
    }
} catch (Exception $e) {
    echo "<p class='bad'>❌ Exception: " . $e->getMessage() . "</p>";
}

/* 5. Check uploads folder */
echo "<h2>5. Uploads Folder Check</h2>";
$uploadDir = __DIR__ . '/uploads/members';
if (is_dir($uploadDir)) {
    echo "<p class='ok'>✅ uploads/members/ folder EXISTS</p>";
    if (is_writable($uploadDir)) {
        echo "<p class='ok'>✅ uploads/members/ is WRITABLE</p>";
    } else {
        echo "<p class='bad'>❌ uploads/members/ is NOT writable (set permissions to 755 or 775)</p>";
    }
} else {
    echo "<p class='bad'>❌ uploads/members/ folder DOES NOT EXIST</p>";
    echo "<p class='info'>Create it manually in the file manager.</p>";
}

/* 6. Check key files exist */
echo "<h2>6. Key File Check</h2>";
$keyFiles = ['db_connect.php', 'login.php', 'index.php', 'includes/header.php', 'includes/helpers.php', 'includes/auth.php'];
foreach ($keyFiles as $f) {
    $path = __DIR__ . '/' . $f;
    if (file_exists($path)) {
        echo "<p class='ok'>✅ $f</p>";
    } else {
        echo "<p class='bad'>❌ $f — MISSING!</p>";
    }
}

echo "<h2>Done</h2>";
echo "<p class='info'>If you see ❌ errors above, that's what's causing your 500 error. Fix them, then delete this diag.php file.</p>";
echo "</body></html>";
