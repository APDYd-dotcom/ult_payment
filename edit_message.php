<?php
session_start();
header('Content-Type: application/json');
if (ob_get_length()) ob_end_clean();

if (!isset($_SESSION['userId'])) {
    echo json_encode(['success' => false, 'error' => 'Session expirée.']);
    exit;
}

$currentUserId = (int) $_SESSION['userId'];
$messageId = (int) ($_POST['message_id'] ?? 0);
$newMessage = trim($_POST['message'] ?? '');
$csrf = $_POST['csrf_token'] ?? '';

if (!is_string($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    echo json_encode(['success' => false, 'error' => 'Token invalide.']);
    exit;
}

if (!$messageId || empty($newMessage)) {
    echo json_encode(['success' => false, 'error' => 'Données manquantes.']);
    exit;
}

try {
    $bdd = new PDO('mysql:host=127.0.0.1;dbname=ult_payment;charset=utf8mb4', 'app_user', 'secure_password_123');
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Mise à jour uniquement si c'est l'auteur
    $stmt = $bdd->prepare("UPDATE messages SET message = ? WHERE id = ? AND sender_id = ?");
    $stmt->execute([$newMessage, $messageId, $currentUserId]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Message non trouvé ou non autorisé.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur SQL : ' . $e->getMessage()]);
}
exit;
?>
