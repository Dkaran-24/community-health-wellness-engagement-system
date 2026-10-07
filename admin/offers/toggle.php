<?php
/**
 * admin/offers/toggle.php — activate / deactivate an offer.
 * Flips the status between Active and Inactive. Only Active offers are
 * eligible to be published as email campaigns.
 */
$PAGE_KEY = 'offers';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/offers.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php?err=' . urlencode('Invalid offer id.')); exit; }

$offer = getOffer($id);
if (!$offer) { header('Location: index.php?err=' . urlencode('Offer not found.')); exit; }

if (toggleOfferStatus($id)) {
    $newStatus = $offer['status'] === 'Active' ? 'Inactive' : 'Active';
    header('Location: index.php?ok=' . urlencode('Offer "' . $offer['title'] . '" is now ' . $newStatus . '.'));
} else {
    header('Location: index.php?err=' . urlencode('Could not update offer status.'));
}
exit;
