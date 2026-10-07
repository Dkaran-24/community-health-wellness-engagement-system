<?php
/**
 * admin/members/delete.php — remove a member.
 * Cascades: attendance & fees are auto-deleted via ON DELETE CASCADE foreign keys.
 * Trainer member-count is a live subquery on the trainers page, so it updates
 * automatically once the member row is gone.
 */
$PAGE_TITLE = 'Delete Member';
$PAGE_KEY   = 'members';
require_once __DIR__ . '/../../includes/header.php';

$db = db();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php?err=Invalid member id.'); exit; }

/* Fetch member name + assigned trainer (for a clear confirmation message) */
$m = $db->query("SELECT name, trainer_id FROM members WHERE id=$id")->fetch_assoc();
if (!$m) { header('Location: index.php?err=Member not found.'); exit; }

/* Delete the member. Attendance & fees rows cascade-delete automatically
   (ON DELETE CASCADE). Trainer count on the Trainers page is a live
   COUNT(*) subquery, so it drops by one immediately. */
$stmt = $db->prepare("DELETE FROM members WHERE id=?");
$stmt->bind_param('i', $id);
if ($stmt->execute()) {
    header('Location: index.php?ok=' . urlencode('Member "' . $m['name'] . '" deleted. Related attendance, fees and trainer assignment removed.'));
} else {
    header('Location: index.php?err=' . urlencode('Delete failed: ' . $stmt->error));
}
$stmt->close();
exit;
