<?php
/**
 * admin/fees/send-reminder.php
 *
 * Re-send a payment reminder email to a member whose fee is Pending/Overdue.
 * Triggered by the "Remind" button on the fee list (admin/fees/index.php).
 *
 * Works via GET (button link) so it can be clicked directly from the list.
 * Redirects back to index.php with an ok/err flash message.
 */
$PAGE_KEY = 'fees';
require_once __DIR__ . '/../../includes/header.php';
$db = db();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: index.php?err=' . urlencode('Invalid payment id.'));
    exit;
}

// Load the fee + member so we can verify the status and show the member name
$fee = @$db->query(
  "SELECT f.id, f.status, f.receipt_no, m.name AS member_name
   FROM fees f
   JOIN members m ON f.member_id = m.id
   WHERE f.id = $id"
);
if (!$fee || $fee->num_rows === 0) {
    header('Location: index.php?err=' . urlencode('Payment record not found.'));
    exit;
}
$row = $fee->fetch_assoc();

// Only allow reminders for Pending / Overdue (Paid fees should get a receipt, not a reminder)
$status = $row['status'] ?? '';
if (strtolower($status) === 'paid') {
    header('Location: index.php?err=' . urlencode('This payment is already marked as Paid. Use Edit to re-send a receipt.'));
    exit;
}

require_once __DIR__ . '/../../includes/mailer.php';
$result = sendPaymentReminderEmail($db, $id);

if ($result['ok']) {
    $ok = 'Payment reminder emailed to ' . e($row['member_name']) . ' (Ref #' . e($row['receipt_no'] ?: $id) . ').';
    header('Location: index.php?ok=' . urlencode($ok));
} else {
    header('Location: index.php?err=' . urlencode('Reminder not sent to ' . $row['member_name'] . ': ' . $result['error']));
}
exit;
