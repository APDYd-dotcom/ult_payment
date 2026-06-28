<?php
require_once __DIR__ . '/notification_endpoint_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    notificationJsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$bdd = requireNotificationSession(true);
$csrfToken = $_POST['csrf_token'] ?? '';
$notificationId = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT);

if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    notificationJsonResponse(['success' => false, 'message' => 'Invalid token'], 403);
}

if (!$notificationId) {
    notificationJsonResponse(['success' => false, 'message' => 'Invalid notification'], 400);
}

$stmt = $bdd->prepare("
    UPDATE notifications
    SET is_read = 1
    WHERE id = ?
      AND user_id = ?
");
$stmt->execute([$notificationId, (int) $_SESSION['userId']]);

notificationJsonResponse(['success' => true]);
