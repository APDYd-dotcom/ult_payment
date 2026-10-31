<?php
declare(strict_types=1);
define('REQUIRED_ROLE', 'student');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) { die('CSRF'); }
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $f = $_FILES['file'];
        if ($f['size'] > 5 * 1024 * 1024) { $msg = 'Fichier trop volumineux.'; }
        else {
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf','jpg','jpeg','png'], true)) { $msg = 'Extension invalide.'; }
            else {
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
                $allowedMime = ['application/pdf','image/jpeg','image/png'];
                if (!in_array($mime, $allowedMime, true)) { $msg = 'Type MIME invalide.'; }
                else {
                    $stored = bin2hex(random_bytes(16));
                    $destDir = __DIR__ . '/../uploads/documents';
                    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
                    $dest = $destDir . '/' . $stored;
                    if (move_uploaded_file($f['tmp_name'], $dest)) {
                        $st = $bdd->prepare("INSERT INTO documents (user_id, stored_name, original_name, mime_type, size, version) VALUES (?,?,?,?,?,1)");
                        $st->execute([$_SESSION['userId'], $stored, $f['name'], $mime, $f['size']]);
                        // notify admins
                        $admins = $bdd->query("SELECT userId FROM user WHERE role='admin'")->fetchAll(PDO::FETCH_COLUMN);
                        foreach ($admins as $aid) {
                            createNotification($bdd, (int)$aid, 'Nouveau document', 'Un étudiant a téléversé un document.', '/payment/admin/documents.php');
                        }
                        logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'UPLOAD_DOC', $f['name']);
                        $msg = 'Document téléversé.';
                    } else { $msg = 'Erreur de déplacement.'; }
                }
            }
        }
    } else { $msg = 'Aucun fichier.'; }
}
?>
<!DOCTYPE html>
<html><head>
    <link rel="stylesheet" href="/payment/assets/css/app.css?v=20261030">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"><title>Upload document</title><link rel="stylesheet" href="../admin/styles.css?v=20261030">    <link rel="stylesheet" href="/payment/assets/css/responsive.css?v=20261030">
    <link rel="stylesheet" href="/payment/assets/ui.css">
</head><body>
<div class="container">    <main class="main-content">
        <section class="page active">
            <h1 class="page-title">Téléverser un document</h1>
<?php if ($msg): ?><div class="message message-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<form method="POST" enctype="multipart/form-data" class="form-section">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<label>Fichier (pdf/jpg/png, max 5 Mo)</label><input type="file" name="file" required>
<button class="btn-primary" type="submit">Téléverser</button>
</form>
<p><a href="documents.php">Mes documents</a></p>
</main></div>    <script src="/payment/assets/js/responsive.js?v=20261030"></script>
</body></html>
PHP