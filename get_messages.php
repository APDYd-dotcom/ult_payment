<?php
declare(strict_types=1);
define('REQUIRED_ROLE', 'any');
require __DIR__ . '/auth_check.php';
require_once __DIR__ . '/functions.php';

$with = (int)($_GET['with'] ?? 0);
$since = (int)($_GET['since'] ?? 0);
if ($with <= 0) { echo json_encode([]); exit(); }

// mark as read messages to me
$bdd->prepare("UPDATE messages SET is_read=1 WHERE receiver_id=? AND sender_id=? AND is_read=0")->execute([$_SESSION['userId'], $with]);

$stmt = $bdd->prepare("SELECT id, sender_id, receiver_id, message, created_at FROM messages WHERE ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)) AND id > ? ORDER BY id ASC");
$stmt->execute([$_SESSION['userId'], $with, $with, $_SESSION['userId'], $since]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
exit();
