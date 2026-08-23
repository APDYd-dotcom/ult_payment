<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('pcre.jit', '0');

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../two_factor.php';

$error = '';
$success = '';
$hasMatriculeColumn = tableColumnExists($bdd, 'user', 'matricule');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_email'])) {
        $newEmail = trim($_POST['email'] ?? '');

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Veuillez entrer une adresse email valide.';
        } else {
            try {
                $stmt = $bdd->prepare("SELECT userId FROM user WHERE email = ? AND userId != ? LIMIT 1");
                $stmt->execute([$newEmail, $_SESSION['userId']]);

                if ($stmt->fetch()) {
                    $error = 'Cet email est déjà utilisé par un autre compte.';
                } else {
                    $stmt = $bdd->prepare("UPDATE user SET email = ? WHERE userId = ?");
                    $stmt->execute([$newEmail, $_SESSION['userId']]);

                    $_SESSION['email'] = $newEmail;
                    logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'student_account_email_updated', 'Email du compte etudiant mis a jour.');
                    $success = 'Votre email a été mis à jour avec succès.';
                }
            } catch (PDOException $e) {
                $error = 'Erreur lors de la mise à jour : ' . $e->getMessage();
            }
        }
    }

    if (isset($_POST['update_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $error = 'Tous les champs du mot de passe sont obligatoires.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'Le nouveau mot de passe doit contenir au moins 6 caractères.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Les mots de passe ne correspondent pas.';
        } else {
            try {
                $stmt = $bdd->prepare("SELECT password FROM user WHERE userId = ? LIMIT 1");
                $stmt->execute([$_SESSION['userId']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user || !password_verify($currentPassword, $user['password'])) {
                    $error = 'Le mot de passe actuel est incorrect.';
                } else {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $bdd->prepare("UPDATE user SET password = ? WHERE userId = ?");
                    $stmt->execute([$hashedPassword, $_SESSION['userId']]);
                    logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'student_account_password_updated', 'Mot de passe du compte etudiant mis a jour.');
                    $success = 'Votre mot de passe a été changé avec succès.';
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

$selectFields = $hasMatriculeColumn
    ? 'fullname, email, role, matricule, created_at'
    : 'fullname, email, role, created_at';
$stmt = $bdd->prepare("SELECT {$selectFields} FROM user WHERE userId = ? LIMIT 1");
$stmt->execute([$_SESSION['userId']]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userData) {
    header('Location: /payment');
    exit();
}

$twoFactorState = ultTwoFactorProfileState($bdd);

$displayEmail = (string) ($userData['email'] ?? '');
$studentEmailSuffix = '@student.local';
if (substr($displayEmail, -strlen($studentEmailSuffix)) === $studentEmailSuffix) {
    $displayEmail = '';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Mon profil - ULT Payment</title>
    <link rel="stylesheet" href="./styles.css?v=1.4">
    <?php loadTheme($bdd); ?>
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
            color: var(--primary-color, #1e3a8a);
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
        .btn {
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-primary {
            background: var(--secondary-color, #2563eb);
            color: white;
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
            .profile-row {
                flex-direction: column;
                gap: 0.25rem;
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
            <h1 class="page-title">Mon profil</h1>

            <?php if ($success): ?>
                <div class="message message-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="message message-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="profile-container">
                <div class="profile-card">
                    <h3>Informations personnelles</h3>
                    <div class="profile-row">
                        <span class="profile-label">Nom complet</span>
                        <span class="profile-value"><?= htmlspecialchars($userData['fullname']) ?></span>
                    </div>
                    <?php if ($hasMatriculeColumn): ?>
                        <div class="profile-row">
                            <span class="profile-label">Matricule</span>
                            <span class="profile-value"><?= htmlspecialchars($userData['matricule'] ?? '') ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="profile-row">
                        <span class="profile-label">Email</span>
                        <span class="profile-value"><?= $displayEmail !== '' ? htmlspecialchars($displayEmail) : 'Non défini' ?></span>
                    </div>
                    <div class="profile-row">
                        <span class="profile-label">Rôle</span>
                        <span class="profile-value">Étudiant</span>
                    </div>
                    <?php if ($currentStudent): ?>
                        <div class="profile-row">
                            <span class="profile-label">Dossier</span>
                            <span class="profile-value">
                                <?= htmlspecialchars($currentStudent['name'], ENT_QUOTES, 'UTF-8') ?>
                                -
                                <?= htmlspecialchars($currentStudent['department_name'] ?? 'Departement non defini', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                    <?php else: ?>
                        <div class="profile-row">
                            <span class="profile-label">Dossier</span>
                            <span class="profile-value">A completer dans Mon dossier</span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php include __DIR__ . '/../two_factor_profile_section.php'; ?>

                <div class="grid-2">
                    <div class="profile-card">
                        <h3>Ajouter ou modifier mon email</h3>
                        <form method="POST" action="profile.php">
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input id="email" type="email" name="email" value="<?= htmlspecialchars($displayEmail) ?>" required>
                            </div>
                            <button type="submit" name="update_email" class="btn btn-primary">Enregistrer</button>
                        </form>
                    </div>

                    <div class="profile-card">
                        <h3>Changer mon mot de passe</h3>
                        <form method="POST" action="profile.php">
                            <div class="form-group">
                                <label for="current_password">Mot de passe actuel</label>
                                <input id="current_password" type="password" name="current_password" required>
                            </div>
                            <div class="form-group">
                                <label for="new_password">Nouveau mot de passe</label>
                                <input id="new_password" type="password" name="new_password" required minlength="6">
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirmer le mot de passe</label>
                                <input id="confirm_password" type="password" name="confirm_password" required minlength="6">
                            </div>
                            <button type="submit" name="update_password" class="btn btn-primary">Changer</button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>
</body>
</html>
