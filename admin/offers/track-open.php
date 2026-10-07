<?php
/**
 * admin/offers/track-open.php — email open-tracking pixel.
 *
 * Embedded as a 1x1 transparent image in each campaign email. When the
 * recipient's mail client loads it, this endpoint flips email_logs.opened
 * to 1 and records opened_at. Returns an actual transparent GIF so the
 * client renders nothing visible.
 *
 * No auth required (loaded by mail clients). The log id is opaque, and the
 * only effect is a boolean open flag — no PII is exposed.
 */
require_once __DIR__ . '/../../db_connect.php';

$logId = (int)($_GET['log'] ?? 0);
if ($logId) {
    /* Mark opened only if not already, to avoid repeated writes */
    @db()->query("UPDATE email_logs SET opened = 1, opened_at = IFNULL(opened_at, NOW()) WHERE id = $logId AND opened = 0");
}

/* 1x1 transparent GIF (43 bytes) — standard tracking-pixel response */
header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
echo base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw==');
