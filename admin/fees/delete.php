<?php
/**
 * admin/fees/delete.php — delete a fee / payment record.
 */
require_once __DIR__ . '/../../includes/header.php';
$db = db();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php?err=' . urlencode('Invalid payment id.')); exit; }

$fee = $db->query("SELECT receipt_no, member_id FROM fees WHERE id=$id")->fetch_assoc();
if (!$fee) { header('Location: index.php?err=' . urlencode('Payment record not found.')); exit; }

$stmt = $db->prepare("DELETE FROM fees WHERE id=?");
$stmt->bind_param('i', $id);
if ($stmt->execute()) {
    header('Location: index.php?ok=' . urlencode('Payment ' . ($fee['receipt_no'] ?: '#' . $id) . ' deleted.'));
} else {
    header('Location: index.php?err=' . urlencode('Delete failed: ' . $stmt->error));
}
$stmt->close();
exit;
