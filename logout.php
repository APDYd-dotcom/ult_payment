<?php
// --- Inclusion des fonctions ---
require_once __DIR__ . '/functions.php';

// --- Démarrer la session si ce n'est pas déjà fait ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Vérifier la méthode HTTP (doit être POST) ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /payment');
    exit();
}

// --- Vérifier le token CSRF ---
if (!isset($_POST['logout_token']) || !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['logout_token'])) {
    die('Invalid request. Please try again.');
}

// --- Enregistrer la déconnexion dans login_history ---
if (isset($_SESSION['userId'])) {
    try {
        // Connexion à la base de données (utilise les mêmes identifiants que ton application)
        $bdd = new PDO('mysql:host=localhost;dbname=ult_payment;charset=utf8', 'app_user', 'secure_password_123');
        $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Appeler la fonction logLogout (définie dans functions.php)
        logLogout($bdd, $_SESSION['userId']);
    } catch (PDOException $e) {
        // En cas d'erreur, on continue quand même la déconnexion (on ne bloque pas)
        // Tu peux logger l'erreur si tu veux, mais ce n'est pas nécessaire pour l'utilisateur
    }
}

// --- Nettoyer la session ---
$_SESSION = [];

// --- Supprimer le cookie de session ---
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

// --- Détruire la session côté serveur ---
session_destroy();

// --- Rediriger vers la page de connexion ---
header("Location: /payment");
exit();