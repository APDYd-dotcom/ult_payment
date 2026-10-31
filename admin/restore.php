<?php
declare(strict_types=1);
define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../backup.php';

$backupDir = __DIR__ . '/../backups';
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) { die('CSRF'); }
    $password = $_POST['password'] ?? '';
    $confirm = isset($_POST['confirm']);
    if (!$confirm) { $msg = 'Confirmation requise.'; }
    else {
        $stmt = $bdd->prepare("SELECT password FROM user WHERE userId=?");
        $stmt->execute([$_SESSION['userId']]);
        $hash = $stmt->fetchColumn();
        if (!password_verify($password, $hash)) { $msg = 'Mot de passe invalide.'; }
        else {
            $file = null;
            $uploadedTemp = null;
            if (!empty($_POST['existing'])) {
                $name = basename($_POST['existing']);
                $path = $backupDir . '/' . $name;
                $real = realpath($path);
                if ($real && strpos($real, realpath($backupDir)) === 0 && is_file($real)) {
                    $file = $real;
                }
            } elseif (isset($_FILES['upload']) && $_FILES['upload']['error'] === UPLOAD_ERR_OK) {
                $up = $_FILES['upload'];
                if ($up['size'] > 20 * 1024 * 1024) { $msg = 'Fichier trop volumineux.'; }
                else {
                    $ext = strtolower(pathinfo($up['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['sql','gz'], true)) { $msg = 'Extension invalide.'; }
                    else {
                        $dest = $backupDir . '/upload_' . time() . '.' . $ext;
                        if (move_uploaded_file($up['tmp_name'], $dest)) { $file = $dest; $uploadedTemp = $dest; }
                    }
                }
            }
            if ($file && $msg === '') {
                // reject dump without CREATE TABLE
                $checkCmd = '/bin/bash -o pipefail -c ' . escapeshellarg('/usr/bin/gunzip -c ' . escapeshellarg($file) . ' 2>/dev/null | /usr/bin/grep -qi "CREATE TABLE" && echo OK');
                if (str_ends_with($file, '.sql')) {
                    $checkCmd = '/bin/bash -o pipefail -c ' . escapeshellarg('/usr/bin/grep -qi "CREATE TABLE" ' . escapeshellarg($file) . ' && echo OK');
                }
                exec($checkCmd, $cout, $crc);
                if (!in_array('OK', $cout, true)) {
                    $msg = 'Fichier invalide : CREATE TABLE introuvable.';
                } else {
                    runDatabaseBackup(); // safety
                    $tmp = tempnam(sys_get_temp_dir(), 'ult_');
                    file_put_contents($tmp, "[client]\nhost=localhost\nuser=app_user\npassword=secure_password_123\n");
                    chmod($tmp, 0600);
                    if (str_ends_with($file, '.gz')) {
                        $pipeline = '/usr/bin/gunzip -c ' . escapeshellarg($file) . " | /usr/bin/sed 's/DEFINER=[^ ]* //g' | /usr/bin/mariadb --defaults-extra-file=" . escapeshellarg($tmp) . ' ult_payment';
                    } else {
                        $pipeline = "/usr/bin/sed 's/DEFINER=[^ ]* //g' " . escapeshellarg($file) . ' | /usr/bin/mariadb --defaults-extra-file=' . escapeshellarg($tmp) . ' ult_payment';
                    }
                    $cmd = '/bin/bash -o pipefail -c ' . escapeshellarg($pipeline);
                    exec($cmd . ' 2>&1', $out, $rc);
                    @unlink($tmp);
                    if ($uploadedTemp) { @unlink($uploadedTemp); }
                    if ($rc === 0) {
                        $msg = 'Restauration reussie.';
                        logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'RESTORE', basename($file));
                    } else {
                        $detail = trim(implode("\n", array_slice($out, -3)));
                        if (strlen($detail) > 300) $detail = substr($detail, -300);
                        $msg = 'Echec de la restauration (code ' . $rc . ') : ' . $detail;
                        logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'RESTORE_FAILED', 'code=' . $rc);
                    }
                }
            } elseif ($msg === '') {
                $msg = 'Aucun fichier selectionne.';
            }
            if ($uploadedTemp && is_file($uploadedTemp) && $msg !== 'Restauration reussie.') {
                @unlink($uploadedTemp);
            }
        }
    }
}

$files = glob($backupDir . '/*.sql.gz') ?: [];
?>
<!DOCTYPE html>
<html><head>
    <link rel="stylesheet" href="/payment/assets/css/app.css?v=20261030">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"><title>Restauration</title><link rel="stylesheet" href="./styles.css?v=20261030">    <link rel="stylesheet" href="/payment/assets/css/responsive.css?v=20261030">
    <link rel="stylesheet" href="/payment/assets/ui.css">
</head><body>
<div class="container">
    <?php include 'sidebar.php'; ?>
    <main class="main-content">
        <section class="page active">
            <h1 class="page-title">Restauration</h1>
<?php if ($msg): ?><div class="message <?= strpos($msg,'reussie')!==false?'message-success':'message-error' ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<form method="POST" enctype="multipart/form-data" class="form-section">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<label>Sauvegarde existante</label>
<select name="existing"><option value="">--</option><?php foreach ($files as $f): ?><option value="<?= htmlspecialchars(basename($f)) ?>"><?= htmlspecialchars(basename($f)) ?></option><?php endforeach; ?></select>
<label>Ou fichier .sql/.sql.gz</label><input type="file" name="upload">
<label>Mot de passe admin</label><input type="password" name="password" required>
<label><input type="checkbox" name="confirm"> Je confirme la restauration (sauvegarde de securite automatique)</label>
<button class="btn-primary" type="submit">Restaurer</button>
</form>
</main></div>    <script src="/payment/assets/js/responsive.js?v=20261030"></script>
</body></html>
PHP