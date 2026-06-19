<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('REQUIRED_ROLE', 'admin');

require __DIR__ . '/../auth_check.php';  

// --- Récupération des données ---
try {
    $stmtMailing = $bdd->query("SELECT * FROM mailing_list ORDER BY student_name ASC");
    $mailinglist = $stmtMailing->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Erreur lors de la récupération de la mailing list : ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>ULT Payment System</title>
    <link rel="stylesheet" href="./styles.css">
</head>
<body>

<div class="container">
    <aside id="sidebar" class="sidebar">
        <?php include  'sidebar.php'; ?>  
    </aside>

    <main id="main-content" class="main-content">
        <section id="mailing" class="page active">
            <h1 class="page-title">Mailing List</h1>

            <div class="crud-container">
                <div class="table-section">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Student Name</th>
                                <th>Email Status</th>
                                <th>Created At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($mailinglist): ?>
                                <?php foreach ($mailinglist as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['id']) ?></td>
                                        <td><?= htmlspecialchars($row['student_name']) ?></td>
                                        <td><?= htmlspecialchars($row['email_status']) ?></td>
                                        <td><?= htmlspecialchars($row['created_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4">Aucun abonné trouvé.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</div>

</body>
</html>