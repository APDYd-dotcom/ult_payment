<?php
// --- DÉBOGAGE (À retirer plus tard) ---
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

if (ob_get_length()) {
    ob_end_clean();
}

if (!isset($_SESSION['userId'])) {
    echo json_encode(['success' => false, 'error' => 'Session expirée. Reconnectez-vous.']);
    exit;
}

$currentUserId = (int) $_SESSION['userId'];
$receiverId = (int) ($_POST['receiver_id'] ?? 0);
$message = trim($_POST['message'] ?? '');
$csrf = $_POST['csrf_token'] ?? '';

if (!is_string($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    echo json_encode(['success' => false, 'error' => 'Token invalide. Rechargez la page.']);
    exit;
}

if (!$receiverId || empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Données manquantes.']);
    exit;
}

try {
    // Connexion PDO
    $bdd = new PDO('mysql:host=127.0.0.1;dbname=ult_payment;charset=utf8mb4', 'app_user', 'secure_password_123');
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Insertion
    $stmt = $bdd->prepare("INSERT INTO messages (sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$currentUserId, $receiverId, $message]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur SQL : ' . $e->getMessage()]);
}
exit;
?>
