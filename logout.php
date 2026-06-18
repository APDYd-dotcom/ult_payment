<?php
// Start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- 1. Ensure this script is accessed via POST (for security) ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // If someone tries GET, redirect to home
    header('Location: /payment');
    exit();
}

// --- 2. Verify CSRF token ---
// The token must be present in the POST data and match the one stored in session
if (!isset($_POST['logout_token']) || !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['logout_token'])) {
    // Invalid or missing token – likely a CSRF attack
    // You can log this or simply die
    die('Invalid request. Please try again.');
}

// --- 3. Clear all session variables ---
$_SESSION = [];

// --- 4. Delete the session cookie from the browser ---
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),                 // PHPSESSID by default
        '',                             // empty value
        time() - 42000,                 // expire in the past
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// --- 5. Destroy the session on the server ---
session_destroy();

// --- 6. Redirect to a public page (login or home) ---
header("Location: /payment");   // change to your login page if needed
exit();
?>