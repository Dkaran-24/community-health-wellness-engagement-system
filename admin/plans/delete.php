<?php
/**
 * admin/plans/delete.php — remove a membership plan.
 */
require_once __DIR__ . '/../../includes/header.php';
$db = db();
$id = (int)($_GET['id'] ?? 0);
$p = $db->query("SELECT plan_name FROM membership_plans WHERE id=$id")->fetch_assoc();
if (!$p) { header('Location: index.php?err=Plan not found.'); exit; }
$stmt = $db->prepare("DELETE FROM membership_plans WHERE id=$id");
if ($stmt->execute()) header('Location: index.php?ok='.urlencode('Plan "'.$p['plan_name'].'" deleted.'));
else header('Location: index.php?err='.urlencode('Delete failed: '.$stmt->error));
$stmt->close(); exit;
