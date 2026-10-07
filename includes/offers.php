<?php
/**
 * offers.php — Offer Management & Email Campaign core logic.
 *
 * Central, reusable module for the New Life Fitness Club admin panel.
 * Provides:
 *   - Offer CRUD helpers (create / update / delete / fetch / toggle status)
 *   - Poster/banner image upload + validation (mirrors handlePhotoUpload)
 *   - Campaign orchestration: create a campaign + per-recipient email_logs
 *     rows, then process the queue in batches without blocking the UI
 *   - Background-safe worker: processCampaignBatch() sends N emails per call
 *     and is polled via AJAX (admin/offers/process.php) for live progress
 *   - Duplicate-send protection via the unique (campaign_id, member_id) key
 *   - Stats aggregation for the campaign dashboard
 *   - Campaign action audit logging (email_campaign_actions)
 *
 * All functions expect a mysqli connection from db(). Requires helpers.php
 * (e(), fmtDate(), base_url()) and mailer.php (sendOfferCampaignEmail()).
 */

require_once __DIR__ . '/helpers.php';

/* --------------------------------------------------------------------- *
 *  Schema self-migration
 *  Ensures the offers / campaigns / logs tables + audit table exist on
 *  first use, so the feature works on existing installs without a manual
 *  SQL import (idempotent — safe to call on every request).
 * --------------------------------------------------------------------- */
