<?php
/**
 * admin/offers/resend.php — re-send FAILED emails.
 *
 * Accepts either:
 *   ?campaign=<id>  -> resend failed emails for a single campaign
 *   ?offer=<id>     -> resend failed emails across ALL campaigns of an offer
 *
 * Resets matching email_logs rows from 'Failed' to 'Pending' and marks the
 * campaign(s) back to 'Processing' so the queue worker picks them up.
 * Redirects to a sensible page with a flash message.
 */
$PAGE_KEY = 'offers';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/offers.php';

$db = db();
$campId = (int)($_GET['campaign'] ?? 0);
$offerId = (int)($_GET['offer'] ?? 0);

if (!$campId && !$offerId) {
    header('Location: index.php?err=' . urlencode('Invalid resend request.'));
    exit;
}

/* Offer-wide resend: find all campaigns for the offer with failures, then
   re-queue each. */
if ($offerId && !$campId) {
    $offer = getOffer($offerId);
    if (!$offer) { header('Location: index.php?err=' . urlencode('Offer not found.')); exit; }

    $res = $db->query("SELECT id FROM email_campaigns WHERE offer_id = $offerId AND failed_count > 0");
    $totalRequeued = 0;
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rr = resendFailedCampaign((int)$r['id']);
            if (!empty($rr['ok'])) $totalRequeued += (int)$rr['count'];
        }
    }
    if ($totalRequeued > 0) {
        header('Location: view.php?id=' . $offerId . '&ok=' . urlencode($totalRequeued . ' failed email(s) re-queued. They will be sent shortly — open the campaign dashboard to watch progress.'));
    } else {
        header('Location: view.php?id=' . $offerId . '&warn=' . urlencode('No failed emails to resend for this offer.'));
    }
    exit;
}

/* Single-campaign resend */
$rr = resendFailedCampaign($campId);
if (!$rr['ok']) {
    header('Location: dashboard.php?err=' . urlencode($rr['error']));
    exit;
}
if ($rr['count'] === 0) {
    header('Location: dashboard.php?warn=' . urlencode('No failed emails to resend for this campaign.'));
    exit;
}
/* Kick off processing immediately via a non-blocking first batch */
processCampaignBatch($campId, 5);

header('Location: dashboard.php?campaign=' . $campId . '&ok=' . urlencode($rr['count'] . ' failed email(s) re-queued and sending. Watch the progress below.'));
exit;
