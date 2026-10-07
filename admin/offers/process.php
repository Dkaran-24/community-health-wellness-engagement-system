<?php
/**
 * admin/offers/process.php — AJAX email-queue worker endpoint.
 *
 * Polls the campaign queue and sends one batch of emails per request,
 * returning JSON progress so the campaign page can update a progress bar
 * without blocking. This is the "background queue/job system": the page
 * fires repeated short requests instead of one long blocking send.
 *
 * Query params:
 *   campaign = campaign id (required)
 *   batch    = emails to send this call (default 5, max 25)
 *
 * Returns JSON:
 *   { done, sent, failed, total, processed, percent, status,
 *     sent_total, failed_total, error? }
 */

/* Show errors during debug — capture any fatal error as JSON */
error_reporting(E_ALL);
ini_set('display_errors', '0');        /* don't output HTML on error */
ini_set('log_errors', '0');

/* Register a shutdown handler so fatal errors become JSON, not HTTP 500 */
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode([
            'done' => true,
            'error' => 'Fatal: ' . $e['message'] . ' in ' . basename($e['file']) . ':' . $e['line'],
            'sent' => 0, 'failed' => 0, 'total' => 0, 'processed' => 0,
            'percent' => 100, 'status' => 'Failed',
        ]);
    }
});

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/offers.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_login();

header('Content-Type: application/json; charset=UTF-8');

/* Only respond to XHR (light CSRF hardening on the worker endpoint) */
$isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
       || (isset($_GET['force']) && $_GET['force'] === '1'); /* allow ?force=1 for manual testing */

if (!$isAjax) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden — AJAX only.']);
    exit;
}

$campaignId = (int)($_GET['campaign'] ?? 0);
$batchSize  = max(1, min(25, (int)($_GET['batch'] ?? 5)));

if (!$campaignId) {
    echo json_encode(['error' => 'Missing campaign id.']);
    exit;
}

/* Opportunistically activate any due scheduled campaigns */
activateDueScheduledCampaigns();

try {
    $result = processCampaignBatch($campaignId, $batchSize);
    echo json_encode($result);
} catch (\Throwable $e) {
    echo json_encode(['done' => true, 'error' => $e->getMessage(), 'sent' => 0, 'failed' => 0,
                      'total' => 0, 'processed' => 0, 'percent' => 100, 'status' => 'Failed']);
}
