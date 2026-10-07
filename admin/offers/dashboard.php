<?php
/**
 * admin/offers/dashboard.php — Email Campaign analytics dashboard.
 *
 * Shows aggregate campaign metrics (total recipients, sent, failed, pending,
 * delivery %, open rate) and a per-campaign history table with search,
 * status filter, and pagination. Supports a "Resend all failed" button and,
 * when ?campaign=N is present, focuses on a single campaign with live
 * progress (for the resend flow).
 */
$PAGE_TITLE = 'Campaign Dashboard';
$PAGE_KEY   = 'offers';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/offers.php';

$db = db();
$base = base_url();

/* Promote any due scheduled campaigns on dashboard visit */
activateDueScheduledCampaigns();

/* Aggregate stats across all campaigns */
$stats = getCampaignStats();

/* If a specific campaign is focused (e.g. after resend), load it */
$focusCampaignId = (int)($_GET['campaign'] ?? 0);
$focusCampaign = null;
if ($focusCampaignId) {
    $focusCampaign = getCampaignRowStats($focusCampaignId);
}

/* ---- Campaign history: filters + pagination ---- */
$statusFilter = $_GET['status'] ?? '';
$validStatuses = ['Pending', 'Processing', 'Completed', 'Failed', 'Scheduled', 'Cancelled'];
if (!in_array($statusFilter, $validStatuses, true)) $statusFilter = '';

$whereSql = $statusFilter ? "WHERE c.status = '" . $db->real_escape_string($statusFilter) . "'" : '';
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$totalCamp = (int)$db->query("SELECT COUNT(*) c FROM email_campaigns c $whereSql")->fetch_assoc()['c'];
$totalPages = $totalCamp > 0 ? (int)ceil($totalCamp / $perPage) : 1;
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

$campaigns = $db->query(
    "SELECT c.*, o.title AS offer_title, o.poster_path
     FROM email_campaigns c
     JOIN offers o ON o.id = c.offer_id
     $whereSql
     ORDER BY c.created_at DESC
     LIMIT $perPage OFFSET $offset"
);
?>
<?= flash() ?>

<div class="page-head">
  <div><h2>Email Campaign Dashboard</h2><p>Track delivery performance, open rates, and resend failed emails.</p></div>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <a href="index.php" class="btn btn-ghost">&larr; Offers</a>
    <?php if ($stats['failed'] > 0): ?>
      <a href="resend.php?offer=all" class="btn btn-warning" data-confirm="Re-send all <?= $stats['failed'] ?> failed emails across all campaigns?">Resend All Failed (<?= $stats['failed'] ?>)</a>
    <?php endif; ?>
  </div>
</div>

<!-- KPI cards -->
<div class="grid cols-4">
  <div class="stat">
    <div class="stat-ico navy">&#128100;</div>
    <div><div class="stat-val"><?= $stats['total_recipients'] ?></div><div class="stat-lbl">Total Recipients</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico green">&#10003;</div>
    <div><div class="stat-val"><?= $stats['sent'] ?></div><div class="stat-lbl">Emails Sent</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico red">&#9888;</div>
    <div><div class="stat-val"><?= $stats['failed'] ?></div><div class="stat-lbl">Emails Failed</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico gold">&#9203;</div>
    <div><div class="stat-val"><?= $stats['pending'] ?></div><div class="stat-lbl">Pending Emails</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico steel">&#128202;</div>
    <div><div class="stat-val"><?= $stats['delivery_pct'] ?>%</div><div class="stat-lbl">Delivery Rate</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico gold">&#128065;</div>
    <div><div class="stat-val"><?= $stats['open_rate'] ?>%</div><div class="stat-lbl">Open Rate</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico navy">&#128231;</div>
    <div><div class="stat-val"><?= $stats['campaigns_total'] ?></div><div class="stat-lbl">Total Campaigns</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico green">&#10003;</div>
    <div><div class="stat-val"><?= $stats['campaigns_completed'] ?></div><div class="stat-lbl">Completed</div></div>
  </div>
</div>

<div class="spacer"></div>

<?php if ($focusCampaign): ?>
<!-- Focused campaign live progress (after resend) -->
<div class="card" id="focusCard">
  <div class="card-head"><h3>Campaign #<?= (int)$focusCampaign['id'] ?> — <?= e($focusCampaign['offer_title'] ?? '') ?></h3>
    <?= campaignStatusBadge($focusCampaign['status']) ?>
  </div>
  <div class="card-body">
    <div class="campaign-progress"><div class="campaign-progress-bar" id="focusBar" style="width:0%"></div></div>
    <div style="display:flex;justify-content:space-between;margin-top:10px;font-size:14px">
      <span><b id="focusPct">0%</b></span>
      <span>Sent: <b id="focusSent" style="color:var(--ok)">0</b> &middot; Failed: <b id="focusFailed" style="color:var(--err)">0</b> &middot; Total: <b><?= (int)$focusCampaign['total_recipients'] ?></b></span>
    </div>
    <div id="focusDone" style="display:none;margin-top:14px"><div class="alert ok" id="focusDoneAlert"></div></div>
  </div>
