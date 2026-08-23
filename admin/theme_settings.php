<?php
define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$messageType = '';
$theme = getThemeSettings($bdd);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
            throw new RuntimeException('Session expiree. Veuillez reessayer.');
        }

        if (isset($_POST['reset_theme'])) {
            foreach (getDefaultThemeSettings() as $key => $value) {
                setSetting($bdd, $key, (string) $value, 'theme');
            }
            logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'theme_reset', 'Theme settings reset to defaults');
            header('Location: theme_settings.php?success=reset');
            exit();
        }

        $primaryColor = trim((string) ($_POST['primary_color'] ?? ''));
        $secondaryColor = trim((string) ($_POST['secondary_color'] ?? ''));
        $backgroundColor = trim((string) ($_POST['background_color'] ?? ''));
        $fontFamily = normalizeThemeFont((string) ($_POST['font_family'] ?? ''));
        $themeName = trim((string) ($_POST['theme_name'] ?? 'ULT Payment'));

        foreach ([
            'couleur primaire' => $primaryColor,
            'couleur secondaire' => $secondaryColor,
            'couleur de fond' => $backgroundColor,
        ] as $label => $color) {
            if (!isValidThemeColor($color)) {
                throw new RuntimeException("La {$label} doit etre un code hexadecimal valide.");
            }
        }

        if ($themeName === '' || strlen($themeName) > 80) {
            throw new RuntimeException('Le nom du theme est obligatoire et limite a 80 caracteres.');
        }

        $logoUrl = uploadThemeImage($_FILES['logo_file'] ?? [], 'logo');
        $faviconUrl = uploadThemeImage($_FILES['favicon_file'] ?? [], 'favicon');

        $values = [
            'primary_color' => $primaryColor,
            'secondary_color' => $secondaryColor,
            'background_color' => $backgroundColor,
            'font_family' => $fontFamily,
            'theme_name' => $themeName,
        ];

        if ($logoUrl !== '') {
            $values['logo_url'] = $logoUrl;
        }
        if ($faviconUrl !== '') {
            $values['favicon_url'] = $faviconUrl;
        }

        foreach ($values as $key => $value) {
            setSetting($bdd, $key, (string) $value, 'theme');
        }

        logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'theme_updated', 'Theme settings updated');
        header('Location: theme_settings.php?success=saved');
        exit();
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $messageType = 'error';
        $theme = array_merge($theme, [
            'primary_color' => $_POST['primary_color'] ?? $theme['primary_color'],
            'secondary_color' => $_POST['secondary_color'] ?? $theme['secondary_color'],
            'background_color' => $_POST['background_color'] ?? $theme['background_color'],
            'font_family' => $_POST['font_family'] ?? $theme['font_family'],
            'theme_name' => $_POST['theme_name'] ?? $theme['theme_name'],
        ]);
    }
}

if (isset($_GET['success'])) {
    $message = $_GET['success'] === 'reset'
        ? 'Le theme par defaut a ete restaure.'
        : 'Le theme a ete enregistre avec succes.';
    $messageType = 'success';
    $theme = getThemeSettings($bdd);
}

