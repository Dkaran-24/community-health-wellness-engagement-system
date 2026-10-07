<?php
/**
 * admin/community/_nav.php — shared sub-navigation tabs for the
 * COMMUNITY ENGAGEMENT PROJECT admin modules.
 *
 * Include this from every admin/community/*.php page (after header.php)
 * to render the section tabs and keep navigation consistent.
 *
 * $PAGE_KEY should be one of:
 *   community, community-users, community-events, community-attendance,
 *   community-volunteers, community-feedback, community-surveys,
 *   community-requests, community-resources, community-announcements,
 *   community-challenges
 */

$communityNav = [
    ['community',            'Overview',       'index.php',       '&#9632;'],
    ['community-users',      'Users',          'users.php',       '&#9635;'],
    ['community-events',     'Events',         'events.php',      '&#128197;'],
    ['community-attendance', 'Attendance',     'attendance.php',  '&#10003;'],
    ['community-volunteers', 'Volunteers',     'volunteers.php',  '&#9997;'],
    ['community-feedback',   'Feedback',       'feedback.php',    '&#11088;'],
    ['community-surveys',    'Surveys',        'surveys.php',     '&#128202;'],
    ['community-requests',   'Requests',       'requests.php',    '&#128233;'],
    ['community-resources',  'Resources',      'resources.php',   '&#128218;'],
    ['community-announcements', 'Announcements', 'announcements.php', '&#128227;'],
    ['community-challenges', 'Challenges',     'challenges.php',  '&#127942;'],
];
$__active = $PAGE_KEY ?? '';
?>
<div class="comm-tabs">
  <?php foreach ($communityNav as $__t): list($__k, $__label, $__href, $__ico) = $__t; ?>
    <a href="<?= $__href ?>"
       class="<?= $__active === $__k ? 'on' : '' ?>"
       title="<?= e($__label) ?>"><span><?= $__ico ?></span> <?= e($__label) ?></a>
  <?php endforeach; ?>
</div>
