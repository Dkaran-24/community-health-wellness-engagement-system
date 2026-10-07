<?php
/**
 * admin/offers/campaign.php — publish / send an email campaign for an offer.
 *
 * Shows:
 *   - Offer summary + poster
 *   - Live HTML email preview (iframe) using a sample member name
 *   - Active recipient count
 *   - Duplicate-send warning if a campaign already exists for this offer
 *   - "Send now" (immediate) or "Schedule for" (future datetime)
 *
 * On submit it creates the campaign (createCampaign) which queues one
 * email_logs row per active member. For immediate sends, the page then
 * polls process.php (AJAX) to send in batches with a live progress bar,
 * never blocking the UI. For scheduled sends, the campaign is marked
 * 'Scheduled' and activateDueScheduledCampaigns() promotes it when due.
 */
$PAGE_TITLE = 'Publish Campaign';
$PAGE_KEY   = 'offers';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/offers.php';
require_once __DIR__ . '/../../includes/mailer.php';

$db = db();
$base = base_url();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php?err=' . urlencode('Invalid offer id.')); exit; }

$offer = getOffer($id);
if (!$offer) { header('Location: index.php?err=' . urlencode('Offer not found.')); exit; }

/* Only Active offers may be published */
if ($offer['status'] !== 'Active') {
    header('Location: view.php?id=' . $id . '&err=' . urlencode('Only Active offers can be published. Activate the offer first.'));
    exit;
}

/* Recipient preview (active members with valid emails) */
$recipients = getActiveEmailMembers();
$recipientCount = $recipients ? $recipients->num_rows : 0;

/* Duplicate-send check */
$hasExisting = hasExistingCampaignForOffer($id);

/* Build a sample email preview for a fictional member */
$settings = get_settings(true);
$sampleMember = ['name' => 'John Carter', 'email' => 'john.c@example.com'];
$previewBuilt = buildOfferEmailHtml($offer, $sampleMember, $settings, 0);
$previewHtml = $previewBuilt['html'];

/* Handle create-campaign submission */
$campaignId = 0;
$justCreated = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($recipientCount > 0)) {
    $mode = $_POST['send_mode'] ?? 'now';            /* now | schedule */
    $scheduleAt = '';
    if ($mode === 'schedule') {
        $scheduleAt = trim($_POST['schedule_at'] ?? '');
        if ($scheduleAt === '') {
            header('Location: campaign.php?id=' . $id . '&err=' . urlencode('Please choose a date/time to schedule the campaign.'));
            exit;
        }
        /* Validate it's in the future */
        if (strtotime($scheduleAt) <= time()) {
            header('Location: campaign.php?id=' . $id . '&err=' . urlencode('Schedule time must be in the future.'));
            exit;
        }
    }

    $result = createCampaign($id, [
        'admin_id'    => $_SESSION['admin_id'] ?? 0,
        'schedule_at' => $scheduleAt,
        'subject'     => '🎉 Exclusive Gym Offer Just for You!',
    ]);

    if (!$result['ok']) {
        header('Location: campaign.php?id=' . $id . '&err=' . urlencode($result['error']));
        exit;
    }
    $campaignId = (int)$result['campaign_id'];
    $justCreated = true;
}
?>
<?= flash() ?>

<div class="page-head">
  <div><h2>Publish Campaign — <?= e($offer['title']) ?></h2><p>Send this offer to all active members by email.</p></div>
  <a href="view.php?id=<?= $id ?>" class="btn btn-ghost">&larr; Back to Offer</a>
</div>

<?php if ($recipientCount === 0): ?>
  <div class="alert err">
    <strong>No recipients available.</strong> There are no active members with a valid email address.
    Add active members (with emails) in Member Management before publishing a campaign.
  </div>
  <div class="card"><div class="card-body">
    <a href="<?= $base ?>/admin/members/add.php" class="btn btn-primary">&#43; Add a Member</a>
    <a href="index.php" class="btn btn-ghost">Back to Offers</a>
  </div></div>
