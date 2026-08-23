<?php
define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$messageType = '';
$definitions = getDefaultSystemSettings();
$values = getSystemSettings($bdd);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
            throw new RuntimeException('Session expiree. Veuillez reessayer.');
        }

        if (isset($_POST['reset_settings'])) {
            resetSystemSettings($bdd);
            logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'system_settings_reset', 'System settings reset to defaults');
            header('Location: system_settings.php?success=reset');
            exit();
        }

        [$submittedValues, $errors] = validateSystemSettingsInput($_POST);
        if ($errors) {
            throw new RuntimeException(implode(' ', $errors));
        }

        $changes = [];
        foreach ($submittedValues as $key => $value) {
            $oldValue = getSystemSetting($bdd, $key, (string) $definitions[$key]['default']);
            if ((string) $oldValue !== (string) $value) {
                $changes[] = $key . ': ' . $oldValue . ' -> ' . $value;
            }
            setSystemSetting($bdd, $key, (string) $value);
        }

        logActivity(
            $bdd,
            $_SESSION['userId'] ?? null,
            $_SESSION['fullname'] ?? '',
            $_SESSION['email'] ?? '',
            'system_settings_updated',
            $changes ? implode('; ', $changes) : 'No value changed'
        );

        header('Location: system_settings.php?success=saved');
        exit();
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $messageType = 'error';
        $values = array_merge($values, $_POST);
        $values['enable_2fa'] = isset($_POST['enable_2fa']) ? '1' : '0';
    }
}

if (isset($_GET['success'])) {
    $message = $_GET['success'] === 'reset'
        ? 'Les parametres par defaut ont ete restaures.'
        : 'Les parametres systeme ont ete enregistres avec succes.';
    $messageType = 'success';
    $values = getSystemSettings($bdd);
}

$grouped = [];
foreach ($definitions as $key => $definition) {
    $grouped[$definition['category']][$key] = $definition;
}

function renderSystemSettingField(string $key, array $definition, array $values): void
{
    $value = (string) ($values[$key] ?? $definition['default']);
    $safeKey = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
    $safeLabel = htmlspecialchars($definition['label'], ENT_QUOTES, 'UTF-8');
    $safeValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $type = $definition['type'];

    echo '<label class="settings-field">';
    echo '<span>' . $safeLabel . '</span>';

    if ($type === 'date') {
        echo '<input type="date" name="' . $safeKey . '" value="' . $safeValue . '" required>';
    } elseif ($type === 'boolean') {
        echo '<span class="settings-toggle">';
        echo '<input type="checkbox" name="' . $safeKey . '" value="1" ' . ($value === '1' ? 'checked' : '') . '>';
        echo '<span>Actif</span>';
        echo '</span>';
    } elseif (in_array($type, ['number', 'percent'], true)) {
        $min = isset($definition['min']) ? ' min="' . htmlspecialchars((string) $definition['min'], ENT_QUOTES, 'UTF-8') . '"' : '';
        $max = isset($definition['max']) ? ' max="' . htmlspecialchars((string) $definition['max'], ENT_QUOTES, 'UTF-8') . '"' : '';
        echo '<input type="number" name="' . $safeKey . '" value="' . $safeValue . '"' . $min . $max . ' step="1" required>';
    } else {
        echo '<input type="text" name="' . $safeKey . '" value="' . $safeValue . '" maxlength="255" required>';
    }

    echo '<small>' . $safeKey . '</small>';
    echo '</label>';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parametrage du systeme - ULT Payment System</title>
    <link rel="stylesheet" href="./styles.css?v=1.4">
    <?php loadTheme($bdd); ?>
    <style>
        .settings-page { display: grid; gap: 18px; }
        .settings-header { background: #fff; border-radius: 12px; padding: 18px 20px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .settings-header p { color: #64748b; margin-top: 6px; line-height: 1.5; }
        .settings-section { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .settings-section h2 { color: var(--primary-color, #1e3a8a); font-size: 1.1rem; margin-bottom: 14px; }
        .settings-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .settings-field { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
        .settings-field span:first-child { color: #334155; font-weight: 700; }
        .settings-field input[type="text"],
        .settings-field input[type="number"],
        .settings-field input[type="date"] { min-height: 42px; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; }
        .settings-field input:focus { outline: none; border-color: var(--secondary-color, #2563eb); box-shadow: 0 0 0 4px rgba(37,99,235,.1); }
        .settings-field small { color: #64748b; font-size: .82rem; }
        .settings-toggle { display: inline-flex; align-items: center; gap: 10px; min-height: 42px; }
        .settings-toggle input { width: 18px; height: 18px; accent-color: var(--secondary-color, #2563eb); }
        .settings-actions { display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; background: #fff; border-radius: 12px; padding: 16px 20px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .settings-actions button { width: auto; min-width: 170px; }
        .btn-danger { background: #dc2626; }
        .btn-danger:hover { background: #b91c1c; }
        @media (max-width: 900px) { .settings-grid { grid-template-columns: 1fr; } .settings-actions { justify-content: flex-start; } }
    </style>
</head>
<body>
<div class="container">
    <aside id="sidebar" class="sidebar">
        <?php include 'sidebar.php'; ?>
    </aside>

    <main id="main-content" class="main-content">
        <section class="page active">
            <h1 class="page-title">Parametrage du systeme</h1>

            <?php if ($message): ?>
                <div class="message <?= $messageType === 'success' ? 'message-success' : 'message-error' ?>">
                    <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="settings-page">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                <div class="settings-header">
                    <strong>Regles metier dynamiques</strong>
                    <p>Ces valeurs sont stockees dans la table <code>settings</code> avec la categorie <code>system</code>. Les changements s'appliquent aux nouvelles operations et aux recalculs SQL.</p>
                </div>

                <?php foreach ($grouped as $category => $items): ?>
                    <div class="settings-section">
                        <h2><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?></h2>
                        <div class="settings-grid">
                            <?php foreach ($items as $key => $definition): ?>
                                <?php renderSystemSettingField($key, $definition, $values); ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="settings-actions">
                    <button type="submit" name="save_settings">Enregistrer</button>
                    <button type="submit" class="btn-danger" name="reset_settings" onclick="return confirm('Retablir les valeurs systeme par defaut ?');">Retablir les valeurs par defaut</button>
                </div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