$fontOptions = getAllowedThemeFonts();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des themes - ULT Payment System</title>
    <link rel="stylesheet" href="./styles.css?v=1.3">
    <?php loadTheme($bdd); ?>
    <style>
        .theme-layout { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(280px, .9fr); gap: 20px; align-items: start; }
        .theme-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .theme-field-full { grid-column: 1 / -1; }
        .theme-swatch { width: 100%; height: 44px; padding: 4px; cursor: pointer; }
        .asset-preview { margin-top: 8px; color: #64748b; font-size: .9rem; word-break: break-word; }
        .asset-preview img { display: block; max-width: 180px; max-height: 70px; object-fit: contain; margin-top: 8px; border: 1px solid #dbe3ef; border-radius: 8px; padding: 8px; background: #fff; }
        .theme-preview { background: var(--background-color); border: 1px solid #dbe3ef; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .theme-preview-header { background: var(--primary-color); color: #fff; padding: 18px; font-family: var(--font-family); }
        .theme-preview-body { padding: 18px; font-family: var(--font-family); }
        .theme-preview-body h3 { color: var(--primary-color); margin-bottom: 8px; }
        .theme-preview-actions { display: flex; gap: 10px; margin-top: 16px; flex-wrap: wrap; }
        .theme-preview-primary { background: var(--secondary-color); color: #fff; border: none; border-radius: 8px; padding: 10px 14px; }
        .theme-preview-secondary { background: #fff; color: var(--primary-color); border: 1px solid var(--primary-color); border-radius: 8px; padding: 10px 14px; }
        .theme-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 18px; }
        .theme-actions button { width: auto; min-width: 150px; }
        .btn-danger { background: #dc2626; }
        .btn-danger:hover { background: #b91c1c; }
        @media (max-width: 900px) { .theme-layout, .theme-form-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="container">
    <aside id="sidebar" class="sidebar">
        <?php include 'sidebar.php'; ?>
    </aside>

    <main id="main-content" class="main-content">
        <section class="page active">
            <h1 class="page-title">Gestion des themes</h1>

            <?php if ($message): ?>
                <div class="message <?= $messageType === 'success' ? 'message-success' : 'message-error' ?>">
                    <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <div class="theme-layout">
                <div class="form-section">
                    <form method="POST" enctype="multipart/form-data" id="theme-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                        <div class="theme-form-grid">
                            <label>
                                Nom du theme
                                <input type="text" name="theme_name" id="theme_name" maxlength="80" required value="<?= htmlspecialchars($theme['theme_name'], ENT_QUOTES, 'UTF-8') ?>">
                            </label>

                            <label>
                                Typographie
                                <select name="font_family" id="font_family">
                                    <?php foreach ($fontOptions as $value => $label): ?>
                                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $theme['font_family'] === $value ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>
                                Couleur primaire
                                <input class="theme-swatch" type="color" name="primary_color" id="primary_color" value="<?= htmlspecialchars($theme['primary_color'], ENT_QUOTES, 'UTF-8') ?>">
                            </label>

                            <label>
                                Couleur secondaire
                                <input class="theme-swatch" type="color" name="secondary_color" id="secondary_color" value="<?= htmlspecialchars($theme['secondary_color'], ENT_QUOTES, 'UTF-8') ?>">
                            </label>

                            <label>
                                Couleur de fond
                                <input class="theme-swatch" type="color" name="background_color" id="background_color" value="<?= htmlspecialchars($theme['background_color'], ENT_QUOTES, 'UTF-8') ?>">
                            </label>

                            <label>
                                Logo
                                <input type="file" name="logo_file" accept="image/png,image/jpeg,image/gif,image/webp,image/x-icon">
                                <span class="asset-preview">
                                    Actuel: <?= $theme['logo_url'] ? htmlspecialchars($theme['logo_url'], ENT_QUOTES, 'UTF-8') : 'logo texte par defaut' ?>
                                    <?php if ($theme['logo_url']): ?>
                                        <img src="<?= htmlspecialchars($theme['logo_url'], ENT_QUOTES, 'UTF-8') ?>" alt="Logo actuel">
                                    <?php endif; ?>
                                </span>
                            </label>

                            <label class="theme-field-full">
                                Favicon
                                <input type="file" name="favicon_file" accept="image/png,image/jpeg,image/gif,image/webp,image/x-icon">
                                <span class="asset-preview">Actuel: <?= $theme['favicon_url'] ? htmlspecialchars($theme['favicon_url'], ENT_QUOTES, 'UTF-8') : 'aucun favicon personnalise' ?></span>
                            </label>
                        </div>

                        <div class="theme-actions">
                            <button type="submit" name="save_theme">Enregistrer</button>
                            <button class="btn-danger" type="submit" name="reset_theme" onclick="return confirm('Restaurer le theme par defaut ?');">Reinitialiser</button>
                        </div>
                    </form>
                </div>

                <div class="theme-preview" id="theme-preview">
                    <div class="theme-preview-header">
                        <strong id="preview-name"><?= htmlspecialchars($theme['theme_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                    <div class="theme-preview-body">
                        <h3>Apercu du theme</h3>
                        <p>Les couleurs et la police selectionnees seront appliquees aux pages admin et etudiant.</p>
                        <div class="theme-preview-actions">
                            <button class="theme-preview-primary" type="button">Action principale</button>
                            <button class="theme-preview-secondary" type="button">Action secondaire</button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('theme-form');
    const previewName = document.getElementById('preview-name');

    function applyPreview() {
        document.documentElement.style.setProperty('--primary-color', form.primary_color.value);
        document.documentElement.style.setProperty('--secondary-color', form.secondary_color.value);
        document.documentElement.style.setProperty('--background-color', form.background_color.value);
        document.documentElement.style.setProperty('--font-family', form.font_family.value);
        previewName.textContent = form.theme_name.value || 'ULT Payment';
    }

    ['input', 'change'].forEach(function (eventName) {
        form.addEventListener(eventName, applyPreview);
    });
});
</script>
</body>
</html>
