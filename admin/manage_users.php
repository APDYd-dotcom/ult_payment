<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success = '';
$error = '';

// --- GESTION DES ACTIONS (Déverrouillage / Changement de rôle) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $error = 'Requête invalide. Veuillez réessayer.';
    } else {
        $userId = filter_input(INPUT_POST, 'userId', FILTER_VALIDATE_INT);

        if (!$userId) {
            $error = 'Utilisateur invalide.';
        } 
        // ACTION 1 : Déverrouiller
        elseif ($action === 'unlock') {
            try {
                $stmt = $bdd->prepare("UPDATE user SET is_locked = 0, failed_attempts = 0, last_failed_attempt = NULL, unlock_time = NULL WHERE userId = ?");
                $stmt->execute([$userId]);
                $success = 'Compte déverrouillé avec succès.';
                logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'USER_UNLOCK', "ID: $userId");
            } catch (PDOException $e) {
                $error = 'Erreur lors du déverrouillage.';
            }
        }
        // ACTION 2 : Changer le rôle
        elseif ($action === 'change_role') {
            $newRole = $_POST['new_role'] ?? '';
            if (!in_array($newRole, ['admin', 'student'], true)) {
                $error = 'Rôle invalide.';
            } 
            // Sécurité : empêcher de modifier son propre rôle
            elseif ($userId == ($_SESSION['userId'] ?? 0)) {
                $error = 'Vous ne pouvez pas modifier votre propre rôle.';
            } 
            else {
                try {
                    // Vérifier le rôle actuel
                    $stmtCheck = $bdd->prepare("SELECT role FROM user WHERE userId = ?");
                    $stmtCheck->execute([$userId]);
                    $currentRole = $stmtCheck->fetchColumn();

                    // Si on retire le rôle admin, vérifier qu'il reste au moins un autre admin
                    if ($currentRole === 'admin' && $newRole !== 'admin') {
                        $stmtCount = $bdd->query("SELECT COUNT(*) FROM user WHERE role = 'admin'");
                        $adminCount = $stmtCount->fetchColumn();
                        if ($adminCount <= 1) {
                            $error = 'Impossible de retirer le rôle du dernier administrateur restant.';
                        }
                    }

                    if (empty($error)) {
                        $stmt = $bdd->prepare("UPDATE user SET role = ? WHERE userId = ?");
                        $stmt->execute([$newRole, $userId]);
                        $success = 'Rôle mis à jour avec succès.';
                        logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'USER_ROLE_CHANGE', "ID: $userId -> $newRole");
                    }
                } catch (PDOException $e) {
                    $error = 'Erreur lors du changement de rôle : ' . $e->getMessage();
                }
            }
        }
    }
}

// --- RÉCUPÉRATION DES UTILISATEURS ---
try {
    $stmt = $bdd->prepare("SELECT userId, fullname, email, role, created_at, failed_attempts, is_locked, last_failed_attempt FROM user ORDER BY is_locked DESC, failed_attempts DESC, fullname ASC");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Erreur lors du chargement des utilisateurs : ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="stylesheet" href="/payment/assets/css/app.css?v=20261030">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des utilisateurs - ULT Payment</title>
    <link rel="stylesheet" href="./styles.css?v=20261030">
    <?php loadTheme($bdd); ?>
    <style>
        .status-badge { display: inline-flex; align-items: center; min-height: 28px; padding: 4px 10px; border-radius: 999px; font-size: 0.85rem; font-weight: 700; }
        .status-locked { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .status-active { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .inline-form { display: inline-flex; width: auto; gap: 5px; align-items: center; }
        .btn-unlock { min-height: 34px; padding: 7px 12px; background: #16a34a; color: white; border: none; border-radius: 6px; cursor: pointer; }
        .btn-unlock:hover { background: #15803d; }
        .muted-text { color: #64748b; }
        .select-role { padding: 5px; border-radius: 4px; border: 1px solid #cbd5e1; }
        .btn-save-role { padding: 5px 10px; background: #2563eb; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .btn-save-role:hover { background: #1d4ed8; }
    </style>
    <link rel="stylesheet" href="/payment/assets/css/responsive.css?v=20261030">
    <link rel="stylesheet" href="/payment/assets/ui.css">
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main id="main-content" class="main-content">
        <section id="manage-users" class="page active">
            <h1 class="page-title">Gestion des utilisateurs</h1>

            <?php if ($success): ?>
                <div class="message message-success"><span class="message-icon">✓</span> <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="message message-error"><span class="message-icon">!</span> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <div class="table-section">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Rôle</th>
                                <th>Statut</th>
                                <th>Tentatives échouées / 3</th>
                                <th>Dernière tentative</th>
                                <th>Création</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($users): ?>
                                <?php foreach ($users as $user): ?>
                                    <?php $isLocked = (int) $user['is_locked'] === 1; ?>
                                    <tr>
                                        <td><?= htmlspecialchars($user['fullname'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <form method="POST" class="inline-form">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="action" value="change_role">
                                                <input type="hidden" name="userId" value="<?= (int) $user['userId'] ?>">
                                                <select name="new_role" class="select-role">
                                                    <option value="student" <?= $user['role'] === 'student' ? 'selected' : '' ?>>Étudiant</option>
                                                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Administrateur</option>
                                                </select>
                                                <button type="submit" class="btn-save-role">OK</button>
                                            </form>
                                        </td>
                                        <td>
                                            <span class="status-badge <?= $isLocked ? 'status-locked' : 'status-active' ?>">
                                                <?= $isLocked ? 'Verrouillé' : 'Déverrouillé' ?>
                                            </span>
                                        </td>
                                        <td><?= (int) $user['failed_attempts'] ?></td>
                                        <td>
                                            <?= $user['last_failed_attempt']
                                                ? date('d/m/Y H:i', strtotime($user['last_failed_attempt']))
                                                : '<span class="muted-text">-</span>' ?>
                                        </td>
                                        <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                                        <td>
                                            <?php if ($isLocked): ?>
                                                <form method="POST" class="inline-form">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="action" value="unlock">
                                                    <input type="hidden" name="userId" value="<?= (int) $user['userId'] ?>">
                                                    <button type="submit" class="btn-unlock">Déverrouiller</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="muted-text">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="8">Aucun utilisateur trouvé.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</div>
<script src="/payment/assets/js/responsive.js?v=20261030"></script>
</body>
</html>
