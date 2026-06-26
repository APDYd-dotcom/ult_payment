<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';

// Récupérer tous les historiques avec les noms des utilisateurs
$stmt = $bdd->query("
    SELECT h.*, u.email 
    FROM login_history h 
    JOIN user u ON h.userId = u.userId 
    ORDER BY h.login_time DESC
");
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Historique des connexions - ULT Payment</title>
    <link rel="stylesheet" href="./styles.css">
</head>
<body>
<div class="container">
    <aside id="sidebar" class="sidebar">
        <?php include __DIR__ . '/sidebar.php'; ?>
    </aside>
    <main id="main-content" class="main-content">
        <section id="history" class="page active">
            <h1 class="page-title">📋 Historique des connexions</h1>
            
            <div class="table-section">
                <table>
                    <thead>
                        <tr>
                            <!-- <th>#</th> -->
                            <th>Utilisateur</th>
                            <th>Email</th>
                            <th>IP</th>
                            <th>User Agent</th>
                            <th>Connexion</th>
                            <th>Déconnexion</th>
                            <th>Durée</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($history): ?>
                            <?php foreach ($history as $row): ?>
                                <tr>
                                    <!-- <td><?= $row['id'] ?></td> -->
                                    <td><?= htmlspecialchars($row['user_name'] ?? 'Inconnu') ?></td>
                                    <td><?= htmlspecialchars($row['email']) ?></td>
                                    <td><?= htmlspecialchars($row['ip']) ?></td>
                                    <td style="max-width:150px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                        <?= htmlspecialchars($row['user_agent'] ?? '') ?>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($row['login_time'])) ?></td>
                                    <td><?= $row['logout_time'] ? date('d/m/Y H:i', strtotime($row['logout_time'])) : 'En cours' ?></td>
                                    <td><?= $row['session_duration'] ? gmdate('H:i:s', $row['session_duration']) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8">Aucune connexion enregistrée</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
</body>
</html>