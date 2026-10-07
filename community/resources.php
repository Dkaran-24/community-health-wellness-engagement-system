<?php
/**
 * community/resources.php — Wellness Resources library (CEP).
 *
 * Educational content on fitness, nutrition and healthy lifestyle,
 * curated by organisers. Category filter + view counter (increments
 * when the full article is opened).
 */
$PAGE_TITLE = 'Wellness Resources';
$PAGE_KEY   = 'resources';
require_once __DIR__ . '/../includes/community_auth.php';
require_community_login();
require_once __DIR__ . '/_header.php';

$db = db();

$cats = ['Exercise Guide','Nutrition','Healthy Lifestyle','Fitness Education','Exercise Safety','Wellness Awareness','Beginner Fitness','Other'];
$cat = (isset($_GET['cat']) && in_array($_GET['cat'], $cats, true)) ? $_GET['cat'] : '';

/* view one article in full */
$open = (int)($_GET['read'] ?? 0);
$article = null;
if ($open) {
    $stmt = $db->prepare("SELECT * FROM wellness_resources WHERE id = ? AND status = 'Published' LIMIT 1");
    $stmt->bind_param('i', $open); $stmt->execute();
    $article = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if ($article) {
        $db->query("UPDATE wellness_resources SET views = views + 1 WHERE id = $open");
        $article['views'] = (int)$article['views'] + 1;
    }
}

/* listing */
$where = "WHERE status = 'Published'";
if ($cat) $where .= " AND category = '" . $db->real_escape_string($cat) . "'";
$resources = $db->query("SELECT * FROM wellness_resources $where ORDER BY created_at DESC");
$resCount = $resources ? $resources->num_rows : 0;
?>
<div class="c-hero small">
  <span class="c-tag">LEARN &amp; GROW</span>
  <h1>Wellness Resources</h1>
  <p>Practical, easy-to-follow guides on fitness, nutrition and healthy living — curated by
     the New Life Fitness community team. Free for every resident.</p>
</div>

<?php if ($article): ?>
<!-- ================= full article view ================= -->
<div class="c-card" style="margin-bottom:18px;">
  <a class="c-more" style="margin:0 0 8px;display:inline-block;" href="resources.php">&larr; Back to all resources</a>
  <h3 style="margin:0 0 6px;"><?= e($article['title']) ?></h3>
  <span class="c-badge blue"><?= e($article['category']) ?></span>
  <span class="c-badge gray">&#128065; <?= (int)$article['views'] ?> views</span>
  <span class="c-muted" style="font-size:12px;margin-left:8px;">published <?= date('M j, Y', strtotime($article['created_at'])) ?></span>
  <p style="font-size:14px;font-weight:600;color:var(--navy-800);margin:12px 0;"><?= e($article['summary']) ?></p>
  <div style="font-size:14px;line-height:1.75;color:var(--ink);"><?= nl2br(e($article['content'] ?? '')) ?></div>
  <hr style="border:none;border-top:1px dashed var(--line);margin:18px 0;">
  <a class="c-btn gold sm" href="resources.php">More resources &rarr;</a>
  <a class="c-btn ghost sm" href="events.php">Join a live event &rarr;</a>
</div>
<?php endif; ?>

<!-- ================= filters ================= -->
<div class="c-card" style="margin-bottom:18px;">
  <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
    <b style="font-size:13px;color:var(--navy-800);">Category:</b>
    <a class="c-badge <?= !$cat ? 'red' : 'gray' ?>" style="padding:6px 12px;" href="resources.php">All</a>
    <?php foreach ($cats as $c): ?>
      <a class="c-badge <?= $cat === $c ? 'red' : 'gray' ?>" style="padding:6px 12px;" href="resources.php?cat=<?= urlencode($c) ?>"><?= e($c) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<!-- ================= listing ================= -->
<?php if ($resCount): ?>
<div class="c-item-grid">
  <?php while ($r = $resources->fetch_assoc()): ?>
    <div class="c-item">
      <h4><?= e($r['title']) ?></h4>
      <span class="c-badge blue"><?= e($r['category']) ?></span>
      <div class="c-desc" style="margin:6px 0;"><?= e($r['summary']) ?></div>
      <div class="c-meta">
        <div><b>&#128065;</b> <?= (int)$r['views'] ?> views &middot; <?= date('M j, Y', strtotime($r['created_at'])) ?></div>
      </div>
      <div class="c-actions">
        <a class="c-btn sm" href="resources.php?cat=<?= $cat ? urlencode($cat) : '' ?>&amp;read=<?= (int)$r['id'] ?>#res-<?= (int)$r['id'] ?>">Read more &rarr;</a>
      </div>
    </div>
  <?php endwhile; ?>
</div>
<?php else: ?>
<div class="c-card"><p class="c-muted">No published resources in this category yet.</p></div>
<?php endif; ?>

<?php require_once __DIR__ . '/_footer.php'; ?>
