<?php
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('SESSION_TIMEOUT')) {
    define('SESSION_TIMEOUT', 900);
}

header('Content-Type: application/json; charset=utf-8');

if (!function_exists('ultDestroySessionForJson')) {
    function ultDestroySessionForJson(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

if (empty($_SESSION['userId']) || empty($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'expired' => true, 'redirect' => '/payment?expired=1']);
    exit();
}

$now = time();
$lastActivity = (int) ($_SESSION['last_activity'] ?? $now);

if (($now - $lastActivity) > SESSION_TIMEOUT) {
    try {
        $bdd = new PDO('mysql:host=localhost;dbname=ult_payment;charset=utf8', 'app_user', 'secure_password_123');
        $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        logLogout($bdd, $_SESSION['userId']);
    } catch (PDOException $e) {
        // La session doit quand meme expirer meme si l'historique echoue.
    }

    ultDestroySessionForJson();
    http_response_code(401);
    echo json_encode(['success' => false, 'expired' => true, 'redirect' => '/payment?expired=1']);
    exit();
}

$_SESSION['last_activity'] = $now;

echo json_encode([
    'success' => true,
    'timeout' => SESSION_TIMEOUT,
    'last_activity' => $_SESSION['last_activity'],
]);
