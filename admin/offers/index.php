<?php
/**
 * admin/offers/index.php — Offer Management landing page.
 *
 * Displays offers as responsive cards with poster previews, status badges,
 * live/expired indicators, and quick actions (View, Edit, Delete, toggle
 * Active/Inactive, Publish campaign). Includes client-side search, a status
 * filter, and server-side pagination.
 */
$PAGE_TITLE = 'Offers';
$PAGE_KEY   = 'offers';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/offers.php';

$db = db();
$base = base_url();

/* ---- Filters + pagination (server-side) ---- */
$statusFilter = $_GET['status'] ?? '';
$validStatuses = ['Active', 'Inactive'];
if (!in_array($statusFilter, $validStatuses, true)) $statusFilter = '';

$perPage = 9;                       // 9 cards = 3x3 grid
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$whereSql = $statusFilter ? "WHERE status = '" . $db->real_escape_string($statusFilter) . "'" : '';

$totalOffers = (int)$db->query("SELECT COUNT(*) c FROM offers $whereSql")->fetch_assoc()['c'];
$totalPages  = $totalOffers > 0 ? (int)ceil($totalOffers / $perPage) : 1;
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

$offers = $db->query("SELECT * FROM offers $whereSql ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");

/* Campaign counts per visible offer (one query, lookup map) */
$campMap = [];
$ids = [];
while ($o = $offers->fetch_assoc()) { $ids[] = (int)$o['id']; }
$offers->data_seek(0);
if ($ids) {
    $idList = implode(',', $ids);
    $cr = $db->query("SELECT offer_id, COUNT(*) c FROM email_campaigns WHERE offer_id IN ($idList) GROUP BY offer_id");
    if ($cr) { while ($r = $cr->fetch_assoc()) $campMap[(int)$r['offer_id']] = (int)$r['c']; }
}
?>
<?= flash() ?>

<div class="page-head">
  <div>
    <h2>Offer Management</h2>
    <p>Create promotional offers and publish email campaigns to all active members.</p>
  </div>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <a href="dashboard.php" class="btn btn-ghost">&#128202; Campaign Dashboard</a>
    <a href="add.php" class="btn btn-primary">&#43; Add Offer</a>
  </div>
</div>

<!-- Toolbar: search + status filter -->
<div class="toolbar">
  <div class="search">
    <span class="ico">&#128269;</span>
    <input type="text" id="offerSearch" placeholder="Search offers by title, description, discount…">
  </div>
  <form method="get" style="display:flex;gap:8px;align-items:center">
    <select name="status" onchange="this.form.submit()" style="padding:9px 12px;border:1px solid var(--line);border-radius:9px;font-size:14px;background:#fff">
      <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>All statuses</option>
      <option value="Active" <?= $statusFilter === 'Active' ? 'selected' : '' ?>>Active</option>
      <option value="Inactive" <?= $statusFilter === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
    </select>
  </form>
</div>

<?php if ($offers->num_rows === 0): ?>
  <div class="card"><div class="card-body empty">
    <span class="ico">🎉</span>
    <h3 style="margin:6px 0;color:var(--navy-800)">No offers yet</h3>
    <p>Click <strong>&ldquo;Add Offer&rdquo;</strong> to create your first promotional offer and publish it to your members.</p>
  </div></div>
<?php else: ?>
  <div class="offer-grid" id="offerGrid">
    <?php while ($o = $offers->fetch_assoc()):
      $live = offerIsLive($o);
      $campCount = $campMap[(int)$o['id']] ?? 0;
    ?>
      <div class="offer-card" data-search="<?= e(strtolower($o['title'] . ' ' . $o['description'] . ' ' . $o['discount'])) ?>">
        <div class="offer-card-poster">
          <?= offerPosterImg($o['poster_path'], $o['title'], 'offer-card-img') ?>
          <div class="offer-card-badges">
            <?= offerStatusBadge($o['status']) ?>
            <?php if ($live): ?><span class="badge green">● Live now</span><?php endif; ?>
          </div>
        </div>
        <div class="offer-card-body">
          <h3><?= e($o['title']) ?></h3>
          <p class="offer-desc"><?= e(mb_strimwidth($o['description'] ?? 'No description', 0, 110, '…')) ?></p>
          <div class="offer-discount"><?= e($o['discount'] ?: '—') ?></div>
          <div class="offer-dates">
            <span><small>From</small><b><?= fmtDate($o['start_date']) ?></b></span>
            <span><small>Until</small><b><?= fmtDate($o['end_date']) ?></b></span>
          </div>
          <div class="offer-meta">
            <span title="Campaigns sent">&#128231; <?= $campCount ?></span>
            <span title="Created"><?= fmtDate($o['created_at']) ?></span>
          </div>
          <div class="offer-actions">
            <a href="view.php?id=<?= $o['id'] ?>" class="btn btn-ghost btn-sm">View</a>
            <a href="campaign.php?id=<?= $o['id'] ?>" class="btn btn-primary btn-sm" title="Publish / send email campaign">📣 Publish</a>
            <a href="edit.php?id=<?= $o['id'] ?>" class="btn btn-navy btn-sm">Edit</a>
            <a href="toggle.php?id=<?= $o['id'] ?>" class="btn btn-warning btn-sm" data-confirm="Toggle status of &ldquo;<?= e($o['title']) ?>&rdquo;?">
              <?= $o['status'] === 'Active' ? 'Deactivate' : 'Activate' ?>
            </a>
            <a href="delete.php?id=<?= $o['id'] ?>" class="btn btn-danger btn-sm" data-confirm="Delete offer &ldquo;<?= e($o['title']) ?>&rdquo;? This also removes its campaigns and email logs. This cannot be undone.">Delete</a>
          </div>
        </div>
      </div>
    <?php endwhile; ?>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1):
    $q = $statusFilter ? '&status=' . urlencode($statusFilter) : '';
  ?>
    <div class="pager">
      <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 . $q ?>">&laquo; Prev</a>
      <?php else: ?>
        <span class="muted">&laquo; Prev</span>
      <?php endif; ?>
      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <?php if ($p == $page): ?>
          <span class="cur"><?= $p ?></span>
        <?php else: ?>
          <a href="?page=<?= $p . $q ?>"><?= $p ?></a>
        <?php endif; ?>
      <?php endfor; ?>
      <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page + 1 . $q ?>">Next &raquo;</a>
      <?php else: ?>
        <span class="muted">Next &raquo;</span>
      <?php endif; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<script>
  /* Client-side card search across the visible page */
  document.getElementById('offerSearch').addEventListener('input', function () {
    var q = this.value.trim().toLowerCase();
    document.querySelectorAll('#offerGrid .offer-card').forEach(function (card) {
      var hay = card.getAttribute('data-search') || '';
      card.style.display = hay.indexOf(q) !== -1 ? '' : 'none';
    });
  });
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
