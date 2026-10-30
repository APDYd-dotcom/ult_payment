<?php
declare(strict_types=1);
define('REQUIRED_ROLE', 'admin');
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
                    $studentId = ($_POST['student_id'] ?? '') !== '' ? (int)$_POST['student_id'] : null;
                    $version = 1; $parentId = null;
                    if ($studentId) {
                        $st = $bdd->prepare("SELECT id, version, parent_id FROM documents WHERE original_name=? AND student_id=? ORDER BY version DESC LIMIT 1");
                        $st->execute([$f['name'], $studentId]);
                        $prev = $st->fetch(PDO::FETCH_ASSOC);
                        if ($prev) {
                            $version = (int)$prev['version'] + 1;
                            $parentId = $prev['parent_id'] ?: (int)$prev['id'];
                        }
                    }
                    $stored = bin2hex(random_bytes(16));
                    $destDir = __DIR__ . '/../uploads/documents';
                    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
                    $dest = $destDir . '/' . $stored;
                    if (move_uploaded_file($f['tmp_name'], $dest)) {
                        $st = $bdd->prepare("INSERT INTO documents (user_id, student_id, stored_name, original_name, mime_type, size, version, parent_id) VALUES (?,?,?,?,?,?,?,?)");
                        $st->execute([$_SESSION['userId'], $studentId, $stored, $f['name'], $mime, $f['size'], $version, $parentId]);
                        logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'UPLOAD_DOC', $f['name']);
                        $msg = 'Document téléversé.';
                    } else { $msg = 'Erreur de déplacement.'; }
                }
            }
        }
    } else { $msg = 'Aucun fichier.'; }
}
$students = $bdd->query("SELECT id, name, matricule FROM student ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html><head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"><title>Upload document</title><link rel="stylesheet" href="./styles.css?v=1.1">
<style>
.upload-card { background:#fff; border-radius:12px; padding:24px; max-width:520px; box-shadow:0 2px 8px rgba(0,0,0,.08); }
.upload-card label { display:block; margin:12px 0 6px; font-weight:600; color:#1e293b; }
.upload-card input, .upload-card select { width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; }
</style>
    <link rel="stylesheet" href="/payment/assets/css/responsive.css">
</head><body>
<div class="container"><aside class="sidebar"><?php include 'sidebar.php'; ?></aside>    <?php include "topbar.php"; ?>
<main class="main-content">
<h1 class="page-title">Téléverser un document</h1>
<?php if ($msg): ?><div class="message message-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<div class="upload-card">
<form method="POST" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<label>Fichier (pdf/jpg/png, max 5 Mo)</label>
<input type="file" name="file" required>
<label>Étudiant (optionnel)</label>
<select name="student_id"><option value="">--</option><?php foreach ($students as $s): ?><option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?></select>
<button class="btn-primary" type="submit" style="margin-top:16px;width:100%;">Téléverser</button>
</form>
</div>
<p style="margin-top:16px;"><a href="documents.php">Liste des documents</a></p>
</main></div>    <script src="/payment/assets/js/responsive.js"></script>
</body></html>
PHP