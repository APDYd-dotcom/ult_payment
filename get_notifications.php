<?php
require_once __DIR__ . '/notification_endpoint_auth.php';

$bdd = requireNotificationSession(false);
$userId = (int) $_SESSION['userId'];

$countStmt = $bdd->prepare("
    SELECT COUNT(*)
    FROM notifications
    WHERE user_id = ?
      AND is_read = 0
");
$countStmt->execute([$userId]);
$unreadCount = (int) $countStmt->fetchColumn();

$listStmt = $bdd->prepare("
    SELECT id, title, message, link, is_read, created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC, id DESC
    LIMIT 10
");
$listStmt->execute([$userId]);
$notifications = $listStmt->fetchAll(PDO::FETCH_ASSOC);

notificationJsonResponse([
    'success' => true,
    'unread_count' => $unreadCount,
    'notifications' => array_map(static function (array $notification): array {
        return [
            'id' => (int) $notification['id'],
            'title' => $notification['title'],
            'message' => $notification['message'],
            'link' => $notification['link'],
            'is_read' => (int) $notification['is_read'],
            'created_at' => $notification['created_at'],
        ];
    }, $notifications),
]);
