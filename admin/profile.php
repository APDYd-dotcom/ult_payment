<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('pcre.jit', '0');

define('REQUIRED_ROLE', 'admin');


require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../two_factor.php';

$error = '';
$success = '';
$fullname = $_SESSION['fullname'] ?? '';
$email = $_SESSION['email'] ?? '';
$role = $_SESSION['role'] ?? '';

// --- Traitement du formulaire de mise à jour des infos ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_info'])) {
        $newFullname = trim($_POST['fullname'] ?? '');
        $newEmail = trim($_POST['email'] ?? '');

        // Validation
        if (empty($newFullname) || strlen($newFullname) < 3) {
            $error = 'Le nom complet doit contenir au moins 3 caractères.';
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Veuillez entrer une adresse email valide.';
        } else {
            try {
                // Vérifier si l'email est déjà utilisé par un autre utilisateur
                $stmt = $bdd->prepare("SELECT userId FROM user WHERE email = ? AND userId != ?");
                $stmt->execute([$newEmail, $_SESSION['userId']]);
                if ($stmt->fetch()) {
                    $error = 'Cet email est déjà utilisé par un autre compte.';
                } else {
                    // Mise à jour en base
                    $stmt = $bdd->prepare("UPDATE user SET fullname = ?, email = ? WHERE userId = ?");
                    $stmt->execute([$newFullname, $newEmail, $_SESSION['userId']]);

                    // Mise à jour de la session
                    $_SESSION['fullname'] = $newFullname;
                    $_SESSION['email'] = $newEmail;

                    $success = '✅ Vos informations ont été mises à jour avec succès.';
                    $fullname = $newFullname;
                    $email = $newEmail;
                }
            } catch (PDOException $e) {
                $error = 'Erreur lors de la mise à jour : ' . $e->getMessage();
            }
        }
    }

    // --- Traitement du changement de mot de passe ---
    if (isset($_POST['update_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'Tous les champs du mot de passe sont obligatoires.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'Le nouveau mot de passe doit contenir au moins 6 caractères.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Les mots de passe ne correspondent pas.';
        } else {
            try {
                // Récupérer le mot de passe actuel
                $stmt = $bdd->prepare("SELECT password FROM user WHERE userId = ?");
                $stmt->execute([$_SESSION['userId']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($currentPassword, $user['password'])) {
                    // Hacher le nouveau mot de passe
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $bdd->prepare("UPDATE user SET password = ? WHERE userId = ?");
                    $stmt->execute([$hashedPassword, $_SESSION['userId']]);

                    $success = '✅ Votre mot de passe a été changé avec succès.';
                } else {
                    $error = 'Le mot de passe actuel est incorrect.';
                }
            } catch (PDOException $e) {
                $error = 'Erreur lors du changement de mot de passe : ' . $e->getMessage();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ultTwoFactorHandleProfilePost($bdd, $error, $success);
}

// --- Récupérer les informations complètes de l'utilisateur (pour affichage) ---
$stmt = $bdd->prepare("SELECT fullname, email, role, created_at FROM user WHERE userId = ?");
$stmt->execute([$_SESSION['userId']]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userData) {
    header('Location: /payment');
    exit();
}

$twoFactorState = ultTwoFactorProfileState($bdd);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Mon profil - ULT Payment</title>
    <link rel="stylesheet" href="./styles.css">
    <style>
        .profile-container {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        .profile-card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .profile-card h3 {
            color: #1e3a8a;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 0.75rem;
        }
        .profile-row {
            display: flex;
            margin-bottom: 0.8rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .profile-label {
            font-weight: 600;
            width: 150px;
            color: #475569;
        }
        .profile-value {
            color: #1e293b;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.3rem;
        }
        .form-group input {
            width: 100%;
            padding: 0.7rem;
            border: 1px solid #ccc;
            border-radius: 6px;
        }
        .form-group input:focus {
            border-color: #2563eb;
            outline: none;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }
        .btn {
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.2s;
        }
        .btn-primary {
            background: #2563eb;
            color: white;
        }
        .btn-primary:hover {
            background: #1d4ed8;
        }
        .btn-secondary {
            background: #e2e8f0;
            color: #1e293b;
        }
        .btn-secondary:hover {
            background: #cbd5e1;
        }
        .btn-danger {
            background: #dc2626;
            color: white;
        }
        .btn-danger:hover {
            background: #b91c1c;
        }
        .message {
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }
        .message-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        @media (max-width: 768px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <aside id="sidebar" class="sidebar">
        <?php include 'sidebar.php'; ?>
    </aside>
    <main id="main-content" class="main-content">
        <section id="profile" class="page active">
            <h1 class="page-title">👤 Mon profil</h1>

            <?php if ($success): ?>
                <div class="message message-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="message message-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="profile-container">

                <!-- Carte d'informations -->
                <div class="profile-card">
                    <h3>📋 Informations personnelles</h3>
                    <div class="profile-row">
                        <span class="profile-label">Nom complet</span>
                        <span class="profile-value"><?= htmlspecialchars($userData['fullname']) ?></span>
                    </div>
                    <div class="profile-row">
                        <span class="profile-label">Email</span>
                        <span class="profile-value"><?= htmlspecialchars($userData['email']) ?></span>
                    </div>
                    <div class="profile-row">
                        <span class="profile-label">Rôle</span>
                        <span class="profile-value">
                            <?= $userData['role'] === 'admin' ? 'Administrateur' : 'Étudiant' ?>
                        </span>
                    </div>
                    <div class="profile-row">
                        <span class="profile-label">Membre depuis</span>
                        <span class="profile-value"><?= date('d/m/Y', strtotime($userData['created_at'])) ?></span>
                    </div>
                </div>

                <?php include __DIR__ . '/../two_factor_profile_section.php'; ?>

                <!-- Formulaire de mise à jour des informations -->
                <div class="profile-card">
                    <h3>✏️ Modifier mes informations</h3>
                    <form method="POST" action="profile.php">
                        <div class="form-group">
                            <label for="fullname">Nom complet</label>
                            <input type="text" id="fullname" name="fullname" value="<?= htmlspecialchars($fullname) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
                        </div>
                        <button type="submit" name="update_info" class="btn btn-primary">Mettre à jour</button>
                    </form>
                </div>

                <!-- Formulaire de changement de mot de passe -->
                <div class="profile-card">
                    <h3>🔒 Changer mon mot de passe</h3>
                    <form method="POST" action="profile.php">
                        <div class="form-group">
                            <label for="current_password">Mot de passe actuel</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        <div class="form-group">
                            <label for="new_password">Nouveau mot de passe (min. 6 caractères)</label>
                            <input type="password" id="new_password" name="new_password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirmer le nouveau mot de passe</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                        <button type="submit" name="update_password" class="btn btn-danger">Changer le mot de passe</button>
                    </form>
                </div>

            </div>
        </section>
    </main>
</div>
</body>
</html>
