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
            // determine file
            $file = null;
            if (!empty($_POST['existing'])) {
                $name = basename($_POST['existing']);
                $path = $backupDir . '/' . $name;
                if (is_file($path)) $file = $path;
            } elseif (isset($_FILES['upload']) && $_FILES['upload']['error'] === UPLOAD_ERR_OK) {
                $up = $_FILES['upload'];
                if ($up['size'] > 5 * 1024 * 1024) { $msg = 'Fichier trop volumineux.'; }
                else {
                    $ext = strtolower(pathinfo($up['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['sql','gz'], true)) { $msg = 'Extension invalide.'; }
                    else {
                        $dest = $backupDir . '/upload_' . time() . '.' . $ext;
                        if (move_uploaded_file($up['tmp_name'], $dest)) $file = $dest;
                    }
                }
            }
            if ($file && $msg === '') {
                // safety backup
                runDatabaseBackup();
                // run restore
                $tmp = tempnam(sys_get_temp_dir(), 'ult_');
                file_put_contents($tmp, "[client]\nhost=localhost\nuser=app_user\npassword=secure_password_123\n");
                chmod($tmp, 0600);
                if (str_ends_with($file, '.gz')) {
                    $cmd = 'gunzip -c ' . escapeshellarg($file) . " | sed 's/DEFINER=[^*]*\\*/\\*/g' | mariadb --defaults-extra-file=" . escapeshellarg($tmp) . ' ult_payment';
                } else {
                    $cmd = "sed 's/DEFINER=[^*]*\\*/\\*/g' " . escapeshellarg($file) . ' | mariadb --defaults-extra-file=' . escapeshellarg($tmp) . ' ult_payment';
                }
                exec($cmd . ' 2>&1', $out, $rc);
                error_log('RESTORE CMD: ' . $cmd);
                error_log('RESTORE RC: ' . $rc . ' OUT: ' . implode(' | ', $out));
                unlink($tmp);
                if ($rc === 0) {
                    $msg = 'Restauration reussie.';
                    logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'RESTORE', basename($file));
                } else {
                    $msg = 'Echec de la restauration.';
                }
            } elseif ($msg === '') {
                $msg = 'Aucun fichier selectionne.';
            }
        }
    }
}

$files = glob($backupDir . '/*.sql.gz') ?: [];
?>
<!DOCTYPE html>
<html><head><title>Restauration</title><link rel="stylesheet" href="./styles.css?v=1.1"></head><body>
<div class="container"><aside class="sidebar"><?php include 'sidebar.php'; ?></aside><main class="main-content">
<h1 class="page-title">Restauration</h1>
<?php if ($msg): ?><div class="message message-error"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<form method="POST" enctype="multipart/form-data" class="form-section">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<label>Sauvegarde existante</label>
<select name="existing"><option value="">--</option><?php foreach ($files as $f): ?><option value="<?= htmlspecialchars(basename($f)) ?>"><?= htmlspecialchars(basename($f)) ?></option><?php endforeach; ?></select>
<label>Ou fichier .sql/.sql.gz</label><input type="file" name="upload">
<label>Mot de passe admin</label><input type="password" name="password" required>
<label><input type="checkbox" name="confirm"> Je confirme la restauration (sauvegarde de securite automatique)</label>
<button class="btn-primary" type="submit">Restaurer</button>
</form>
</main></div></body></html>
PHP