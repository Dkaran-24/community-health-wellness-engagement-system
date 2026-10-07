<?php
/**
 * index.php — NEW LIFE FITNESS public Community Engagement homepage (CEP).
 *
 * This is the public landing page for the Community Engagement Project:
 * hero, benefits, upcoming events, challenges, announcements, wellness
 * resources, community impact snapshot and contact/CTA sections.
 *
 * No login required — shows only public-safe information. All numbers in
 * the impact section are computed from the platform's demo seed data
 * (clearly labelled), never presented as real community results.
 */
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/community_auth.php';

$db = db();
$settings = get_settings();
$base = community_base_url();
$gymName = $settings['gym_name'] ?: 'New Life Fitness Club';

/* ---------------- public data ---------------- */

/* Upcoming events (next 6) */
$upcomingEvents = $db->query(
    "SELECT ce.id, ce.event_name, ce.category, ce.event_date, ce.start_time, ce.location,
            ce.max_participants,
            (SELECT COUNT(*) FROM event_registrations er
              WHERE er.event_id = ce.id AND er.status <> 'Cancelled') AS taken
       FROM community_events ce
      WHERE ce.status IN ('Upcoming','Ongoing') AND ce.event_date >= CURDATE()
      ORDER BY ce.event_date ASC, ce.start_time ASC
      LIMIT 6"
);

/* Active & upcoming challenges (4) */
$challenges = $db->query(
    "SELECT c.id, c.challenge_name, c.category, c.target_value, c.target_unit,
            c.status, c.start_date, c.end_date,
            (SELECT COUNT(*) FROM challenge_participants cp WHERE cp.challenge_id = c.id) AS joined
       FROM fitness_challenges c
      WHERE c.status IN ('Active','Upcoming')
      ORDER BY FIELD(c.status,'Active','Upcoming'), c.start_date ASC
      LIMIT 4"
);

/* Active announcements (4 most recent) */
$announcements = $db->query(
    "SELECT id, title, message, type
       FROM community_announcements
      WHERE status = 'Active'
      ORDER BY created_at DESC
      LIMIT 4"
);

/* Top wellness resources (6 by views) */
$resources = $db->query(
    "SELECT id, title, category, summary, views
       FROM wellness_resources
      WHERE status = 'Published'
      ORDER BY views DESC, id ASC
      LIMIT 6"
);

/* Impact snapshot (from platform records — demo data in development) */
$impact = [
    'members'    => (int)$db->query("SELECT COUNT(*) c FROM community_users WHERE status='Active'")->fetch_assoc()['c'],
    'events'     => (int)$db->query("SELECT COUNT(*) c FROM community_events WHERE status <> 'Cancelled'")->fetch_assoc()['c'],
    'regs'       => (int)$db->query("SELECT COUNT(*) c FROM event_registrations WHERE status <> 'Cancelled'")->fetch_assoc()['c'],
    'volunteers' => (int)$db->query("SELECT COUNT(*) c FROM volunteers WHERE status='Active'")->fetch_assoc()['c'],
    'hours'      => (float)$db->query("SELECT COALESCE(SUM(total_hours),0) s FROM volunteer_hours WHERE status='Approved'")->fetch_assoc()['s'],
];

