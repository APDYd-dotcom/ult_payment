<?php
declare(strict_types=1);
define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';

$msg = '';
$msgType = 'success';
$backupDir = __DIR__ . '/../backups';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restore') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        die('CSRF');
    }

    $fileToRestore = '';
    $originalName = '';

    // Cas 1 : Fichier uploadé
    if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
        $fileToRestore = $_FILES['backup_file']['tmp_name'];
        $originalName = $_FILES['backup_file']['name'];
    }
    // Cas 2 : Sélection depuis la liste
    elseif (!empty($_POST['backup_name'])) {
        $backupName = basename($_POST['backup_name']);
        $fileToRestore = $backupDir . '/' . $backupName;
        $originalName = $backupName;
    }

    if (!$fileToRestore || !file_exists($fileToRestore)) {
        $msg = 'Fichier introuvable.';
        $msgType = 'error';
    } else {
        // Configuration Docker (root pour les droits sur les triggers)
        $host = '127.0.0.1';
        $user = 'root';
        $pass = 'root_password_secret';
        $db   = 'ult_payment';

        $decompressed = null;

        // Détection GZIP par magic bytes
        $fh = fopen($fileToRestore, 'rb');
        $magic = fread($fh, 2);
        fclose($fh);

        if ($magic === "\x1f\x8b") {
            $decompressed = tempnam(sys_get_temp_dir(), 'rest_') . '.sql';
            exec("gunzip -c " . escapeshellarg($fileToRestore) . " > " . escapeshellarg($decompressed), $out, $rc);
            if ($rc === 0 && filesize($decompressed) > 0) {
                $fileToRestore = $decompressed;
            } else {
                $msg = 'Erreur lors de la décompression du fichier.';
                $msgType = 'error';
            }
        }

        if (empty($msg)) {
            $cmd = "mariadb --host={$host} --user={$user} --password={$pass} {$db} < " . escapeshellarg($fileToRestore) . " 2>&1";
            exec($cmd, $output, $returnCode);

            if ($returnCode === 0) {
                $msg = 'Restauration réussie avec succès !';
                $msgType = 'success';
                logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'RESTORE', 'Restauration de ' . $originalName);
            } else {
                $msg = 'Erreur lors de la restauration : ' . implode('<br>', $output);
                $msgType = 'error';
            }
        }

        if ($decompressed && file_exists($decompressed)) {
            @unlink($decompressed);
        }
    }
}

$files = glob($backupDir . '/*.sql.gz') ?: [];
rsort($files);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurer - ULT Payment</title>
    <link rel="stylesheet" href="/payment/assets/css/app.css?v=20261030">
    <link rel="stylesheet" href="./styles.css?v=20261030">
    <?php loadTheme($bdd); ?>
    <link rel="stylesheet" href="/payment/assets/css/responsive.css?v=20261030">
    <link rel="stylesheet" href="/payment/assets/ui.css">
</head>
<body>
<div class="container">
    <?php include 'sidebar.php'; ?>
    <main class="main-content">
        <section class="page active">
            <h1 class="page-title">Restaurer une sauvegarde</h1>

            <?php if ($msg): ?>
                <div class="alert <?= $msgType === 'success' ? 'alert-success' : 'alert-danger' ?>">
                    <?= $msg ?>
                </div>
            <?php endif; ?>

            <!-- Option 1 : Upload -->
            <div class="page-card">
                <h2>Option 1 : Uploader un fichier</h2>
                <p style="color:#64748b; margin-bottom:16px; font-size:0.9rem;">
                    Formats acceptés : <strong>.sql</strong> ou <strong>.sql.gz</strong>
                </p>
                <form method="POST" enctype="multipart/form-data" class="form-modern">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="action" value="restore">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="backup_file">Fichier de sauvegarde</label>
                        <input type="file" id="backup_file" name="backup_file" accept=".sql,.gz" required>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <button type="submit" class="btn btn-primary">Lancer la restauration</button>
                    </div>
                </form>
            </div>

            <!-- Option 2 : Choisir une sauvegarde existante -->
            <div class="page-card">
                <h2>Option 2 : Choisir une sauvegarde existante</h2>
                <?php if ($files): ?>
                    <form method="POST" class="form-modern">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                        <input type="hidden" name="action" value="restore">
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="backup_name">Sauvegarde</label>
                            <select id="backup_name" name="backup_name" required>
                                <option value="">-- Sélectionner --</option>
                                <?php foreach ($files as $f): ?>
                                    <option value="<?= htmlspecialchars(basename($f)) ?>">
                                        <?= htmlspecialchars(basename($f)) ?> (<?= date('d/m/Y H:i', filemtime($f)) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="grid-column: 1 / -1;">
                            <button type="submit" class="btn btn-success">Restaurer</button>
                        </div>
                    </form>
                <?php else: ?>
                    <p style="color:#64748b;">Aucune sauvegarde disponible. <a href="backups.php">Créez-en une d'abord</a>.</p>
                <?php endif; ?>
            </div>

            <!-- Lien retour -->
            <p style="margin-top: 24px;">
                <a href="backups.php" class="btn btn-secondary">← Retour aux sauvegardes</a>
            </p>
        </section>
    </main>
</div>
<script src="/payment/assets/js/responsive.js?v=20261030"></script>
</body>
</html>
