<?php
/**
 * helpers.php — shared utility functions for New Life Fitness Club.
 */

/** Escape output safely. */
function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

/** Format money with currency symbol. */
function fmtMoney($n, $sym = '₹') {
    $num = (float)($n ?? 0);
    return $sym . number_format($num, 2, '.', ',');
}

/** Format a date nicely. */
function fmtDate($d) {
    if (!$d || $d === '0000-00-00') return '—';
    return date('M j, Y', strtotime($d));
}

/** Generate a unique receipt number. */
function genReceiptNo() {
    $yr = date('Y');
    $r = db()->query("SELECT COUNT(*) c FROM fees")->fetch_assoc()['c'];
    return 'NLF-' . $yr . '-' . str_pad((int)$r + 1, 4, '0', STR_PAD_LEFT);
}

/** Render a flash alert from a query-string message (?ok=... or ?err=...). */
function flash() {
    $out = '';
    if (!empty($_GET['ok']))  $out .= '<div class="alert ok flash">&#10003; ' . e($_GET['ok']) . '</div>';
    if (!empty($_GET['err'])) $out .= '<div class="alert err flash">&#9888; ' . e($_GET['err']) . '</div>';
    if (!empty($_GET['warn']))$out .= '<div class="alert warn flash">&#9888; ' . e($_GET['warn']) . '</div>';
    return $out;
}

/** Membership expiry date for a member (join_date + plan months). */
function memberExpiry($joinDate, $months) {
    if (!$joinDate || !$months) return null;
    return date('Y-m-d', strtotime("+$months months", strtotime($joinDate)));
}

/**
 * Renew a member's membership when they pay for a plan.
 *
 * Logic:
 *  - If the membership is STILL ACTIVE (current expiry >= payment date):
 *      extend from the current expiry date  →  new_join_date = current_expiry
 *      so the new expiry = current_expiry + duration_months (stacks/extends).
 *  - If the membership has ALREADY EXPIRED (current expiry < payment date):
 *      restart from the payment date  →  new_join_date = payment_date
 *      so the new expiry = payment_date + duration_months.
 *  - Also sets members.status = 'Active' so the member shows as active everywhere.
 *
 * @param mysqli  $db          Database connection
 * @param int     $member_id   Member ID
 * @param int|null $plan_id    Plan ID (must be set to renew; null = skip)
 * @param string  $paymentDate Payment date (Y-m-d)
 * @return bool   True if membership was renewed, false if skipped/failed
 */
function renewMembership($db, $member_id, $plan_id, $paymentDate) {
    $member_id = (int)$member_id;
    $plan_id   = $plan_id ? (int)$plan_id : 0;
    if (!$member_id || !$plan_id || !$paymentDate) return false;

    // Fetch member's current join_date + plan duration
    $row = $db->query(
      "SELECT m.join_date, p.duration_months,
              DATE_ADD(m.join_date, INTERVAL p.duration_months MONTH) AS current_expiry
       FROM members m
       JOIN membership_plans p ON p.id = $plan_id
       WHERE m.id = $member_id"
    );
    if (!$row) return false;
    $info = $row->fetch_assoc();
    if (!$info || !$info['duration_months']) return false;

    $months        = (int)$info['duration_months'];
    $currentExpiry = $info['current_expiry']; // join_date + duration
    $paymentDate   = date('Y-m-d', strtotime($paymentDate));

    // Decide the new join_date
    if ($currentExpiry && $currentExpiry >= $paymentDate) {
        // Still active → extend from current expiry
        $newJoinDate = $currentExpiry;
    } else {
        // Already expired → restart from payment date
        $newJoinDate = $paymentDate;
    }

    // Update join_date (drives expiry badge) and set status to Active
    $stmt = $db->prepare("UPDATE members SET join_date = ?, status = 'Active' WHERE id = ?");
    $stmt->bind_param('si', $newJoinDate, $member_id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/** Status badge HTML for a membership given expiry date. */
function expiryBadge($expiry) {
    if (!$expiry) return '<span class="badge gray">No plan</span>';
    $today = date('Y-m-d');
    $days = (strtotime($expiry) - strtotime($today)) / 86400;
    if ($days < 0)        return '<span class="badge red">Expired</span>';
    if ($days <= 30)      return '<span class="badge gold">Expiring soon</span>';
    return '<span class="badge green">Active</span>';
}

/**
 * Handle a member photo upload from $_FILES['photo'].
 * Validates type/size, saves to uploads/members/, returns the relative path
 * (stored in DB) or null if no file was uploaded. Calls $errorRef on failure.
 */
function handlePhotoUpload(&$errorRef) {
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES['photo'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorRef = 'Photo upload failed (error code ' . $file['error'] . ').';
        return null;
    }
    // 5 MB max
    if ($file['size'] > 5 * 1024 * 1024) {
        $errorRef = 'Photo is too large (max 5 MB).';
        return null;
    }
    // Validate actual image type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        $errorRef = 'Photo must be a JPG, PNG, GIF, or WebP image.';
        return null;
    }
    // Save with a unique name
    $ext = $allowed[$mime];
    $name = 'member_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destDir = __DIR__ . '/../uploads/members';
    if (!is_dir($destDir)) mkdir($destDir, 0775, true);
    $dest = $destDir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        $errorRef = 'Could not save the uploaded photo.';
        return null;
    }
    // Relative path stored in DB (works with base_url)
    return 'uploads/members/' . $name;
}

/** Output an <img> for a member photo, or a placeholder avatar. */
function photoImg($path, $alt = 'Member', $cls = 'member-photo') {
    // base_url() lives in includes/auth.php (admin pages) and member_base_url()
    // lives in includes/member_auth.php (member pages). Use whichever is
    // available so photoImg works in both contexts without a fatal error.
    if (function_exists('base_url')) {
        $base = base_url();
    } elseif (function_exists('member_base_url')) {
        $base = member_base_url();
    } else {
        $base = '';
    }
    if ($path && file_exists(__DIR__ . '/../' . $path)) {
        return '<img src="' . $base . '/' . htmlspecialchars($path) . '" alt="' . htmlspecialchars($alt) . '" class="' . $cls . '">';
    }
    // First letter of the name as a placeholder avatar (mb-safe if available)
    $initial = function_exists('mb_substr')
        ? mb_strtoupper(mb_substr($alt, 0, 1))
        : strtoupper(substr($alt, 0, 1));
    return '<div class="' . $cls . ' photo-placeholder">' . htmlspecialchars($initial) . '</div>';
}