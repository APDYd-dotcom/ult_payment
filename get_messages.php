<?php
declare(strict_types=1);
define('REQUIRED_ROLE', 'any');
require __DIR__ . '/auth_check.php';
require_once __DIR__ . '/functions.php';

$with = (int)($_GET['with'] ?? 0);
$since = (int)($_GET['since'] ?? 0);
if ($with <= 0) { echo json_encode([]); exit(); }

$me = (int)$_SESSION['userId'];
$role = $_SESSION['role'] ?? '';

if ($role === 'admin') {
    $adminIds = $bdd->query("SELECT userId FROM user WHERE role='admin'")->fetchAll(PDO::FETCH_COLUMN);
    $in = implode(',', array_map('intval', $adminIds));
    $bdd->prepare("UPDATE messages SET is_read=1 WHERE receiver_id IN ($in) AND sender_id=? AND is_read=0")->execute([$with]);
    $stmt = $bdd->prepare("SELECT id, sender_id, receiver_id, message, created_at, TIMESTAMPDIFF(SECOND, created_at, NOW()) AS age_seconds FROM messages WHERE ((sender_id=? AND receiver_id IN ($in)) OR (sender_id IN ($in) AND receiver_id=?)) AND id > ? ORDER BY id ASC");
    $stmt->execute([$with, $with, $since]);
} else {
    $adminIds = $bdd->query("SELECT userId FROM user WHERE role='admin'")->fetchAll(PDO::FETCH_COLUMN);
    $in = implode(',', array_map('intval', $adminIds));
    $bdd->prepare("UPDATE messages SET is_read=1 WHERE receiver_id=? AND sender_id IN ($in) AND is_read=0")->execute([$me]);
    $stmt = $bdd->prepare("SELECT id, sender_id, receiver_id, message, created_at, TIMESTAMPDIFF(SECOND, created_at, NOW()) AS age_seconds FROM messages WHERE ((sender_id=? AND receiver_id IN ($in)) OR (sender_id IN ($in) AND receiver_id=?)) AND id > ? ORDER BY id ASC");
    $stmt->execute([$me, $me, $since]);
}
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
exit();
