<?php
declare(strict_types=1);
define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../backup.php';

$backupDir = __DIR__ . '/../backups';

if (isset($_GET['download'])) {
    $name = basename($_GET['download']);
    $path = $backupDir . '/' . $name;
    if (!preg_match('/\.sql\.gz$/', $name) || !is_file($path)) {
        http_response_code(404);
        exit();
    }
    header('Content-Type: application/gzip');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    readfile($path);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'backup') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) { die('CSRF'); }
    $res = runDatabaseBackup();
    $msg = $res['success'] ? 'Sauvegarde créée: ' . $res['file'] : $res['message'];
    logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'BACKUP', $msg);
}

$files = glob($backupDir . '/*.sql.gz') ?: [];
rsort($files);
?>
<!DOCTYPE html>
<html><head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"><title>Sauvegardes</title><link rel="stylesheet" href="./styles.css?v=1.1">    <link rel="stylesheet" href="/payment/assets/css/responsive.css">
</head><body>
<div class="container"><?php include 'sidebar.php'; ?>    <main class="main-content">
<h1 class="page-title">Sauvegardes</h1>
<?php if (isset($msg)): ?><div class="message message-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<form method="POST" class="form-section">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<input type="hidden" name="action" value="backup">
<button class="btn-primary" type="submit">Lancer une sauvegarde</button>
</form>
<div class="table-responsive">
<table class="table-section"><tr><th>Nom</th><th>Taille</th><th>Date</th><th></th></tr>
<?php foreach ($files as $f): ?>
<tr>
<td><?= htmlspecialchars(basename($f)) ?></td>
<td><?= number_format(filesize($f)) ?> octets</td>
<td><?= date('Y-m-d H:i:s', filemtime($f)) ?></td>
<td><a class="btn-secondary" href="backups.php?download=<?= urlencode(basename($f)) ?>">Telecharger</a></td>
</tr>
<?php endforeach; ?>
</table>
</div>
<p><a href="restore.php">Restaurer</a></p>
</main></div>    <script src="/payment/assets/js/responsive.js"></script>
</body></html>
PHP