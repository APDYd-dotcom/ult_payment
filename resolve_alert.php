<?php
define('REQUIRED_ROLE', 'any');
require_once __DIR__ . '/auth_check.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$csrfToken = $_POST['csrf_token'] ?? '';
$alertId = filter_input(INPUT_POST, 'alert_id', FILTER_VALIDATE_INT);

if (!$alertId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid alert']);
    exit();
}

if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit();
}

try {
    if (($_SESSION['role'] ?? '') === 'admin') {
        $stmt = $bdd->prepare("
            UPDATE alerts
            SET is_resolved = 1, resolved_at = NOW()
            WHERE id = ?
              AND is_resolved = 0
        ");
        $stmt->execute([$alertId]);
    } else {
        $stmt = $bdd->prepare("
            UPDATE alerts
            SET is_resolved = 1, resolved_at = NOW()
            WHERE id = ?
              AND user_id = ?
              AND is_resolved = 0
        ");
        $stmt->execute([$alertId, (int) $_SESSION['userId']]);
    }

    echo json_encode(['success' => true, 'resolved' => $stmt->rowCount() > 0]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