</div>
<div class="spacer"></div>
<script>
(function(){
  var cid = <?= (int)$focusCampaign['id'] ?>;
  var done = false;
  function poll(){
    if (done) return;
    fetch('process.php?campaign=' + cid + '&batch=5&force=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (!d || d.error) { setTimeout(poll, 1500); return; }
        var pct = d.percent || 0;
        document.getElementById('focusBar').style.width = pct + '%';
        document.getElementById('focusPct').textContent = pct + '%';
        document.getElementById('focusSent').textContent = d.sent_total != null ? d.sent_total : 0;
        document.getElementById('focusFailed').textContent = d.failed_total != null ? d.failed_total : 0;
        if (d.done) {
          done = true;
          document.getElementById('focusBar').style.width = '100%';
          document.getElementById('focusPct').textContent = '100%';
          var a = document.getElementById('focusDoneAlert');
          var sent = d.sent_total||0, failed = d.failed_total||0;
          a.className = failed === 0 ? 'alert ok' : 'alert warn';
          a.innerHTML = (failed===0 ? '✅' : '⚠️') + ' Done — ' + sent + ' sent, ' + failed + ' failed.';
          document.getElementById('focusDone').style.display = 'block';
          if (d.status === 'Scheduled') { a.innerHTML = '⏱️ This campaign is scheduled and will send automatically when due.'; a.className = 'alert info'; }
        } else { setTimeout(poll, 1200); }
      }).catch(function(){ setTimeout(poll, 2000); });
  }
  poll();
})();
</script>
<?php endif; ?>

<!-- Campaign history table -->
<div class="card">
  <div class="card-head"><h3>Campaign History</h3></div>
  <div class="card-body" style="padding:0">
    <div class="toolbar" style="margin:14px 16px">
      <div class="search">
        <span class="ico">&#128269;</span>
        <input type="text" id="campSearch" placeholder="Search by offer title or campaign id…">
      </div>
      <form method="get" style="display:flex;gap:8px;align-items:center" id="campFilterForm">
        <select name="status" onchange="this.form.submit()" style="padding:9px 12px;border:1px solid var(--line);border-radius:9px;font-size:14px;background:#fff">
          <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>All statuses</option>
          <?php foreach ($validStatuses as $s): ?>
            <option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e($s) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
    <div class="table-wrap">
      <table class="data" id="campTable" data-sortable>
        <thead><tr>
          <th>ID</th><th>Offer</th><th>Status</th><th>Recipients</th><th>Sent</th><th>Failed</th><th>Pending</th><th>Delivery</th><th>Opens</th><th>Open Rate</th><th>Created</th><th class="no-sort">Actions</th>
        </tr></thead>
        <tbody>
        <?php if ($campaigns && $campaigns->num_rows): while ($c = $campaigns->fetch_assoc()):
          $cid = (int)$c['id'];
          $opens = (int)db()->query("SELECT COUNT(*) c2 FROM email_logs WHERE campaign_id = $cid AND opened = 1 AND status='Sent'")->fetch_assoc()['c2'];
          $sentN = (int)$c['sent_count'];
          $del = (int)$c['total_recipients'] ? round($sentN / (int)$c['total_recipients'] * 100, 1) : 0;
          $orate = $sentN ? round($opens / $sentN * 100, 1) : 0;
        ?>
          <tr data-search="<?= e(strtolower($c['offer_title'] . ' #' . $c['id'])) ?>">
            <td>#<?= $cid ?></td>
            <td><?= e($c['offer_title']) ?></td>
            <td><?= campaignStatusBadge($c['status']) ?></td>
            <td><?= (int)$c['total_recipients'] ?></td>
            <td><span class="badge green"><?= $sentN ?></span></td>
            <td><span class="badge red"><?= (int)$c['failed_count'] ?></span></td>
            <td><span class="badge gold"><?= (int)$c['pending_count'] ?></span></td>
            <td><?= $del ?>%</td>
            <td><?= $opens ?></td>
            <td><?= $orate ?>%</td>
            <td><?= fmtDate($c['created_at']) ?></td>
            <td class="row-actions">
              <?php if ((int)$c['failed_count'] > 0): ?>
                <a href="resend.php?campaign=<?= $cid ?>" class="btn btn-warning btn-sm" data-confirm="Re-send <?= (int)$c['failed_count'] ?> failed emails?">Resend</a>
              <?php endif; ?>
              <a href="view.php?id=<?= (int)$c['offer_id'] ?>" class="btn btn-ghost btn-sm">Offer</a>
            </td>
          </tr>
        <?php endwhile; else: ?>
          <tr><td colspan="12" class="empty"><span class="ico">&#128231;</span>No campaigns yet. Publish an offer to start an email campaign.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <!-- Pagination -->
    <?php if ($totalPages > 1):
      $q = $statusFilter ? '&status=' . urlencode($statusFilter) : '';
      $fq = $focusCampaignId ? '&campaign=' . $focusCampaignId : '';
    ?>
      <div class="pager" style="padding:0 16px 16px">
        <?php if ($page > 1): ?><a href="?page=<?= $page-1 . $q . $fq ?>">&laquo; Prev</a><?php else: ?><span class="muted">&laquo; Prev</span><?php endif; ?>
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <?php if ($p == $page): ?><span class="cur"><?= $p ?></span>
          <?php else: ?><a href="?page=<?= $p . $q . $fq ?>"><?= $p ?></a><?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?><a href="?page=<?= $page+1 . $q . $fq ?>">Next &raquo;</a><?php else: ?><span class="muted">Next &raquo;</span><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
  document.getElementById('campSearch').addEventListener('input', function () {
    var q = this.value.trim().toLowerCase();
    document.querySelectorAll('#campTable tbody tr').forEach(function (tr) {
      var hay = tr.getAttribute('data-search') || tr.textContent.toLowerCase();
      tr.style.display = hay.indexOf(q) !== -1 ? '' : 'none';
    });
  });
  if (typeof initTableSort === 'function') initTableSort('campTable');
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
