<?php
declare(strict_types=1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('pcre.jit', '0');

define('REQUIRED_ROLE', 'student');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';

$message = '';
$messageType = '';

// --- Mise à jour du thème (logique inchangée) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_theme'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        die('CSRF');
    }

    try {
        $bdd->beginTransaction();
        $stmt = $bdd->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ? AND category = 'theme'");
        
        $themeFields = [
            'primary_color', 'secondary_color', 'background_color', 
            'font_family', 'logo_url', 'favicon_url', 'theme_name'
        ];
        
        foreach ($themeFields as $key) {
            if (isset($_POST[$key])) {
                $stmt->execute([trim($_POST[$key]), $key]);
            }
        }
        
        logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'THEME_UPDATED', 'Mise à jour du thème par l\'étudiant');
        $bdd->commit();
        
        $message = 'Thème enregistré avec succès.';
        $messageType = 'success';
    } catch (PDOException $e) {
        $bdd->rollBack();
        $message = 'Erreur lors de l\'enregistrement : ' . $e->getMessage();
        $messageType = 'error';
    }
}

// --- Réinitialisation ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_theme'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        die('CSRF');
    }

    try {
        $bdd->beginTransaction();
        $stmt = $bdd->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ? AND category = 'theme'");
        
        $defaults = [
            'primary_color' => '#1e3a8a',
            'secondary_color' => '#2563eb',
            'background_color' => '#f4f6f9',
            'font_family' => "'Segoe UI', sans-serif",
            'logo_url' => '',
            'favicon_url' => '',
            'theme_name' => 'ULT Payment'
        ];
        
        foreach ($defaults as $key => $value) {
            $stmt->execute([$value, $key]);
        }
        
        logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'THEME_RESET', 'Réinitialisation du thème par l\'étudiant');
        $bdd->commit();
        
        $message = 'Thème réinitialisé aux valeurs par défaut.';
        $messageType = 'success';
    } catch (PDOException $e) {
        $bdd->rollBack();
        $message = 'Erreur lors de la réinitialisation : ' . $e->getMessage();
        $messageType = 'error';
    }
}

// --- Récupération des paramètres du thème ---
try {
    $stmt = $bdd->query("SELECT setting_key, setting_value FROM settings WHERE category = 'theme'");
    $themeSettings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $themeSettings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    die('Erreur lors du chargement du thème : ' . $e->getMessage());
}

