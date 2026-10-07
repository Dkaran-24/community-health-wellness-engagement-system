<?php
/**
 * community/_header.php — shared layout header for COMMUNITY pages.
 *
 * Usage (top of every community page):
 *   $PAGE_TITLE = '...'; $PAGE_KEY = '...';
 *   require_once __DIR__ . '/../includes/community_auth.php';
 *   require_once __DIR__ . '/_header.php';
 */
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/_flash.php';

$PAGE_TITLE  = $PAGE_TITLE  ?? 'Community';
$PAGE_KEY    = $PAGE_KEY    ?? '';
$settings    = get_settings();
$base        = community_base_url();
$cuser       = current_community_user();
$cuser_name  = $cuser['full_name'] ?? 'Community Member';
$is_vol      = is_volunteer_user();
$today_date  = date('l, F j, Y');

$community_nav = [
    ['dashboard',  'My Dashboard',   'dashboard.php',  '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>'],
    ['events',     'Events',         'events.php',     '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>'],
    ['my-events',  'My Registrations','my-events.php', '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'],
    ['challenges', 'Challenges',     'challenges.php', '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></svg>'],
    ['resources',  'Resources',      'resources.php',  '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>'],
    ['surveys',    'Surveys & Polls','surveys.php',    '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>'],
    ['requests',   'Requests',       'requests.php',   '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>'],
    ['volunteer',  'Become a Volunteer', 'volunteer.php', '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>'],
    ['feedback',   'My Feedback',    'my-feedback.php','<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>'],
    ['impact',     'Community Impact','impact.php',    '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>'],
    ['profile',    'My Profile',     'profile.php',    '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>'],
];
if ($is_vol) {
    $community_nav[] = ['volunteer-dashboard', 'Volunteer Dashboard', 'volunteer-dashboard.php', '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c0 1.7 2.7 3 6 3s6-1.3 6-3v-5"/></svg>'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($settings['gym_name']) ?> | Community — <?= htmlspecialchars($PAGE_TITLE) ?></title>
<link rel="icon" href="<?= $base ?>/assets/images/favicon.ico" type="image/x-icon">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>/assets/css/community.css">
<link rel="stylesheet" href="<?= $base ?>/assets/css/style.css">
</head>
<body class="community-page">
<nav class="c-topbar">
  <a href="<?= $base ?>/community/dashboard.php" class="c-brand">
    <img src="<?= $base ?>/assets/images/logo.jpg" alt="logo">
    <span><b>NEW LIFE FITNESS</b><small>Community Platform</small></span>
  </a>
  <button class="c-burger" aria-label="Menu" onclick="document.querySelector('.c-nav').classList.toggle('open')">&#9776;</button>
  <span class="c-user">
    <span class="c-avatar"><?= strtoupper(substr($cuser_name, 0, 1)) ?></span>
    <span class="c-uname"><?= htmlspecialchars($cuser_name) ?><?= $is_vol ? ' <em class="c-voltag">VOLUNTEER</em>' : '' ?></span>
    <a class="c-logout" href="<?= $base ?>/community/logout.php">Logout</a>
  </span>
  <div class="c-nav">
    <?php foreach ($community_nav as $item): [$k, $label, $href, $ico] = $item; ?>
      <a href="<?= $base ?>/community/<?= $href ?>" class="<?= ($PAGE_KEY === $k) ? 'active' : '' ?>"><span><?= $ico ?></span> <?= $label ?></a>
    <?php endforeach; ?>
  </div>
</nav>
<main class="c-main">
<?php community_flash(); ?>
