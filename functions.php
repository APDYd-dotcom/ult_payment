<?php
// /payment/functions.php

/**
 * Récupère l'adresse IP du client
 */
function getClientIP() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

/**
 * Récupère le User Agent du client
 */
function getUserAgent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
}

/**
 * Enregistre une connexion dans login_history
 * @param PDO $bdd
 * @param int $userId
 * @param string $email
 * @return int L'ID de la ligne insérée
 */
function logLogin($bdd, $userId, $email) {
    $ip = getClientIP();
    $userAgent = getUserAgent();
    
    $stmt = $bdd->prepare("INSERT INTO login_history (userId, email, ip, user_agent, login_time) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$userId, $email, $ip, $userAgent]);
    
    return $bdd->lastInsertId();
}

function logActivity($bdd, $userId, $fullname, $email, $action, $details = null) {
    $ip = getClientIP();
    $stmt = $bdd->prepare("INSERT INTO activity_logs (userId, action, details, ip, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$userId, $action, $details, $ip]);
}

/**
 * Met à jour l'heure de déconnexion et calcule la durée
 * @param PDO $bdd
 * @param int $userId
 */
function logLogout($bdd, $userId) {
    // Récupère la dernière session non terminée
    $stmt = $bdd->prepare("SELECT id, login_time FROM login_history WHERE userId = ? AND logout_time IS NULL ORDER BY login_time DESC LIMIT 1");
    $stmt->execute([$userId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($session) {
        $logoutTime = date('Y-m-d H:i:s');
        $duration = strtotime($logoutTime) - strtotime($session['login_time']);
        
        $update = $bdd->prepare("UPDATE login_history SET logout_time = ?, session_duration = ? WHERE id = ?");
        $update->execute([$logoutTime, $duration, $session['id']]);
    }
}
