<?php
/**
 * header.php — shared <head> + opening layout for protected admin pages.
 * Usage: set $PAGE_TITLE and $PAGE_KEY before including this file.
 *        $PAGE_KEY is used to highlight the active sidebar item.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';   /* CSRF token helpers for ALL admin forms */
require_login();

$PAGE_TITLE = $PAGE_TITLE ?? 'Admin';
$PAGE_KEY   = $PAGE_KEY   ?? '';
$PAGE_DESCRIPTION = $PAGE_DESCRIPTION ?? ($PAGE_TITLE . ' — New Life Fitness Club Admin Panel');
$canonical_url = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '');
$settings   = get_settings();
$admin_name = $_SESSION['admin_name'] ?? 'Administrator';
$base       = base_url();
$cur_date   = date('l, F j, Y');

/* Security headers for the protected admin area.
   Inline styles are used throughout the legacy admin UI, so CSP allows
   inline styles but restricts scripts, frames, objects and connections. */
if (!headers_sent()) {
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'; img-src 'self' data: blob:; font-src 'self' https://fonts.gstatic.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; script-src 'self' https://cdn.jsdelivr.net; connect-src 'self';");
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($settings['gym_name']) ?> | Admin Panel</title>
<meta name="description" content="<?= htmlspecialchars($PAGE_DESCRIPTION, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:title" content="<?= htmlspecialchars($settings['gym_name'], ENT_QUOTES, 'UTF-8') ?> | Admin Panel">
<meta property="og:description" content="<?= htmlspecialchars($PAGE_DESCRIPTION, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:image" content="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/images/logo.jpg">
<meta property="og:url" content="<?= htmlspecialchars($canonical_url, ENT_QUOTES, 'UTF-8') ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= htmlspecialchars($settings['gym_name'], ENT_QUOTES, 'UTF-8') ?> | Admin Panel">
<meta name="twitter:description" content="<?= htmlspecialchars($PAGE_DESCRIPTION, ENT_QUOTES, 'UTF-8') ?>">
<meta name="twitter:image" content="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/images/logo.jpg">
<link rel="icon" href="<?= $base ?>/assets/images/favicon.ico" type="image/x-icon">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>/assets/css/style.css">
<?php if (strpos($PAGE_KEY ?? '', 'community') === 0): ?>
<link rel="stylesheet" href="<?= $base ?>/assets/css/community-admin.css">
<?php endif; ?>
<script src="<?= $base ?>/assets/js/main.js" defer></script>
</head>
<body>
<div class="app">
  <?php include __DIR__ . '/sidebar.php'; ?>
  <div class="main">
    <header class="topbar">
      <button class="sidebar-toggle" type="button" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="sidebar">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div class="page-title"><?= htmlspecialchars($PAGE_TITLE) ?>
        <small><?= htmlspecialchars($settings['gym_name']) ?> &middot; Admin Panel</small>
      </div>
      <div class="user-chip">
        <div class="avatar"><?= strtoupper(substr($admin_name,0,1)) ?></div>
        <div>
          <div style="font-weight:600;color:var(--navy-800)"><?= htmlspecialchars($admin_name) ?></div>
          <div style="font-size:12px">Administrator</div>
        </div>
        <a href="<?= $base ?>/logout.php" class="btn btn-ghost btn-sm" data-confirm="Log out of the admin panel?">Logout</a>
      </div>
    </header>
    <main class="content">
