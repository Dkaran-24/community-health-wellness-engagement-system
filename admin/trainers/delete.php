<?php
/**
 * admin/trainers/delete.php — remove a trainer.
 */
require_once __DIR__ . '/../../includes/header.php';
$db = db();
$id = (int)($_GET['id'] ?? 0);
$t = $db->query("SELECT name FROM trainers WHERE id=$id")->fetch_assoc();
if (!$t) { header('Location: index.php?err=Trainer not found.'); exit; }
$stmt = $db->prepare("DELETE FROM trainers WHERE id=$id");
if ($stmt->execute()) header('Location: index.php?ok='.urlencode('Trainer "'.$t['name'].'" deleted.'));
else header('Location: index.php?err='.urlencode('Delete failed: '.$stmt->error));
$stmt->close(); exit;
