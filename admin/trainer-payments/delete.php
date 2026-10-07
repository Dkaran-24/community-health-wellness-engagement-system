<?php
/**
 * admin/trainer-payments/delete.php — delete a trainer payment record.
 */
require_once __DIR__ . '/../../includes/header.php';
$db = db();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php?err=' . urlencode('Invalid payment id.')); exit; }

$pay = $db->query(
  "SELECT tp.amount, t.name AS trainer_name
   FROM trainer_payments tp
   JOIN trainers t ON tp.trainer_id = t.id
   WHERE tp.id = $id"
)->fetch_assoc();
if (!$pay) { header('Location: index.php?err=' . urlencode('Payment record not found.')); exit; }

$stmt = $db->prepare("DELETE FROM trainer_payments WHERE id=?");
$stmt->bind_param('i', $id);
if ($stmt->execute()) {
    header('Location: index.php?ok=' . urlencode('Payment of ' . fmtMoney($pay['amount'], cur()) . ' to ' . $pay['trainer_name'] . ' deleted.'));
} else {
    header('Location: index.php?err=' . urlencode('Delete failed: ' . $stmt->error));
}
$stmt->close();
exit;
