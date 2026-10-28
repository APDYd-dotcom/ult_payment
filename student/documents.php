<?php
declare(strict_types=1);
define('REQUIRED_ROLE', 'student');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';

$stmt = $bdd->prepare("SELECT * FROM documents WHERE user_id=? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['userId']]);
$docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html><head><title>Mes documents</title><link rel="stylesheet" href="../admin/styles.css?v=1.1"></head><body>
<div class="container"><main class="main-content">
<h1 class="page-title">Mes documents</h1>
<p><a href="upload_document.php">Téléverser</a></p>
<table class="table-section"><tr><th>Nom</th><th>Version</th><th>Taille</th><th>Date</th><th></th></tr>
<?php foreach ($docs as $d): ?>
<tr>
<td><?= htmlspecialchars($d['original_name']) ?></td>
<td><?= (int)$d['version'] ?></td>
<td><?= (int)$d['size'] ?></td>
<td><?= htmlspecialchars($d['created_at']) ?></td>
<td><a href="../admin/download.php?id=<?= (int)$d['id'] ?>">Télécharger</a></td>
</tr>
<?php endforeach; ?>
</table>
</main></div></body></html>
PHP