// Valeurs par défaut si absentes
$theme = array_merge([
    'primary_color' => '#1e3a8a',
    'secondary_color' => '#2563eb',
    'background_color' => '#f4f6f9',
    'font_family' => "'Segoe UI', sans-serif",
    'logo_url' => '',
    'favicon_url' => '',
    'theme_name' => 'ULT Payment'
], $themeSettings);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Thème - ULT Payment</title>
    <link rel="stylesheet" href="/payment/assets/css/app.css?v=20261030">
    <link rel="stylesheet" href="./styles.css?v=20261030">
    <?php loadTheme($bdd); ?>
    <link rel="stylesheet" href="/payment/assets/css/responsive.css?v=20261030">
    <link rel="stylesheet" href="/payment/assets/ui.css">
    <style>
        .theme-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        @media (max-width: 992px) { .theme-layout { grid-template-columns: 1fr; } }

        .theme-card {
            background: #fff;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow);
        }
        .theme-card h2 {
            color: var(--primary);
            font-size: 1.1rem;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--gray-100);
        }

        .theme-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        .theme-grid .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .theme-grid label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--gray-600);
        }
        .theme-grid input[type="text"],
        .theme-grid input[type="color"] {
            padding: 10px 12px;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            font-size: 0.95rem;
            background: var(--gray-50);
        }
        .theme-grid input[type="color"] {
            height: 44px;
            padding: 4px;
            cursor: pointer;
        }
        .theme-grid input:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
            background: #fff;
        }

        .theme-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
            flex-wrap: wrap;
        }

        .preview-card {
            background: #fff;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow);
        }
        .preview-card h2 {
            color: var(--primary);
            font-size: 1.1rem;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--gray-100);
        }

        .theme-preview {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .preview-swatch {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .preview-color {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: 1px solid var(--gray-200);
        }
        .preview-label {
            font-size: 0.85rem;
            color: var(--gray-600);
        }
        .preview-value {
            font-weight: 600;
            color: var(--gray-900);
        }
        .preview-font {
            font-size: 1.1rem;
            color: var(--gray-900);
            padding: 12px;
            background: var(--gray-50);
            border-radius: 8px;
        }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main id="main-content" class="main-content">
        <section class="page active">
            <h1 class="page-title">Personnalisation du thème</h1>

            <?php if ($message): ?>
                <div class="alert <?= $messageType === 'success' ? 'alert-success' : 'alert-danger' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="theme-layout">
                <!-- Formulaire de configuration -->
                <div class="theme-card">
                    <h2>Paramètres du thème</h2>
                    <form method="POST" action="theme_settings.php">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                        
                        <div class="theme-grid">
                            <div class="form-group">
                                <label for="theme_name">Nom du thème</label>
                                <input type="text" id="theme_name" name="theme_name" value="<?= htmlspecialchars($theme['theme_name']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="primary_color">Couleur primaire</label>
                                <input type="color" id="primary_color" name="primary_color" value="<?= htmlspecialchars($theme['primary_color']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="secondary_color">Couleur secondaire</label>
                                <input type="color" id="secondary_color" name="secondary_color" value="<?= htmlspecialchars($theme['secondary_color']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="background_color">Couleur de fond</label>
                                <input type="color" id="background_color" name="background_color" value="<?= htmlspecialchars($theme['background_color']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="font_family">Police</label>
                                <input type="text" id="font_family" name="font_family" value="<?= htmlspecialchars($theme['font_family']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="logo_url">URL du logo</label>
                                <input type="text" id="logo_url" name="logo_url" value="<?= htmlspecialchars($theme['logo_url']) ?>" placeholder="/uploads/themes/logo.png">
                            </div>
                            <div class="form-group">
                                <label for="favicon_url">URL du favicon</label>
                                <input type="text" id="favicon_url" name="favicon_url" value="<?= htmlspecialchars($theme['favicon_url']) ?>" placeholder="/uploads/themes/favicon.ico">
                            </div>
                        </div>

                        <div class="theme-actions">
                            <button type="submit" name="save_theme" class="btn btn-primary">Enregistrer</button>
                            <button type="submit" name="reset_theme" class="btn btn-secondary" onclick="return confirm('Réinitialiser le thème aux valeurs par défaut ?');">Réinitialiser</button>
                        </div>
                    </form>
                </div>

                <!-- Aperçu -->
                <div class="preview-card">
                    <h2>Aperçu du thème</h2>
                    <div class="theme-preview">
                        <div class="preview-swatch">
                            <div class="preview-color" style="background: <?= htmlspecialchars($theme['primary_color']) ?>;"></div>
                            <div>
                                <div class="preview-label">Primaire</div>
                                <div class="preview-value"><?= htmlspecialchars($theme['primary_color']) ?></div>
                            </div>
                        </div>
                        <div class="preview-swatch">
                            <div class="preview-color" style="background: <?= htmlspecialchars($theme['secondary_color']) ?>;"></div>
                            <div>
                                <div class="preview-label">Secondaire</div>
                                <div class="preview-value"><?= htmlspecialchars($theme['secondary_color']) ?></div>
                            </div>
                        </div>
                        <div class="preview-swatch">
                            <div class="preview-color" style="background: <?= htmlspecialchars($theme['background_color']) ?>;"></div>
                            <div>
                                <div class="preview-label">Fond</div>
                                <div class="preview-value"><?= htmlspecialchars($theme['background_color']) ?></div>
                            </div>
                        </div>
                        <div>
                            <div class="preview-label">Police</div>
                            <div class="preview-font" style="font-family: <?= htmlspecialchars($theme['font_family']) ?>;">
                                Aperçu de la police — 1234567890
                            </div>
                        </div>
                        <?php if (!empty($theme['logo_url'])): ?>
                        <div>
                            <div class="preview-label">Logo</div>
                            <img src="<?= htmlspecialchars($theme['logo_url']) ?>" alt="Logo" style="max-height: 60px; margin-top: 8px;">
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>
<script src="/payment/assets/js/responsive.js?v=20261030"></script>
</body>
</html>