/* Ann icon by type */
$annIcons = [
    'Event' => '&#128197;', 'Health Camp' => '&#127973;', 'Challenge' => '&#127942;',
    'Workshop' => '&#128188;', 'Volunteer Opportunity' => '&#129309;',
    'Notice' => '&#128227;', 'Other' => '&#128483;',
];
$chalIcons = ['Walking' => '&#128694;', 'Running' => '&#127939;', 'Yoga' => '&#129496;',
              'General Fitness' => '&#128170;', 'Steps' => '&#128099;', 'Other' => '&#127941;'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($gymName) ?> | Community Engagement — Stronger Together, Healthier Community!</title>
<meta name="description" content="New Life Fitness community engagement: free fitness events, wellness challenges, health resources and volunteering for everyone.">
<link rel="icon" href="<?= $base ?>/assets/images/favicon.ico" type="image/x-icon">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>/assets/css/public.css">
</head>
<body class="public-page">

<!-- Demo-data notice -->
<div class="p-notice">
  <b>Demo platform:</b> the events, challenges and numbers below are clearly-labelled sample data for the college CEP demonstration &mdash; not real community results.
</div>

<!-- ================= Top navigation ================= -->
<header class="p-topbar">
  <div class="p-topbar-inner">
    <a class="p-brand" href="<?= $base ?>/index.php">
      <img src="<?= $base ?>/assets/images/logo.jpg" alt="New Life Fitness logo">
      <span><b>NEW LIFE FITNESS</b><small>Community Engagement</small></span>
    </a>
    <button class="p-burger" onclick="document.querySelector('.p-nav').classList.toggle('open');document.querySelector('.p-actions').classList.toggle('open')" aria-label="Menu">&#9776;</button>
    <nav class="p-nav" id="pnav">
      <a href="#home" class="active">Home</a>
      <a href="#about">About</a>
      <a href="#community">Community</a>
      <a href="#events">Programs &amp; Events</a>
      <a href="#challenges">Challenges</a>
      <a href="#resources">Resources</a>
      <a href="#contact">Contact</a>
    </nav>
    <div class="p-actions">
      <a class="p-btn-outline" href="<?= $base ?>/community/login.php">Login</a>
      <a class="p-btn-gold" href="<?= $base ?>/community/register.php">Register</a>
    </div>
  </div>
</header>

<!-- ================= Hero ================= -->
<section class="p-hero" id="home" style="--hero-img:url('<?= $base ?>/assets/images/hero.jpg')">
  <div class="p-hero-inner">
    <div>
      <h1>NEW LIFE <span class="accent">FITNESS</span></h1>
      <p class="sub">Stronger Together, Healthier Community!</p>
      <p class="lede">Free fitness events, wellness challenges, health education and volunteering opportunities &mdash; bringing our neighbourhood together around a healthier way of living. Everyone is welcome, no gym membership needed.</p>
      <div class="p-hero-ctas">
        <a class="p-btn-hero" href="<?= $base ?>/community/register.php">Join the Community &mdash; Free</a>
        <a class="p-btn-hero-ghost" href="#events">See Upcoming Events</a>
      </div>
      <div class="p-hero-stats">
        <div class="p-hstat"><b><?= $impact['members'] ?></b><span>Community members</span></div>
        <div class="p-hstat"><b><?= $impact['events'] ?></b><span>Events held</span></div>
        <div class="p-hstat"><b><?= $impact['volunteers'] ?></b><span>Active volunteers</span></div>
        <div class="p-hstat"><b><?= number_format($impact['hours'], 1) ?></b><span>Volunteer hours</span></div>
      </div>
    </div>
    <div class="p-hero-art">
      <img src="<?= $base ?>/assets/images/hero.jpg" alt="Community fitness session" onerror="this.style.display='none';this.parentElement.style.background='linear-gradient(135deg,#1B3A5C,#0F2A3E)'">
      <div class="float-tag">
        <span class="ft-ico">&#128100;</span>
        <span><b>Free for everyone</b><small>No membership needed &mdash; just register</small></span>
      </div>
    </div>
  </div>
</section>

<!-- ================= Benefits ================= -->
<section class="p-section" id="about">
  <div class="wrap">
    <div class="p-section-head">
      <span class="kicker">Why join</span>
      <h2>Our Community, Our Health</h2>
      <p>The club opens its doors to the neighbourhood through a structured Community Engagement Programme &mdash; supervised by certified trainers, completely free of charge.</p>
    </div>
    <div class="p-benefits">
      <div class="p-benefit">
        <div class="b-ico">&#127963;</div>
        <h3>Free Fitness Events</h3>
        <p>Twelve categories of supervised sessions &mdash; fitness camps, yoga, zumba, walking and running clubs, senior and women's wellness, nutrition workshops and more.</p>
      </div>
      <div class="p-benefit">
        <div class="b-ico">&#127942;</div>
        <h3>Wellness Challenges</h3>
        <p>Join community-wide step, walking, yoga and habit challenges. Log your progress from your dashboard and celebrate milestones together.</p>
      </div>
      <div class="p-benefit">
        <div class="b-ico">&#128218;</div>
        <h3>Wellness Resources</h3>
        <p>A curated library of practical guides on exercise, nutrition, safety and healthy lifestyle habits &mdash; written in simple language for every age group.</p>
      </div>
      <div class="p-benefit">
        <div class="b-ico">&#129309;</div>
        <h3>Volunteer Programme</h3>
        <p>Give back by helping at events &mdash; guide participants, manage desks, support seniors. Approved volunteers earn tracked service hours and certificates.</p>
      </div>
      <div class="p-benefit">
        <div class="b-ico">&#128483;</div>
        <h3>Your Voice Counts</h3>
        <p>Share feedback after every event you attend, answer surveys and polls, and request new programmes &mdash; organisers review every suggestion.</p>
      </div>
      <div class="p-benefit">
        <div class="b-ico">&#128200;</div>
        <h3>Community Impact</h3>
        <p>A transparent impact dashboard shows participation, attendance, volunteer hours and satisfaction &mdash; the community can see the programme working.</p>
      </div>
    </div>
  </div>
</section>

<!-- ================= Events ================= -->
<section class="p-section alt" id="events">
  <div class="wrap">
    <div class="p-section-head">
      <span class="kicker">Programs &amp; events</span>
      <h2>Upcoming Community Events</h2>
      <p>Register free from your community dashboard. Seats are limited &mdash; capacity is shown live.</p>
    </div>
    <?php if ($upcomingEvents && $upcomingEvents->num_rows): ?>
    <div class="p-events-grid">
      <?php while ($ev = $upcomingEvents->fetch_assoc()):
        $cap = max(1, (int)$ev['max_participants']);
        $taken = (int)$ev['taken'];
        $pct = min(100, (int)round($taken / $cap * 100));
        $dt = strtotime($ev['event_date']);
      ?>
      <div class="p-event-card">
        <div class="p-event-top">
          <div class="p-date-box"><b><?= date('d', $dt) ?></b><span><?= date('M', $dt) ?></span></div>
          <div>
            <h3><?= e($ev['event_name']) ?></h3>
            <span class="cat"><span class="p-cat-badge"><?= e($ev['category']) ?></span></span>
          </div>
        </div>
        <div class="p-event-body">
          <div class="meta">
            <span>&#128337; <?= e(date('h:i A', strtotime($ev['start_time'] ?? ''))) ?></span>
            <span>&#128205; <?= e($ev['location']) ?></span>
          </div>
          <div>
            <div class="p-cap-bar"><i style="width:<?= $pct ?>%"></i></div>
            <div class="p-cap-note"><?= $taken ?>/<?= $cap ?> registered &middot; <?= max(0, $cap - $taken) ?> seats left</div>
          </div>
          <a class="go" href="<?= $base ?>/community/login.php">Register &rarr;</a>
        </div>
      </div>
      <?php endwhile; ?>
    </div>
    <?php else: ?>
    <p style="text-align:center;color:var(--muted)">New events are being planned &mdash; check back soon.</p>
    <?php endif; ?>
  </div>
</section>

<!-- ================= Challenges ================= -->
<section class="p-section" id="challenges">
  <div class="wrap">
    <div class="p-section-head">
      <span class="kicker">Community challenges</span>
      <h2>Current &amp; Upcoming Challenges</h2>
      <p>Join from your dashboard and log daily progress. Finish the target to earn completion credit.</p>
    </div>
    <?php if ($challenges && $challenges->num_rows): ?>
    <div class="p-chal-grid">
      <?php while ($ch = $challenges->fetch_assoc()): ?>
      <div class="p-chal-card">
        <span class="ch-status <?= $ch['status'] === 'Active' ? 'active' : 'upcoming' ?>"><?= e($ch['status']) ?></span>
        <div class="ch-ico"><?= $chalIcons[$ch['category']] ?? '&#127941;' ?></div>
        <h3><?= e($ch['challenge_name']) ?></h3>
        <div class="target"><?= (int)$ch['target_value'] ?> <small><?= e($ch['target_unit']) ?><?= $ch['target_unit'] === 'days' ? '' : ' target' ?></small></div>
        <p><?= e(fmtDate($ch['start_date'])) ?> &rarr; <?= e(fmtDate($ch['end_date'])) ?> &middot; <?= (int)$ch['joined'] ?> joined</p>
      </div>
      <?php endwhile; ?>
    </div>
    <?php else: ?>
    <p style="text-align:center;color:var(--muted)">Challenges open for joining soon.</p>
    <?php endif; ?>
  </div>
</section>

<!-- ================= Announcements ================= -->
<section class="p-section alt" id="community">
  <div class="wrap">
    <div class="p-section-head">
      <span class="kicker">Latest news</span>
      <h2>Community Announcements</h2>
      <p>Short notices from the organisers &mdash; new events, camps, challenges and volunteer calls.</p>
    </div>
    <div class="p-ann-list">
      <?php if ($announcements && $announcements->num_rows): while ($an = $announcements->fetch_assoc()): ?>
      <div class="p-ann">
        <div class="a-ico"><?= $annIcons[$an['type']] ?? '&#128483;' ?></div>
        <div>
          <span class="a-type"><?= e($an['type']) ?></span>
          <h3><?= e($an['title']) ?></h3>
          <p><?= e($an['message']) ?></p>
        </div>
      </div>
      <?php endwhile; else: ?>
      <p style="text-align:center;color:var(--muted)">No announcements right now.</p>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ================= Resources ================= -->
<section class="p-section" id="resources">
  <div class="wrap">
    <div class="p-section-head">
      <span class="kicker">Wellness library</span>
      <h2>Wellness Resources</h2>
      <p>Short, practical guides curated by our certified trainers &mdash; free for the whole community.</p>
    </div>
    <?php if ($resources && $resources->num_rows): ?>
    <div class="p-res-grid">
      <?php while ($r = $resources->fetch_assoc()): ?>
      <div class="p-res-card">
        <span class="r-cat"><?= e($r['category']) ?></span>
        <h3><?= e($r['title']) ?></h3>
        <p><?= e($r['summary']) ?></p>
        <span class="r-views">&#128065; <?= (int)$r['views'] ?> views</span>
      </div>
      <?php endwhile; ?>
    </div>
    <?php else: ?>
    <p style="text-align:center;color:var(--muted)">Resources coming soon.</p>
    <?php endif; ?>
  </div>
</section>

<!-- ================= Impact ================= -->
<section class="p-section p-impact" id="impact">
  <div class="wrap">
    <div class="p-section-head">
      <span class="kicker" style="color:var(--gold-lt)">Community impact</span>
      <h2>Our Community, In Numbers</h2>
      <p>Live counts computed from platform records. During the college demo these figures come from the labelled sample data.</p>
    </div>
    <div class="p-count-grid">
      <div class="p-count"><b><?= $impact['members'] ?></b><span>Community members</span></div>
      <div class="p-count"><b><?= $impact['events'] ?></b><span>Events held</span></div>
      <div class="p-count"><b><?= $impact['regs'] ?></b><span>Event registrations</span></div>
      <div class="p-count"><b><?= $impact['volunteers'] ?></b><span>Active volunteers</span></div>
      <div class="p-count"><b><?= number_format($impact['hours'], 1) ?></b><span>Volunteer hours</span></div>
    </div>
    <p class="p-demo-note">&#9432; Honest disclosure: this is a college CEP prototype. All numbers are computed from the platform's own demo seed data and are <b>not real community results</b>. See docs/AI_DOCUMENTATION.md for how the feedback analysis works.</p>
  </div>
</section>

<!-- ================= CTA ================= -->
<section class="p-cta">
  <div class="wrap p-cta-inner">
    <div>
      <h2>Ready to get moving with your neighbours?</h2>
      <p>Create a free community account today &mdash; register for events, join challenges, or volunteer to help. It takes two minutes.</p>
    </div>
    <a class="p-btn-navy" href="<?= $base ?>/community/register.php">Create Free Account</a>
  </div>
</section>

<!-- ================= Contact ================= -->
<section class="p-section" id="contact">
  <div class="wrap">
    <div class="p-section-head">
      <span class="kicker">Get in touch</span>
      <h2>Contact Us</h2>
      <p>Questions about events, volunteering or the programme? Reach out or simply walk in.</p>
    </div>
    <div class="p-contact-grid">
      <div class="p-contact-card">
        <div class="c-ico">&#128205;</div>
        <h3>Visit Us</h3>
        <p><?= e($settings['address']) ?></p>
      </div>
      <div class="p-contact-card">
        <div class="c-ico">&#128222;</div>
        <h3>Call Us</h3>
        <p><?= e($settings['contact']) ?></p>
      </div>
      <div class="p-contact-card">
        <div class="c-ico">&#9993;</div>
        <h3>Email Us</h3>
        <p><?= e($settings['email']) ?></p>
      </div>
    </div>
  </div>
</section>

<!-- ================= Footer ================= -->
<footer class="p-footer">
  <div class="wrap">
    <div class="p-foot-grid">
      <div>
        <div class="p-foot-brand">
          <img src="<?= $base ?>/assets/images/logo.jpg" alt="logo">
          <span><b>NEW LIFE FITNESS</b><small>Community Engagement</small></span>
        </div>
        <p>A college Community Engagement Project by New Life Fitness Club &mdash; free fitness, wellness education and volunteering for our neighbourhood.</p>
      </div>
      <div>
        <h4>Quick Links</h4>
        <a href="#home">Home</a>
        <a href="#about">About</a>
        <a href="#events">Programs &amp; Events</a>
        <a href="#challenges">Challenges</a>
        <a href="#resources">Resources</a>
      </div>
      <div>
        <h4>Community</h4>
        <a href="<?= $base ?>/community/login.php">Community Login</a>
        <a href="<?= $base ?>/community/register.php">Register Free</a>
        <a href="<?= $base ?>/login.php">Admin Login</a>
      </div>
      <div>
        <h4>Contact</h4>
        <p><?= e($settings['address']) ?></p>
        <p><?= e($settings['contact']) ?></p>
        <p><?= e($settings['email']) ?></p>
      </div>
    </div>
    <div class="p-foot-bottom">
      <span>&copy; <?= date('Y') ?> <?= e($gymName) ?> &mdash; Community Engagement Project. Built as a college CEP.</span>
      <span>Rule-Based Recommendation Prototype &mdash; see docs/AI_DOCUMENTATION.md</span>
    </div>
  </div>
</footer>
<script>
document.addEventListener('DOMContentLoaded', function () {

    const navLinks = document.querySelectorAll('.p-nav a');

    function updateActiveNav() {
        let currentHash = window.location.hash;

        // If there is no # section, Home is active
        if (!currentHash) {
            currentHash = '#home';
        }

        navLinks.forEach(function (link) {
            link.classList.remove('active');

            if (link.getAttribute('href') === currentHash) {
                link.classList.add('active');
            }
        });
    }

    // Change active button when a navigation link is clicked
    navLinks.forEach(function (link) {
        link.addEventListener('click', function () {
            navLinks.forEach(function (item) {
                item.classList.remove('active');
            });

            this.classList.add('active');
        });
    });

    // Also update when the browser hash changes
    window.addEventListener('hashchange', updateActiveNav);

    // Set correct active button when page loads
    updateActiveNav();

});
</script>
</body>
</html>
