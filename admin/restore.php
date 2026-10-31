<?php
declare(strict_types=1);
define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';

$msg = '';
$msgType = 'success';
$backupDir = __DIR__ . '/../backups';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restore') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) { die('CSRF'); }

    $fileToRestore = '';

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
        // Configuration Docker : utilisateur root pour les triggers
        $host = '127.0.0.1';
        $user = 'root'; 
        $pass = 'root_password_secret';
        $db   = 'ult_payment';

        $decompressed = null;

        // 1. Détection fiable du GZIP en lisant les 2 premiers octets (magic bytes)
        $fh = fopen($fileToRestore, 'rb');
        $magic = fread($fh, 2);
        fclose($fh);

        if ($magic === "\x1f\x8b") {
            // Le fichier est compressé, on le décompresse
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
            // Exécution de l'importation via mariadb CLI
            $cmd = "mariadb --host={$host} --user={$user} --password={$pass} {$db} < " . escapeshellarg($fileToRestore) . " 2>&1";
            exec($cmd, $output, $returnCode);

            if ($returnCode === 0) {
                $msg = 'Restauration réussie avec succès !';
                logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'RESTORE', 'Restauration de ' . $originalName);
            } else {
                $msg = 'Erreur lors de la restauration : ' . implode("<br>", $output);
                $msgType = 'error';
            }
        }

        // Nettoyage du fichier temporaire décompressé
        if ($decompressed && file_exists($decompressed)) {
            @unlink($decompressed);
        }
    }
}

// Liste des sauvegardes existantes
$files = glob($backupDir . '/*.sql.gz') ?: [];
rsort($files);
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="/payment/assets/css/app.css?v=20261030">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Restaurer</title>
    <link rel="stylesheet" href="./styles.css?v=20261030">
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
                <div class="message <?= $msgType === 'success' ? 'message-success' : 'message-error' ?>">
                    <?= $msg ?>
                </div>
            <?php endif; ?>

            <div class="form-section" style="max-width: 600px; margin-bottom: 20px;">
                <h3>Option 1 : Uploader un fichier</h3>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="action" value="restore">
                    <label>Fichier SQL ou SQL.GZ</label>
                    <input type="file" name="backup_file" accept=".sql,.gz" required>
                    <div class="buttons" style="margin-top: 10px;">
                        <button class="btn-primary" type="submit">Lancer la restauration</button>
                    </div>
                </form>
            </div>

            <div class="form-section" style="max-width: 600px;">
                <h3>Option 2 : Choisir une sauvegarde existante</h3>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="action" value="restore">
                    <label>Sauvegarde existante</label>
                    <select name="backup_name" required>
                        <option value="">-- Sélectionner --</option>
                        <?php foreach ($files as $f): ?>
                            <option value="<?= htmlspecialchars(basename($f)) ?>"><?= htmlspecialchars(basename($f)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="buttons" style="margin-top: 10px;">
                        <button class="btn-primary" type="submit">Restaurer</button>
                    </div>
                </form>
            </div>
            
            <p style="margin-top: 20px;"><a href="backups.php">&larr; Retour aux sauvegardes</a></p>
        </section>
    </main>
</div>
<script src="/payment/assets/js/responsive.js?v=20261030"></script>
</body>
</html>
