<?php
/**
 * admin/offers/view.php — offer detail + campaign history + email log.
 *
 * Shows the full offer (poster, description, discount, validity, status),
 * a table of all campaigns sent for this offer, and a searchable email log
 * (per-recipient delivery status) with a resend-failed action.
 */
$PAGE_TITLE = 'View Offer';
$PAGE_KEY   = 'offers';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/offers.php';

$db = db();
$base = base_url();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php?err=' . urlencode('Invalid offer id.')); exit; }

$offer = getOffer($id);
if (!$offer) { header('Location: index.php?err=' . urlencode('Offer not found.')); exit; }

$live = offerIsLive($offer);

/* Campaigns for this offer */
$campaigns = $db->query(
    "SELECT * FROM email_campaigns WHERE offer_id = $id ORDER BY created_at DESC"
);
$campaignCount = $campaigns ? $campaigns->num_rows : 0;

/* Aggregate log stats for this offer */
$logStats = $db->query(
    "SELECT
        COUNT(*) AS total,
        SUM(status='Sent') AS sent,
        SUM(status='Failed') AS failed,
        SUM(status='Pending') AS pending,
        SUM(opened=1 AND status='Sent') AS opens
     FROM email_logs WHERE offer_id = $id"
)->fetch_assoc();
$lsTotal  = (int)$logStats['total'];
$lsSent   = (int)$logStats['sent'];
$lsFailed = (int)$logStats['failed'];
$lsPending= (int)$logStats['pending'];
$lsOpens  = (int)$logStats['opens'];
?>
<?= flash() ?>
<div class="page-head">
  <div><h2><?= e($offer['title']) ?></h2><p>Offer details, campaign history, and email delivery log.</p></div>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <a href="index.php" class="btn btn-ghost">&larr; All Offers</a>
    <a href="edit.php?id=<?= $id ?>" class="btn btn-navy">Edit</a>
    <a href="campaign.php?id=<?= $id ?>" class="btn btn-primary">📣 Publish Campaign</a>
  </div>
</div>

<!-- Offer detail card -->
<div class="grid cols-2">
  <div class="card">
    <div class="offer-detail-poster">
      <?= offerPosterImg($offer['poster_path'], $offer['title'], 'offer-poster-lg') ?>
    </div>
  </div>
  <div class="card">
    <div class="card-body">
      <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap">
        <?= offerStatusBadge($offer['status']) ?>
        <?php if ($live): ?><span class="badge green">● Live now</span>
        <?php elseif ($offer['end_date'] < date('Y-m-d')): ?><span class="badge red">Expired</span>
        <?php else: ?><span class="badge gold">Upcoming</span><?php endif; ?>
      </div>

      <table class="data" style="border:1px solid var(--line);border-radius:10px;overflow:hidden">
        <tr><td class="muted" style="width:35%">Discount</td><td><b style="color:var(--gold-dk)"><?= e($offer['discount'] ?: '—') ?></b></td></tr>
        <tr><td class="muted">Valid From</td><td><b><?= fmtDate($offer['start_date']) ?></b></td></tr>
        <tr><td class="muted">Valid Until</td><td><b><?= fmtDate($offer['end_date']) ?></b></td></tr>
        <tr><td class="muted">Created</td><td><?= fmtDate($offer['created_at']) ?></td></tr>
        <tr><td class="muted">Last Updated</td><td><?= fmtDate($offer['updated_at']) ?></td></tr>
      </table>

      <h3 style="margin:18px 0 6px;color:var(--navy-800);font-size:15px">Description</h3>
      <p style="color:var(--ink);line-height:1.7;font-size:14px;white-space:pre-wrap"><?= e($offer['description'] ?: 'No description provided.') ?></p>
    </div>
  </div>
</div>

<div class="spacer"></div>

<!-- Campaign stats summary -->
<div class="grid cols-4">
  <div class="stat"><div class="stat-ico navy">&#128231;</div><div><div class="stat-val"><?= $campaignCount ?></div><div class="stat-lbl">Campaigns</div></div></div>
  <div class="stat"><div class="stat-ico green">&#10003;</div><div><div class="stat-val"><?= $lsSent ?></div><div class="stat-lbl">Emails Sent</div></div></div>
  <div class="stat"><div class="stat-ico red">&#9888;</div><div><div class="stat-val"><?= $lsFailed ?></div><div class="stat-lbl">Failed</div></div></div>
  <div class="stat"><div class="stat-ico gold">&#128065;</div><div><div class="stat-val"><?= $lsOpens ?></div><div class="stat-lbl">Opens Tracked</div></div></div>
</div>

<div class="spacer"></div>

