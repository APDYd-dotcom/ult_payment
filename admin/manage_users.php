<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'unlock') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $userId = filter_input(INPUT_POST, 'userId', FILTER_VALIDATE_INT);

    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $error = 'Requête invalide. Veuillez réessayer.';
    } elseif (!$userId) {
        $error = 'Utilisateur invalide.';
    } else {
        try {
            $stmt = $bdd->prepare("
                UPDATE user
                SET is_locked = 0,
                    failed_attempts = 0,
                    last_failed_attempt = NULL,
                    unlock_time = NULL
                WHERE userId = ?
            ");
            $stmt->execute([$userId]);

            if ($stmt->rowCount() > 0) {
                $success = 'Compte déverrouillé avec succès.';
            } else {
                $error = 'Aucun compte correspondant trouvé.';
            }
        } catch (PDOException $e) {
            $error = 'Erreur lors du déverrouillage du compte.';
        }
    }
}

try {
    $stmt = $bdd->prepare("
        SELECT userId, fullname, email, role, created_at, failed_attempts, is_locked, last_failed_attempt
        FROM user
        ORDER BY is_locked DESC, failed_attempts DESC, fullname ASC
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Erreur lors du chargement des utilisateurs : ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des utilisateurs - ULT Payment</title>
    <link rel="stylesheet" href="./styles.css?v=1.2">
    <?php loadTheme($bdd); ?>
    <style>
        .status-badge {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 700;
        }
        .status-locked {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .status-active {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .inline-form {
            display: inline-flex;
            width: auto;
        }
        .btn-unlock {
            min-height: 34px;
            padding: 7px 12px;
            background: #16a34a;
        }
        .btn-unlock:hover {
            background: #15803d;
        }
        .muted-text {
            color: #64748b;
        }
    </style>
</head>
<body>
<div class="container">
    <aside id="sidebar" class="sidebar">
        <?php include __DIR__ . '/sidebar.php'; ?>
    </aside>

    <main id="main-content" class="main-content">
        <section id="manage-users" class="page active">
            <h1 class="page-title">Gestion des utilisateurs</h1>

            <?php if ($success): ?>
                <div class="message message-success">
                    <span class="message-icon">✓</span>
                    <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="message message-error">
                    <span class="message-icon">!</span>
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <div class="table-section">
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
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users): ?>
                            <?php foreach ($users as $user): ?>
                                <?php $isLocked = (int) $user['is_locked'] === 1; ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['fullname'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') ?></td>
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
        </section>
    </main>
</div>
</body>
</html>