<?php else: ?>

  <?php if ($hasExisting && !$justCreated): ?>
    <div class="alert warn">
      <strong>Note:</strong> A campaign has already been sent (or is scheduled) for this offer.
      Publishing again will create a <em>new</em> campaign and email all active members again.
      Proceed only if you intend to re-send.
    </div>
  <?php endif; ?>

  <div class="grid cols-2">
    <!-- Offer summary -->
    <div class="card">
      <div class="card-head"><h3>Offer Summary</h3></div>
      <div class="card-body">
        <?= offerPosterImg($offer['poster_path'], $offer['title'], 'offer-poster-md') ?>
        <h3 style="margin:14px 0 6px;color:var(--navy-800)"><?= e($offer['title']) ?></h3>
        <p style="color:var(--muted);font-size:14px;line-height:1.6;margin-bottom:12px"><?= nl2br(e($offer['description'] ?: 'No description')) ?></p>
        <table class="data" style="border:1px solid var(--line);border-radius:10px;overflow:hidden">
          <tr><td class="muted" style="width:40%">Discount</td><td><b style="color:var(--gold-dk)"><?= e($offer['discount'] ?: '—') ?></b></td></tr>
          <tr><td class="muted">Valid From</td><td><b><?= fmtDate($offer['start_date']) ?></b></td></tr>
          <tr><td class="muted">Valid Until</td><td><b><?= fmtDate($offer['end_date']) ?></b></td></tr>
        </table>
      </div>
    </div>

    <!-- Send controls -->
    <div class="card">
      <div class="card-head"><h3>Send Campaign</h3>
        <span class="badge steel">&#128100; <?= $recipientCount ?> active recipients</span>
      </div>
      <div class="card-body">
        <?php if (!$justCreated): ?>
          <form method="post" id="campaignForm" data-validate>
            <div class="form-field full" style="margin-bottom:14px">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="radio" name="send_mode" value="now" checked onchange="toggleSchedule()" style="width:18px;height:18px">
                <span><strong>Send immediately</strong> — emails go out right now in batches</span>
              </label>
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-top:10px">
                <input type="radio" name="send_mode" value="schedule" onchange="toggleSchedule()" style="width:18px;height:18px">
                <span><strong>Schedule for later</strong> — queue and send at a chosen date/time</span>
              </label>
              <div id="scheduleBox" style="display:none;margin-top:12px;padding-left:30px">
                <input type="datetime-local" name="schedule_at" id="scheduleAt" min="<?= e(date('Y-m-d\TH:i', strtotime('+10 minutes'))) ?>">
                <span class="hint" style="display:block;margin-top:4px">The campaign will be sent automatically when this time is reached (on the next dashboard/campaign page visit).</span>
              </div>
            </div>

            <div class="form-actions">
              <button type="submit" class="btn btn-primary" id="publishBtn">📣 Publish &amp; Queue Campaign</button>
              <a href="view.php?id=<?= $id ?>" class="btn btn-ghost">Cancel</a>
            </div>
          </form>
        <?php else: ?>
          <div class="alert ok"><strong>&#10003; Campaign created!</strong> <?= $recipientCount ?> recipient(s) queued. Sending in batches below — keep this page open.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="spacer"></div>

  <!-- Email preview -->
  <div class="card">
    <div class="card-head"><h3>Email Preview</h3>
      <a href="preview.php?id=<?= $id ?>" target="_blank" class="btn btn-ghost btn-sm">Open in new tab</a>
    </div>
    <div class="card-body" style="padding:0">
      <iframe src="preview.php?id=<?= $id ?>" class="email-preview-iframe" title="Email preview"></iframe>
    </div>
  </div>

  <?php if ($justCreated): ?>
  <!-- Live send progress (polled via AJAX) -->
  <div class="spacer"></div>
  <div class="card" id="progressCard">
    <div class="card-head"><h3>Sending Progress</h3><span id="progressStatus" class="badge steel">Processing…</span></div>
    <div class="card-body">
      <div class="campaign-progress">
        <div class="campaign-progress-bar" id="progressBar" style="width:0%"></div>
      </div>
      <div style="display:flex;justify-content:space-between;margin-top:10px;font-size:14px">
        <span><b id="pctText">0%</b> complete</span>
        <span>Sent: <b id="sentText" style="color:var(--ok)">0</b> &middot; Failed: <b id="failedText" style="color:var(--err)">0</b> &middot; Total: <b id="totalText"><?= $recipientCount ?></b></span>
      </div>
      <div id="doneBox" style="display:none;margin-top:18px">
        <div class="alert ok" id="doneAlert"></div>
        <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap">
          <a href="view.php?id=<?= $id ?>" class="btn btn-navy">View Offer &amp; Logs</a>
          <a href="dashboard.php" class="btn btn-ghost">Campaign Dashboard</a>
          <a href="index.php" class="btn btn-ghost">All Offers</a>
        </div>
      </div>
    </div>
  </div>

  <script>
    (function () {
      var campaignId = <?= (int)$campaignId ?>;
      var batchUrl = 'process.php';
      var done = false;

      function poll() {
        if (done) return;
        fetch(batchUrl + '?campaign=' + campaignId + '&batch=5', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            if (!d || d.error) { setTimeout(poll, 1500); return; }
            var pct = d.percent || 0;
            document.getElementById('progressBar').style.width = pct + '%';
            document.getElementById('pctText').textContent = pct + '%';
            document.getElementById('sentText').textContent = d.sent_total != null ? d.sent_total : d.processed;
            document.getElementById('failedText').textContent = d.failed_total != null ? d.failed_total : 0;
            document.getElementById('totalText').textContent = d.total;
            var st = document.getElementById('progressStatus');
            st.textContent = d.status;
            st.className = 'badge ' + (d.status === 'Completed' ? 'green' : d.status === 'Failed' ? 'red' : 'steel');

            if (d.done) {
              done = true;
              var box = document.getElementById('doneBox');
              var alert = document.getElementById('doneAlert');
              var sent = d.sent_total || 0, failed = d.failed_total || 0, total = d.total || 0;
              if (failed === 0) {
                alert.className = 'alert ok';
                alert.innerHTML = '✅ Campaign complete! <strong>' + sent + '</strong> of ' + total +
                  ' email(s) sent successfully to active members. No failures.';
              } else {
                alert.className = 'alert warn';
                alert.innerHTML = '⚠️ Campaign finished. <strong>' + sent + '</strong> sent, <strong>' + failed +
                  '</strong> failed. You can re-send the failed emails from the offer page or campaign dashboard.';
              }
              box.style.display = 'block';
              document.getElementById('progressBar').style.width = '100%';
              document.getElementById('pctText').textContent = '100%';
            } else {
              setTimeout(poll, 1200);
            }
          })
          .catch(function () { setTimeout(poll, 2000); });
      }
      poll();
    })();
  </script>
  <?php endif; ?>

  <div class="spacer"></div>
  <div class="card">
    <div class="card-head"><h3>Recipients (<?= $recipientCount ?> active members)</h3></div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap">
        <table class="data" id="recipTable">
          <thead><tr><th>Name</th><th>Email</th></tr></thead>
          <tbody>
          <?php if ($recipients): while ($m = $recipients->fetch_assoc()): ?>
            <tr><td><?= e($m['name']) ?></td><td><?= e($m['email']) ?></td></tr>
          <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>

<script>
  function toggleSchedule() {
    var sched = document.querySelector('input[name="send_mode"][value="schedule"]').checked;
    document.getElementById('scheduleBox').style.display = sched ? 'block' : 'none';
    var inp = document.getElementById('scheduleAt');
    if (inp) inp.required = sched;
  }
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
