<?php
/**
 * community/_footer.php — shared footer for community pages.
 */
$base = community_base_url();
?>
</main>
<footer class="c-footer">
  <div>
    <b>NEW LIFE FITNESS</b> — Community Health, Fitness &amp; Wellness Engagement Platform
    <div class="c-muted">Stronger Together, Healthier Community!</div>
  </div>
  <div class="c-links">
    <a href="<?= $base ?>/index.php">Home</a> &middot;
    <a href="<?= $base ?>/community/events.php">Events</a> &middot;
    <a href="<?= $base ?>/community/challenges.php">Challenges</a> &middot;
    <a href="<?= $base ?>/community/resources.php">Resources</a> &middot;
    <a href="<?= $base ?>/login.php">Admin</a> &middot;
  </div>
  <div class="c-muted">&copy; <?= date('Y') ?> New Life Fitness — Community Engagement Project (CEP)</div>
</footer>
</body>
</html>
