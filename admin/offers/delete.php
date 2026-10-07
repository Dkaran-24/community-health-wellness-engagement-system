<?php
/**
 * admin/offers/delete.php — delete an offer.
 * Cascades to email_campaigns and email_logs (ON DELETE CASCADE FKs) and
 * removes the poster file from disk. Transactional via deleteOffer().
 */
$PAGE_KEY = 'offers';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/offers.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php?err=' . urlencode('Invalid offer id.')); exit; }

$offer = getOffer($id);
if (!$offer) { header('Location: index.php?err=' . urlencode('Offer not found.')); exit; }

$result = deleteOffer($id);
if ($result['ok']) {
    header('Location: index.php?ok=' . urlencode('Offer "' . $offer['title'] . '" deleted. Related campaigns and email logs were removed.'));
} else {
    header('Location: index.php?err=' . urlencode('Delete failed: ' . $result['error']));
}
exit;
