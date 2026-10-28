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
                    // versioning
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
<html><head><title>Upload document</title><link rel="stylesheet" href="./styles.css?v=1.1"></head><body>
<div class="container"><aside class="sidebar"><?php include 'sidebar.php'; ?></aside><main class="main-content">
<h1 class="page-title">Téléverser un document</h1>
<?php if ($msg): ?><div class="message message-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<form method="POST" enctype="multipart/form-data" class="form-section">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<label>Fichier (pdf/jpg/png, max 5 Mo)</label><input type="file" name="file" required>
<label>Étudiant (optionnel)</label>
<select name="student_id"><option value="">--</option><?php foreach ($students as $s): ?><option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?></select>
<button class="btn-primary" type="submit">Téléverser</button>
</form>
<p><a href="documents.php">Liste des documents</a></p>
</main></div></body></html>
PHP