<?php
require_once __DIR__ . '/notification_endpoint_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    notificationJsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$bdd = requireNotificationSession(true);
$csrfToken = $_POST['csrf_token'] ?? '';

if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    notificationJsonResponse(['success' => false, 'message' => 'Invalid token'], 403);
}

$stmt = $bdd->prepare("
    UPDATE notifications
    SET is_read = 1
    WHERE user_id = ?
      AND is_read = 0
");
$stmt->execute([(int) $_SESSION['userId']]);

notificationJsonResponse(['success' => true]);
