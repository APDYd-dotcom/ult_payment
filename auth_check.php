<?php
// /payment/auth_check.php
session_start();

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/check_alerts.php';

if (!function_exists('ultDestroyCurrentSession')) {
    function ultDestroyCurrentSession(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
    }
}

 try {
        // Database connection
        $bdd = new PDO('mysql:host=localhost;dbname=ult_payment;charset=utf8', 'app_user', 'secure_password_123');
        $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if (!defined('SESSION_TIMEOUT')) {
            define('SESSION_TIMEOUT', getSessionTimeoutSeconds($bdd));
        }
    } catch (PDOException $e) {
        die('Database connection failed: ' . $e->getMessage());
    }

// 1. Must be logged in
if (!isset($_SESSION['email'])) {
    header('Location: /payment');
    exit();
}

// 2. Session timeout: deconnecter apres 15 minutes d'inactivite.
$now = time();
$lastActivity = (int) ($_SESSION['last_activity'] ?? $now);

if (($now - $lastActivity) > SESSION_TIMEOUT) {
    if (isset($_SESSION['userId'])) {
        logLogout($bdd, $_SESSION['userId']);
    }

    ultDestroyCurrentSession();
    header('Location: /payment?expired=1');
    exit();
}

$_SESSION['last_activity'] = $now;

if (($now - (int) ($_SESSION['last_alert_check'] ?? 0)) >= 60) {
    try {
        runSystemAlertChecks($bdd);
        $_SESSION['last_alert_check'] = $now;
    } catch (Throwable $alertError) {
        error_log('System alert check failed: ' . $alertError->getMessage());
    }
}

// Defense supplementaire : si un compte est verrouille pendant qu'une session existe,
// on coupe l'acces aux pages protegees.
$lockStmt = $bdd->prepare("SELECT is_locked FROM user WHERE userId = ? LIMIT 1");
$lockStmt->execute([$_SESSION['userId'] ?? 0]);
$isLocked = $lockStmt->fetchColumn();

if ($isLocked === false || (int) $isLocked === 1) {
    ultDestroyCurrentSession();
    header('Location: /payment?locked=1');
    exit();
}

// 3. The role required for this page must be defined as a constant
if (!defined('REQUIRED_ROLE')) {
    die('Required role not defined');
}

// 4. Check if the user's role matches the required role
if (REQUIRED_ROLE !== 'any' && $_SESSION['role'] !== REQUIRED_ROLE) {
    // Redirect to the correct dashboard
    if ($_SESSION['role'] === 'admin') {
        header('Location: /payment/admin/dashboard.php');
    } else {
        header('Location: /payment/student/dashboard.php');
    }
    exit();
}

// If we reach here, the user has the correct role for this page.
?>