function offers_ensure_schema() {
    $db = db();
    mysqli_report(MYSQLI_REPORT_OFF);

    $db->query("CREATE TABLE IF NOT EXISTS offers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(160) NOT NULL,
        description TEXT NULL,
        discount VARCHAR(160) NOT NULL DEFAULT '',
        poster_path VARCHAR(255) NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
        created_by INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_offers_admin FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
        INDEX idx_offers_status (status),
        INDEX idx_offers_dates (start_date, end_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS email_campaigns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        offer_id INT NOT NULL,
        admin_id INT NULL,
        subject VARCHAR(200) NOT NULL DEFAULT '🎉 Exclusive Gym Offer Just for You!',
        total_recipients INT NOT NULL DEFAULT 0,
        sent_count INT NOT NULL DEFAULT 0,
        failed_count INT NOT NULL DEFAULT 0,
        pending_count INT NOT NULL DEFAULT 0,
        status ENUM('Pending','Processing','Completed','Failed','Scheduled','Cancelled') NOT NULL DEFAULT 'Pending',
        schedule_at DATETIME NULL,
        started_at DATETIME NULL,
        completed_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_campaigns_offer FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE CASCADE,
        CONSTRAINT fk_campaigns_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL,
        INDEX idx_campaigns_offer (offer_id),
        INDEX idx_campaigns_status (status),
        INDEX idx_campaigns_schedule (schedule_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS email_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        campaign_id INT NOT NULL,
        offer_id INT NOT NULL,
        member_id INT NULL,
        email VARCHAR(160) NOT NULL,
        member_name VARCHAR(160) NULL,
        status ENUM('Pending','Sent','Failed') NOT NULL DEFAULT 'Pending',
        error_message TEXT NULL,
        opened TINYINT(1) NOT NULL DEFAULT 0,
        opened_at DATETIME NULL,
        sent_at DATETIME NULL,
        attempts INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_logs_campaign FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) ON DELETE CASCADE,
        CONSTRAINT fk_logs_offer FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE CASCADE,
        CONSTRAINT fk_logs_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL,
        UNIQUE KEY uniq_campaign_member (campaign_id, member_id),
        INDEX idx_logs_status (status),
        INDEX idx_logs_offer (offer_id),
        INDEX idx_logs_campaign (campaign_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    /* Audit log for every campaign action (create/send/reschedule/cancel) */
    $db->query("CREATE TABLE IF NOT EXISTS email_campaign_actions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        campaign_id INT NULL,
        offer_id INT NULL,
        admin_id INT NULL,
        action VARCHAR(40) NOT NULL,
        detail VARCHAR(255) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_actions_campaign (campaign_id),
        INDEX idx_actions_offer (offer_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/* --------------------------------------------------------------------- *
 *  Image upload (poster/banner)
 * --------------------------------------------------------------------- */

/**
 * Handle an offer poster upload from $_FILES['poster'].
 * Validates MIME type + size, saves to uploads/offers/, returns the relative
 * path (stored in DB) or null when no file was uploaded. Sets $errorRef on
 * failure (mirrors handlePhotoUpload's contract).
 *
 * @param string &$errorRef  Filled with an error message on failure
 * @return string|null       Relative path or null
 */
function handleOfferPosterUpload(&$errorRef) {
    if (!isset($_FILES['poster']) || $_FILES['poster']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES['poster'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorRef = 'Poster upload failed (error code ' . $file['error'] . ').';
        return null;
    }
    /* 5 MB max (banner images) */
    if ($file['size'] > 5 * 1024 * 1024) {
        $errorRef = 'Poster is too large (max 5 MB).';
        return null;
    }
    /* Validate the real MIME type, not the client-supplied one */
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        $errorRef = 'Poster must be a JPG, PNG, GIF, or WebP image.';
        return null;
    }
    $ext  = $allowed[$mime];
    $name = 'offer_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destDir = __DIR__ . '/../uploads/offers';
    if (!is_dir($destDir)) mkdir($destDir, 0775, true);
    $dest = $destDir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        $errorRef = 'Could not save the uploaded poster.';
        return null;
    }
    return 'uploads/offers/' . $name;
}

/* --------------------------------------------------------------------- *
 *  Offer CRUD
 * --------------------------------------------------------------------- */

/** Fetch a single offer by id (or null). */
function getOffer($id) {
    $id = (int)$id;
    if (!$id) return null;
    $res = db()->query("SELECT * FROM offers WHERE id = $id");
    return ($res && $res->num_rows) ? $res->fetch_assoc() : null;
}

/**
 * Create an offer. Runs inside a transaction.
 * @return array ['ok'=>bool,'id'=>int,'error'=>string]
 */
function createOffer($data) {
    $db = db();
    $title    = trim($data['title'] ?? '');
    $desc     = trim($data['description'] ?? '');
    $discount = trim($data['discount'] ?? '');
    $poster   = trim($data['poster_path'] ?? '');
    $start    = trim($data['start_date'] ?? '');
    $end      = trim($data['end_date'] ?? '');
    $status   = ($data['status'] ?? 'Active') === 'Inactive' ? 'Inactive' : 'Active';
    $adminId  = (int)($data['created_by'] ?? ($_SESSION['admin_id'] ?? 0)) ?: null;

    /* Validation */
    if ($title === '') return ['ok' => false, 'error' => 'Offer title is required.'];
    if ($start === '' || $end === '') return ['ok' => false, 'error' => 'Start and end dates are required.'];
    if (strtotime($end) < strtotime($start)) return ['ok' => false, 'error' => 'End date cannot be before the start date.'];

    $db->begin_transaction();
    try {
        $stmt = $db->prepare(
            "INSERT INTO offers (title, description, discount, poster_path, start_date, end_date, status, created_by)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        $n = null;
        $stmt->bind_param('sssssssi', $title, $desc, $discount, $poster, $start, $end, $status, $adminId);
        $ok = $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();
        if (!$ok) throw new \Exception($db->error);
        $db->commit();
        return ['ok' => true, 'id' => $id];
    } catch (\Throwable $e) {
        $db->rollback();
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Update an offer. Runs inside a transaction.
 * @return array ['ok'=>bool,'error'=>string]
 */
function updateOffer($id, $data) {
    $db = db();
    $id = (int)$id;
    if (!$id) return ['ok' => false, 'error' => 'Invalid offer id.'];

    $title    = trim($data['title'] ?? '');
    $desc     = trim($data['description'] ?? '');
    $discount = trim($data['discount'] ?? '');
    $poster   = trim($data['poster_path'] ?? '');
    $start    = trim($data['start_date'] ?? '');
    $end      = trim($data['end_date'] ?? '');
    $status   = ($data['status'] ?? 'Active') === 'Inactive' ? 'Inactive' : 'Active';

    if ($title === '') return ['ok' => false, 'error' => 'Offer title is required.'];
    if ($start === '' || $end === '') return ['ok' => false, 'error' => 'Start and end dates are required.'];
    if (strtotime($end) < strtotime($start)) return ['ok' => false, 'error' => 'End date cannot be before the start date.'];

    $db->begin_transaction();
    try {
        $stmt = $db->prepare(
            "UPDATE offers SET title=?, description=?, discount=?, poster_path=?, start_date=?, end_date=?, status=? WHERE id=?"
        );
        $stmt->bind_param('sssssssi', $title, $desc, $discount, $poster, $start, $end, $status, $id);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) throw new \Exception($db->error);
        $db->commit();
        return ['ok' => true];
    } catch (\Throwable $e) {
        $db->rollback();
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/** Toggle an offer's status Active <-> Inactive. */
function toggleOfferStatus($id) {
    $id = (int)$id;
    if (!$id) return false;
    $stmt = db()->prepare("UPDATE offers SET status = IF(status='Active','Inactive','Active') WHERE id=?");
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Delete an offer. Cascades to email_campaigns + email_logs (ON DELETE
 * CASCADE FKs) and removes the poster file from disk. Transactional.
 */
function deleteOffer($id) {
    $db = db();
    $id = (int)$id;
    if (!$id) return ['ok' => false, 'error' => 'Invalid offer id.'];

    $offer = getOffer($id);
    if (!$offer) return ['ok' => false, 'error' => 'Offer not found.'];

    $db->begin_transaction();
    try {
        $stmt = $db->prepare("DELETE FROM offers WHERE id=?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) throw new \Exception($db->error);
        $db->commit();

        /* Remove the poster from disk (best-effort) */
        if (!empty($offer['poster_path'])) {
            $file = __DIR__ . '/../' . $offer['poster_path'];
            if (file_exists($file)) @unlink($file);
        }
        return ['ok' => true];
    } catch (\Throwable $e) {
        $db->rollback();
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/* --------------------------------------------------------------------- *
 *  Campaign orchestration
 * --------------------------------------------------------------------- */

/**
 * Fetch all active members (status='Active') with a valid email address.
 * These are the campaign recipients.
 * @return mysqli_result
 */
function getActiveEmailMembers() {
    return db()->query(
        "SELECT id, name, email FROM members
         WHERE status = 'Active'
           AND email IS NOT NULL AND email <> ''
           AND email LIKE '%@%'
         ORDER BY name"
    );
}

/** Count active members with a valid email (for quick recipient totals). */
function countActiveEmailMembers() {
    $res = db()->query(
        "SELECT COUNT(*) c FROM members
         WHERE status = 'Active'
           AND email IS NOT NULL AND email <> ''
           AND email LIKE '%@%'"
    );
    return $res ? (int)$res->fetch_assoc()['c'] : 0;
}

/**
 * Create an email campaign for an offer.
 *
 * - Inserts an email_campaigns row (status Scheduled or Pending).
 * - Creates one email_logs row per active member with a valid email
 *   (status='Pending'). The UNIQUE(campaign_id, member_id) key prevents
 *   duplicate recipients within the same campaign.
 * - Sets total_recipients / pending_count to the number of recipients.
 * - Logs the action to email_campaign_actions.
 *
 * Duplicate-send protection: before creating a fresh campaign, callers may
 * check hasExistingCampaignForOffer() to warn the admin.
 *
 * @param int    $offerId
 * @param array  $opts  ['admin_id'=>int, 'schedule_at'=>'Y-m-d H:i:s'|null, 'subject'=>string]
 * @return array ['ok'=>bool,'campaign_id'=>int,'recipients'=>int,'error'=>string]
 */
function createCampaign($offerId, $opts = []) {
    $db = db();
    $offerId = (int)$offerId;
    if (!$offerId) return ['ok' => false, 'error' => 'Invalid offer id.'];

    $offer = getOffer($offerId);
    if (!$offer) return ['ok' => false, 'error' => 'Offer not found.'];

    $adminId    = (int)($opts['admin_id'] ?? ($_SESSION['admin_id'] ?? 0)) ?: null;
    $scheduleAt = trim($opts['schedule_at'] ?? '');
    $subject    = trim($opts['subject'] ?? '🎉 Exclusive Gym Offer Just for You!');
    $isScheduled = ($scheduleAt !== '' && strtotime($scheduleAt) > time());
    $status = $isScheduled ? 'Scheduled' : 'Pending';

    $members = getActiveEmailMembers();
    if (!$members) return ['ok' => false, 'error' => 'Could not fetch active members.'];
    $recipients = (int)$members->num_rows;
    if ($recipients === 0) {
        return ['ok' => false, 'error' => 'No active members with a valid email address found. Add active members with emails first.'];
    }

    $db->begin_transaction();
    try {
        /* 1. Create the campaign row */
        $schedParam = $isScheduled ? $scheduleAt : null;
        $stmt = $db->prepare(
            "INSERT INTO email_campaigns
                (offer_id, admin_id, subject, total_recipients, sent_count, failed_count, pending_count, status, schedule_at)
             VALUES (?,?,?,?,0,0,?,?,?)"
        );
        $stmt->bind_param('iisiiss', $offerId, $adminId, $subject, $recipients, $recipients, $status, $schedParam);
        $ok = $stmt->execute();
        $campaignId = $stmt->insert_id;
        $stmt->close();
        if (!$ok) throw new \Exception($db->error);

        /* 2. Create one email_logs row per recipient (Pending).
              Use INSERT IGNORE so the UNIQUE(campaign_id, member_id) key
              quietly skips a duplicate if the same member appears twice. */
        $logStmt = $db->prepare(
            "INSERT IGNORE INTO email_logs (campaign_id, offer_id, member_id, email, member_name, status)
             VALUES (?,?,?,?,?, 'Pending')"
        );
        $memberId = 0; $email = ''; $memberName = ''; $logStmt->bind_param('iiiss', $campaignId, $offerId, $memberId, $email, $memberName);
        $count = 0;
        while ($m = $members->fetch_assoc()) {
            $memberId   = (int)$m['id'];
            $email      = $m['email'];
            $memberName = $m['name'];
            $logStmt->execute();
            if ($db->affected_rows > 0) $count++;
        }
        $logStmt->close();

        /* Reconcile pending_count with the actual number of log rows created
           (INSERT IGNORE may have skipped dupes). */
        $db->query("UPDATE email_campaigns SET pending_count = $count, total_recipients = $count WHERE id = $campaignId");

        /* 3. Audit log */
        logCampaignAction($campaignId, $offerId, $adminId, $isScheduled ? 'scheduled' : 'created',
            $isScheduled ? "Scheduled for $scheduleAt" : 'Immediate send queued');

        $db->commit();
        return ['ok' => true, 'campaign_id' => $campaignId, 'recipients' => $count];
    } catch (\Throwable $e) {
        $db->rollback();
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Has any non-cancelled campaign already been created for this offer?
 * Used to warn the admin about duplicate sends.
 */
function hasExistingCampaignForOffer($offerId) {
    $offerId = (int)$offerId;
    $res = db()->query("SELECT COUNT(*) c FROM email_campaigns WHERE offer_id = $offerId AND status <> 'Cancelled'");
    return $res && (int)$res->fetch_assoc()['c'] > 0;
}

/* --------------------------------------------------------------------- *
 *  Background queue worker (batch processing)
 * --------------------------------------------------------------------- */

/**
 * Process one batch of a campaign's email queue.
 *
 * Designed to be called repeatedly (polled) by admin/offers/process.php
 * so the UI is never blocked. Each call sends up to $batchSize pending
 * emails, updates the email_logs + campaign aggregate counters, and
 * returns progress info.
 *
 * Uses a short SELECT ... LIMIT then row-by-row send (with prepared UPDATEs)
 * to keep memory low for large member lists. Each send is independent so a
 * single SMTP failure does not abort the whole batch.
 *
 * @param int $campaignId
 * @param int $batchSize   emails to send this call (default 5)
 * @return array ['done'=>bool, 'sent'=>int, 'failed'=>int, 'total'=>int,
 *               'processed'=>int, 'percent'=>int, 'status'=>string]
 */
function processCampaignBatch($campaignId, $batchSize = 5) {
    $db = db();
    $campaignId = (int)$campaignId;
    if (!$campaignId) return ['done' => true, 'sent' => 0, 'failed' => 0, 'total' => 0, 'processed' => 0, 'percent' => 100, 'status' => 'Failed'];

    /* Load campaign + offer in one join */
    $cRes = $db->query(
        "SELECT c.*, o.title, o.description, o.discount, o.poster_path, o.start_date, o.end_date
         FROM email_campaigns c JOIN offers o ON o.id = c.offer_id
         WHERE c.id = $campaignId"
    );
    $camp = $cRes ? $cRes->fetch_assoc() : null;
    if (!$camp) return ['done' => true, 'sent' => 0, 'failed' => 0, 'total' => 0, 'processed' => 0, 'percent' => 100, 'status' => 'Failed'];

    /* Don't touch scheduled campaigns before their time */
    if ($camp['status'] === 'Scheduled' && !empty($camp['schedule_at']) && strtotime($camp['schedule_at']) > time()) {
        $tot = (int)$camp['total_recipients']; $proc = (int)$camp['sent_count'] + (int)$camp['failed_count'];
        return ['done' => false, 'sent' => 0, 'failed' => 0, 'total' => $tot, 'processed' => $proc,
                'percent' => $tot ? (int)round($proc / $tot * 100) : 0, 'status' => 'Scheduled'];
    }
    if ($camp['status'] === 'Completed' || $camp['status'] === 'Cancelled') {
        $tot = (int)$camp['total_recipients']; $proc = (int)$camp['sent_count'] + (int)$camp['failed_count'];
        return ['done' => true, 'sent' => (int)$camp['sent_count'], 'failed' => (int)$camp['failed_count'],
                'total' => $tot, 'processed' => $proc,
                'percent' => $tot ? (int)round($proc / $tot * 100) : 100, 'status' => $camp['status']];
    }

    /* Mark as Processing (only if it was Pending/Scheduled) */
    if (in_array($camp['status'], ['Pending', 'Scheduled'], true)) {
        $db->query("UPDATE email_campaigns SET status='Processing', started_at = IFNULL(started_at, NOW()) WHERE id = $campaignId");
    }

    $offer = [
        'title'       => $camp['title'],
        'description' => $camp['description'],
        'discount'    => $camp['discount'],
        'poster_path' => $camp['poster_path'],
        'start_date'  => $camp['start_date'],
        'end_date'    => $camp['end_date'],
    ];

    require_once __DIR__ . '/mailer.php';

    /* Pull the next batch of pending logs (oldest first) */
    $batch = $db->query(
        "SELECT * FROM email_logs WHERE campaign_id = $campaignId AND status = 'Pending'
         ORDER BY id ASC LIMIT " . (int)$batchSize
    );

    $sent = 0; $failed = 0;
    $updSent = $db->prepare("UPDATE email_logs SET status='Sent', sent_at=NOW(), attempts=attempts+1, error_message=NULL WHERE id=?");
    $updSent->bind_param('i', $logId);
    $updFail = $db->prepare("UPDATE email_logs SET status='Failed', attempts=attempts+1, error_message=? WHERE id=?");
    $updFail->bind_param('si', $errMsg, $logId);

    while ($batch && ($row = $batch->fetch_assoc())) {
        $logId = (int)$row['id'];
        $member = ['name' => $row['member_name'] ?? 'Member', 'email' => $row['email']];
        $result = sendOfferCampaignEmail($db, $offer, $member, $logId);
        if ($result['ok']) {
            $sent++;
            $updSent->execute();
        } else {
            $failed++;
            $errMsg = mb_substr($result['error'] ?? 'Unknown error', 0, 500);
            $updFail->execute();
        }
    }
    $updSent->close();
    $updFail->close();

    /* Refresh aggregate counters from the logs (source of truth) */
    $db->query(
        "UPDATE email_campaigns c
         SET c.sent_count    = (SELECT COUNT(*) FROM email_logs WHERE campaign_id = c.id AND status='Sent'),
             c.failed_count  = (SELECT COUNT(*) FROM email_logs WHERE campaign_id = c.id AND status='Failed'),
             c.pending_count = (SELECT COUNT(*) FROM email_logs WHERE campaign_id = c.id AND status='Pending')
         WHERE c.id = $campaignId"
    );

    /* Reload counts to decide completion */
    $r = $db->query("SELECT total_recipients, sent_count, failed_count, pending_count FROM email_campaigns WHERE id = $campaignId");
    $c = $r->fetch_assoc();
    $total = (int)$c['total_recipients'];
    $sCount = (int)$c['sent_count'];
    $fCount = (int)$c['failed_count'];
    $pCount = (int)$c['pending_count'];
    $processed = $sCount + $fCount;
    $done = ($pCount === 0);

    if ($done) {
        $finalStatus = ($sCount > 0) ? 'Completed' : 'Failed';
        $db->query("UPDATE email_campaigns SET status='$finalStatus', completed_at=NOW() WHERE id = $campaignId");
        logCampaignAction($campaignId, (int)$camp['offer_id'], (int)($camp['admin_id'] ?? 0), 'completed',
            "Sent: $sCount, Failed: $fCount");
    }

    return [
        'done'       => $done,
        'sent'       => $sent,           /* sent THIS batch */
        'failed'     => $failed,         /* failed THIS batch */
        'total'      => $total,
        'processed'  => $processed,
        'percent'    => $total ? (int)round($processed / $total * 100) : 100,
        'status'     => $done ? $finalStatus : 'Processing',
        'sent_total' => $sCount,
        'failed_total' => $fCount,
    ];
}

/**
 * Resend all FAILED emails for a campaign. Resets their log status to
 * Pending so the next processCampaignBatch() picks them up. Returns the
 * number of emails queued for resend.
 */
function resendFailedCampaign($campaignId) {
    $db = db();
    $campaignId = (int)$campaignId;
    if (!$campaignId) return ['ok' => false, 'count' => 0, 'error' => 'Invalid campaign id.'];

    $db->begin_transaction();
    try {
        $db->query("UPDATE email_logs SET status='Pending', error_message=NULL WHERE campaign_id = $campaignId AND status='Failed'");
        $count = (int)$db->affected_rows;
        if ($count > 0) {
            $db->query("UPDATE email_campaigns SET status='Processing', pending_count = pending_count + $count, completed_at = NULL WHERE id = $campaignId");
            $r = $db->query("SELECT offer_id, admin_id FROM email_campaigns WHERE id = $campaignId");
            $crow = $r->fetch_assoc();
            logCampaignAction($campaignId, (int)$crow['offer_id'], (int)($crow['admin_id'] ?? 0), 'resend', "Re-queued $count failed emails");
        }
        $db->commit();
        return ['ok' => true, 'count' => $count];
    } catch (\Throwable $e) {
        $db->rollback();
        return ['ok' => false, 'count' => 0, 'error' => $e->getMessage()];
    }
}

/* --------------------------------------------------------------------- *
 *  Scheduling — promote due Scheduled campaigns to Processing
 * --------------------------------------------------------------------- */

/**
 * Activate any Scheduled campaigns whose schedule_at has passed.
 * Called opportunistically on dashboard/campaign page loads so scheduled
 * sends fire without an external cron. Returns the number activated.
 */
function activateDueScheduledCampaigns() {
    $db = db();
    $res = $db->query(
        "SELECT id FROM email_campaigns
         WHERE status = 'Scheduled' AND schedule_at IS NOT NULL AND schedule_at <= NOW()"
    );
    $n = 0;
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $id = (int)$row['id'];
            $db->query("UPDATE email_campaigns SET status='Pending' WHERE id = $id");
            $n++;
        }
    }
    return $n;
}

/* --------------------------------------------------------------------- *
 *  Stats (campaign dashboard)
 * --------------------------------------------------------------------- */

/**
 * Aggregate campaign statistics across ALL campaigns for the dashboard.
 * Returns: total_recipients, sent, failed, pending, delivery_pct, open_rate,
 *           campaigns_total, campaigns_completed.
 */
function getCampaignStats() {
    $db = db();
    $row = $db->query(
        "SELECT
            COALESCE(SUM(total_recipients),0) AS total_recipients,
            COALESCE(SUM(sent_count),0)      AS sent,
            COALESCE(SUM(failed_count),0)    AS failed,
            COALESCE(SUM(pending_count),0)   AS pending,
            COUNT(*)                          AS campaigns_total,
            SUM(status='Completed')           AS campaigns_completed
         FROM email_campaigns"
    )->fetch_assoc();

    $opens = $db->query("SELECT COUNT(*) c FROM email_logs WHERE opened = 1 AND status='Sent'")->fetch_assoc()['c'];
    $sent  = (int)$row['sent'];

    $total = (int)$row['total_recipients'];
    $delivered = (int)$row['sent'];
    $deliveryPct = $total ? round($delivered / $total * 100, 1) : 0;
    $openRate = $sent ? round($opens / $sent * 100, 1) : 0;

    return [
        'total_recipients'    => $total,
        'sent'                => $delivered,
        'failed'              => (int)$row['failed'],
        'pending'             => (int)$row['pending'],
        'delivery_pct'        => $deliveryPct,
        'open_rate'           => $openRate,
        'opens'               => (int)$opens,
        'campaigns_total'     => (int)$row['campaigns_total'],
        'campaigns_completed' => (int)$row['campaigns_completed'],
    ];
}

/** Stats for a single campaign (for the campaign dashboard per-row view). */
function getCampaignRowStats($campaignId) {
    $campaignId = (int)$campaignId;
    $r = db()->query("SELECT * FROM email_campaigns WHERE id = $campaignId");
    if (!$r || !$r->num_rows) return null;
    $c = $r->fetch_assoc();
    $opens = (int)db()->query("SELECT COUNT(*) c FROM email_logs WHERE campaign_id = $campaignId AND opened = 1 AND status='Sent'")->fetch_assoc()['c'];
    $sent = (int)$c['sent_count'];
    $c['opens'] = $opens;
    $c['open_rate'] = $sent ? round($opens / $sent * 100, 1) : 0;
    $c['delivery_pct'] = (int)$c['total_recipients'] ? round((int)$c['sent_count'] / (int)$c['total_recipients'] * 100, 1) : 0;
    return $c;
}

/* --------------------------------------------------------------------- *
 *  Audit log
 * --------------------------------------------------------------------- */

function logCampaignAction($campaignId, $offerId, $adminId, $action, $detail = '') {
    $db = db();
    $campaignId = $campaignId ? (int)$campaignId : null;
    $offerId    = $offerId ? (int)$offerId : null;
    $adminId    = $adminId ? (int)$adminId : null;
    $stmt = $db->prepare("INSERT INTO email_campaign_actions (campaign_id, offer_id, admin_id, action, detail) VALUES (?,?,?,?,?)");
    $stmt->bind_param('iiiss', $campaignId, $offerId, $adminId, $action, $detail);
    $stmt->execute();
    $stmt->close();
}

/* --------------------------------------------------------------------- *
 *  Display helpers
 * --------------------------------------------------------------------- */

/** Render an offer poster <img> or a branded gradient placeholder. */
function offerPosterImg($path, $alt = 'Offer poster', $cls = 'offer-poster') {
    $base = base_url();
    if ($path && file_exists(__DIR__ . '/../' . $path)) {
        return '<img src="' . $base . '/' . htmlspecialchars($path) . '" alt="' . htmlspecialchars($alt) . '" class="' . $cls . '">';
    }
    return '<div class="' . $cls . ' offer-poster-placeholder">'
         . '<span class="ico">🎉</span>'
         . '<span class="lbl">No poster uploaded</span>'
         . '</div>';
}

/** Offer status badge HTML. */
function offerStatusBadge($status) {
    return $status === 'Active'
        ? '<span class="badge green">Active</span>'
        : '<span class="badge gray">Inactive</span>';
}

/** Campaign status badge HTML. */
function campaignStatusBadge($status) {
    $map = [
        'Pending'    => 'gold',
        'Processing' => 'steel',
        'Completed'  => 'green',
        'Failed'     => 'red',
        'Scheduled'  => 'gold',
        'Cancelled'  => 'gray',
    ];
    $cls = $map[$status] ?? 'gray';
    return '<span class="badge ' . $cls . '">' . e($status) . '</span>';
}

/** Is an offer currently within its valid date window? */
function offerIsLive($offer) {
    $today = date('Y-m-d');
    return $offer['status'] === 'Active'
        && $offer['start_date'] <= $today
        && $offer['end_date'] >= $today;
}

/* Run the schema self-migration on include (idempotent). */
offers_ensure_schema();
