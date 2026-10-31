<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('pcre.jit', '0');

define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';

$message = '';
$messageType = '';

// --- Mise à jour des paramètres ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        die('CSRF');
    }

    try {
        $bdd->beginTransaction();
        $stmt = $bdd->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'setting_') === 0) {
                $settingKey = substr($key, 8); // Enlever 'setting_'
                $stmt->execute([trim($value), $settingKey]);
            }
        }
        
        logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'SETTINGS_UPDATED', 'Mise à jour des paramètres système');
        $bdd->commit();
        
        $message = 'Paramètres enregistrés avec succès.';
        $messageType = 'success';
    } catch (PDOException $e) {
        $bdd->rollBack();
        $message = 'Erreur lors de l\'enregistrement : ' . $e->getMessage();
        $messageType = 'error';
    }
}

// --- Récupération des paramètres ---
try {
    $stmt = $bdd->query("SELECT setting_key, setting_value, category FROM settings ORDER BY category, setting_key");
    $settingsRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organiser par catégorie
    $settings = [];
    foreach ($settingsRows as $row) {
        $settings[$row['category']][$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    die('Erreur lors du chargement des paramètres : ' . $e->getMessage());
}

// Labels personnalisés pour les clés
$labels = [
    'tranche_1_due_date' => 'Date d\'échéance Tranche 1 (25%)',
    'tranche_1_derogation_deadline' => 'Date de dérogation Tranche 1',
    'tranche_2_due_date' => 'Date d\'échéance Tranche 2 (50%)',
    'tranche_2_derogation_deadline' => 'Date de dérogation Tranche 2',
    'tranche_3_due_date' => 'Date d\'échéance Tranche 3 (75%)',
    'tranche_3_derogation_deadline' => 'Date de dérogation Tranche 3',
    'tranche_4_due_date' => 'Date d\'échéance Tranche 4 (100%)',
    'tranche_4_derogation_deadline' => 'Date de dérogation Tranche 4',
    'penalty_grace_period' => 'Délai de grâce (jours)',
    'penalty_percent_level1' => 'Pénalité niveau 1 (%)',
    'penalty_days_level1' => 'Jours de retard niveau 1',
    'penalty_percent_level2' => 'Pénalité niveau 2 (%)',
    'penalty_days_level2' => 'Jours de retard niveau 2',
    'penalty_percent_level3' => 'Pénalité niveau 3 (%)',
    'penalty_days_level3' => 'Jours de retard niveau 3',
    'school_name' => 'Nom de l\'établissement',
    'school_address' => 'Adresse',
    'school_phone' => 'Téléphone',
    'school_nif' => 'NIF',
    'academic_year_start' => 'Début de l\'année académique',
    'academic_year_end' => 'Fin de l\'année académique',
    'max_login_attempts' => 'Tentatives de connexion max',
    'session_timeout_minutes' => 'Timeout de session (minutes)',
    'enable_2fa' => 'Activer la 2FA (0/1)',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Paramètres système - ULT Payment</title>
    <link rel="stylesheet" href="/payment/assets/css/app.css?v=20261030">
    <link rel="stylesheet" href="./styles.css?v=20261030">
    <?php loadTheme($bdd); ?>
    <link rel="stylesheet" href="/payment/assets/css/responsive.css?v=20261030">
    <link rel="stylesheet" href="/payment/assets/ui.css">
    <style>
        .settings-layout { display: flex; flex-direction: column; gap: 24px; }
        .settings-group {
            background: #fff;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow);
        }
        .settings-group h2 {
            color: var(--primary);
            font-size: 1.1rem;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--gray-100);
        }
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
        }
        .settings-grid .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .settings-grid label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--gray-600);
        }
        .settings-grid input {
            padding: 10px 12px;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            font-size: 0.95rem;
            background: var(--gray-50);
        }
        .settings-grid input:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
            background: #fff;
        }
        .settings-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 24px;
        }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main id="main-content" class="main-content">
        <section class="page active">
            <h1 class="page-title">Paramétrage du système</h1>

            <?php if ($message): ?>
                <div class="alert <?= $messageType === 'success' ? 'alert-success' : 'alert-danger' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="system_settings.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                
                <div class="settings-layout">
                    <?php foreach ($settings as $category => $items): ?>
                        <div class="settings-group">
                            <h2><?= ucfirst(htmlspecialchars($category === 'system' ? 'Règles métier' : $category)) ?></h2>
                            <div class="settings-grid">
                                <?php foreach ($items as $key => $value): ?>
                                    <div class="form-group">
                                        <label for="setting_<?= htmlspecialchars($key) ?>">
                                            <?= htmlspecialchars($labels[$key] ?? $key) ?>
                                        </label>
                                        <?php if (strpos($key, 'date') !== false || strpos($key, 'deadline') !== false || strpos($key, 'start') !== false || strpos($key, 'end') !== false): ?>
                                            <input type="date" id="setting_<?= htmlspecialchars($key) ?>" name="setting_<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                                        <?php elseif (strpos($key, 'percent') !== false || strpos($key, 'max_login') !== false || strpos($key, 'timeout') !== false || strpos($key, 'grace_period') !== false || strpos($key, 'days') !== false): ?>
                                            <input type="number" step="1" id="setting_<?= htmlspecialchars($key) ?>" name="setting_<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                                        <?php else: ?>
                                            <input type="text" id="setting_<?= htmlspecialchars($key) ?>" name="setting_<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="settings-actions">
                        <button type="submit" name="save_settings" class="btn btn-primary">Enregistrer les paramètres</button>
                    </div>
                </div>
            </form>
        </section>
    </main>
</div>
<script src="/payment/assets/js/responsive.js?v=20261030"></script>
</body>
</html>
