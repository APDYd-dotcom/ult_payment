<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';   // $bdd est défini ici

$message = '';
$messageType = ''; // success / error

// --- Traduction personnalisée des erreurs pour les départements ---
function translateDepartmentError($message) {
    $translations = [
        'Duplicate entry' => ' Ce nom de département existe déjà. Veuillez en choisir un autre.',
        'Cannot add or update a child row' => ' Ce département est référencé par d\'autres tables et ne peut pas être modifié.',
        'You have an error in your SQL syntax' => ' Erreur de syntaxe SQL. Vérifiez les données saisies.',
    ];
    foreach ($translations as $key => $value) {
        if (strpos($message, $key) !== false) {
            return $value;
        }
    }
    // Si aucun message connu, on retourne le message original avec une icône
    return '❌ ' . htmlspecialchars($message);
}

// --- Gestion du formulaire (INSERT) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['Create'])) {
    $Name     = trim($_POST['Name'] ?? '');
    $minerval = floatval($_POST['minerval'] ?? 0);

    // Validation basique
    if (empty($Name)) {
        $message = '⚠ Le nom du département est obligatoire.';
        $messageType = 'error';
    } elseif ($minerval <= 0) {
        $message = '⚠ Le montant du minerval doit être supérieur à 0.';
        $messageType = 'error';
    } else {
        try {
            // Insertion directe (pas de procédure stockée pour le moment)
            $stmt = $bdd->prepare("INSERT INTO department (name, minerval_total) VALUES (?, ?)");
            $stmt->execute([$Name, $minerval]);

            // Succès → redirection avec message
            header('Location: departement.php?success=1');
            exit();
        } catch (PDOException $e) {
            // Traduire le message d'erreur
            $message = translateDepartmentError($e->getMessage());
            $messageType = 'error';
        }
    }
}

// --- Affichage d'un message de succès si redirigé ---
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = ' Département créé avec succès.';
    $messageType = 'success';
}

// --- Récupération des départements ---
try {
    $stmtDepartments = $bdd->query("SELECT * FROM department ORDER BY name ASC");
    $departments = $stmtDepartments->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Erreur lors de la récupération des départements : ' . $e->getMessage());
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
<section id="department" class="page active">
<h1 class="page-title">Départements</h1>

<!-- Affichage des messages -->
<?php if ($message): ?>
<div class="message <?= $messageType === 'success' ? 'message-success' : 'message-error' ?>">
<span class="message-icon"></span>
<span><?= htmlspecialchars($message) ?></span>
</div>
<?php endif; ?>

<div class="crud-container">
<div class="table-section">
<table>
<thead>
<tr>
<th>Nom du département</th>
<th>Minerval total</th>
</tr>
</thead>
<tbody>
<?php if ($departments): ?>
<?php foreach ($departments as $row): ?>
<tr>
<td><?= htmlspecialchars($row['name'] ?? '') ?></td>
<td><?= number_format($row['minerval_total'], 2) ?> BIF</td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr><td colspan="2">Aucun département trouvé.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

<div class="form-section">
<h3>Ajouter un département</h3>
<form method="POST" action="departement.php">
<label for="Name">Nom du département</label>
<input id="Name" type="text" name="Name" required placeholder="e.g. Informatique">

<label for="minerval">Minerval total</label>
<input id="minerval" type="number" step="0.01" name="minerval" required placeholder="e.g. 785000">

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
