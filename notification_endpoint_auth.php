<?php
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function notificationJsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit();
}

function destroyNotificationSession(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

function requireNotificationSession(bool $touchActivity = false): PDO
{
    if (empty($_SESSION['userId']) || empty($_SESSION['email'])) {
        notificationJsonResponse(['success' => false, 'expired' => true, 'redirect' => '/payment?expired=1'], 401);
    }

    try {
        $bdd = new PDO('mysql:host=localhost;dbname=ult_payment;charset=utf8', 'app_user', 'secure_password_123');
        $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if (!defined('SESSION_TIMEOUT')) {
            define('SESSION_TIMEOUT', getSessionTimeoutSeconds($bdd));
        }
    } catch (PDOException $e) {
        notificationJsonResponse(['success' => false, 'message' => 'Database unavailable'], 500);
    }

    $now = time();
    $lastActivity = (int) ($_SESSION['last_activity'] ?? $now);

    if (($now - $lastActivity) > SESSION_TIMEOUT) {
        try {
            logLogout($bdd, $_SESSION['userId']);
        } catch (Throwable $e) {
            // L'expiration de session ne doit pas dependre de l'historique.
        }

        destroyNotificationSession();
        notificationJsonResponse(['success' => false, 'expired' => true, 'redirect' => '/payment?expired=1'], 401);
    }

    if ($touchActivity) {
        $_SESSION['last_activity'] = $now;
    }

    return $bdd;
}
