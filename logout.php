<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /payment');
    exit();
}


if (!isset($_POST['logout_token']) || !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['logout_token'])) {
   
    die('Invalid request. Please try again.');
}


$_SESSION = [];


if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),                 
        '',                             
        time() - 42000,              
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}


session_destroy();


header("Location: /payment");
exit();
?>