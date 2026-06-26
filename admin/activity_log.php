<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('pcre.jit', '0');

define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';

// Récupérer tous les logs (derniers en premier)
$stmt = $bdd->query("
    SELECT
        al.id,
        al.action,
        al.details,
        al.ip,
        al.created_at,
        COALESCE(u.fullname, 'Inconnu') AS fullname,
        COALESCE(u.email, '') AS email
    FROM activity_logs al
    LEFT JOIN user u ON u.userId = al.userId
    ORDER BY al.created_at DESC
    LIMIT 100
");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Journal des activités - ULT Payment</title>
    <link rel="stylesheet" href="./styles.css">
    <style>
        .log-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .log-table th, .log-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .log-table th { background: #1e3a8a; color: white; }
        .action-login { color: #2563eb; }
        .action-logout { color: #6b7280; }
        .action-created { color: #16a34a; }
        .action-updated { color: #f59e0b; }
        .action-deleted { color: #dc2626; }
        .action-payment { color: #8b5cf6; }
    </style>
</head>
<body>
<div class="container">
    <aside id="sidebar" class="sidebar">
        <?php include 'sidebar.php'; ?>
    </aside>
    <main id="main-content" class="main-content">
        <section id="activity" class="page active">
            <h1 class="page-title">📋 Journal des activités</h1>

            <div class="table-section" style="overflow-x: auto;">
                <table class="log-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Utilisateur</th>
                            <th>Email</th>
                            <th>Action</th>
                            <th>Détails</th>
                            <th>IP</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($logs): ?>
                            <?php foreach ($logs as $row): ?>
                                <tr>
                                    <td><?= $row['id'] ?></td>
                                    <td><?= htmlspecialchars($row['fullname']) ?></td>
                                    <td><?= htmlspecialchars($row['email']) ?></td>
                                    <td class="action-<?= strpos($row['action'], 'login') !== false ? 'login' : (strpos($row['action'], 'logout') !== false ? 'logout' : (strpos($row['action'], 'created') !== false ? 'created' : (strpos($row['action'], 'updated') !== false ? 'updated' : (strpos($row['action'], 'deleted') !== false ? 'deleted' : (strpos($row['action'], 'payment') !== false ? 'payment' : ''))))) ?>">
                                        <?= htmlspecialchars(str_replace('_', ' ', $row['action'])) ?>
                                    </td>
                                    <td><?= htmlspecialchars($row['details'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['ip']) ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7">Aucune activité enregistrée.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
</body>
</html>
