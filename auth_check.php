<?php
// /payment/auth_check.php
session_start();


 try {
        // Database connection
        $bdd = new PDO('mysql:host=localhost;dbname=ult_payment;charset=utf8', 'app_user', 'secure_password_123');
        $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die('Database connection failed: ' . $e->getMessage());
    }

// 1. Must be logged in
if (!isset($_SESSION['email'])) {
    header('Location: /payment');
    exit();
}

// 2. The role required for this page must be defined as a constant
if (!defined('REQUIRED_ROLE')) {
    die('Required role not defined');
}

// 3. Check if the user's role matches the required role
if ($_SESSION['role'] !== REQUIRED_ROLE) {
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