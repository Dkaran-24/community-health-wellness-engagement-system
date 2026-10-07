<?php
/**
 * admin/offers/preview.php — standalone HTML email preview.
 *
 * Renders the exact responsive HTML email that members will receive, using
 * a sample member name, so the admin can review it inside an iframe / new
 * tab before publishing. Outputs raw HTML (no admin chrome).
 *
 * Auth: only logged-in admins (require_login) — but we bypass the admin
 * header/sidebar to emit a clean document for the iframe.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/../../includes/offers.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(400); echo 'Invalid offer id.'; exit; }

$offer = getOffer($id);
if (!$offer) { http_response_code(404); echo 'Offer not found.'; exit; }

$settings = get_settings(true);
$sampleMember = ['name' => 'John Carter', 'email' => 'john.c@example.com'];
$built = buildOfferEmailHtml($offer, $sampleMember, $settings, 0);

/* Output the HTML email as a standalone document.
   The poster uses cid:offerPoster in the email; for the on-screen preview
   we swap the CID src for the real uploaded file URL so it renders. */
$html = $built['html'];
$base = base_url();
if (!empty($offer['poster_path']) && file_exists(__DIR__ . '/../../' . $offer['poster_path'])) {
    $realUrl = $base . '/' . htmlspecialchars($offer['poster_path']);
    $html = str_replace('src="cid:offerPoster"', 'src="' . $realUrl . '"', $html);
}
header('Content-Type: text/html; charset=UTF-8');
echo $html;
