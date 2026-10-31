<?php
session_start();
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

$currentUserId = (int) ($_SESSION['userId'] ?? 0);
$receiverId = (int) ($_GET['receiver_id'] ?? 0);
$lastId = (int) ($_GET['last_id'] ?? 0);

if (!$currentUserId || !$receiverId) {
    echo json_encode(['messages' => []]);
    exit;
}

try {
    // Récupérer les messages entre les deux utilisateurs
    $sql = "
        SELECT id, sender_id, message, created_at 
        FROM messages 
        WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
        AND id > ?
        ORDER BY created_at ASC
    ";
    $stmt = $bdd->prepare($sql);
    $stmt->execute([$currentUserId, $receiverId, $receiverId, $currentUserId, $lastId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $messages = [];
    foreach ($rows as $row) {
        $messages[] = [
            'id' => $row['id'],
            'message' => $row['message'],
            'is_mine' => ($row['sender_id'] == $currentUserId),
            'created_at' => date('H:i', strtotime($row['created_at']))
        ];
    }

    // Marquer les messages comme lus
    $update = $bdd->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
    $update->execute([$receiverId, $currentUserId]);

    echo json_encode(['messages' => $messages]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
exit;
?>
