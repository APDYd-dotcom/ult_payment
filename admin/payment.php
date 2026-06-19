<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';

$error = '';
$success = false;

// --- Traduction personnalisée des erreurs ---
function translateError($message) {
    $translations = [
        'Étudiant introuvable.' => ' L\'étudiant avec ce matricule n\'existe pas.',
        'Tranche introuvable pour ce département.' => ' La tranche spécifiée n\'existe pas pour ce département.',
        'Impossible de créer le paiement.' => ' Une erreur est survenue lors de la création du paiement. Vérifiez les données saisies.',
    ];
    foreach ($translations as $key => $value) {
        if (strpos($message, $key) !== false) {
            return $value;
        }
    }
    // Si aucun message connu, on retourne le message original
    return '❌ ' . htmlspecialchars($message);
}

// --- Gestion du formulaire ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['Create'])) {
    $matricule  = trim($_POST['matricule'] ?? '');
    $amount     = floatval($_POST['amount'] ?? 0);
    $department = trim($_POST['department'] ?? '');
    $tranche    = trim($_POST['tranche'] ?? '');

    if (empty($matricule) || $amount <= 0 || empty($department) || empty($tranche)) {
        $error = '⚠️ Tous les champs sont obligatoires et le montant doit être supérieur à 0.';
    } else {
        try {
            $stmt = $bdd->prepare("CALL sp_payment_create_simple(?, ?, ?, ?)");
            $stmt->execute([$matricule, $amount, $department, $tranche]);
            // Succès → redirection avec message
            header('Location: payment.php?success=1');
            exit();
        } catch (PDOException $e) {
            // Traduire le message d'erreur
            $error = translateError($e->getMessage());
        }
    }
}

// --- Récupération des paiements ---
try {
    $stmtPayments = $bdd->query("SELECT * FROM vw_payment_details ORDER BY student_name ASC");
    $payments = $stmtPayments->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Erreur lors du chargement des paiements : ' . $e->getMessage());
}

// --- Message de succès après redirection ---
if (isset($_GET['success'])) {
    $success = true;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>ULT Payment System</title>
    <link rel="stylesheet" href="./styles.css">
    <style>
        /* Styles pour les messages */
        .message {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
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
        .message-icon {
            font-size: 1.4rem;
        }
    </style>
</head>
<body>

<div class="container">
    <aside id="sidebar" class="sidebar">
        <?php include 'sidebar.php'; ?>
    </aside>

    <main id="main-content" class="main-content">
        <section id="payment" class="page active">
            <h1 class="page-title">Payments</h1>

            <!-- Affichage des messages -->
            <?php if ($success): ?>
                <div class="message message-success">
                    <span class="message-icon"></span>
                    <span>Paiement créé avec succès.</span>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="message message-error">
                    <span class="message-icon"></span>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <div class="crud-container">

                <!-- Tableau des paiements -->
                <div class="table-section">
                    <table>
                        <thead>
                            <tr>
                                <th>Référence</th>
                                <th>Étudiant</th>
                                <th>Montant</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($payments): ?>
                                <?php foreach ($payments as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['payment_reference']) ?></td>
                                        <td><?= htmlspecialchars($row['student_name']) ?></td>
                                        <td><?= number_format($row['amount'], 2) ?> BIF</td>
                                        <td><?= date('Y-m-d H:i', strtotime($row['payment_date'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4">Aucun paiement trouvé.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Formulaire d'ajout -->
                <div class="form-section">
                    <h3>Ajouter un paiement</h3>
                    <form method="POST" action="payment.php">
                        <label for="matricule">Matricule étudiant</label>
                        <input id="matricule" type="text" name="matricule" placeholder="S-001" required>

                        <label for="amount">Montant</label>
                        <input id="amount" type="number" step="0.01" name="amount" placeholder="0.00" required>

                        <label for="department">Département</label>
                        <input id="department" type="text" name="department" placeholder="Science" required>

                        <label for="tranche">Tranche</label>
                        <input id="tranche" type="text" name="tranche" placeholder="Science Tranche 1" required>

                        <div class="buttons">
                            <button type="submit" name="Create">Créer</button>
                            <button type="reset">Effacer</button>
                        </div>
                    </form>
                </div>

            </div>
        </section>
    </main>
</div>

</body>
</html>