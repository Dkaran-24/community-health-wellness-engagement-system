<?php
/**
 * member/_header.php — shared <head> + opening layout for member portal pages.
 *
 * This is the member equivalent of includes/header.php (admin).
 * It renders a member-specific top navigation bar (no admin sidebar).
 *
 * Usage: set $PAGE_TITLE and $PAGE_KEY before including this file.
 *        require_once __DIR__ . '/_header.php';
 */
require_once __DIR__ . '/../includes/member_auth.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/helpers.php';
require_member_login();

/* Defence in depth: if the session was bound to a different client IP at
   login, treat it as tampered and force a re-login. (Best-effort; harmless
   behind a proxy that rewrites REMOTE_ADDR consistently.) */
if (isset($_SESSION['member_ip']) && $_SESSION['member_ip'] !== ''
    && ($_SERVER['REMOTE_ADDR'] ?? '') !== ''
    && $_SESSION['member_ip'] !== $_SERVER['REMOTE_ADDR']) {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . member_login_url());
    exit;
}

$PAGE_TITLE = $PAGE_TITLE ?? 'Member Portal';
$PAGE_KEY   = $PAGE_KEY   ?? '';
$settings   = get_settings();
$base       = member_base_url();
$m          = current_member();
$member_name = $m['name'] ?? 'Member';
$today_date  = date('l, F j, Y');

$member_nav = [
  ['dashboard',  'My Dashboard',  'dashboard.php',   '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>'],
  ['progress',   'My Progress',   'progress.php',    '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>'],
  ['attendance', 'Attendance',    'attendance.php',  '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'],
  ['payments',   'Payments',      'payments.php',    '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>'],
  ['profile',    'My Profile',    'profile.php',     '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($settings['gym_name']) ?> | Member Portal</title>
<link rel="icon" href="<?= $base ?>/assets/images/favicon.ico" type="image/x-icon">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= $base ?>/assets/css/member.css">
<script src="<?= $base ?>/assets/js/main.js" defer></script>
</head>
<body>
<div class="member-app">
  <header class="member-topbar">
    <div class="member-brand">
      <img src="<?= $base ?>/assets/images/logo.jpg" alt="logo">
      <div>
        <div class="member-brand-name"><?= htmlspecialchars($settings['gym_name']) ?></div>
        <div class="member-brand-sub">Member Portal</div>
      </div>
    </div>
    <nav class="member-nav">
      <?php foreach ($member_nav as $item):
        [$k, $label, $slug, $ico] = $item;
        $active = ($PAGE_KEY === $k) ? ' active' : '';
      ?>
        <a href="<?= $base ?>/member/<?= $slug ?>" class="member-nav-link<?= $active ?>"><span class="ico"><?= $ico ?></span><?= $label ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="member-user">
      <div class="member-avatar"><?= strtoupper(substr($member_name,0,1)) ?></div>
      <div class="member-user-info">
        <div class="member-user-name"><?= htmlspecialchars($member_name) ?></div>
        <div class="member-user-role">Member #<?= $m['id'] ?? '' ?></div>
      </div>
      <a href="<?= $base ?>/member/logout.php" class="btn btn-ghost btn-sm" data-confirm="Log out of your member portal?">Logout</a>
    </div>
  </header>
  <main class="member-content">
