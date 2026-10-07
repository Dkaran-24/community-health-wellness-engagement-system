<?php
/**
 * sidebar.php — branded navigation sidebar.
 * Uses $PAGE_KEY (set in each page) to highlight the active link.
 * Icons are inline SVG (stroke-based, professional look).
 */
$settings = get_settings();
$base = base_url();
$key  = $PAGE_KEY ?? '';

/* Reusable inline SVG icon helper (24px grid, stroke style). */
function nlf_ico($path, $label = '') {
  return '<svg class="ico" viewBox="0 0 24 24" width="17" height="17" fill="none" '
       . 'stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" '
       . 'aria-hidden="true">' . $path . '</svg>';
}

$icons = [
  'dashboard'  => nlf_ico('<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>'),
  'members'    => nlf_ico('<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'),
  'trainers'   => nlf_ico('<circle cx="12" cy="7" r="4"/><path d="M6 21v-2a6 6 0 0 1 12 0v2"/><path d="M12 9v6"/>', ''),
  'attendance' => nlf_ico('<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'),
  'plans'      => nlf_ico('<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>'),
  'offers'     => nlf_ico('<path d="M20 12v10H4V12"/><path d="M2 7h20v5H2z"/><path d="M12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>'),
  'fees'       => nlf_ico('<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>'),
  'trainer-payments' => nlf_ico('<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>'),
  'analytics'  => nlf_ico('<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>'),
  'reports'    => nlf_ico('<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>'),
  'settings'   => nlf_ico('<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>'),
  'email-test' => nlf_ico('<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>'),
  'community'  => nlf_ico('<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>'),
];

$nav = [
  ['dashboard',  'Dashboard',          'overview',     $icons['dashboard']],
  ['members',    'Members',            'members',      $icons['members']],
  ['trainers',   'Trainers',           'trainers',     $icons['trainers']],
  ['attendance', 'Attendance',         'attendance',   $icons['attendance']],
  ['plans',      'Membership Plans',   'plans',        $icons['plans']],
  ['offers',     'Offers',             'offers',       $icons['offers']],
  ['fees',       'Fee Management',     'fees',         $icons['fees']],
  ["trainer-payments", "Trainer Payments", "trainer-payments", $icons['trainer-payments']],
  ['analytics',  'Analytics',          'analytics',    $icons['analytics']],
  ['reports',    'Reports',            'reports',      $icons['reports']],
  ['settings',   'Settings',           'settings',     $icons['settings']],
  ['email-test', 'Email Diagnostic',   '',             $icons['email-test']],
];

/* COMMUNITY ENGAGEMENT PROJECT (CEP) modules. */
$communityPages = [
  ['community',            'Overview',       'index.php'],
  ['community-users',      'Users',          'users.php'],
  ['community-events',     'Events',         'events.php'],
  ['community-attendance', 'Attendance',     'attendance.php'],
  ['community-volunteers', 'Volunteers',     'volunteers.php'],
  ['community-feedback',   'Feedback',       'feedback.php'],
  ['community-surveys',    'Surveys',        'surveys.php'],
  ['community-requests',   'Requests',       'requests.php'],
  ['community-resources',  'Resources',      'resources.php'],
  ['community-announcements', 'Announcements', 'announcements.php'],
  ['community-challenges', 'Challenges',     'challenges.php'],
];
$communityIco = $icons['community'];
?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <img src="<?= $base ?>/assets/images/logo.jpg" width="640" height="640" alt="New Life Fitness Club logo">
    <div class="name">New Life<br>Fitness Club
      <small>Admin Panel</small>
    </div>
  </div>

  <nav class="sidebar-nav">

    <div class="nav-label">Community</div>

    <a href="<?= $base ?>/admin/community/index.php"
       class="<?= ($PAGE_KEY ?? '') === 'community' ? 'active' : '' ?>">
        <span class="ico">■</span>
        <span>Overview</span>
    </a>

    <a href="<?= $base ?>/admin/community/users.php"
       class="<?= ($PAGE_KEY ?? '') === 'community-users' ? 'active' : '' ?>">
        <span class="ico">▣</span>
        <span>Users</span>
    </a>

    <a href="<?= $base ?>/admin/community/events.php"
       class="<?= ($PAGE_KEY ?? '') === 'community-events' ? 'active' : '' ?>">
        <span class="ico">📅</span>
        <span>Events</span>
    </a>

    <a href="<?= $base ?>/admin/community/attendance.php"
       class="<?= ($PAGE_KEY ?? '') === 'community-attendance' ? 'active' : '' ?>">
        <span class="ico">✓</span>
        <span>Attendance</span>
    </a>

    <a href="<?= $base ?>/admin/community/volunteers.php"
       class="<?= ($PAGE_KEY ?? '') === 'community-volunteers' ? 'active' : '' ?>">
        <span class="ico">✍</span>
        <span>Volunteers</span>
    </a>

    <a href="<?= $base ?>/admin/community/feedback.php"
       class="<?= ($PAGE_KEY ?? '') === 'community-feedback' ? 'active' : '' ?>">
        <span class="ico">⭐</span>
        <span>Feedback</span>
    </a>

    <a href="<?= $base ?>/admin/community/surveys.php"
       class="<?= ($PAGE_KEY ?? '') === 'community-surveys' ? 'active' : '' ?>">
        <span class="ico">📊</span>
        <span>Surveys</span>
    </a>

    <a href="<?= $base ?>/admin/community/requests.php"
       class="<?= ($PAGE_KEY ?? '') === 'community-requests' ? 'active' : '' ?>">
        <span class="ico">📩</span>
        <span>Requests</span>
    </a>

    <a href="<?= $base ?>/admin/community/resources.php"
       class="<?= ($PAGE_KEY ?? '') === 'community-resources' ? 'active' : '' ?>">
        <span class="ico">📚</span>
        <span>Resources</span>
    </a>

    <a href="<?= $base ?>/admin/community/announcements.php"
       class="<?= ($PAGE_KEY ?? '') === 'community-announcements' ? 'active' : '' ?>">
        <span class="ico">📣</span>
        <span>Announcements</span>
    </a>

    <a href="<?= $base ?>/admin/community/challenges.php"
       class="<?= ($PAGE_KEY ?? '') === 'community-challenges' ? 'active' : '' ?>">
        <span class="ico">🏆</span>
        <span>Challenges</span>
    </a>

</nav>

  <div class="sidebar-footer">
    &copy; <?= date('Y') ?> New Life Fitness Club<br>
    <a href="<?= $base ?>/login.php">System v1.0</a>
  </div>
</aside>
