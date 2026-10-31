<?php
declare(strict_types=1);
define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../backup.php';

$backupDir = __DIR__ . '/../backups';
$message = '';
$messageType = '';

// --- GESTION DU TÉLÉCHARGEMENT (uniquement en GET) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['download'])) {
    $name = basename($_GET['download']);
    $path = $backupDir . '/' . $name;
    
    if (!preg_match('/\.sql\.gz$/', $name) || !is_file($path)) {
        http_response_code(404);
        die('Fichier introuvable.');
    }
    
    header('Content-Type: application/gzip');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit();
}

// --- CRÉATION D'UNE SAUVEGARDE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'backup') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        die('CSRF');
    }
    $res = runDatabaseBackup();
    $msg = $res['success'] ? 'Sauvegarde créée : ' . $res['file'] : $res['message'];
    $type = $res['success'] ? 'success' : 'error';
    logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'BACKUP', $msg);

    // Redirection (POST-Redirect-GET) pour éviter de rejouer la requête
    $_SESSION['backup_flash_message'] = $msg;
    $_SESSION['backup_flash_type'] = $type;
    header('Location: backups.php');
    exit();
}

// --- LECTURE DU MESSAGE FLASH (après redirection) ---
if (isset($_SESSION['backup_flash_message'])) {
    $message = $_SESSION['backup_flash_message'];
    $messageType = $_SESSION['backup_flash_type'];
    unset($_SESSION['backup_flash_message'], $_SESSION['backup_flash_type']);
}

$files = glob($backupDir . '/*.sql.gz') ?: [];
rsort($files);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sauvegardes - ULT Payment</title>
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
            <h1 class="page-title">Sauvegardes</h1>

            <?php if ($message): ?>
                <div class="alert <?= $messageType === 'success' ? 'alert-success' : 'alert-danger' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="page-card">
                <h2>Créer une sauvegarde</h2>
                <!-- CORRECTION : action="backups.php" pour éviter le conflit avec ?download= -->
                <form method="POST" action="backups.php" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="action" value="backup">
                    <button type="submit" class="btn btn-primary">Lancer une sauvegarde</button>
                </form>
            </div>

            <div class="page-card">
                <h2>Sauvegardes existantes</h2>
                <?php if ($files): ?>
                    <div class="table-responsive">
                        <table class="table-modern">
                            <thead>
                                <tr><th>Nom</th><th>Taille</th><th>Date</th><th></th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($files as $f): ?>
                                    <tr>
                                        <td><?= htmlspecialchars(basename($f)) ?></td>
                                        <td><?= number_format(filesize($f)) ?> octets</td>
                                        <td><?= date('Y-m-d H:i:s', filemtime($f)) ?></td>
                                        <td>
                                            <a class="btn btn-secondary" href="backups.php?download=<?= urlencode(basename($f)) ?>" style="padding:6px 12px;font-size:0.8rem;">Télécharger</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>Aucune sauvegarde trouvée.</p>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>
<script src="/payment/assets/js/responsive.js?v=20261030"></script>
</body>
</html>