<!-- Campaign history -->
<div class="card">
  <div class="card-head"><h3>Campaign History</h3>
    <?php if ($lsFailed > 0): ?>
      <a href="resend.php?offer=<?= $id ?>" class="btn btn-warning btn-sm" data-confirm="Re-send all <?= $lsFailed ?> failed emails for this offer?">Resend Failed (<?= $lsFailed ?>)</a>
    <?php endif; ?>
  </div>
  <div class="card-body" style="padding:0">
    <div class="table-wrap">
      <table class="data" id="campaignTable" data-sortable>
        <thead><tr>
          <th>ID</th><th>Status</th><th>Recipients</th><th>Sent</th><th>Failed</th><th>Pending</th><th>Delivery</th><th>Scheduled</th><th>Created</th><th class="no-sort">Actions</th>
        </tr></thead>
        <tbody>
        <?php if ($campaigns && $campaignCount): while ($c = $campaigns->fetch_assoc()):
          $del = (int)$c['total_recipients'] ? round((int)$c['sent_count'] / (int)$c['total_recipients'] * 100, 1) : 0;
        ?>
          <tr>
            <td>#<?= $c['id'] ?></td>
            <td><?= campaignStatusBadge($c['status']) ?></td>
            <td><?= (int)$c['total_recipients'] ?></td>
            <td><span class="badge green"><?= (int)$c['sent_count'] ?></span></td>
            <td><span class="badge red"><?= (int)$c['failed_count'] ?></span></td>
            <td><span class="badge gold"><?= (int)$c['pending_count'] ?></span></td>
            <td><?= $del ?>%</td>
            <td><?= $c['schedule_at'] ? e(date('M j, Y g:i A', strtotime($c['schedule_at']))) : '—' ?></td>
            <td><?= fmtDate($c['created_at']) ?></td>
            <td class="row-actions">
              <?php if ((int)$c['failed_count'] > 0): ?>
                <a href="resend.php?campaign=<?= (int)$c['id'] ?>" class="btn btn-warning btn-sm" data-confirm="Re-send <?= (int)$c['failed_count'] ?> failed emails?">Resend</a>
              <?php endif; ?>
              <a href="dashboard.php?campaign=<?= (int)$c['id'] ?>" class="btn btn-ghost btn-sm">Details</a>
            </td>
          </tr>
        <?php endwhile; else: ?>
          <tr><td colspan="10" class="empty"><span class="ico">&#128231;</span>No campaigns sent for this offer yet. Click &ldquo;Publish Campaign&rdquo; to send one.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="spacer"></div>

<!-- Email delivery log (per recipient) -->
<div class="card">
  <div class="card-head"><h3>Email Delivery Log</h3></div>
  <div class="card-body" style="padding:0">
    <div class="toolbar" style="margin:14px 16px">
      <div class="search">
        <span class="ico">&#128269;</span>
        <input type="text" id="logSearch" placeholder="Search by member name or email…">
      </div>
    </div>
    <div class="table-wrap">
      <table class="data" id="logTable">
        <thead><tr><th>Member</th><th>Email</th><th>Status</th><th>Opened</th><th>Sent At</th><th>Error</th></tr></thead>
        <tbody>
        <?php
        $logs = $db->query("SELECT * FROM email_logs WHERE offer_id = $id ORDER BY status, sent_at DESC, id DESC LIMIT 200");
        if ($logs && $logs->num_rows):
          while ($l = $logs->fetch_assoc()):
            $stBadge = $l['status'] === 'Sent' ? '<span class="badge green">Sent</span>'
                     : ($l['status'] === 'Failed' ? '<span class="badge red">Failed</span>'
                     : '<span class="badge gold">Pending</span>');
        ?>
          <tr>
            <td><?= e($l['member_name'] ?: '—') ?></td>
            <td><?= e($l['email']) ?></td>
            <td><?= $stBadge ?></td>
            <td><?= $l['opened'] ? '<span class="badge green">Yes</span>' : '<span class="badge gray">—</span>' ?></td>
            <td><?= $l['sent_at'] ? e(date('M j, Y g:i A', strtotime($l['sent_at']))) : '—' ?></td>
            <td style="max-width:280px"><?= $l['error_message'] ? '<span class="muted" title="' . e($l['error_message']) . '">' . e(mb_strimwidth($l['error_message'], 0, 60, '…')) . '</span>' : '—' ?></td>
          </tr>
        <?php endwhile; else: ?>
          <tr><td colspan="6" class="empty"><span class="ico">&#9993;</span>No email log entries yet.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
  if (typeof initTableSearch === 'function') initTableSearch('logSearch', 'logTable');
  if (typeof initTableSort  === 'function') initTableSort('campaignTable');
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
