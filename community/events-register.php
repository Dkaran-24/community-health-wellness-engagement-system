<?php
/**
 * community/events-register.php — POST handler for event registration/cancel.
 *
 * Server-side enforcement of ALL business rules (never trust the UI):
 *   - CSRF token required
 *   - event must exist, be Upcoming/Ongoing and not in the past
 *   - registration deadline respected
 *   - duplicates prevented (checked in DB + UNIQUE constraint as backstop)
 *   - capacity enforced atomically
 *   - cancel only allowed for own registration before the event date
 */
require_once __DIR__ . '/../includes/community_auth.php';
require_once __DIR__ . '/../db_connect.php';
require_community_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: events.php');
    exit;
}
csrf_require();

$db   = db();
$uid  = (int)$_SESSION['community_user_id'];
$eid  = (int)($_POST['event_id'] ?? 0);
$act  = ($_POST['action'] ?? '') === 'cancel' ? 'cancel' : 'register';
$back = 'events.php#event-' . $eid;

function fail($msg, $eid) {
    header('Location: events.php?err=' . urlencode($msg) . '#event-' . $eid);
    exit;
}
function done($msg, $eid) {
    header('Location: events.php?ok=' . urlencode($msg) . '#event-' . $eid);
    exit;
}

/* ---------- event must exist ---------- */
$stmt = $db->prepare("SELECT * FROM community_events WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $eid);
$stmt->execute();
$ev = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$ev) fail('Event not found.', $eid);

/* ============================ CANCEL ============================ */
if ($act === 'cancel') {
    $stmt = $db->prepare("SELECT id FROM event_registrations WHERE community_user_id = ? AND event_id = ? AND status <> 'Cancelled' LIMIT 1");
    $stmt->bind_param('ii', $uid, $eid);
    $stmt->execute();
    $reg = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$reg) fail('You are not registered for this event.', $eid);
    if ($ev['event_date'] <= date('Y-m-d')) fail('Too late to cancel — the event date has arrived. Contact the organisers if you cannot attend.', $eid);

    $stmt = $db->prepare("UPDATE event_registrations SET status = 'Cancelled' WHERE community_user_id = ? AND event_id = ?");
    $stmt->bind_param('ii', $uid, $eid);
    $stmt->execute();
    $stmt->close();
    done('Your registration for "' . $ev['event_name'] . '" was cancelled.', $eid);
}

/* =========================== REGISTER =========================== */
if ($ev['status'] === 'Completed' || $ev['status'] === 'Cancelled') fail('Registrations are closed for this event.', $eid);
if ($ev['event_date'] < date('Y-m-d')) fail('This event has already taken place.', $eid);
if (!empty($ev['reg_deadline']) && date('Y-m-d') > $ev['reg_deadline']) {
    fail('The registration deadline (' . date('M j, Y', strtotime($ev['reg_deadline'])) . ') has passed.', $eid);
}

/* duplicate check (UNIQUE constraint is the backstop) */
$stmt = $db->prepare("SELECT id, status FROM event_registrations WHERE community_user_id = ? AND event_id = ? LIMIT 1");
$stmt->bind_param('ii', $uid, $eid);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($existing && $existing['status'] !== 'Cancelled') {
    fail('You are already registered for this event — duplicates are not allowed.', $eid);
}

/* capacity check (fresh count) */
$stmt = $db->prepare("SELECT COUNT(*) c FROM event_registrations WHERE event_id = ? AND status <> 'Cancelled'");
$stmt->bind_param('i', $eid);
$stmt->execute();
$taken = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();
if ($taken >= (int)$ev['max_participants']) fail('Sorry, this event just reached full capacity.', $eid);

/* insert (or revive a cancelled row to keep UNIQUE happy) */
if ($existing) {
    $stmt = $db->prepare("UPDATE event_registrations SET status = 'Registered', reg_date = NOW() WHERE id = ?");
    $stmt->bind_param('i', $existing['id']);
    $stmt->execute();
    $stmt->close();
} else {
    $stmt = $db->prepare("INSERT INTO event_registrations (community_user_id, event_id, status) VALUES (?, ?, 'Registered')");
    $stmt->bind_param('ii', $uid, $eid);
    try {
        $stmt->execute();
    } catch (mysqli_sql_exception $ex) {
        if (strpos($ex->getMessage(), 'uniq_user_event') !== false) {
            fail('You are already registered for this event.', $eid);
        }
        throw $ex;
    }
    $stmt->close();
}
done('Registered successfully for "' . $ev['event_name'] . '" on ' . date('M j, Y', strtotime($ev['event_date'])) . '. See you there!', $eid);
